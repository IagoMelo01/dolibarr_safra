<?php
/*
 * Copyright (C) 2025
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
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
if (!$res) {
        die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

dol_include_once('/safra/class/safra_praga.class.php');

// Load translation files
$langs->loadLangs(array('safra@safra', 'products'));

if (empty($user->rights->safra->pragas->read)
        || empty($user->rights->safra->produtoformulado->read)
        || empty($user->rights->produit->lire)) {
        accessforbidden();
}

$canViewTecnicos = !empty($user->rights->safra->produtostecnicos->read);
$canViewStock = !empty($user->rights->stock->lire);

$pragaId = GETPOSTINT('praga_id');
$pragaDao = new SafraPraga($db);

$title = $langs->trans('SafraPragaProductSearchTitle');
llxHeader('', $title);

print load_fiche_titre($title, '', 'fa-bug');

$availablePragas = $pragaDao->fetchAllForSelect();

print '<form method="GET" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" class="marginbottom">';
print '<div class="fichecenter">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('SafraPragaProductSearchSelect').'</th>';
print '<th class="right">'.$langs->trans('Action').'</th>';
print '</tr>';
print '<tr class="oddeven">';
print '<td class="maxwidth500">';
print '<select name="praga_id" class="flat minwidth300 select2">';
print '<option value="">'.$langs->trans('SafraPragaProductSelectPlaceholder').'</option>';
foreach ($availablePragas as $pragaOption) {
        $selected = ($pragaId > 0 && (int) $pragaOption->rowid === $pragaId) ? ' selected' : '';
        $label = $pragaOption->ref;
        if (!empty($pragaOption->label)) {
                $label .= ' - '.$pragaOption->label;
        }
        print '<option value="'.(int) $pragaOption->rowid.'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
}
print '</select>';
print '</td>';
print '<td class="right">';
print '<button type="submit" class="button">'.$langs->trans('Search').'</button>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';

if ($pragaId > 0) {
        $praga = new SafraPraga($db);
        if ($praga->fetch($pragaId) > 0) {
                print '<div class="fichecenter">';
                print '<div class="fichehalfleft">';
                print '<table class="border centpercent">';
                print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.dol_escape_htmltag($praga->ref).'</td></tr>';
                print '<tr><td>'.$langs->trans('Label').'</td><td>'.dol_escape_htmltag($praga->label).'</td></tr>';
                print '<tr><td>'.$langs->trans('ScientificName').'</td><td>'.dol_escape_htmltag($praga->label_cientifico).'</td></tr>';
                print '</table>';
                print '</div>';
                print '<div class="clearboth"></div>';
                print '</div>';

                $sql = 'SELECT pf.rowid, pf.ref, pf.label, pf.ingrediente_ativo, link.observacao'
                        .' FROM '.MAIN_DB_PREFIX.'safra_produto_formulado AS pf'
                        .' INNER JOIN '.MAIN_DB_PREFIX.'safra_produto_praga AS link ON link.fk_produto = pf.rowid'
                        .' WHERE link.fk_praga = '.((int) $pragaId)
                        .' ORDER BY pf.ref ASC';

                $formulados = array();
                $resql = $db->query($sql);
                if ($resql) {
                        while ($obj = $db->fetch_object($resql)) {
                                $formulados[(int) $obj->rowid] = $obj;
                        }
                        $db->free($resql);
                } else {
                        dol_print_error($db);
                }

                if (empty($formulados)) {
                        print '<div class="opacitymedium">'.$langs->trans('SafraPragaProductNoResult').'</div>';
                } else {
                        $formuladoIds = array_keys($formulados);

                        $productLinks = array();
                        $productIds = array();
                        if (!empty($formuladoIds) && $user->rights->produit->lire) {
                                $sqlLinks = 'SELECT fk_produto_formulado, fk_product FROM '.MAIN_DB_PREFIX.'safra_product_formulado'
                                        .' WHERE fk_produto_formulado IN ('.implode(',', array_map('intval', $formuladoIds)).')';
                                $resLinks = $db->query($sqlLinks);
                                if ($resLinks) {
                                        while ($linkObj = $db->fetch_object($resLinks)) {
                                                $pfId = (int) $linkObj->fk_produto_formulado;
                                                $prodId = (int) $linkObj->fk_product;
                                                if ($pfId > 0 && $prodId > 0) {
                                                        $productLinks[$pfId][$prodId] = $prodId;
                                                        $productIds[$prodId] = $prodId;
                                                }
                                        }
                                        $db->free($resLinks);
                                }
                        }

                        $products = array();
                        if (!empty($productIds) && $user->rights->produit->lire) {
                                foreach ($productIds as $pid) {
                                        $prod = new Product($db);
                                        if ($prod->fetch($pid) > 0) {
                                                $prod->load_stock();
                                                $products[$pid] = $prod;
                                        }
                                }
                        }

                        $productTecnicos = array();
                        if (!empty($productIds) && $canViewTecnicos) {
                                $sqlTecnicos = 'SELECT l.fk_product, pt.rowid, pt.ref, pt.label'
                                        .' FROM '.MAIN_DB_PREFIX.'safra_product_produtostecnico AS l'
                                        .' INNER JOIN '.MAIN_DB_PREFIX.'safra_produtostecnicos AS pt ON pt.rowid = l.fk_produtotecnico'
                                        .' WHERE l.fk_product IN ('.implode(',', array_map('intval', $productIds)).')'
                                        .' ORDER BY pt.ref ASC';
                                $resTec = $db->query($sqlTecnicos);
                                if ($resTec) {
                                        while ($tecObj = $db->fetch_object($resTec)) {
                                                $fkProduct = (int) $tecObj->fk_product;
                                                if ($fkProduct > 0) {
                                                        $productTecnicos[$fkProduct][] = $tecObj;
                                                }
                                        }
                                        $db->free($resTec);
                                }
                        }

                        print '<div class="div-table-responsive">';
                        print '<table class="noborder centpercent">';
                        print '<tr class="liste_titre">';
                        print '<th>'.$langs->trans('Ref').'</th>';
                        print '<th>'.$langs->trans('Label').'</th>';
                        print '<th>'.$langs->trans('IngredienteAtivo').'</th>';
                        print '<th>'.$langs->trans('SafraPragaProductObservation').'</th>';
                        print '<th>'.$langs->trans('SafraPragaProductLinkedProducts').'</th>';
                        print '</tr>';

                        foreach ($formulados as $pfId => $pf) {
                                print '<tr class="oddeven">';
                                print '<td>'.dol_escape_htmltag($pf->ref).'</td>';
                                print '<td>'.dol_escape_htmltag($pf->label).'</td>';
                                print '<td>'.dol_escape_htmltag($pf->ingrediente_ativo).'</td>';
                                print '<td>'.dol_escape_htmltag($pf->observacao).'</td>';
                                print '<td>';

                                if (!empty($productLinks[$pfId])) {
                                        print '<ul class="safra-linked-products" style="margin:0;padding-left:1.2em;list-style:none;">';
                                        foreach ($productLinks[$pfId] as $prodId) {
                                                print '<li>';
                                                if (!empty($products[$prodId])) {
                                                        $prod = $products[$prodId];
                                                        print $prod->getNomUrl(1);
                                                        if ($canViewStock) {
                                                                $stockValue = isset($prod->stock_reel) ? $prod->stock_reel : (isset($prod->stock) ? $prod->stock : 0);
                                                                $qtyDecimals = isset($conf->global->MAIN_MAX_DECIMALS_QTY) ? (int) $conf->global->MAIN_MAX_DECIMALS_QTY : 2;
                                                                print ' <span class="badge">'.price($stockValue, 0, '', 0, 0, -1, 0, $qtyDecimals).'</span>';
                                                        } else {
                                                                print ' <span class="opacitymedium">'.$langs->trans('SafraPragaProductStockUnavailable').'</span>';
                                                        }

                                                        if (!empty($productTecnicos[$prodId])) {
                                                                $tecnicoLinks = array();
                                                                foreach ($productTecnicos[$prodId] as $tec) {
                                                                        $url = dol_buildpath('/safra/produtostecnicos_card.php', 1).'?id='.(int) $tec->rowid;
                                                                        $label = dol_escape_htmltag($tec->ref.(empty($tec->label) ? '' : ' - '.$tec->label));
                                                                        $tecnicoLinks[] = '<a href="'.$url.'">'.$label.'</a>';
                                                                }
                                                                if ($tecnicoLinks) {
                                                                        print '<br><span class="opacitymedium">'.$langs->trans('SafraPragaProductTechnical').': '.implode(', ', $tecnicoLinks).'</span>';
                                                                }
                                                        } elseif ($canViewTecnicos) {
                                                                print '<br><span class="opacitymedium">'.$langs->trans('SafraPragaProductNoTechnical').'</span>';
                                                        }
                                                } else {
                                                        print '<span class="opacitymedium">'.$langs->trans('SafraPragaProductMissingProduct').'</span>';
                                                }
                                                print '</li>';
                                        }
                                        print '</ul>';
                                } else {
                                        print '<span class="opacitymedium">'.$langs->trans('SafraPragaProductNoDolibarrProduct').'</span>';
                                }

                                print '</td>';
                                print '</tr>';
                        }

                        print '</table>';
                        print '</div>';
                }
        } else {
                setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
        }
}

print '<script>jQuery(function($){ $(".select2").select2({width:"resolve"}); });</script>';

llxFooter();
$db->close();
