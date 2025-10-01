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

$action = GETPOST('action', 'aZ09');
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOSTINT('page');
if ($page < 0) {
    $page = 0;
}
$limit = GETPOSTINT('limit') ?: $conf->liste_limit;
$offset = $limit * $page;

$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_label = trim(GETPOST('search_label', 'alphanohtml'));
$search_status = GETPOSTINT('search_status');

if ($action === 'clear') {
    $search_ref = $search_label = '';
    $search_status = '';
}

$object = new SafraProdutoFormulado($db);
$form = new Form($db);

$sql = 'SELECT pf.rowid, pf.ref, pf.label, pf.status, pf.date_creation,';
$sql .= ' COUNT(DISTINCT pc.rowid) AS nb_culturas,';
$sql .= ' COUNT(DISTINCT pp.rowid) AS nb_pragas';
$sql .= ' FROM '.MAIN_DB_PREFIX.'safra_produto_formulado AS pf';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'safra_produto_cultura AS pc ON pc.fk_produto = pf.rowid';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'safra_produto_praga AS pp ON pp.fk_produto = pf.rowid';
$sql .= ' WHERE 1=1';
if ($search_ref !== '') {
    $sql .= " AND pf.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_label !== '') {
    $sql .= " AND pf.label LIKE '%".$db->escape($search_label)."%'";
}
if ($search_status !== '' && $search_status !== null) {
    $sql .= ' AND pf.status = '.((int) $search_status);
}
$sql .= ' GROUP BY pf.rowid, pf.ref, pf.label, pf.status, pf.date_creation';
if (!$sortfield) {
    $sortfield = 'pf.ref';
}
if (!$sortorder) {
    $sortorder = 'ASC';
}
$sql .= ' ORDER BY '.preg_replace('/[^a-zA-Z0-9_\.]/', '', $sortfield).' '.($sortorder === 'DESC' ? 'DESC' : 'ASC');
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($resql);
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
if ($search_status !== '' && $search_status !== null) {
    $param .= '&search_status='.(int) $search_status;
}

print '<form method="GET" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" class="listform">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $db->num_rows($resql), 'generic');

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 'pf.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Label'), $_SERVER['PHP_SELF'], 'pf.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 'pf.status', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('CulturasCount'), $_SERVER['PHP_SELF'], 'nb_culturas', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('PragasCount'), $_SERVER['PHP_SELF'], 'nb_pragas', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('DateCreation'), $_SERVER['PHP_SELF'], 'pf.date_creation', '', $param, '', $sortfield, $sortorder, 'center');
print '</tr>';

// Filter row
print '<tr class="liste_titre">';
print '<td class="liste_titre"><input type="text" class="flat" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" /></td>';
print '<td class="liste_titre"><input type="text" class="flat" name="search_label" value="'.dol_escape_htmltag($search_label).'" /></td>';
$statuses = array(
    '' => '',
    SafraProdutoFormulado::STATUS_ACTIVE => $langs->trans('ProdutoFormuladoStatusActive'),
    SafraProdutoFormulado::STATUS_DISABLED => $langs->trans('ProdutoFormuladoStatusDisabled'),
);
print '<td class="liste_titre center">'.$form->selectarray('search_status', $statuses, $search_status, 0).'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre" style="text-align:right">';
print '<button type="submit" class="button reposition">'.$langs->trans('Search').'</button> ';
print '<button type="submit" name="action" value="clear" class="button">'.$langs->trans('Reset').'</button>';
print '</td>';
print '</tr>';

$i = 0;
while ($i < min($num, $limit)) {
    $obj = $db->fetch_object($resql);
    if (!$obj) {
        break;
    }
    $object->id = $obj->rowid;
    $object->ref = $obj->ref;
    $object->label = $obj->label;
    $object->status = $obj->status;

    print '<tr class="oddeven">';
    print '<td>'.$object->getNomUrl(1).'</td>';
    print '<td>'.dol_escape_htmltag($obj->label).'</td>';
    print '<td class="center">'.$object->getLibStatut(5).'</td>';
    print '<td class="center">'.(int) $obj->nb_culturas.'</td>';
    print '<td class="center">'.(int) $obj->nb_pragas.'</td>';
    print '<td class="center">'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
    print '</tr>';
    $i++;
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
