<?php
/*
 * Duplicate one agricultural activity to several field plots.
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

$langs->loadLangs(array('safra@safra', 'projects'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$permissiontowrite = $user->rights->safra->SafraActivity->write ?? 0;
if (!$permissiontowrite) {
    accessforbidden();
}

$source = new FvActivity($db);
if ($id <= 0 || $source->fetch($id) <= 0) {
    accessforbidden($langs->trans('ErrorSafraActivityInvalidIdentifier'));
}

function safra_duplicate_datetime_from_post($name)
{
    $value = trim((string) GETPOST($name, 'alphanohtml'));
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime(str_replace('T', ' ', $value));
    return $timestamp ? $timestamp : null;
}

function safra_duplicate_datetime_input($value)
{
    if (empty($value)) {
        return '';
    }
    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);

    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

$fieldplots = array();
$sql = 'SELECT rowid, ref, label, area FROM ' . MAIN_DB_PREFIX . 'safra_talhao ORDER BY ref, label';
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $label = trim((string) (($obj->ref ? $obj->ref . ' - ' : '') . $obj->label));
        if ((float) $obj->area > 0) {
            $label .= ' (' . price($obj->area, 0, '', 1, 4) . ' ha)';
        }
        $fieldplots[(int) $obj->rowid] = $label;
    }
}

$selectedFieldplots = GETPOST('fieldplots', 'array:int');
$selectedFieldplots = is_array($selectedFieldplots) ? array_map('intval', $selectedFieldplots) : array();
$plannedStart = isset($_POST['date_planned_start']) ? safra_duplicate_datetime_from_post('date_planned_start') : $source->date_planned_start;
$plannedEnd = isset($_POST['date_planned_end']) ? safra_duplicate_datetime_from_post('date_planned_end') : $source->date_planned_end;
$copyResources = $_SERVER['REQUEST_METHOD'] === 'POST' ? GETPOSTINT('copy_resources') : 1;
$createdIds = array();
$errors = array();

if ($action === 'duplicate') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        accessforbidden();
    }
    if (function_exists('checkToken') && !checkToken(GETPOST('token', 'alphanohtml'))) {
        accessforbidden('Invalid security token.');
    }

    $service = new ActivityPlanningService($db);
    $result = $service->duplicateToFieldplots($source, $selectedFieldplots, $user, array(
        'date_planned_start' => $plannedStart,
        'date_planned_end' => $plannedEnd,
        'copy_resources' => $copyResources,
    ));
    if (is_array($result)) {
        $createdIds = $result;
        setEventMessages($langs->trans('SafraActivityDuplicateSuccess', count($createdIds)), null, 'mesgs');
    } else {
        $errors[] = $langs->trans($service->error ?: 'ErrorRecordNotSaved');
    }
}

llxHeader('', $langs->trans('SafraActivityDuplicate'), '', '', 0, 0, array(), array('/safra/css/safra.css.php'));

if ($errors) {
    setEventMessages(null, $errors, 'errors');
}

$linkback = '<a href="' . dol_buildpath('/safra/activity/activity_card.php', 1) . '?id=' . ((int) $source->id) . '">' . $langs->trans('SafraBackToActivity') . '</a>';
print load_fiche_titre($langs->trans('SafraActivityDuplicate'), $linkback, 'fa-copy');
print '<div class="opacitymedium">' . $langs->trans('SafraActivityDuplicateHelp') . '</div>';

print '<div class="fichecenter" style="margin-top:12px">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">' . $langs->trans('SafraActivity') . '</td><td>' . $source->getNomUrl(1) . ' - ' . dol_escape_htmltag($source->label) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityType') . '</td><td>' . dol_escape_htmltag(FvActivity::getTypeLabel($source->type, $langs)) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivitySeason') . '</td><td>' . dol_escape_htmltag($source->season) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityCrop') . '</td><td>' . dol_escape_htmltag($source->crop_name) . '</td></tr>';
print '</table>';
print '</div>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="duplicate">';
print '<input type="hidden" name="id" value="' . ((int) $source->id) . '">';
print '<div class="div-table-responsive-no-min" style="margin-top:16px"><table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('SafraActivityDuplicateFieldPlots') . '</td><td><select class="flat minwidth500" name="fieldplots[]" multiple size="10" required>';
foreach ($fieldplots as $fieldplotId => $label) {
    $selected = in_array($fieldplotId, $selectedFieldplots, true) ? ' selected' : '';
    print '<option value="' . $fieldplotId . '"' . $selected . '>' . dol_escape_htmltag($label) . '</option>';
}
print '</select><div class="opacitymedium">' . $langs->trans('SafraActivityDuplicateFieldPlotsHelp') . '</div></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDatePlannedStart') . '</td><td><input class="flat" type="datetime-local" name="date_planned_start" value="' . dol_escape_htmltag(safra_duplicate_datetime_input($plannedStart)) . '"></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDatePlannedEnd') . '</td><td><input class="flat" type="datetime-local" name="date_planned_end" value="' . dol_escape_htmltag(safra_duplicate_datetime_input($plannedEnd)) . '"></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDuplicateResources') . '</td><td><label><input type="checkbox" name="copy_resources" value="1"' . ($copyResources ? ' checked' : '') . '> ' . $langs->trans('SafraActivityDuplicateResourcesHelp') . '</label></td></tr>';
print '</table></div>';
print '<div class="tabsAction"><button class="button button-save" type="submit">' . $langs->trans('SafraActivityDuplicateAction') . '</button></div>';
print '</form>';

if ($createdIds) {
    print load_fiche_titre($langs->trans('SafraActivityDuplicateCreated'), '', '');
    print '<div class="div-table-responsive"><table class="liste centpercent"><tr class="liste_titre"><th>' . $langs->trans('Ref') . '</th><th>' . $langs->trans('Label') . '</th><th>' . $langs->trans('Status') . '</th></tr>';
    foreach ($createdIds as $createdId) {
        $created = new FvActivity($db);
        if ($created->fetch($createdId) <= 0) {
            continue;
        }
        print '<tr class="oddeven"><td>' . $created->getNomUrl(1) . '</td><td>' . dol_escape_htmltag($created->label) . '</td><td>' . dol_escape_htmltag(FvActivity::getStatusLabel($created->status, $langs)) . '</td></tr>';
    }
    print '</table></div>';
}

llxFooter();
$db->close();
