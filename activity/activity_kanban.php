<?php
/*
 * Agricultural activity planning kanban.
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

global $db, $user, $langs, $conf;

$langs->loadLangs(array('safra@safra', 'projects'));
if (!($user->rights->safra->SafraActivity->read ?? 0)) {
    accessforbidden();
}

$searchSeason = GETPOST('search_season', 'alpha');
$searchCrop = GETPOST('search_crop', 'alpha');
$searchFieldplot = GETPOSTINT('search_fieldplot');
$searchType = GETPOST('search_type', 'alphanohtml');
$searchFrom = GETPOST('search_from', 'alphanohtml');
$searchTo = GETPOST('search_to', 'alphanohtml');

$form = new Form($db);
$typeOptions = array('' => '') + FvActivity::getTypeOptions($langs);
$fieldplotOptions = array(0 => '');
$resql = $db->query('SELECT rowid, ref, label FROM ' . MAIN_DB_PREFIX . 'safra_talhao ORDER BY ref, label');
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $fieldplotOptions[(int) $obj->rowid] = trim((string) (($obj->ref ? $obj->ref . ' - ' : '') . $obj->label));
    }
}

$sql = 'SELECT a.rowid, a.ref, a.label, a.type, a.status, a.priority, a.progress, a.season, a.crop_name, a.fk_fieldplot,';
$sql .= ' a.area_planned, a.date_planned_start, a.date_planned_end, t.ref as fieldplot_ref, t.label as fieldplot_label';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'safra_activity as a';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'safra_talhao as t ON t.rowid = a.fk_fieldplot';
$sql .= ' WHERE a.entity IN (' . getEntity('safra_activity') . ')';
$sql .= ' AND a.status IN (' . FvActivity::STATUS_DRAFT . ',' . FvActivity::STATUS_PLANNED . ',' . FvActivity::STATUS_IN_PROGRESS . ')';
if ($searchSeason !== '') {
    $sql .= natural_search('a.season', $searchSeason);
}
if ($searchCrop !== '') {
    $sql .= natural_search('a.crop_name', $searchCrop);
}
if ($searchFieldplot > 0) {
    $sql .= ' AND a.fk_fieldplot = ' . $searchFieldplot;
}
if ($searchType !== '') {
    $sql .= " AND a.type = '" . $db->escape(FvActivity::normalizeType($searchType)) . "'";
}
if ($searchFrom !== '' && strtotime($searchFrom)) {
    $sql .= " AND COALESCE(a.date_planned_start, a.date_creation) >= '" . $db->idate(strtotime($searchFrom . ' 00:00:00')) . "'";
}
if ($searchTo !== '' && strtotime($searchTo)) {
    $sql .= " AND COALESCE(a.date_planned_start, a.date_creation) <= '" . $db->idate(strtotime($searchTo . ' 23:59:59')) . "'";
}
$sql .= ' ORDER BY COALESCE(a.date_planned_end, a.date_planned_start) ASC, a.priority DESC, a.rowid ASC';

$columns = array('planned' => array(), 'in_progress' => array(), 'overdue' => array());
$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}
while ($obj = $db->fetch_object($resql)) {
    if (ActivityPlanningService::isOverdue($obj->status, $obj->date_planned_start, $obj->date_planned_end)) {
        $columns['overdue'][] = $obj;
    } elseif ((int) $obj->status === FvActivity::STATUS_IN_PROGRESS) {
        $columns['in_progress'][] = $obj;
    } else {
        $columns['planned'][] = $obj;
    }
}

$columnLabels = array(
    'planned' => 'SafraActivityStatusPlanned',
    'in_progress' => 'SafraActivityStatusInProgress',
    'overdue' => 'SafraActivityFilterOverdueLate',
);

llxHeader('', $langs->trans('SafraActivityKanbanTitle'), '', '', 0, 0, array(), array('/safra/css/safra.css.php'));

$buttons = '<a class="butAction" href="' . dol_buildpath('/safra/activity/activity_list.php', 1) . '">' . $langs->trans('SafraActivityListTitle') . '</a>';
$buttons .= '<a class="butAction" href="' . dol_buildpath('/safra/report/input_consumption.php', 1) . '">' . $langs->trans('SafraInputConsumptionReport') . '</a>';
print load_fiche_titre($langs->trans('SafraActivityKanbanTitle'), $buttons, 'fa-columns');

print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '" class="safra-filter-panel">';
print '<div class="safra-filter-grid">';
print '<label>' . $langs->trans('SafraActivitySeason') . '<input class="flat" type="text" name="search_season" value="' . dol_escape_htmltag($searchSeason) . '"></label>';
print '<label>' . $langs->trans('SafraActivityCrop') . '<input class="flat" type="text" name="search_crop" value="' . dol_escape_htmltag($searchCrop) . '"></label>';
print '<label>' . $langs->trans('FieldPlot') . $form->selectarray('search_fieldplot', $fieldplotOptions, $searchFieldplot, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</label>';
print '<label>' . $langs->trans('SafraActivityType') . $form->selectarray('search_type', $typeOptions, $searchType, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</label>';
print '<label>' . $langs->trans('DateStart') . '<input class="flat" type="date" name="search_from" value="' . dol_escape_htmltag($searchFrom) . '"></label>';
print '<label>' . $langs->trans('DateEnd') . '<input class="flat" type="date" name="search_to" value="' . dol_escape_htmltag($searchTo) . '"></label>';
print '</div><div class="safra-filter-actions">' . $form->showFilterButtons() . '</div></form>';

print '<div class="safra-kanban">';
foreach ($columns as $columnKey => $activities) {
    print '<section class="safra-kanban-column safra-kanban-column--' . $columnKey . '">';
    print '<header><strong>' . $langs->trans($columnLabels[$columnKey]) . '</strong><span>' . count($activities) . '</span></header>';
    print '<div class="safra-kanban-cards">';
    if (empty($activities)) {
        print '<div class="safra-kanban-empty">' . $langs->trans('SafraActivityKanbanEmpty') . '</div>';
    }
    foreach ($activities as $row) {
        $activity = new FvActivity($db);
        $activity->id = (int) $row->rowid;
        $activity->ref = $row->ref;
        $activity->label = $row->label;
        $fieldplotLabel = trim((string) (($row->fieldplot_ref ? $row->fieldplot_ref . ' - ' : '') . $row->fieldplot_label));
        $deadline = !empty($row->date_planned_end) ? $row->date_planned_end : $row->date_planned_start;
        print '<article class="safra-kanban-card">';
        print '<div class="safra-kanban-card__top">' . $activity->getNomUrl(1) . '<span class="safra-priority safra-priority--' . ((int) $row->priority) . '">' . dol_escape_htmltag(FvActivity::getPriorityOptions($langs)[(int) $row->priority] ?? '') . '</span></div>';
        print '<h3>' . dol_escape_htmltag($row->label) . '</h3>';
        print '<div class="safra-kanban-card__meta"><span>' . dol_escape_htmltag(FvActivity::getTypeLabel($row->type, $langs)) . '</span>';
        if ($fieldplotLabel !== '') {
            print '<span>' . dol_escape_htmltag($fieldplotLabel) . '</span>';
        }
        if (!empty($row->season) || !empty($row->crop_name)) {
            print '<span>' . dol_escape_htmltag(trim((string) $row->season . ' ' . (string) $row->crop_name)) . '</span>';
        }
        if (!empty($deadline)) {
            print '<span class="safra-kanban-deadline">' . $langs->trans('SafraActivityFilterOverdue') . ': ' . dol_print_date($db->jdate($deadline), 'dayhour') . '</span>';
        }
        print '<span>' . $langs->trans('SafraActivityAreaPlanned') . ': ' . price($row->area_planned, 0, '', 1, 4) . '</span>';
        print '</div></article>';
    }
    print '</div></section>';
}
print '</div>';

llxFooter();
$db->close();
