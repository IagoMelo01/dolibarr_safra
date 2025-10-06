<?php
/*
 * Custom search page for pests and related products.
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
    while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] === $tmp2[$j]) {
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
dol_include_once('/safra/class/safra_praga.class.php');
dol_include_once('/safra/class/safra_produto_formulado.class.php');
dol_include_once('/safra/class/safra_product_link.class.php');

$langs->loadLangs(array('safra@safra', 'products'));

if (empty($user->rights->safra->pragas->read)) {
    accessforbidden();
}

$searchValue = trim(GETPOST('search_praga', 'restricthtml'));
$pragaId = GETPOSTINT('praga_id');

$form = new Form($db);

$pragaDao = new SafraPraga($db);
$pragaMatches = array();
if ($searchValue !== '') {
    $pragaMatches = $pragaDao->fetchAllForSelect($searchValue);
    if (empty($pragaId) && count($pragaMatches) === 1) {
        $pragaId = (int) $pragaMatches[0]->rowid;
    }
}

$selectedPraga = null;
if ($pragaId > 0) {
    $pragaTmp = new SafraPraga($db);
    if ($pragaTmp->fetch($pragaId) > 0) {
        $selectedPraga = $pragaTmp;
    } else {
        setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
    }
}

$schemaReady = SafraProdutoFormulado::ensureDatabaseSchema($db);
if (!$schemaReady) {
    setEventMessages($langs->trans('ProdutoFormuladoSchemaError'), null, 'errors');
}

$linkSchemaReady = SafraProductLink::ensureDatabaseSchema($db);
if (!$linkSchemaReady) {
    setEventMessages($langs->trans('SafraProductLinkSchemaError'), null, 'errors');
}

$products = array();
$linkedProducts = array();
$totalStock = array();
$hasStock = array();

if ($selectedPraga && $schemaReady) {
    $sql = 'SELECT pf.rowid, pf.ref, pf.label, pf.ingrediente_ativo, pf.modo_acao, pf.classe, pf.status, pp.observacao'
        .' FROM '.MAIN_DB_PREFIX.'safra_produto_formulado AS pf'
        .' INNER JOIN '.MAIN_DB_PREFIX.'safra_produto_praga AS pp ON pp.fk_produto = pf.rowid'
        .' WHERE pp.fk_praga = '.((int) $selectedPraga->id)
        .' ORDER BY pf.label';

    $resql = $db->query($sql);
    if ($resql) {
        $pfIds = array();
        while ($obj = $db->fetch_object($resql)) {
            $products[] = $obj;
            $pfIds[(int) $obj->rowid] = (int) $obj->rowid;
        }
        $db->free($resql);

        if (!empty($pfIds) && $linkSchemaReady) {
            $sqlLinks = 'SELECT l.fk_produto_formulado AS pf_id, p.rowid AS product_id, p.ref, p.label, '
                .'COALESCE(SUM(ps.reel), p.stock) AS stock'
                .' FROM '.MAIN_DB_PREFIX.'safra_product_formulado AS l'
                .' INNER JOIN '.MAIN_DB_PREFIX.'product AS p ON p.rowid = l.fk_product'
                .' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock AS ps ON ps.fk_product = p.rowid'
                .' WHERE l.fk_produto_formulado IN ('.implode(',', $pfIds).')'
                .' AND p.entity IN ('.getEntity('product').')'
                .' GROUP BY l.fk_produto_formulado, p.rowid, p.ref, p.label, p.stock'
                .' ORDER BY p.ref';

            $resqlLinks = $db->query($sqlLinks);
            if ($resqlLinks) {
                while ($link = $db->fetch_object($resqlLinks)) {
                    $pfId = (int) $link->pf_id;
                    if (!isset($linkedProducts[$pfId])) {
                        $linkedProducts[$pfId] = array();
                        $totalStock[$pfId] = 0.0;
                        $hasStock[$pfId] = false;
                    }
                    $stockValue = price2num((float) $link->stock, 'MS');
                    $linkedProducts[$pfId][] = array(
                        'id' => (int) $link->product_id,
                        'ref' => $link->ref,
                        'label' => $link->label,
                        'stock' => $stockValue,
                    );
                    $totalStock[$pfId] += $stockValue;
                    if ($stockValue > 0) {
                        $hasStock[$pfId] = true;
                    }
                }
                $db->free($resqlLinks);
            } else {
                setEventMessages($db->lasterror(), null, 'errors');
            }
        }
    } else {
        setEventMessages($db->lasterror(), null, 'errors');
    }
}

$title = $langs->trans('PragaProductSearchTitle');
llxHeader('', $title);

print load_fiche_titre($title, '', 'fa-bug');

print '<form method="GET" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('PragaProductSearchTitle').'</th></tr>';
print '<tr><td>';
print '<input type="text" class="flat minwidth300" name="search_praga" value="'.dol_escape_htmltag($searchValue).'" placeholder="'.$langs->trans('PragaProductSearchPlaceholder').'">';
print ' <input type="submit" class="button" value="'.$langs->trans('PragaProductSearchButton').'">';
print '</td></tr>';
print '<tr><td class="opacitymedium">'.$langs->trans('PragaProductSearchHelp').'</td></tr>';
print '</table>';
print '</div>';
print '<div class="clearboth"></div>';
print '</form>';

if ($searchValue !== '') {
    print '<div class="fichecenter">';
    print '<h2 class="noborder">'.$langs->trans('PragaProductMatchesTitle').'</h2>';
    if (empty($pragaMatches)) {
        print '<p class="opacitymedium">'.$langs->trans('PragaProductNoPragaFound').'</p>';
    } else {
        print '<ul class="listwithdot">';
        foreach ($pragaMatches as $match) {
            $link = dol_buildpath('/safra/pragas_produtos.php', 1).'?praga_id='.(int) $match->rowid;
            if ($searchValue !== '') {
                $link .= '&search_praga='.urlencode($searchValue);
            }
            print '<li>';
            print '<strong>'.dol_escape_htmltag($match->ref).'</strong> - '.dol_escape_htmltag($match->label);
            if (!empty($match->label_cientifico)) {
                print ' <span class="opacitymedium">('.dol_escape_htmltag($match->label_cientifico).')</span>';
            }
            print ' <a class="button small" href="'.$link.'">'.$langs->trans('PragaProductSelectLink').'</a>';
            print '</li>';
        }
        print '</ul>';
    }
    print '</div>';
}

if ($selectedPraga) {
    print '<div class="fichecenter">';
    print '<h2 class="noborder">'.sprintf($langs->trans('PragaProductResultsTitle'), dol_escape_htmltag($selectedPraga->label)).'</h2>';
    if (empty($products)) {
        print '<p class="opacitymedium">'.$langs->trans('PragaProductNoProducts').'</p>';
    } else {
        print '<div class="div-table-responsive">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>'.$langs->trans('Ref').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th>'.$langs->trans('IngredienteAtivo').'</th>';
        print '<th>'.$langs->trans('PragaProductObservation').'</th>';
        print '<th>'.$langs->trans('PragaProductLinkedProducts').'</th>';
        print '<th class="right">'.$langs->trans('PragaProductTotalStock').'</th>';
        print '<th class="center">'.$langs->trans('PragaProductStockHeader').'</th>';
        print '</tr>';

        foreach ($products as $row) {
            $pfId = (int) $row->rowid;
            $productUrl = dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.$pfId;
            print '<tr class="oddeven">';
            print '<td><a href="'.$productUrl.'">'.dol_escape_htmltag($row->ref).'</a></td>';
            print '<td>'.dol_escape_htmltag($row->label).'</td>';
            print '<td>'.dol_escape_htmltag($row->ingrediente_ativo).'</td>';
            print '<td>'.dol_escape_htmltag($row->observacao).'</td>';

            print '<td>';
            if (!empty($linkedProducts[$pfId])) {
                print '<ul class="listwithdot">';
                foreach ($linkedProducts[$pfId] as $linked) {
                    $stockValue = (float) $linked['stock'];
                    $formattedStock = dol_escape_htmltag(price2num($stockValue, 'MS'));
                    $productLink = dol_buildpath('/product/card.php', 1).'?id='.(int) $linked['id'];
                    print '<li><a href="'.$productLink.'">'.dol_escape_htmltag($linked['ref']).'</a> - '.dol_escape_htmltag($linked['label']).' <span class="opacitymedium">('.$langs->trans('Stock').' '.$formattedStock.')</span></li>';
                }
                print '</ul>';
            } else {
                print '<span class="opacitymedium">'.$langs->trans('PragaProductStockUnknown').'</span>';
            }
            print '</td>';

            $total = isset($totalStock[$pfId]) ? (float) $totalStock[$pfId] : 0.0;
            $formattedTotal = dol_escape_htmltag(price2num($total, 'MS'));
            print '<td class="right">'.$formattedTotal.'</td>';

            $stockStatus = !empty($hasStock[$pfId]);
            if ($stockStatus) {
                print '<td class="center">'.img_picto($langs->trans('PragaProductStockYes'), 'statut4').'</td>';
            } else {
                print '<td class="center">'.img_picto($langs->trans('PragaProductStockNo'), 'statut0').'</td>';
            }

            print '</tr>';
        }

        print '</table>';
        print '</div>';
    }
    print '</div>';
}

llxFooter();
$db->close();
