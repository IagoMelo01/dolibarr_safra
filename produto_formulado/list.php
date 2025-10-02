<?php
/*
 * Copyright (C) 2025 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
    $res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
// Try main.inc.php using relative path
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once __DIR__.'/../class/safra_produto_formulado.class.php';

$langs->loadLangs(array('safra@safra'));

if (!$user->rights->safra->produtoformulado->read) {
    accessforbidden();
}

$safra_produto_schema_ok = SafraProdutoFormulado::ensureDatabaseSchema($db);
if (!$safra_produto_schema_ok) {
    setEventMessages($langs->trans('ProdutoFormuladoSchemaError'), null, 'errors');
}

$action = GETPOST('action', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page === '' || $page === null || $page < 0) {
    $page = 0;
}
$limit = GETPOSTINT('limit') ?: $conf->liste_limit;
$offset = $limit * $page;
$button_search = GETPOST('button_search', 'aZ09');
$button_removefilter = GETPOST('button_removefilter', 'aZ09');

$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_label = trim(GETPOST('search_label', 'alphanohtml'));
$search_ingrediente = trim(GETPOST('search_ingrediente_ativo', 'restricthtml'));
$search_modo_acao = trim(GETPOST('search_modo_acao', 'restricthtml'));
$search_classe = trim(GETPOST('search_classe', 'restricthtml'));
$search_status_raw = GETPOST('search_status', 'alpha');
$search_status = ($search_status_raw === '' || $search_status_raw === null) ? null : (int) $search_status_raw;

if ($action === 'clear' || $button_removefilter) {
    $search_ref = $search_label = '';
    $search_ingrediente = $search_modo_acao = $search_classe = '';
    $search_status_raw = '';
    $search_status = null;
    $page = 0;
    $offset = 0;
}

if ($button_search || $button_removefilter) {
    $page = 0;
    $offset = 0;
}

$object = new SafraProdutoFormulado($db);
$form = new Form($db);

$resql = false;
$num = 0;
$nbtotalofrecords = 0;
if ($safra_produto_schema_ok) {
    $sql_from = ' FROM '.MAIN_DB_PREFIX.'safra_produto_formulado AS pf';
    $sql_where = ' WHERE 1=1';
    if ($search_ref !== '') {
        $sql_where .= " AND pf.ref LIKE '%".$db->escape($search_ref)."%'";
    }
    if ($search_label !== '') {
        $sql_where .= " AND pf.label LIKE '%".$db->escape($search_label)."%'";
    }
    if ($search_status !== null) {
        $sql_where .= ' AND pf.status = '.((int) $search_status);
    }
    if ($search_ingrediente !== '') {
        $sql_where .= " AND pf.ingrediente_ativo LIKE '%".$db->escape($search_ingrediente)."%'";
    }
    if ($search_modo_acao !== '') {
        $sql_where .= " AND pf.modo_acao LIKE '%".$db->escape($search_modo_acao)."%'";
    }
    if ($search_classe !== '') {
        $sql_where .= " AND pf.classe LIKE '%".$db->escape($search_classe)."%'";
    }

    $sql = 'SELECT pf.rowid, pf.ref, pf.label, pf.ingrediente_ativo, pf.modo_acao, pf.classe, pf.status, pf.date_creation,';
    $sql .= ' COALESCE(pc.nb_culturas, 0) AS nb_culturas,';
    $sql .= ' COALESCE(pp.nb_pragas, 0) AS nb_pragas';
    $sql .= $sql_from;
    $sql .= ' LEFT JOIN (';
    $sql .= ' SELECT fk_produto, COUNT(*) AS nb_culturas FROM '.MAIN_DB_PREFIX.'safra_produto_cultura GROUP BY fk_produto';
    $sql .= ' ) AS pc ON pc.fk_produto = pf.rowid';
    $sql .= ' LEFT JOIN (';
    $sql .= ' SELECT fk_produto, COUNT(*) AS nb_pragas FROM '.MAIN_DB_PREFIX.'safra_produto_praga GROUP BY fk_produto';
    $sql .= ' ) AS pp ON pp.fk_produto = pf.rowid';
    $sql .= $sql_where;
    if (!$sortfield) {
        $sortfield = 'pf.ref';
    }
    if (!$sortorder) {
        $sortorder = 'ASC';
    }
    $sql .= ' ORDER BY '.preg_replace('/[^a-zA-Z0-9_\.]/', '', $sortfield).' '.($sortorder === 'DESC' ? 'DESC' : 'ASC');
    if ($limit > 0) {
        $sql .= $db->plimit($limit, $offset);
    }

    $resql = $db->query($sql);
    if (!$resql) {
        dol_print_error($db);
        exit;
    }

    $num = $db->num_rows($resql);

    $sql_count = 'SELECT COUNT(DISTINCT pf.rowid) AS total'.$sql_from.$sql_where;
    $resql_count = $db->query($sql_count);
    if ($resql_count) {
        $obj_count = $db->fetch_object($resql_count);
        if ($obj_count) {
            $nbtotalofrecords = (int) $obj_count->total;
        }
        $db->free($resql_count);
    } else {
        dol_print_error($db);
        exit;
    }
}
$moreforfilter = ''; // placeholder

$help_url = '';
$title = $langs->trans('ProdutoFormuladoListTitle');
llxHeader('', $title, $help_url);

print load_fiche_titre($title, '', 'fa-flask');

$param = '';
if ($search_ref !== '') {
    $param .= '&search_ref='.urlencode($search_ref);
}
if ($search_label !== '') {
    $param .= '&search_label='.urlencode($search_label);
}
if ($search_ingrediente !== '') {
    $param .= '&search_ingrediente_ativo='.urlencode($search_ingrediente);
}
if ($search_modo_acao !== '') {
    $param .= '&search_modo_acao='.urlencode($search_modo_acao);
}
if ($search_classe !== '') {
    $param .= '&search_classe='.urlencode($search_classe);
}
if ($search_status_raw !== '' && $search_status_raw !== null) {
    $param .= '&search_status='.urlencode($search_status_raw);
}
if ($limit > 0) {
    $param .= '&limit='.((int) $limit);
}

print '<form method="GET" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" class="listform">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';
print '<input type="hidden" name="pageplusone" value="'.((int) $page + 1).'">';
print '<input type="hidden" name="page_y" value="">';

print_barre_liste(
    $title,
    $page,
    $_SERVER['PHP_SELF'],
    $param,
    $sortfield,
    $sortorder,
    '',
    $num,
    $nbtotalofrecords,
    'generic',
    0,
    '',
    '',
    $limit,
    0,
    0,
    1
);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 'pf.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Label'), $_SERVER['PHP_SELF'], 'pf.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('IngredienteAtivo'), $_SERVER['PHP_SELF'], 'pf.ingrediente_ativo', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ModoAcao'), $_SERVER['PHP_SELF'], 'pf.modo_acao', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Classe'), $_SERVER['PHP_SELF'], 'pf.classe', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 'pf.status', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('CulturasCount'), $_SERVER['PHP_SELF'], 'nb_culturas', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('PragasCount'), $_SERVER['PHP_SELF'], 'nb_pragas', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('DateCreation'), $_SERVER['PHP_SELF'], 'pf.date_creation', '', $param, '', $sortfield, $sortorder, 'center');
print '</tr>';

// Filter row
print '<tr class="liste_titre">';
print '<td class="liste_titre"><input type="text" class="flat" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" /></td>';
print '<td class="liste_titre"><input type="text" class="flat" name="search_label" value="'.dol_escape_htmltag($search_label).'" /></td>';
print '<td class="liste_titre"><input type="text" class="flat" name="search_ingrediente_ativo" value="'.dol_escape_htmltag($search_ingrediente).'" /></td>';
print '<td class="liste_titre"><input type="text" class="flat" name="search_modo_acao" value="'.dol_escape_htmltag($search_modo_acao).'" /></td>';
print '<td class="liste_titre"><input type="text" class="flat" name="search_classe" value="'.dol_escape_htmltag($search_classe).'" /></td>';
$statuses = array(
    '' => '',
    SafraProdutoFormulado::STATUS_ACTIVE => $langs->trans('ProdutoFormuladoStatusActive'),
    SafraProdutoFormulado::STATUS_DISABLED => $langs->trans('ProdutoFormuladoStatusDisabled'),
);
print '<td class="liste_titre center">'.$form->selectarray('search_status', $statuses, $search_status_raw, 0).'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre" style="text-align:right">';
print '<button type="submit" name="button_search" value="1" class="button reposition">'.$langs->trans('Search').'</button> ';
print '<button type="submit" name="button_removefilter" value="1" class="button">'.$langs->trans('Reset').'</button>';
print '</td>';
print '</tr>';

if ($safra_produto_schema_ok && $resql) {
    $i = 0;
    while ($i < $num) {
        $obj = $db->fetch_object($resql);
        if (!$obj) {
            break;
        }
        $object->id = $obj->rowid;
        $object->ref = $obj->ref;
        $object->label = $obj->label;
        $object->ingrediente_ativo = $obj->ingrediente_ativo;
        $object->modo_acao = $obj->modo_acao;
        $object->classe = $obj->classe;
        $object->status = $obj->status;

        print '<tr class="oddeven">';
        print '<td>'.$object->getNomUrl(1).'</td>';
        print '<td>'.dol_escape_htmltag($obj->label).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ingrediente_ativo).'</td>';
        print '<td>'.dol_escape_htmltag($obj->modo_acao).'</td>';
        print '<td>'.dol_escape_htmltag($obj->classe).'</td>';
        print '<td class="center">'.$object->getLibStatut(5).'</td>';
        print '<td class="center">'.(int) $obj->nb_culturas.'</td>';
        print '<td class="center">'.(int) $obj->nb_pragas.'</td>';
        print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
        print '</tr>';
        $i++;
    }

    if ($num === 0) {
        print '<tr class="oddeven"><td colspan="9" class="center">'.$langs->trans('NoRecordFound').'</td></tr>';
    }
} else {
    print '<tr class="oddeven"><td colspan="9" class="center">'.$langs->trans('ProdutoFormuladoSchemaMissing').'</td></tr>';
}

if ($resql) {
    $db->free($resql);
}

print '</table>';
print '</div>';

print '<div class="tabsAction">';
if ($user->rights->safra->produtoformulado->write) {
    $newUrl = dol_buildpath('/safra/produto_formulado/card.php', 1).'?action=create';
    print '<a class="butAction" href="'.$newUrl.'">'.$langs->trans('NewProdutoFormulado').'</a>';
}
print '</div>';

print '</form>';

llxFooter();
$db->close();
