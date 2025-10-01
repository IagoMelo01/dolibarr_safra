<?php
/*
 * Copyright (C) 2025 SuperAdmin
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once __DIR__.'/../class/safra_produto_formulado.class.php';
require_once __DIR__.'/../class/safra_cultura.class.php';
require_once __DIR__.'/../class/safra_praga.class.php';

$langs->loadLangs(array('safra@safra'));

$safra_produto_schema_ok = SafraProdutoFormulado::ensureDatabaseSchema($db);
if (!$safra_produto_schema_ok) {
    setEventMessages($langs->trans('ProdutoFormuladoSchemaError'), null, 'errors');
}

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alphanohtml');
$tab = GETPOST('tab', 'alpha');

if (!$user->rights->safra->produtoformulado->read) {
    accessforbidden();
}

$object = new SafraProdutoFormulado($db);
if ($id > 0 || $ref) {
    $object->fetch($id, $ref);
}

include __DIR__.'/../core/actions_produto_formulado.inc.php';

if (($id > 0 || $ref) && empty($object->id) && $action !== 'create') {
    setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
}

if (!empty($object->id)) {
    $id = $object->id;
}

$form = new Form($db);
$formconfirm = '';

$head = array();
if (!empty($object->id)) {
    $h = 0;
    $link = dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $object->id;
    $head[$h][0] = $link;
    $head[$h][1] = $langs->trans('Card');
    $head[$h][2] = 'card';
    $h++;
    $head[$h][0] = $link.'&tab=culturas';
    $head[$h][1] = $langs->trans('Culturas');
    $head[$h][2] = 'culturas';
    $h++;
    $head[$h][0] = $link.'&tab=pragas';
    $head[$h][1] = $langs->trans('Pragas');
    $head[$h][2] = 'pragas';
}

$title = $langs->trans('ProdutoFormuladoCardTitle');
llxHeader('', $title);
$token = newToken();

if ($action === 'create') {
    $availableCulturas = array();
    $availablePragas = array();
    $selectedCulturas = GETPOST('fk_culturas', 'array:int');
    if (!is_array($selectedCulturas)) {
        $selectedCulturas = array();
    }
    $selectedPragas = GETPOST('fk_pragas', 'array:int');
    if (!is_array($selectedPragas)) {
        $selectedPragas = array();
    }
    $createDoseLabel = GETPOST('dose_label_cultura', 'alphanohtml');
    $createObservationCult = GETPOST('observacao_cultura', 'alphanohtml');
    $createObservationPraga = GETPOST('observacao_praga', 'alphanohtml');

    if ($safra_produto_schema_ok) {
        $culturaDao = new SafraCultura($db);
        $availableCulturas = $culturaDao->fetchAllForSelect();
        $pragaDao = new SafraPraga($db);
        $availablePragas = $pragaDao->fetchAllForSelect();
    }

    print load_fiche_titre($langs->trans('NewProdutoFormulado'), '', 'fa-flask');
    print '<form method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'">';
    print '<input type="hidden" name="token" value="'.$token.'">';
    print '<input type="hidden" name="action" value="add">';

    print '<table class="border centpercent">';
    print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td><input type="text" name="ref" class="flat minwidth200" value="'.dol_escape_htmltag(GETPOST('ref', 'alphanohtml')).'" required></td></tr>';
    print '<tr><td>'.$langs->trans('Label').'</td><td><input type="text" name="label" class="flat minwidth300" value="'.dol_escape_htmltag(GETPOST('label', 'alphanohtml')).'" required></td></tr>';
    print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('status', array(
        SafraProdutoFormulado::STATUS_ACTIVE => $langs->trans('ProdutoFormuladoStatusActive'),
        SafraProdutoFormulado::STATUS_DISABLED => $langs->trans('ProdutoFormuladoStatusDisabled'),
    ), GETPOSTINT('status') ?: SafraProdutoFormulado::STATUS_ACTIVE).'</td></tr>';
    print '<tr><td>'.$langs->trans('Description').'</td><td><textarea name="description" class="flat quatrevingtpercent" rows="4">'.dol_escape_htmltag(GETPOST('description', 'restricthtml')).'</textarea></td></tr>';
    print '</table>';

    if ($safra_produto_schema_ok) {
        print '<div class="fichecenter">';
        print '<p class="opacitymedium">'.$langs->trans('ProdutoFormuladoInitialInfo').'</p>';
        print '<div class="fichehalfleft">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('ProdutoFormuladoInitialCulturas').'</th></tr>';
        print '<tr><td class="width25p">'.$langs->trans('SelectCulturas').'</td><td>';
        print '<select name="fk_culturas[]" class="flat minwidth300 select2" multiple>';
        foreach ($availableCulturas as $cultura) {
            $selected = in_array((int) $cultura->rowid, $selectedCulturas, true) ? ' selected' : '';
            $label = ($cultura->code ? $cultura->code.' - ' : '').$cultura->label;
            print '<option value="'.(int) $cultura->rowid.'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
        }
        print '</select>';
        print '</td></tr>';
        print '<tr><td>'.$langs->trans('DoseLabel').'</td><td><input type="text" name="dose_label_cultura" class="flat minwidth200" value="'.dol_escape_htmltag($createDoseLabel).'"></td></tr>';
        print '<tr><td>'.$langs->trans('Observation').'</td><td><input type="text" name="observacao_cultura" class="flat minwidth200" value="'.dol_escape_htmltag($createObservationCult).'"></td></tr>';
        print '</table>';
        print '</div>';
        print '<div class="fichehalfright">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('ProdutoFormuladoInitialPragas').'</th></tr>';
        print '<tr><td class="width25p">'.$langs->trans('SelectPragas').'</td><td>';
        print '<select name="fk_pragas[]" class="flat minwidth300 select2" multiple>';
        foreach ($availablePragas as $praga) {
            $selected = in_array((int) $praga->rowid, $selectedPragas, true) ? ' selected' : '';
            $label = $praga->ref.' - '.$praga->label;
            print '<option value="'.(int) $praga->rowid.'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
        }
        print '</select>';
        print '</td></tr>';
        print '<tr><td>'.$langs->trans('Observation').'</td><td><input type="text" name="observacao_praga" class="flat minwidth200" value="'.dol_escape_htmltag($createObservationPraga).'"></td></tr>';
        print '</table>';
        print '</div>';
        print '<div class="clearboth"></div>';
        print '</div>';
    }

    print '<div class="center">';
    print '<input type="submit" class="button" value="'.$langs->trans('Create').'"> ';
    print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
    print '</div>';
    print '</form>';
} elseif (!empty($object->id)) {
    if ($action === 'edit' && $user->rights->safra->produtoformulado->write) {
        dol_fiche_head($head, empty($tab) ? 'card' : $tab, $langs->trans('ProdutoFormuladoCardTitle'), -1, 'fa-flask');
        print '<form method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'">';
        print '<input type="hidden" name="token" value="'.$token.'">';
        print '<input type="hidden" name="action" value="update">';
        print '<input type="hidden" name="id" value="'.(int) $object->id.'">';

        print '<table class="border centpercent">';
        print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td><input type="text" name="ref" class="flat minwidth200" value="'.dol_escape_htmltag($object->ref).'" required></td></tr>';
        print '<tr><td>'.$langs->trans('Label').'</td><td><input type="text" name="label" class="flat minwidth300" value="'.dol_escape_htmltag($object->label).'" required></td></tr>';
        print '<tr><td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('status', array(
            SafraProdutoFormulado::STATUS_ACTIVE => $langs->trans('ProdutoFormuladoStatusActive'),
            SafraProdutoFormulado::STATUS_DISABLED => $langs->trans('ProdutoFormuladoStatusDisabled'),
        ), $object->status).'</td></tr>';
        print '<tr><td>'.$langs->trans('Description').'</td><td><textarea name="description" class="flat quatrevingtpercent" rows="4">'.dol_escape_htmltag($object->description).'</textarea></td></tr>';
        print '</table>';

        print '<div class="center">';
        print '<input type="submit" class="button" value="'.$langs->trans('Save').'"> ';
        print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
        print '</div>';
        print '</form>';
        dol_fiche_end();
    } else {
        if ($action === 'delete') {
            $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteProdutoFormulado'), 'confirm_delete', array('token' => $token), 0, 1);
        }

        $tabactive = $tab ?: 'card';
        dol_fiche_head($head, $tabactive, $langs->trans('ProdutoFormuladoCardTitle'), -1, 'fa-flask');

        $linkback = '<a href="'.dol_buildpath('/safra/produto_formulado/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
        dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '', '', 0, '', '', $object->getLibStatut(4));

        print '<div class="fichecenter">';
        print '<div class="fichehalfleft">';
        print '<table class="border tableforfield" width="100%">';
        print '<tr><td class="titlefield">'.$langs->trans('Label').'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
        print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';
        print '<tr><td>'.$langs->trans('DateCreation').'</td><td>'.dol_print_date($db->jdate($object->date_creation), 'dayhour').'</td></tr>';
        print '<tr><td>'.$langs->trans('Description').'</td><td>'.dol_nl2br(dol_escape_htmltag($object->description)).'</td></tr>';
        print '</table>';
        print '</div>';
        print '<div class="fichehalfright">';
        print '</div>';
        print '</div>';

        dol_fiche_end();

        print '<div class="tabsAction">';
        if ($user->rights->safra->produtoformulado->write) {
            print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=edit">'.$langs->trans('Modify').'</a>';
        }
        if ($user->rights->safra->produtoformulado->delete) {
            print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=delete">'.$langs->trans('Delete').'</a>';
        }
        print '</div>';

        if ($tabactive === 'culturas') {
            $culturas = $object->fetchCultures();
            $culturaDao = new SafraCultura($db);
            $existingIds = array();
            foreach ($culturas as $line) {
                $existingIds[] = (int) $line->fk_cultura;
            }
            $availableCulturas = $culturaDao->fetchAllForSelect('', $existingIds);

            print load_fiche_titre($langs->trans('Culturas'), '', 'fa-leaf');
            include __DIR__.'/../tpl/lines_culturas.tpl.php';

            if ($user->rights->safra->produtoformulado->write) {
                print '<form method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'">';
                print '<input type="hidden" name="token" value="'.$token.'">';
                print '<input type="hidden" name="action" value="addculture">';
                print '<input type="hidden" name="id" value="'.(int) $object->id.'">';
                print '<div class="fichehalfleft">';
                print '<table class="noborder">';
                print '<tr><td>'.$langs->trans('SelectCulturas').'</td><td><select name="fk_culturas[]" class="flat minwidth300 select2" multiple>';
                foreach ($availableCulturas as $cultura) {
                    print '<option value="'.(int) $cultura->rowid.'">'.dol_escape_htmltag(($cultura->code ? $cultura->code.' - ' : '').$cultura->label).'</option>';
                }
                print '</select></td></tr>';
                print '<tr><td>'.$langs->trans('DoseLabel').'</td><td><input type="text" name="dose_label_cultura" class="flat minwidth200"></td></tr>';
                print '<tr><td>'.$langs->trans('Observation').'</td><td><input type="text" name="observacao_cultura" class="flat minwidth200"></td></tr>';
                print '</table>';
                print '</div>';
                print '<div class="clearboth"></div>';
                print '<div class="center"><button type="submit" class="button">'.$langs->trans('Add').'</button></div>';
                print '</form>';
            }
        } elseif ($tabactive === 'pragas') {
            $pragas = $object->fetchPragas();
            $pragaDao = new SafraPraga($db);
            $existingIds = array();
            foreach ($pragas as $line) {
                $existingIds[] = (int) $line->fk_praga;
            }
            $availablePragas = $pragaDao->fetchAllForSelect('', $existingIds);

            print load_fiche_titre($langs->trans('Pragas'), '', 'fa-bug');
            include __DIR__.'/../tpl/lines_pragas.tpl.php';

            if ($user->rights->safra->produtoformulado->write) {
                print '<form method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'">';
                print '<input type="hidden" name="token" value="'.$token.'">';
                print '<input type="hidden" name="action" value="addpraga">';
                print '<input type="hidden" name="id" value="'.(int) $object->id.'">';
                print '<div class="fichehalfleft">';
                print '<table class="noborder">';
                print '<tr><td>'.$langs->trans('SelectPragas').'</td><td><select name="fk_pragas[]" class="flat minwidth300 select2" multiple>';
                foreach ($availablePragas as $praga) {
                    print '<option value="'.(int) $praga->rowid.'">'.dol_escape_htmltag($praga->ref.' - '.$praga->label).'</option>';
                }
                print '</select></td></tr>';
                print '<tr><td>'.$langs->trans('Observation').'</td><td><input type="text" name="observacao_praga" class="flat minwidth200"></td></tr>';
                print '</table>';
                print '</div>';
                print '<div class="clearboth"></div>';
                print '<div class="center"><button type="submit" class="button">'.$langs->trans('Add').'</button></div>';
                print '</form>';
            }
        }
    }
} else {
    setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
}

if ($action === 'create' || (!empty($object->id) && in_array($tab ?: 'card', array('culturas', 'pragas'), true))) {
    print '<script>jQuery(function(){jQuery(".select2").select2();});</script>';
}

if ($formconfirm) {
    print $formconfirm;
}

llxFooter();
$db->close();
