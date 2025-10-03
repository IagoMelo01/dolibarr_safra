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
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
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
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/lib/product.lib.php';
dol_include_once('/safra/class/safra_product_link.class.php');

$langs->loadLangs(array('products', 'safra@safra'));

$id = GETPOSTINT('id');
$type = GETPOST('type', 'alpha');
$action = GETPOST('action', 'aZ09');

$validTypes = array('formulados', 'tecnicos');
if (!in_array($type, $validTypes, true)) {
    accessforbidden();
}

if (empty($user->rights->produit->lire)) {
    accessforbidden();
}

$object = new Product($db);
if ($object->fetch($id) <= 0) {
    accessforbidden($langs->trans('ErrorRecordNotFound'));
}

$canEditFormulados = !empty($user->rights->safra->produtoformulado->write);
$canEditTecnicos = !empty($user->rights->safra->produtostecnicos->write);

$canEdit = ($type === 'formulados' && $canEditFormulados) || ($type === 'tecnicos' && $canEditTecnicos);

if ($action === 'save' && !$canEdit) {
    accessforbidden();
}

$form = new Form($db);
$backUrl = dol_buildpath('/product/card.php', 1).'?id='.(int) $object->id;

$tabCode = $type === 'formulados' ? 'safra_formulados' : 'safra_tecnicos';
$title = $type === 'formulados' ? $langs->trans('SafraProductTabFormulados') : $langs->trans('SafraProductTabTecnicos');
$helpLabel = $type === 'formulados' ? $langs->trans('SafraProductTabFormuladosHelp') : $langs->trans('SafraProductTabTecnicosHelp');
$toggleLabel = $type === 'formulados' ? $langs->trans('SafraProductLinkFormulados') : $langs->trans('SafraProductLinkTecnicos');

SafraProductLink::ensureSchema($db);

if ($action === 'save') {
    $enabled = GETPOST('safra_link_toggle', 'int');
    $selected = $enabled ? GETPOST('safra_links', 'array:int') : array();
    if (!is_array($selected)) {
        $selected = array();
    }

    if (SafraProductLink::replaceProductLinks($db, $object->id, $type, $selected)) {
        setEventMessages($langs->trans('SafraProductLinksSaved'), null, 'mesgs');
    } else {
        setEventMessages($langs->trans('SafraProductLinksError'), null, 'errors');
    }
}

$selectedIds = SafraProductLink::fetchLinkedIds($db, $object->id, $type);
$options = SafraProductLink::fetchOptions($db, $type);

$toggleChecked = !empty($selectedIds) || GETPOST('safra_link_toggle', 'int');

$head = product_prepare_head($object);
llxHeader('', $title);

dol_fiche_head($head, $tabCode, $langs->trans('Product'), -1, $object->picto ?: 'product');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<form method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="id" value="'.(int) $object->id.'">';
print '<input type="hidden" name="type" value="'.dol_escape_htmltag($type).'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="safra_product_links_submitted" value="1">';

print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
print '<tr><td>'.$langs->trans('Label').'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(3).'</td></tr>';
print '<tr><td class="titlefield">'.$toggleLabel.'</td><td>';

if ($canEdit) {
    print '<label class="inline-block">';
    print '<input type="checkbox" name="safra_link_toggle" value="1" '.($toggleChecked ? 'checked' : '').' id="safra_link_toggle"> ';
    print $langs->trans('SafraProductLinkEnableToggle');
    print '</label>';
    print '<div id="safra_link_selector" style="margin-top: 8px;'.($toggleChecked ? '' : ' display:none;').'">';
    print '<select name="safra_links[]" class="flat minwidth300 select2" multiple data-placeholder="'.dol_escape_htmltag($langs->trans('SafraProductLinkPlaceholder')).'">';
    foreach ($options as $optionId => $label) {
        $selected = in_array((int) $optionId, $selectedIds, true) ? ' selected' : '';
        print '<option value="'.((int) $optionId).'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
    }
    print '</select>';
    print '<div class="opacitymedium small">'.$helpLabel.'</div>';
    print '</div>';
} else {
    if (empty($selectedIds)) {
        print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
    } else {
        print '<ul class="listunstyled">';
        foreach ($selectedIds as $sid) {
            if (isset($options[$sid])) {
                $label = dol_escape_htmltag($options[$sid]);
                if ($type === 'formulados') {
                    $url = dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $sid;
                } else {
                    $url = dol_buildpath('/safra/produtostecnicos_card.php', 1).'?id='.(int) $sid;
                }
                print '<li><a href="'.$url.'">'.$label.'</a></li>';
            }
        }
        print '</ul>';
    }
}

print '</td></tr>';
print '</table>';

if ($canEdit) {
    print '<div class="center" style="margin-top: 16px;">';
    print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
    print ' <a class="button" href="'.$backUrl.'">'.$langs->trans('Cancel').'</a>';
    print '</div>';
} else {
    print '<div class="center" style="margin-top: 16px;"><a class="button" href="'.$backUrl.'">'.$langs->trans('Back').'</a></div>';
}

print '</form>';
print '</div>';
print '</div>';

dol_fiche_end();

llxFooter();
$db->close();
