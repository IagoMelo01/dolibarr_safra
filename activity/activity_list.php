<?php
/*
 * Agricultural activity list for Safra.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) {
    $res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
dol_include_once('/safra/class/FvActivity.class.php');

global $db, $user, $langs, $conf;

$langs->loadLangs(array('safra@safra', 'projects'));

$sortfield = GETPOST('sortfield', 'alpha') ?: 't.date_planned_start';
$sortorder = GETPOST('sortorder', 'alpha') ?: 'DESC';
$page = max(0, GETPOSTINT('page'));
$limit = GETPOSTINT('limit') ?: $conf->liste_limit;
$offset = $limit * $page;

$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_season = GETPOST('search_season', 'alpha');
$search_crop = GETPOST('search_crop', 'alpha');
$search_talhao = GETPOST('search_talhao', 'alpha');
$search_type = GETPOST('search_type', 'alphanohtml');
$search_status = GETPOST('search_status', 'alpha');

if ($search_type !== '') {
    $search_type = FvActivity::normalizeType($search_type);
}

$permissiontoread = $user->rights->safra->SafraActivity->read ?? 0;
if (!$permissiontoread) {
    accessforbidden();
}

$form = new Form($db);
$object = new FvActivity($db);

$param = '';
foreach (array('search_ref', 'search_label', 'search_season', 'search_crop', 'search_talhao', 'search_type', 'search_status', 'limit') as $key) {
    if (GETPOST($key, 'alpha') !== '') {
        $param .= '&' . $key . '=' . urlencode(GETPOST($key, 'alpha'));
    }
}

$sql = 'SELECT t.rowid, t.ref, t.label, t.type, t.status, t.priority, t.progress, t.season, t.crop_name, t.cultivar_name,';
$sql .= ' t.fk_project, t.fk_fieldplot, t.area_planned, t.area_done, t.date_planned_start, t.date_planned_end, t.date_start, t.date_end,';
$sql .= ' p.ref as project_ref, p.title as project_title, tp.ref as talhao_ref, tp.label as talhao_label';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'safra_activity as t';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = t.fk_project';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'safra_talhao as tp ON tp.rowid = t.fk_fieldplot';
$sql .= ' WHERE t.entity IN (' . getEntity('safra_activity') . ')';

if ($search_ref !== '') {
    $sql .= natural_search('t.ref', $search_ref);
}
if ($search_label !== '') {
    $sql .= natural_search('t.label', $search_label);
}
if ($search_season !== '') {
    $sql .= natural_search('t.season', $search_season);
}
if ($search_crop !== '') {
    $sql .= natural_search('t.crop_name', $search_crop);
}
if ($search_talhao !== '') {
    $sql .= natural_search(array('tp.ref', 'tp.label'), $search_talhao);
}
if ($search_type !== '') {
    $sql .= " AND t.type = '" . $db->escape($search_type) . "'";
}
if ($search_status !== '') {
    $sql .= ' AND t.status = ' . ((int) $search_status);
}

$sql .= $db->order($sortfield, $sortorder);

$nbtotalofrecords = 0;
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
    $resqlCount = $db->query($sql);
    if ($resqlCount) {
        $nbtotalofrecords = $db->num_rows($resqlCount);
    }
}

$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($resql);
$varpage = $_SERVER['PHP_SELF'];
$typeOptions = array('' => '') + FvActivity::getTypeOptions($langs);
$statusOptions = array('' => '') + FvActivity::getStatusOptions($langs);

llxHeader('', $langs->trans('SafraActivityListTitle'), '');

$newButton = '';
if ($user->rights->safra->SafraActivity->write ?? 0) {
    $newButton = '<a class="butAction" href="' . dol_buildpath('/safra/activity/activity_card.php', 1) . '?action=create">' . $langs->trans('New') . '</a>';
}

print load_fiche_titre($langs->trans('SafraActivityListTitle'), $newButton, 'fa-tractor');

print '<form method="GET" action="' . $varpage . '" name="formfilter">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="list">';
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('Ref'), $varpage, 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Label'), $varpage, 't.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('SafraActivitySeason'), $varpage, 't.season', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('SafraActivityCrop'), $varpage, 't.crop_name', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('FieldPlot'), $varpage, 'tp.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('SafraActivityType'), $varpage, 't.type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $varpage, 't.status', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('SafraActivityDatePlannedStart'), $varpage, 't.date_planned_start', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans('SafraActivityAreaPlanned'), $varpage, 't.area_planned', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre($langs->trans('SafraActivityProgress'), $varpage, 't.progress', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('', $varpage, '', '', $param, '', $sortfield, $sortorder, 'right ');
print '</tr>';

print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input class="flat" type="text" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '" size="8"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_label" value="' . dol_escape_htmltag($search_label) . '" size="12"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_season" value="' . dol_escape_htmltag($search_season) . '" size="8"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_crop" value="' . dol_escape_htmltag($search_crop) . '" size="10"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_talhao" value="' . dol_escape_htmltag($search_talhao) . '" size="10"></td>';
print '<td class="liste_titre">' . $form->selectarray('search_type', $typeOptions, $search_type, 0, 0, 0, '', 0, 0, 0, '', '', 1) . '</td>';
print '<td class="liste_titre">' . $form->selectarray('search_status', $statusOptions, $search_status, 0, 0, 0, '', 0, 0, 0, '', '', 1) . '</td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre right"></td>';
print '<td class="liste_titre right"></td>';
print '<td class="liste_titre right">' . $form->showFilterButtons() . '</td>';
print '</tr>';

while ($obj = $db->fetch_object($resql)) {
    print '<tr class="oddeven">';

    $activity = new FvActivity($db);
    $activity->id = (int) $obj->rowid;
    $activity->ref = $obj->ref;
    $activity->label = $obj->label;
    print '<td>' . $activity->getNomUrl(1) . '</td>';
    print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
    print '<td>' . dol_escape_htmltag($obj->season) . '</td>';
    print '<td>' . dol_escape_htmltag($obj->crop_name) . '</td>';

    $talhaoLabel = trim((string) (($obj->talhao_ref ? $obj->talhao_ref . ' - ' : '') . $obj->talhao_label));
    print '<td>' . dol_escape_htmltag($talhaoLabel) . '</td>';

    print '<td>' . dol_escape_htmltag(FvActivity::getTypeLabel($obj->type, $langs)) . '</td>';
    print '<td>' . dol_escape_htmltag(FvActivity::getStatusLabel((int) $obj->status, $langs)) . '</td>';
    print '<td class="center">' . (!empty($obj->date_planned_start) ? dol_print_date($db->jdate($obj->date_planned_start), 'dayhour') : '') . '</td>';
    print '<td class="right">' . price($obj->area_planned, 0, '', 1, 4) . '</td>';
    print '<td class="right">' . price($obj->progress, 0, '', 1, 0) . '%</td>';
    print '<td class="right"><a class="button small" href="' . dol_buildpath('/safra/activity/activity_card.php', 1) . '?id=' . ((int) $obj->rowid) . '">' . $langs->trans('Card') . '</a></td>';

    print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

print_barre_liste('', $page, $varpage, $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, '', 0, '', '', $limit, 0, 0, 1);

llxFooter();
$db->close();
