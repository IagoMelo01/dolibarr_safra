<?php
/*
 * Copyright (C) 2025
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

/**
 *    \file       product_safra_links.php
 *    \ingroup    safra
 *    \brief      Product tab to display Safra links
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
        $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && !empty($_SERVER['SCRIPT_FILENAME'])) {
        $tmp = $_SERVER['SCRIPT_FILENAME'];
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
}
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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/lib/product.lib.php';

dol_include_once('/safra/class/safra_product_link.class.php');

// Load translation files
$langs->loadLangs(array('products', 'safra@safra'));

$id = GETPOST('id', 'int');
$type = GETPOST('type', 'aZ09');
$action = GETPOST('action', 'aZ09');

if (empty($id)) {
        accessforbidden();
}

if (empty($user->rights->produit->lire)) {
        accessforbidden();
}

if ($type === SafraProductLink::TYPE_FORMULADO) {
        if (empty($user->rights->safra->produtoformulado->read)) {
                accessforbidden();
        }
        $title = $langs->trans('SafraProductLinkTitleFormulado');
        $tabCode = 'safra_product_formulado';
        $cardUrlPattern = '/safra/produto_formulado/card.php?id=%d';
} elseif ($type === SafraProductLink::TYPE_TECNICO) {
        if (empty($user->rights->safra->produtostecnicos->read)) {
                accessforbidden();
        }
        $title = $langs->trans('SafraProductLinkTitleTecnico');
        $tabCode = 'safra_product_tecnico';
        $cardUrlPattern = '/safra/produtostecnicos_card.php?id=%d';
} else {
        accessforbidden();
}

$object = new Product($db);
if ($object->fetch($id) <= 0) {
        accessforbidden();
}

$backtopage = dol_buildpath('/product/card.php?id='.$object->id, 1);

if (!SafraProductLink::ensureDatabaseSchema($db)) {
        setEventMessages($langs->trans('SafraProductLinkSchemaError'), null, 'errors');
}

if ($action === 'unlink') {
        $token = GETPOST('token', 'alpha');
        if (empty($token) || !dol_verify_token($token)) {
                accessforbidden();
        }

        $rowid = GETPOST('rowid', 'int');
        if ($rowid > 0) {
                $allowed = ($type === SafraProductLink::TYPE_FORMULADO && !empty($user->rights->safra->produtoformulado->write))
                        || ($type === SafraProductLink::TYPE_TECNICO && !empty($user->rights->safra->produtostecnicos->write));
                if (!$allowed) {
                        accessforbidden();
                }

                if (SafraProductLink::deleteLink($db, $rowid, $type)) {
                        setEventMessages($langs->trans('SafraProductLinkRemoved'), null, 'mesgs');
                } else {
                        setEventMessages($langs->trans('SafraProductLinkNotRemoved'), null, 'errors');
                }
        }

        header('Location: '.dol_buildpath('/safra/product_safra_links.php', 1).'?id='.$object->id.'&type='.$type);
        exit;
}

$links = array();
if (!empty($object->id)) {
        $links = SafraProductLink::fetchLinks($db, $object->id, $type);
}

llxHeader('', $title);

$head = product_prepare_head($object);
print dol_get_fiche_head($head, $tabCode, $langs->trans('Product'), -1, $object->type ? 'service' : 'product');

dol_banner_tab($object, 'ref', '', 0, 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '</div>';

print dol_get_fiche_end();

print '<div class="tabsAction">';
print '<a class="butAction" href="'.dol_escape_htmltag($backtopage).'">'.dol_escape_htmltag($langs->trans('BackToList')).'</a>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';
print '</div>';
print '</div>';

print '<div class="opacitymedium small">'.dol_escape_htmltag($langs->trans('SafraProductLinkTabHelp')).'</div>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Ref').'</th>';
print '<th>'.$langs->trans('Label').'</th>';
print '<th class="right">'.$langs->trans('SafraProductLinkActions').'</th>';
print '</tr>';

if (empty($links)) {
        print '<tr class="oddeven">';
        print '<td colspan="3">'.dol_escape_htmltag($langs->trans('SafraProductLinkNone')).'</td>';
        print '</tr>';
} else {
        foreach ($links as $link) {
                $url = dol_buildpath(sprintf($cardUrlPattern, (int) $link->target_id), 1);
                print '<tr class="oddeven">';
                print '<td><a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($link->ref).'</a></td>';
                print '<td>'.dol_escape_htmltag($link->label).'</td>';

                $button = '';
                $allowed = ($type === SafraProductLink::TYPE_FORMULADO && !empty($user->rights->safra->produtoformulado->write))
                        || ($type === SafraProductLink::TYPE_TECNICO && !empty($user->rights->safra->produtostecnicos->write));
                if ($allowed) {
                        $button = dolGetButtonAction(
                                $langs->trans('Delete'),
                                '',
                                'delete',
                                dol_buildpath('/safra/product_safra_links.php', 1).'?id='.$object->id.'&type='.$type.'&action=unlink&rowid='.(int) $link->rowid.'&token='.newToken(),
                                '',
                                1
                        );
                }

                print '<td class="right">'.$button.'</td>';
                print '</tr>';
        }
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
