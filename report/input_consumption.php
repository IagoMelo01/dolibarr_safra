<?php
/*
 * Input consumption report by season, crop, field plot and product.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/safra/class/ActivityPlanningService.class.php');

global $db, $user, $langs;

$langs->loadLangs(array('safra@safra', 'products', 'stocks'));
if (!($user->rights->safra->SafraActivity->read ?? 0)) {
    accessforbidden();
}

function safra_consumption_load_options($db, $sql)
{
    $options = array(0 => '');
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $options[(int) $obj->rowid] = trim((string) $obj->label);
        }
    }

    return $options;
}

$searchSeason = GETPOST('search_season', 'alpha');
$searchCrop = GETPOST('search_crop', 'alpha');
$searchFieldplot = GETPOSTINT('search_fieldplot');
$searchProduct = GETPOSTINT('search_product');
$searchFrom = GETPOST('search_from', 'alphanohtml');
$searchTo = GETPOST('search_to', 'alphanohtml');
$format = GETPOST('format', 'alpha');

$quantityBase = 'COALESCE(NULLIF(l.stock_movement_qty, 0), NULLIF(l.qty_done, 0), 0)';
$quantityExpression = "CASE WHEN l.movement_type = 'return' THEN -ABS(" . $quantityBase . ') ELSE ABS(' . $quantityBase . ') END';
$dateExpression = 'COALESCE(a.date_end, a.date_start, a.date_planned_start, a.date_creation)';
$whereSql = ' WHERE a.entity IN (' . getEntity('safra_activity') . ')';
$whereSql .= ' AND a.status IN (' . FvActivity::STATUS_IN_PROGRESS . ',' . FvActivity::STATUS_COMPLETED . ')';
$whereSql .= ' AND l.fk_stock_movement IS NOT NULL AND l.fk_stock_movement > 0';
if ($searchSeason !== '') {
    $whereSql .= natural_search('a.season', $searchSeason);
}
if ($searchCrop !== '') {
    $whereSql .= natural_search('a.crop_name', $searchCrop);
}
if ($searchFieldplot > 0) {
    $whereSql .= ' AND a.fk_fieldplot = ' . $searchFieldplot;
}
if ($searchProduct > 0) {
    $whereSql .= ' AND l.fk_product = ' . $searchProduct;
}
if ($searchFrom !== '' && strtotime($searchFrom)) {
    $whereSql .= " AND " . $dateExpression . " >= '" . $db->idate(strtotime($searchFrom . ' 00:00:00')) . "'";
}
if ($searchTo !== '' && strtotime($searchTo)) {
    $whereSql .= " AND " . $dateExpression . " <= '" . $db->idate(strtotime($searchTo . ' 23:59:59')) . "'";
}

$sql = 'SELECT a.season, a.crop_name, a.fk_fieldplot, t.ref as fieldplot_ref, t.label as fieldplot_label,';
$sql .= ' l.fk_product, p.ref as product_ref, p.label as product_label, l.dose_unit,';
$sql .= ' SUM(' . $quantityExpression . ') as consumed_qty,';
$sql .= ' SUM((' . $quantityExpression . ') * COALESCE(l.unit_cost, 0)) as total_cost,';
$sql .= ' COUNT(DISTINCT a.rowid) as activity_count, MAX(' . $dateExpression . ') as last_use';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'safra_activity_line as l';
$sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'safra_activity as a ON a.rowid = l.fk_activity';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'safra_talhao as t ON t.rowid = a.fk_fieldplot';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = l.fk_product';
$sql .= $whereSql;
$sql .= ' GROUP BY a.season, a.crop_name, a.fk_fieldplot, t.ref, t.label, l.fk_product, p.ref, p.label, l.dose_unit';
$sql .= ' ORDER BY a.season, a.crop_name, t.ref, t.label, p.ref, p.label';

$rows = array();
$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}
while ($obj = $db->fetch_object($resql)) {
    $rows[] = $obj;
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="safra-input-consumption-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array(
        $langs->trans('SafraActivitySeason'),
        $langs->trans('SafraActivityCrop'),
        $langs->trans('FieldPlot'),
        $langs->trans('Product'),
        $langs->trans('SafraInputConsumptionQuantity'),
        $langs->trans('Unit'),
        $langs->trans('SafraInputConsumptionActivities'),
        $langs->trans('SafraInputConsumptionCost'),
        $langs->trans('SafraInputConsumptionLastUse'),
    ), ';');
    foreach ($rows as $row) {
        fputcsv($output, array(
            $row->season,
            $row->crop_name,
            trim((string) (($row->fieldplot_ref ? $row->fieldplot_ref . ' - ' : '') . $row->fieldplot_label)),
            trim((string) (($row->product_ref ? $row->product_ref . ' - ' : '') . $row->product_label)),
            $row->consumed_qty,
            ActivityPlanningService::consumptionUnit($row->dose_unit),
            $row->activity_count,
            $row->total_cost,
            $row->last_use,
        ), ';');
    }
    fclose($output);
    exit;
}

$form = new Form($db);
$fieldplotOptions = safra_consumption_load_options($db, 'SELECT rowid, CONCAT(ref, " - ", label) as label FROM ' . MAIN_DB_PREFIX . 'safra_talhao ORDER BY ref, label');
$productOptions = safra_consumption_load_options($db, 'SELECT rowid, CONCAT(ref, " - ", label) as label FROM ' . MAIN_DB_PREFIX . 'product WHERE entity IN (' . getEntity('product') . ') ORDER BY ref, label');
$costTotal = 0;
foreach ($rows as $row) {
    $costTotal += (float) $row->total_cost;
}
$activityTotal = 0;
$summarySql = 'SELECT COUNT(DISTINCT a.rowid) as activity_count'
    . ' FROM ' . MAIN_DB_PREFIX . 'safra_activity_line as l'
    . ' INNER JOIN ' . MAIN_DB_PREFIX . 'safra_activity as a ON a.rowid = l.fk_activity'
    . $whereSql;
$summaryResql = $db->query($summarySql);
if ($summaryResql && ($summaryObj = $db->fetch_object($summaryResql))) {
    $activityTotal = (int) $summaryObj->activity_count;
}

llxHeader('', $langs->trans('SafraInputConsumptionReport'), '', '', 0, 0, array(), array('/safra/css/safra.css.php'));

$csvParams = $_GET;
$csvParams['format'] = 'csv';
$buttons = '<a class="butAction" href="' . dol_buildpath('/safra/activity/activity_kanban.php', 1) . '">' . $langs->trans('SafraActivityKanbanTitle') . '</a>';
$buttons .= '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query($csvParams) . '">' . $langs->trans('SafraExportCsv') . '</a>';
print load_fiche_titre($langs->trans('SafraInputConsumptionReport'), $buttons, 'fa-chart-bar');
print '<div class="opacitymedium">' . $langs->trans('SafraInputConsumptionHelp') . '</div>';

print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '" class="safra-filter-panel">';
print '<div class="safra-filter-grid">';
print '<label>' . $langs->trans('SafraActivitySeason') . '<input class="flat" type="text" name="search_season" value="' . dol_escape_htmltag($searchSeason) . '"></label>';
print '<label>' . $langs->trans('SafraActivityCrop') . '<input class="flat" type="text" name="search_crop" value="' . dol_escape_htmltag($searchCrop) . '"></label>';
print '<label>' . $langs->trans('FieldPlot') . $form->selectarray('search_fieldplot', $fieldplotOptions, $searchFieldplot, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</label>';
print '<label>' . $langs->trans('Product') . $form->selectarray('search_product', $productOptions, $searchProduct, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</label>';
print '<label>' . $langs->trans('DateStart') . '<input class="flat" type="date" name="search_from" value="' . dol_escape_htmltag($searchFrom) . '"></label>';
print '<label>' . $langs->trans('DateEnd') . '<input class="flat" type="date" name="search_to" value="' . dol_escape_htmltag($searchTo) . '"></label>';
print '</div><div class="safra-filter-actions">' . $form->showFilterButtons() . '</div></form>';

print '<div class="safra-report-summary">';
print '<div><strong>' . count($rows) . '</strong><span>' . $langs->trans('SafraInputConsumptionGroups') . '</span></div>';
print '<div><strong>' . $activityTotal . '</strong><span>' . $langs->trans('SafraInputConsumptionActivities') . '</span></div>';
print '<div><strong>' . price($costTotal, 0, '', 1, 2) . '</strong><span>' . $langs->trans('SafraInputConsumptionCost') . '</span></div>';
print '</div>';

print '<div class="div-table-responsive"><table class="liste centpercent">';
print '<tr class="liste_titre"><th>' . $langs->trans('SafraActivitySeason') . '</th><th>' . $langs->trans('SafraActivityCrop') . '</th><th>' . $langs->trans('FieldPlot') . '</th><th>' . $langs->trans('Product') . '</th><th class="right">' . $langs->trans('SafraInputConsumptionQuantity') . '</th><th>' . $langs->trans('Unit') . '</th><th class="right">' . $langs->trans('SafraInputConsumptionActivities') . '</th><th class="right">' . $langs->trans('SafraInputConsumptionCost') . '</th><th class="center">' . $langs->trans('SafraInputConsumptionLastUse') . '</th></tr>';
if (empty($rows)) {
    print '<tr class="oddeven"><td colspan="9" class="opacitymedium center">' . $langs->trans('NoRecordFound') . '</td></tr>';
}
foreach ($rows as $row) {
    $fieldplotLabel = trim((string) (($row->fieldplot_ref ? $row->fieldplot_ref . ' - ' : '') . $row->fieldplot_label));
    $productLabel = trim((string) (($row->product_ref ? $row->product_ref . ' - ' : '') . $row->product_label));
    print '<tr class="oddeven"><td>' . dol_escape_htmltag($row->season) . '</td><td>' . dol_escape_htmltag($row->crop_name) . '</td><td>' . dol_escape_htmltag($fieldplotLabel) . '</td>';
    print '<td><a href="' . DOL_URL_ROOT . '/product/card.php?id=' . ((int) $row->fk_product) . '">' . dol_escape_htmltag($productLabel) . '</a></td>';
    print '<td class="right">' . price($row->consumed_qty, 0, '', 1, 4) . '</td><td>' . dol_escape_htmltag(ActivityPlanningService::consumptionUnit($row->dose_unit)) . '</td>';
    print '<td class="right">' . ((int) $row->activity_count) . '</td><td class="right">' . price($row->total_cost, 0, '', 1, 2) . '</td><td class="center">' . (!empty($row->last_use) ? dol_print_date($db->jdate($row->last_use), 'dayhour') : '') . '</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
