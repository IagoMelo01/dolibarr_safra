<?php
/*
 * Agricultural activity card for Safra.
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
dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/safra/class/FvActivityLine.class.php');

global $db, $langs, $user, $conf;

$langs->loadLangs(array('safra@safra', 'projects', 'stocks', 'users', 'products'));

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$tab = GETPOST('tab', 'alpha') ?: 'card';
$validTabs = array('card', 'inputs', 'mixture', 'team', 'vehicles', 'implements');
if (!in_array($tab, $validTabs, true)) {
    $tab = 'card';
}

$form = new Form($db);
$activity = new FvActivity($db);
if ($id > 0) {
    $activity->fetch($id);
}

if ($action === 'create') {
    $activity->fk_project = GETPOSTINT('fk_project');
    $activity->fk_task = GETPOSTINT('fk_task');
    $activity->fk_fieldplot = GETPOSTINT('fk_talhao') ?: GETPOSTINT('fk_fieldplot');
    $activity->status = FvActivity::STATUS_PLANNED;
    $activity->priority = FvActivity::PRIORITY_NORMAL;
    $activity->type = FvActivity::TYPE_PLANTING;
}

$permissiontoread = $user->rights->safra->SafraActivity->read ?? 0;
$permissiontowrite = $user->rights->safra->SafraActivity->write ?? 0;
$permissiontodelete = $user->rights->safra->SafraActivity->delete ?? 0;

if (!$permissiontoread) {
    accessforbidden();
}

$mutatingActions = array('save', 'save_main', 'save_complete', 'save_inputs', 'save_team', 'save_vehicles', 'save_implements', 'start', 'complete', 'cancel', 'reopen', 'delete');
if (in_array($action, $mutatingActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        accessforbidden();
    }
    $token = GETPOST('token', 'alphanohtml');
    if (function_exists('checkToken') && !checkToken($token)) {
        accessforbidden('Invalid security token.');
    }
}
if (in_array($action, array('save', 'save_main', 'save_complete', 'save_inputs', 'save_team', 'save_vehicles', 'save_implements', 'start', 'complete', 'cancel', 'reopen'), true) && !$permissiontowrite) {
    accessforbidden();
}
if ($action === 'delete' && !$permissiontodelete) {
    accessforbidden();
}

/**
 * Load id => label options from a table.
 *
 * @param DoliDB $db
 * @param string $table
 * @param string $labelSql
 * @param string $where
 * @return array
 */
function safra_activity_load_options($db, $table, $labelSql, $where = '')
{
    global $conf;

    $options = array();
    $sql = 'SELECT rowid, ' . $labelSql . ' as label FROM ' . MAIN_DB_PREFIX . $table;
    if ($where !== '') {
        $sql .= ' WHERE ' . $where;
    } elseif (safra_activity_table_has_column($db, $table, 'entity')) {
        $sql .= ' WHERE entity IN (0, ' . ((int) $conf->entity) . ')';
    }
    $sql .= ' ORDER BY label';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $label = trim((string) $obj->label);
            $options[(int) $obj->rowid] = $label !== '' ? $label : '#' . ((int) $obj->rowid);
        }
    }

    return $options;
}

function safra_activity_table_has_column($db, $table, $column)
{
    static $cache = array();

    $key = $table . ':' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $sql = 'SHOW COLUMNS FROM ' . MAIN_DB_PREFIX . $db->escape($table) . " LIKE '" . $db->escape($column) . "'";
    $resql = $db->query($sql);
    $cache[$key] = ($resql && $db->fetch_object($resql));

    return $cache[$key];
}

function safra_activity_get_task_project_id($db, $taskId)
{
    $taskId = (int) $taskId;
    if ($taskId <= 0) {
        return 0;
    }

    $projectColumn = safra_activity_table_has_column($db, 'projet_task', 'fk_projet') ? 'fk_projet' : (safra_activity_table_has_column($db, 'projet_task', 'fk_project') ? 'fk_project' : '');
    if ($projectColumn === '') {
        return 0;
    }

    $sql = 'SELECT ' . $projectColumn . ' as project_id FROM ' . MAIN_DB_PREFIX . 'projet_task WHERE rowid = ' . $taskId . ' LIMIT 1';
    $resql = $db->query($sql);
    if (!$resql) {
        return 0;
    }

    $obj = $db->fetch_object($resql);

    return $obj ? (int) $obj->project_id : 0;
}

function safra_activity_get_extrafield_value($db, $table, $objectId, $keys)
{
    $objectId = (int) $objectId;
    if ($objectId <= 0) {
        return 0;
    }

    foreach ((array) $keys as $key) {
        if (!safra_activity_table_has_column($db, $table, $key)) {
            continue;
        }

        $sql = 'SELECT ' . $key . ' as value FROM ' . MAIN_DB_PREFIX . $table . ' WHERE fk_object = ' . $objectId . ' LIMIT 1';
        $resql = $db->query($sql);
        if (!$resql) {
            continue;
        }

        $obj = $db->fetch_object($resql);
        if ($obj && (int) $obj->value > 0) {
            return (int) $obj->value;
        }
    }

    return 0;
}

function safra_activity_fetch_reference($db, $table, $id, $extraFields = array())
{
    $id = (int) $id;
    if ($id <= 0 || !safra_activity_table_has_column($db, $table, 'rowid')) {
        return null;
    }

    $columns = array('rowid');
    foreach (array('ref', 'label') as $column) {
        if (safra_activity_table_has_column($db, $table, $column)) {
            $columns[] = $column;
        }
    }
    foreach ((array) $extraFields as $column) {
        if (safra_activity_table_has_column($db, $table, $column)) {
            $columns[] = $column;
        }
    }

    $sql = 'SELECT ' . implode(', ', array_unique($columns)) . ' FROM ' . MAIN_DB_PREFIX . $table . ' WHERE rowid = ' . $id . ' LIMIT 1';
    $resql = $db->query($sql);
    if (!$resql) {
        return null;
    }

    $obj = $db->fetch_object($resql);
    if (!$obj) {
        return null;
    }

    $ref = isset($obj->ref) ? trim((string) $obj->ref) : '';
    $label = isset($obj->label) ? trim((string) $obj->label) : '';
    $display = trim(($ref !== '' ? $ref . ' - ' : '') . $label);
    if ($display === '') {
        $display = '#' . $id;
    }

    $data = array(
        'id' => $id,
        'ref' => $ref,
        'label' => $label,
        'display' => $display,
    );
    foreach ((array) $extraFields as $column) {
        $data[$column] = isset($obj->{$column}) ? $obj->{$column} : null;
    }

    return $data;
}

function safra_activity_fetch_project_context($db, $projectId, $taskId = 0)
{
    $projectId = (int) $projectId;
    $taskId = (int) $taskId;
    if ($projectId <= 0 && $taskId > 0) {
        $projectId = safra_activity_get_task_project_id($db, $taskId);
    }

    $context = array(
        'project_id' => $projectId,
        'task_id' => $taskId,
        'talhao' => null,
        'cultura' => null,
        'cultivar' => null,
    );
    if ($projectId <= 0) {
        return $context;
    }

    $talhaoId = safra_activity_get_extrafield_value($db, 'projet_extrafields', $projectId, array('fk_talhao', 'options_fk_talhao', 'fk_fieldplot', 'options_fk_fieldplot', 'talhao', 'options_talhao', 'fieldplot', 'options_fieldplot'));
    $culturaId = safra_activity_get_extrafield_value($db, 'projet_extrafields', $projectId, array('fk_cultura', 'options_fk_cultura', 'fk_crop', 'options_fk_crop', 'cultura', 'options_cultura', 'crop', 'options_crop'));
    $cultivarId = safra_activity_get_extrafield_value($db, 'projet_extrafields', $projectId, array('fk_cultivar', 'options_fk_cultivar', 'cultivar', 'options_cultivar'));

    if ($talhaoId > 0) {
        $context['talhao'] = safra_activity_fetch_reference($db, 'safra_talhao', $talhaoId, array('area'));
    }
    if ($culturaId > 0) {
        $context['cultura'] = safra_activity_fetch_reference($db, 'safra_cultura', $culturaId);
    }
    if ($cultivarId > 0) {
        $context['cultivar'] = safra_activity_fetch_reference($db, 'safra_cultivar', $cultivarId, array('cultivar'));
        if ($context['cultivar'] && empty($context['cultivar']['label']) && !empty($context['cultivar']['cultivar'])) {
            $context['cultivar']['label'] = $context['cultivar']['cultivar'];
            $context['cultivar']['display'] = trim(($context['cultivar']['ref'] !== '' ? $context['cultivar']['ref'] . ' - ' : '') . $context['cultivar']['label']);
        }
    }

    return $context;
}

function safra_activity_apply_project_context(FvActivity $activity, $context, $force = false)
{
    if (!empty($context['project_id']) && ($force || empty($activity->fk_project))) {
        $activity->fk_project = (int) $context['project_id'];
    }
    if (!empty($context['task_id']) && ($force || empty($activity->fk_task))) {
        $activity->fk_task = (int) $context['task_id'];
    }
    if (!empty($context['talhao']['id']) && ($force || empty($activity->fk_fieldplot))) {
        $activity->fk_fieldplot = (int) $context['talhao']['id'];
    }
    if (!empty($context['talhao']['area']) && ($force || price2num($activity->area_planned, 'MT') <= 0)) {
        $activity->area_planned = price2num($context['talhao']['area'], 'MT');
        if ($force || price2num($activity->area_total, 'MT') <= 0) {
            $activity->area_total = $activity->area_planned;
        }
    }
    if (!empty($context['cultura']['label']) && ($force || trim((string) $activity->crop_name) === '')) {
        $activity->crop_name = $context['cultura']['label'];
    }
    if (!empty($context['cultivar']['label']) && ($force || trim((string) $activity->cultivar_name) === '')) {
        $activity->cultivar_name = $context['cultivar']['label'];
    }
}

function safra_activity_datetime_from_post($name)
{
    $value = trim((string) GETPOST($name, 'alphanohtml'));
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime(str_replace('T', ' ', $value));

    return $timestamp ? $timestamp : null;
}

function safra_activity_datetime_input($value)
{
    if (empty($value)) {
        return '';
    }

    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
    if (!$timestamp) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

function safra_activity_pick($array, $key, $default = '')
{
    return isset($array[$key]) ? $array[$key] : $default;
}

function safra_activity_build_link_rows($idField, $ids, $roles, $plannedHours, $doneHours, $notes)
{
    $rows = array();
    foreach ((array) $ids as $idx => $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $rows[] = array(
            $idField => $id,
            'role' => safra_activity_pick($roles, $idx, ''),
            'planned_hours' => price2num(safra_activity_pick($plannedHours, $idx, 0), 'MT'),
            'done_hours' => price2num(safra_activity_pick($doneHours, $idx, 0), 'MT'),
            'note' => safra_activity_pick($notes, $idx, ''),
        );
    }

    return $rows;
}

function safra_activity_build_asset_rows($idField, $classField, $defaultClass, $ids, $plannedHours, $doneHours, $notes)
{
    $rows = array();
    foreach ((array) $ids as $idx => $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $rows[] = array(
            $idField => $id,
            $classField => $defaultClass,
            'planned_hours' => price2num(safra_activity_pick($plannedHours, $idx, 0), 'MT'),
            'done_hours' => price2num(safra_activity_pick($doneHours, $idx, 0), 'MT'),
            'note' => safra_activity_pick($notes, $idx, ''),
        );
    }

    return $rows;
}

function safra_activity_load_fleet_options($db, $className, $table, $labelColumns)
{
    $classFile = DOL_DOCUMENT_ROOT . '/custom/frota/class/' . strtolower($className) . '.class.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }

    $options = array();
    if (class_exists($className)) {
        $object = new $className($db);
        if (method_exists($object, 'fetchAll')) {
            $records = $object->fetchAll('', '', 0, 0, array('customsql' => '1=1'));
            if (is_array($records)) {
                foreach ($records as $record) {
                    $parts = array();
                    foreach ($labelColumns as $field) {
                        if (!empty($record->{$field})) {
                            $parts[] = $record->{$field};
                        }
                    }
                    $label = implode(' - ', $parts);
                    $options[(int) $record->id] = $label !== '' ? $label : '#' . (int) $record->id;
                }
            }
        }
    }

    if (!empty($options)) {
        return $options;
    }

    if ($table !== '' && safra_activity_table_has_column($db, $table, 'rowid')) {
        $firstColumn = safra_activity_table_has_column($db, $table, 'ref') ? 'ref' : 'rowid';
        $secondColumn = safra_activity_table_has_column($db, $table, 'label') ? 'label' : $firstColumn;

        return safra_activity_load_options($db, $table, 'CONCAT(' . $firstColumn . ', " - ", ' . $secondColumn . ')', '1=1');
    }

    return array();
}

function safra_activity_tab_url($activityId, $tab)
{
    return dol_buildpath('/safra/activity/activity_card.php', 1) . '?id=' . ((int) $activityId) . '&tab=' . urlencode($tab);
}

function safra_activity_prepare_head(FvActivity $activity, $langs)
{
    if (empty($activity->id)) {
        return array();
    }

    return array(
        array(safra_activity_tab_url($activity->id, 'card'), $langs->trans('SafraActivityGeneralTab'), 'card'),
        array(safra_activity_tab_url($activity->id, 'inputs'), $langs->trans('SafraActivityInputs'), 'inputs'),
        array(safra_activity_tab_url($activity->id, 'mixture'), $langs->trans('SafraAplicacaoCaldaCalculation'), 'mixture'),
        array(safra_activity_tab_url($activity->id, 'team'), $langs->trans('SafraActivityTeam'), 'team'),
        array(safra_activity_tab_url($activity->id, 'vehicles'), $langs->trans('SafraVehicleLabel'), 'vehicles'),
        array(safra_activity_tab_url($activity->id, 'implements'), $langs->trans('SafraImplementsLabel'), 'implements'),
    );
}

$projectOptions = safra_activity_load_options($db, 'projet', 'CONCAT(ref, " - ", title)', 'entity IN (0, ' . ((int) $conf->entity) . ')');
$talhaoOptions = safra_activity_load_options($db, 'safra_talhao', 'CONCAT(ref, " - ", label)');
$warehouseLabel = safra_activity_table_has_column($db, 'entrepot', 'lieu') ? 'lieu' : (safra_activity_table_has_column($db, 'entrepot', 'label') ? 'label' : 'ref');
$warehouseOptions = safra_activity_load_options($db, 'entrepot', $warehouseLabel);
$productWhere = 'entity IN (0, ' . ((int) $conf->entity) . ')';
if (safra_activity_table_has_column($db, 'product', 'stockable_product')) {
    $productWhere .= ' AND stockable_product = 1';
} elseif (safra_activity_table_has_column($db, 'product', 'fk_product_type')) {
    $productWhere .= ' AND fk_product_type = 0';
}
$productOptions = safra_activity_load_options($db, 'product', 'CONCAT(ref, " - ", label)', $productWhere);
$userOptions = safra_activity_load_options($db, 'user', 'CONCAT(firstname, " ", lastname)', 'statut = 1 AND entity IN (0, ' . ((int) $conf->entity) . ')');
$vehicleOptions = safra_activity_load_fleet_options($db, 'Veiculo', 'frota_veiculo', array('ref', 'placa', 'label'));
$implementOptions = safra_activity_load_fleet_options($db, 'Implemento', 'frota_implemento', array('ref', 'label'));

$typeOptions = FvActivity::getTypeOptions($langs);
$statusOptions = FvActivity::getStatusOptions($langs);
$priorityOptions = FvActivity::getPriorityOptions($langs);
$movementOptions = FvActivityLine::getMovementOptions($langs);
$unitOptions = array(
    'kg/ha' => 'kg/ha',
    'g/ha' => 'g/ha',
    'L/ha' => 'L/ha',
    'mL/ha' => 'mL/ha',
    'sc/ha' => 'sc/ha',
    'un/ha' => 'un/ha',
);

$projectContext = safra_activity_fetch_project_context($db, $activity->fk_project, $activity->fk_task);
safra_activity_apply_project_context($activity, $projectContext, (int) $activity->fk_project > 0);

$errors = array();

if ($action === 'start' && $activity->id) {
    $result = $activity->start($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityStart'), null, 'mesgs');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
        exit;
    }
    $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
}

if ($action === 'complete' && $activity->id) {
    $result = $activity->complete($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityComplete'), null, 'mesgs');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
        exit;
    }
    $errors[] = $langs->trans($activity->error ?: 'ErrorRecordNotSaved');
}

if ($action === 'cancel' && $activity->id) {
    $result = $activity->cancel($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityCanceled'), null, 'mesgs');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
        exit;
    }
    $errors[] = $langs->trans($activity->error ?: 'ErrorRecordNotSaved');
}

if ($action === 'reopen' && $activity->id) {
    $result = $activity->reopen($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityReopened'), null, 'mesgs');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
        exit;
    }
    $errors[] = $langs->trans($activity->error ?: 'ErrorRecordNotSaved');
}

if ($action === 'delete' && $activity->id) {
    $result = $activity->delete($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityDeleted'), null, 'mesgs');
        header('Location: ' . dol_buildpath('/safra/activity/activity_list.php', 1));
        exit;
    }
    $errors[] = $langs->trans($activity->error ?: 'ErrorRecordNotSaved');
}

if (in_array($action, array('save', 'save_main', 'save_complete'), true)) {
    $isNew = empty($activity->id);
    $activity->ref = isset($_POST['ref']) ? GETPOST('ref', 'alphanohtml') : $activity->ref;
    $activity->label = GETPOST('label', 'restricthtml');
    $activity->type = GETPOST('type', 'alphanohtml') ?: ($activity->type ?: FvActivity::TYPE_PLANTING);
    $activity->status = isset($_POST['status']) ? GETPOSTINT('status') : ($isNew ? FvActivity::STATUS_PLANNED : $activity->status);
    $activity->priority = isset($_POST['priority']) ? GETPOSTINT('priority') : ((string) $activity->priority !== '' ? $activity->priority : FvActivity::PRIORITY_NORMAL);
    $activity->progress = isset($_POST['progress']) ? price2num(GETPOST('progress', 'alphanohtml'), 'MT') : ($isNew ? 0 : $activity->progress);
    $activity->season = GETPOST('season', 'alphanohtml');
    $activity->crop_name = GETPOST('crop_name', 'alphanohtml');
    $activity->cultivar_name = isset($_POST['cultivar_name']) ? GETPOST('cultivar_name', 'alphanohtml') : $activity->cultivar_name;
    $activity->fk_project = isset($_POST['fk_project']) ? GETPOSTINT('fk_project') : $activity->fk_project;
    $activity->fk_task = isset($_POST['fk_task']) ? GETPOSTINT('fk_task') : $activity->fk_task;
    $activity->fk_fieldplot = GETPOSTINT('fk_fieldplot');
    $activity->area_planned = price2num(GETPOST('area_planned', 'alphanohtml'), 'MT');
    $activity->area_done = isset($_POST['area_done']) ? price2num(GETPOST('area_done', 'alphanohtml'), 'MT') : $activity->area_done;
    $activity->date_planned_start = safra_activity_datetime_from_post('date_planned_start');
    $activity->date_planned_end = isset($_POST['date_planned_end']) ? safra_activity_datetime_from_post('date_planned_end') : $activity->date_planned_end;
    $activity->date_start = isset($_POST['date_start']) ? safra_activity_datetime_from_post('date_start') : $activity->date_start;
    $activity->date_end = isset($_POST['date_end']) ? safra_activity_datetime_from_post('date_end') : $activity->date_end;
    $activity->weather = isset($_POST['weather']) ? GETPOST('weather', 'restricthtml') : $activity->weather;
    $activity->note_public = GETPOST('note_public', 'restricthtml');

    $postedProjectContext = safra_activity_fetch_project_context($db, $activity->fk_project, $activity->fk_task);
    safra_activity_apply_project_context($activity, $postedProjectContext, (int) $activity->fk_project > 0);

    if (trim((string) $activity->label) === '') {
        $errors[] = $langs->trans('ErrorFieldRequired', $langs->trans('SafraActivityName'));
    }
    if (trim((string) $activity->type) === '') {
        $errors[] = $langs->trans('ErrorFieldRequired', $langs->trans('SafraActivityType'));
    }
    if ((int) $activity->fk_fieldplot <= 0) {
        $errors[] = $langs->trans('SafraAplicacaoErrorTalhaoRequired');
    }

    if (!$errors) {
        $db->begin();

        $result = $isNew ? $activity->create($user) : $activity->update($user);
        if ($result <= 0) {
            $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
        }

        if (!$errors && $action === 'save_complete') {
            $activity->fetch($activity->id);
            $activity->fetchLines();
            $result = $activity->complete($user);
            if ($result <= 0) {
                $errors[] = $langs->trans($activity->error ?: 'ErrorRecordNotSaved');
            }
        }

        if ($errors) {
            $db->rollback();
        } else {
            $db->commit();
            setEventMessages($langs->trans($action === 'save_complete' ? 'SafraActivityComplete' : 'SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=card');
            exit;
        }
    }
}

if ($action === 'save_inputs') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $lineIds = GETPOST('line_id', 'array');
        $products = GETPOST('line_product_id', 'array');
        $warehouses = GETPOST('line_warehouse_id', 'array');
        $movements = GETPOST('line_movement_type', 'array');
        $areaPlanned = GETPOST('line_area_planned', 'array');
        $areaDone = GETPOST('line_area_done', 'array');
        $dosePlanned = GETPOST('line_dose_planned', 'array');
        $doseDone = GETPOST('line_dose_done', 'array');
        $units = GETPOST('line_dose_unit', 'array');
        $qtyPlanned = GETPOST('line_qty_planned', 'array');
        $qtyDone = GETPOST('line_qty_done', 'array');
        $unitCosts = GETPOST('line_unit_cost', 'array');
        $lineNotes = GETPOST('line_note', 'array');

        $inputLines = array();
        foreach ((array) $products as $idx => $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            $line = new FvActivityLine($db);
            $line->id = (int) safra_activity_pick($lineIds, $idx, 0);
            $line->rowid = $line->id;
            $line->fk_activity = (int) $activity->id;
            $line->position = $idx + 1;
            $line->fk_product = $productId;
            $line->fk_warehouse = (int) safra_activity_pick($warehouses, $idx, 0);
            $line->movement_type = safra_activity_pick($movements, $idx, FvActivityLine::MOVEMENT_CONSUME);
            $line->area_planned = price2num(safra_activity_pick($areaPlanned, $idx, 0), 'MT');
            $line->area_done = price2num(safra_activity_pick($areaDone, $idx, 0), 'MT');
            $line->dose_planned = price2num(safra_activity_pick($dosePlanned, $idx, 0), 'MT');
            $line->dose_done = price2num(safra_activity_pick($doseDone, $idx, 0), 'MT');
            $line->dose_unit = safra_activity_pick($units, $idx, '');
            $line->qty_planned = price2num(safra_activity_pick($qtyPlanned, $idx, 0), 'MT');
            $line->qty_done = price2num(safra_activity_pick($qtyDone, $idx, 0), 'MT');
            $line->unit_cost = price2num(safra_activity_pick($unitCosts, $idx, 0), 'MT');
            $line->note = safra_activity_pick($lineNotes, $idx, '');

            $inputLines[] = $line;
        }

        $db->begin();
        if ($activity->replaceInputLines($inputLines, $user, false) < 0) {
            $errors[] = $langs->trans($activity->error ?: 'ErrorRecordNotSaved');
        }

        if ($errors) {
            $db->rollback();
        } else {
            $db->commit();
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=inputs');
            exit;
        }
    }
}

if ($action === 'save_team') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $teamRows = safra_activity_build_link_rows(
            'fk_user',
            GETPOST('team_user_id', 'array'),
            GETPOST('team_role', 'array'),
            GETPOST('team_planned_hours', 'array'),
            GETPOST('team_done_hours', 'array'),
            GETPOST('team_note', 'array')
        );
        if ($activity->setUsers($teamRows) < 0) {
            $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=team');
            exit;
        }
    }
}

if ($action === 'save_vehicles') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $vehicleRows = safra_activity_build_asset_rows(
            'fk_vehicle',
            'vehicle_class',
            'Veiculo',
            GETPOST('vehicle_id', 'array'),
            GETPOST('vehicle_planned_hours', 'array'),
            GETPOST('vehicle_done_hours', 'array'),
            GETPOST('vehicle_note', 'array')
        );
        if ($activity->setVehicles($vehicleRows) < 0) {
            $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=vehicles');
            exit;
        }
    }
}

if ($action === 'save_implements') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $implementRows = safra_activity_build_asset_rows(
            'fk_implement',
            'implement_class',
            'Implemento',
            GETPOST('implement_id', 'array'),
            GETPOST('implement_planned_hours', 'array'),
            GETPOST('implement_done_hours', 'array'),
            GETPOST('implement_note', 'array')
        );
        if ($activity->setImplements($implementRows) < 0) {
            $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=implements');
            exit;
        }
    }
}

if ($activity->id) {
    $activity->fetch($activity->id);
    $projectContext = safra_activity_fetch_project_context($db, $activity->fk_project, $activity->fk_task);
    safra_activity_apply_project_context($activity, $projectContext, (int) $activity->fk_project > 0);
}

$teamLinks = $activity->id ? $activity->fetchUserLinks() : array();
$vehicleLinks = $activity->id ? $activity->fetchVehicleLinks() : array();
$implementLinks = $activity->id ? $activity->fetchImplementLinks() : array();

llxHeader('', $langs->trans('SafraActivity'), '', '', 0, 0, array(), array('/safra/css/safra.css.php'));

if ($errors) {
    setEventMessages(null, $errors, 'errors');
}

$title = $activity->id ? $langs->trans('SafraActivityCardTitle', $activity->ref ?: $activity->id) : $langs->trans('New');
$linkback = '<a href="' . dol_buildpath('/safra/activity/activity_list.php', 1) . '">' . $langs->trans('BackToList') . '</a>';
$isCreateMode = empty($activity->id);
$activeTab = $isCreateMode ? 'card' : $tab;
$head = safra_activity_prepare_head($activity, $langs);
$formActionByTab = array(
    'card' => 'save_main',
    'inputs' => 'save_inputs',
    'mixture' => 'save_main',
    'team' => 'save_team',
    'vehicles' => 'save_vehicles',
    'implements' => 'save_implements',
);
$formAction = isset($formActionByTab[$activeTab]) ? $formActionByTab[$activeTab] : 'save_main';
$baseArea = price2num($activity->area_done ?: $activity->area_planned ?: $activity->area_total, 'MT');
$canFinishFromCard = $activity->id && !$activity->isCompleted() && !$activity->isCanceled();

print '<style>
.safra-activity-page{max-width:1180px}
.safra-tab-pane{display:none}
.safra-tab-pane-active{display:block}
.safra-tab-pane + .safra-tab-pane-active{margin-top:14px}
.safra-table-wrap{overflow:auto}
.safra-input-table input,.safra-input-table select{box-sizing:border-box;width:100%;max-width:100%;min-height:32px}
.safra-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.safra-primary-actions{justify-content:flex-end}
.safra-status-info{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.safra-status{font-weight:600}
.safra-danger{color:#b42318}
.safra-line-actions{width:42px}
.safra-mixture-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:12px 0}
.safra-mixture-summary div{border:1px solid #d9e0e6;background:#f8fafb;padding:10px}
.safra-mixture-summary strong{display:block;font-size:18px}
@media (max-width:700px){.safra-actions{display:grid}.safra-primary-actions{justify-content:stretch}}
@media (max-width:900px){.safra-mixture-summary{grid-template-columns:1fr}}
</style>';

if ($isCreateMode) {
    print load_fiche_titre($title, $linkback, 'fa-tractor');
} else {
    print dol_get_fiche_head($head, $activeTab, $title, -1, 'fa-tractor', 0, $linkback);
}

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" id="safra-activity-form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" id="safra-form-action" value="' . dol_escape_htmltag($formAction) . '">';
print '<input type="hidden" name="tab" value="' . dol_escape_htmltag($activeTab) . '">';
if ($activity->id) {
    print '<input type="hidden" name="id" value="' . ((int) $activity->id) . '">';
}
print '<input type="hidden" name="fk_task" value="' . ((int) $activity->fk_task) . '">';

print '<div class="safra-activity-page">';

print '<div class="safra-tab-pane ' . ($activeTab === 'card' ? 'safra-tab-pane-active' : '') . '" data-tab="card">';
print '<div class="fichecenter">';
print '<div class="safra-actions safra-primary-actions">';
print '<button class="button button-save" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_main\';">' . $langs->trans('Save') . '</button>';
if ($canFinishFromCard) {
    print '<button class="button" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_complete\';return confirm(\'' . dol_escape_js($langs->transnoentities('SafraActivityFinishConfirm')) . '\');">' . $langs->trans('SafraActivityFinishNow') . '</button>';
}
print '</div>';

print '<div class="fichehalfleft">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('SafraActivityName') . '</td><td><input class="flat minwidth300" type="text" name="label" required value="' . dol_escape_htmltag($activity->label) . '" autocomplete="off"></td></tr>';
print '<tr><td class="fieldrequired">' . $langs->trans('SafraActivityType') . '</td><td>' . $form->selectarray('type', $typeOptions, FvActivity::normalizeType($activity->type), 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300', 1) . '</td></tr>';
print '<tr><td class="fieldrequired">' . $langs->trans('FieldPlot') . '</td><td>' . $form->selectarray('fk_fieldplot', array(0 => '') + $talhaoOptions, $activity->fk_fieldplot, 0, 0, 0, 'id="safra-fieldplot-select"', 0, 0, 0, '', 'flat minwidth300', 1) . '</td></tr>';
print '<tr><td>' . $langs->trans('Project') . '</td><td>' . $form->selectarray('fk_project', array(0 => '') + $projectOptions, $activity->fk_project, 0, 0, 0, 'id="safra-project-select" data-context-url="' . dol_escape_htmltag(dol_buildpath('/safra/ajax/project_talhao.php', 1)) . '"', 0, 0, 0, '', 'flat minwidth300', 1) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDatePlannedStart') . '</td><td><input class="flat" type="datetime-local" name="date_planned_start" value="' . dol_escape_htmltag(safra_activity_datetime_input($activity->date_planned_start)) . '"></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityAreaPlanned') . '</td><td><input class="flat js-area-planned" id="safra-area-planned" type="number" step="0.0001" min="0" name="area_planned" value="' . dol_escape_htmltag(price2num($activity->area_planned ?: $activity->area_total)) . '"></td></tr>';
if (!$isCreateMode) {
    print '<tr><td>' . $langs->trans('SafraActivityAreaDone') . '</td><td><input class="flat js-area-done" type="number" step="0.0001" min="0" name="area_done" value="' . dol_escape_htmltag(price2num($activity->area_done)) . '"></td></tr>';
}
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<div class="ficheaddleft">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">' . $langs->trans('Status') . '</td><td>';
print '<div class="safra-status-info">';
print '<span class="safra-status">' . dol_escape_htmltag(FvActivity::getStatusLabel($activity->status, $langs)) . '</span>';
if ($activity->id) {
    print '<span class="opacitymedium">' . dol_escape_htmltag($langs->trans('Ref')) . ': ' . dol_escape_htmltag($activity->ref ?: $activity->id) . '</span>';
}
print '</div>';
print '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityProgress') . '</td><td>' . price($activity->progress ?: 0, 0, '', 1, 0) . '%</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDatePlannedEnd') . '</td><td><input class="flat" type="datetime-local" name="date_planned_end" value="' . dol_escape_htmltag(safra_activity_datetime_input($activity->date_planned_end)) . '"></td></tr>';
print '<tr><td>' . $langs->trans('Priority') . '</td><td>' . $form->selectarray('priority', $priorityOptions, FvActivity::normalizePriority($activity->priority), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivitySeason') . '</td><td><input class="flat" type="text" name="season" value="' . dol_escape_htmltag($activity->season) . '" placeholder="2026/2027"></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityCrop') . '</td><td><input class="flat" id="safra-crop-name" type="text" name="crop_name" value="' . dol_escape_htmltag($activity->crop_name) . '"></td></tr>';
print '<tr><td>' . $langs->trans('Cultivar') . '</td><td><input class="flat" id="safra-cultivar-name" type="text" name="cultivar_name" value="' . dol_escape_htmltag($activity->cultivar_name) . '"></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityWeather') . '</td><td><input class="flat" type="text" name="weather" value="' . dol_escape_htmltag($activity->weather) . '"></td></tr>';
print '</table>';
print '</div>';
print '</div>';
print '<div class="clearboth"></div>';
print '<div id="safra-project-context" class="opacitymedium" style="margin-top:8px"></div>';
print '</div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'inputs' ? 'safra-tab-pane-active' : '') . '" data-tab="inputs">';
print load_fiche_titre($langs->trans('SafraActivityInputs'), '', '');
print '<div class="opacitymedium" style="margin-bottom:6px">' . $langs->trans('SafraActivityInputsHelp') . '</div>';
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste safra-input-table" id="activity-lines" data-area-base="' . dol_escape_htmltag($baseArea) . '"><thead><tr class="liste_titre">';
print '<th>' . $langs->trans('Product') . '</th><th>' . $langs->trans('Warehouse') . '</th><th>' . $langs->trans('SafraLineMovement') . '</th>';
print '<th>' . $langs->trans('SafraActivityAreaDone') . '</th><th>' . $langs->trans('SafraActivityDoseDone') . '</th><th>' . $langs->trans('SafraActivityQtyDone') . '</th>';
print '<th>' . $langs->trans('Unit') . '</th><th>' . $langs->trans('StockMovement') . '</th><th></th>';
print '</tr></thead><tbody>';

$lines = !empty($activity->lines) ? $activity->lines : array();
if (empty($lines)) {
    $emptyLine = new FvActivityLine($db);
    $emptyLine->area_planned = $activity->area_planned ?: $activity->area_total;
    $emptyLine->area_done = $activity->area_done;
    $emptyLine->movement_type = FvActivityLine::MOVEMENT_CONSUME;
    $lines[] = $emptyLine;
}

foreach ($lines as $line) {
    $lineId = !empty($line->id) ? (int) $line->id : (!empty($line->rowid) ? (int) $line->rowid : 0);
    $areaDone = $line->area_done ?: $line->area_planned ?: $line->area_applied ?: $activity->area_done ?: $activity->area_planned;
    $doseDone = $line->dose_done ?: $line->dose_planned ?: $line->dose;
    $qtyDone = $line->qty_done ?: $line->total ?: $line->qty_planned;
    $stockMovementLabel = !empty($line->fk_stock_movement) ? '#' . ((int) $line->fk_stock_movement) : '-';
    print '<tr class="oddeven js-line-row">';
    print '<td><input type="hidden" name="line_id[]" value="' . $lineId . '">' . $form->selectarray('line_product_id[]', array(0 => '') + $productOptions, $line->fk_product, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</td>';
    print '<td>' . $form->selectarray('line_warehouse_id[]', array(0 => '') + $warehouseOptions, $line->fk_warehouse, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</td>';
    print '<td>' . $form->selectarray('line_movement_type[]', $movementOptions, FvActivityLine::normalizeMovementType($line->movement_type), 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</td>';
    print '<td><input type="hidden" name="line_area_planned[]" value="' . dol_escape_htmltag(price2num($line->area_planned ?: $areaDone)) . '"><input class="flat js-line-area-done" type="number" step="0.0001" min="0" name="line_area_done[]" value="' . dol_escape_htmltag(price2num($areaDone)) . '"></td>';
    print '<td><input type="hidden" name="line_dose_planned[]" value="' . dol_escape_htmltag(price2num($line->dose_planned ?: $doseDone)) . '"><input class="flat js-line-dose-done" type="number" step="0.0001" min="0" name="line_dose_done[]" value="' . dol_escape_htmltag(price2num($doseDone)) . '"></td>';
    print '<td><input type="hidden" name="line_qty_planned[]" value="' . dol_escape_htmltag(price2num($line->qty_planned ?: $qtyDone)) . '"><input class="flat js-line-qty-done" type="number" step="0.0001" min="0" name="line_qty_done[]" value="' . dol_escape_htmltag(price2num($qtyDone)) . '"></td>';
    print '<td>' . $form->selectarray('line_dose_unit[]', $unitOptions, $line->dose_unit ?: 'kg/ha', 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '<input type="hidden" name="line_unit_cost[]" value="' . dol_escape_htmltag(price2num($line->unit_cost)) . '"><input type="hidden" name="line_note[]" value="' . dol_escape_htmltag($line->note) . '"></td>';
    print '<td class="center nowrap">' . dol_escape_htmltag($stockMovementLabel) . '</td>';
    print '<td class="center safra-line-actions"><button type="button" class="button small js-remove-row">x</button></td>';
    print '</tr>';
}
print '</tbody></table></div>';
print '<div class="safra-actions" style="margin-top:10px"><button class="button" type="button" id="add-line">' . $langs->trans('Add') . '</button><button class="button button-save" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_inputs\';">' . $langs->trans('Save') . '</button></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'mixture' ? 'safra-tab-pane-active' : '') . '" data-tab="mixture">';
print load_fiche_titre($langs->trans('SafraAplicacaoCaldaCalculation'), '', '');
print '<div class="opacitymedium" style="margin-bottom:10px">' . $langs->trans('SafraActivityMixtureHelp') . '</div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">' . $langs->trans('SafraActivityAreaDone') . '</td><td><input class="flat" id="mix-area" type="number" step="0.0001" min="0" value="' . dol_escape_htmltag($baseArea) . '"></td></tr>';
print '<tr><td>' . $langs->trans('ApplicationRate') . '</td><td><input class="flat" id="mix-rate" type="number" step="0.01" min="0" placeholder="' . dol_escape_htmltag($langs->trans('EnterRatePerHectare')) . '"></td></tr>';
print '<tr><td>' . $langs->trans('TankCapacity') . '</td><td><input class="flat" id="mix-tank" type="number" step="0.01" min="0" placeholder="' . dol_escape_htmltag($langs->trans('EnterTankCapacity')) . '"></td></tr>';
print '</table>';
print '<div class="safra-mixture-summary">';
print '<div><span class="opacitymedium">' . $langs->trans('SafraMixtureTotalVolume') . '</span><strong id="mix-total-volume">-</strong></div>';
print '<div><span class="opacitymedium">' . $langs->trans('SafraMixtureTankCount') . '</span><strong id="mix-tank-count">-</strong></div>';
print '<div><span class="opacitymedium">' . $langs->trans('AppliedAreaPerTank') . '</span><strong id="mix-area-per-tank">-</strong></div>';
print '</div>';
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste" id="mixture-lines"><thead><tr class="liste_titre"><th>' . $langs->trans('Product') . '</th><th class="right">' . $langs->trans('SafraActivityQtyDone') . '</th><th class="right">' . $langs->trans('QuantityPerTank') . '</th></tr></thead><tbody>';
$mixtureRows = 0;
foreach ((array) $activity->lines as $line) {
    $qtyDone = $line->qty_done ?: $line->total ?: $line->qty_planned;
    if ((int) $line->fk_product <= 0 || price2num($qtyDone, 'MT') <= 0) {
        continue;
    }
    $unit = trim((string) $line->dose_unit);
    $unit = str_replace('/ha', '', $unit);
    $label = isset($productOptions[(int) $line->fk_product]) ? $productOptions[(int) $line->fk_product] : '#' . ((int) $line->fk_product);
    print '<tr class="oddeven js-mixture-line" data-qty="' . dol_escape_htmltag(price2num($qtyDone, 'MT')) . '" data-unit="' . dol_escape_htmltag($unit) . '">';
    print '<td>' . dol_escape_htmltag($label) . '</td>';
    print '<td class="right">' . price($qtyDone, 0, '', 1, 4) . ' ' . dol_escape_htmltag($unit) . '</td>';
    print '<td class="right js-mixture-per-tank">-</td>';
    print '</tr>';
    $mixtureRows++;
}
if ($mixtureRows === 0) {
    print '<tr class="oddeven"><td colspan="3" class="opacitymedium">' . $langs->trans('NoProductForMixture') . '</td></tr>';
}
print '</tbody></table></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'team' ? 'safra-tab-pane-active' : '') . '" data-tab="team">';
print load_fiche_titre($langs->trans('SafraActivityTeam'), '', '');
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste safra-input-table" id="team-lines"><thead><tr class="liste_titre"><th>' . $langs->trans('User') . '</th><th>' . $langs->trans('Role') . '</th><th>' . $langs->trans('SafraActivityPlannedHours') . '</th>';
if (!$isCreateMode) {
    print '<th>' . $langs->trans('SafraActivityDoneHours') . '</th>';
}
print '<th></th></tr></thead><tbody>';
foreach (!empty($teamLinks) ? $teamLinks : array((object) array()) as $row) {
    print '<tr class="oddeven">';
    print '<td>' . $form->selectarray('team_user_id[]', array(0 => '') + $userOptions, isset($row->fk_user) ? $row->fk_user : 0, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</td>';
    print '<td><input class="flat" type="text" name="team_role[]" value="' . dol_escape_htmltag(isset($row->role) ? $row->role : '') . '"></td>';
    print '<td><input class="flat" type="number" step="0.01" min="0" name="team_planned_hours[]" value="' . dol_escape_htmltag(isset($row->planned_hours) ? price2num($row->planned_hours) : '') . '"></td>';
    $teamHidden = '<input type="hidden" name="team_note[]" value="' . dol_escape_htmltag(isset($row->note) ? $row->note : '') . '">';
    if ($isCreateMode) {
        $teamHidden .= '<input type="hidden" name="team_done_hours[]" value="">';
    } else {
        print '<td><input class="flat" type="number" step="0.01" min="0" name="team_done_hours[]" value="' . dol_escape_htmltag(isset($row->done_hours) && $row->done_hours > 0 ? price2num($row->done_hours) : (isset($row->planned_hours) ? price2num($row->planned_hours) : '')) . '"></td>';
    }
    print '<td class="center safra-line-actions">' . $teamHidden . '<button type="button" class="button small js-remove-row">x</button></td>';
    print '</tr>';
}
print '</tbody></table></div><div class="safra-actions" style="margin-top:10px"><button class="button" type="button" id="add-team">' . $langs->trans('Add') . '</button><button class="button button-save" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_team\';">' . $langs->trans('Save') . '</button></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'vehicles' ? 'safra-tab-pane-active' : '') . '" data-tab="vehicles">';
print load_fiche_titre($langs->trans('SafraVehicleLabel'), '', '');
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste safra-input-table" id="vehicle-lines"><thead><tr class="liste_titre"><th>' . $langs->trans('SafraVehicleLabel') . '</th><th>' . $langs->trans('SafraActivityPlannedHours') . '</th>';
if (!$isCreateMode) {
    print '<th>' . $langs->trans('SafraActivityDoneHours') . '</th>';
}
print '<th></th></tr></thead><tbody>';
foreach (!empty($vehicleLinks) ? $vehicleLinks : array((object) array()) as $row) {
    print '<tr class="oddeven"><td>' . $form->selectarray('vehicle_id[]', array(0 => '') + $vehicleOptions, isset($row->fk_vehicle) ? $row->fk_vehicle : 0, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</td>';
    print '<td><input class="flat" type="number" step="0.01" min="0" name="vehicle_planned_hours[]" value="' . dol_escape_htmltag(isset($row->planned_hours) ? price2num($row->planned_hours) : '') . '"></td>';
    $vehicleHidden = '<input type="hidden" name="vehicle_note[]" value="' . dol_escape_htmltag(isset($row->note) ? $row->note : '') . '">';
    if ($isCreateMode) {
        $vehicleHidden .= '<input type="hidden" name="vehicle_done_hours[]" value="">';
    } else {
        print '<td><input class="flat" type="number" step="0.01" min="0" name="vehicle_done_hours[]" value="' . dol_escape_htmltag(isset($row->done_hours) && $row->done_hours > 0 ? price2num($row->done_hours) : (isset($row->planned_hours) ? price2num($row->planned_hours) : '')) . '"></td>';
    }
    print '<td class="center safra-line-actions">' . $vehicleHidden . '<button type="button" class="button small js-remove-row">x</button></td></tr>';
}
print '</tbody></table></div><div class="safra-actions" style="margin-top:10px"><button class="button" type="button" id="add-vehicle">' . $langs->trans('Add') . '</button><button class="button button-save" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_vehicles\';">' . $langs->trans('Save') . '</button></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'implements' ? 'safra-tab-pane-active' : '') . '" data-tab="implements">';
print load_fiche_titre($langs->trans('SafraImplementsLabel'), '', '');
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste safra-input-table" id="implement-lines"><thead><tr class="liste_titre"><th>' . $langs->trans('SafraImplementsLabel') . '</th><th>' . $langs->trans('SafraActivityPlannedHours') . '</th>';
if (!$isCreateMode) {
    print '<th>' . $langs->trans('SafraActivityDoneHours') . '</th>';
}
print '<th></th></tr></thead><tbody>';
foreach (!empty($implementLinks) ? $implementLinks : array((object) array()) as $row) {
    print '<tr class="oddeven"><td>' . $form->selectarray('implement_id[]', array(0 => '') + $implementOptions, isset($row->fk_implement) ? $row->fk_implement : 0, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</td>';
    print '<td><input class="flat" type="number" step="0.01" min="0" name="implement_planned_hours[]" value="' . dol_escape_htmltag(isset($row->planned_hours) ? price2num($row->planned_hours) : '') . '"></td>';
    $implementHidden = '<input type="hidden" name="implement_note[]" value="' . dol_escape_htmltag(isset($row->note) ? $row->note : '') . '">';
    if ($isCreateMode) {
        $implementHidden .= '<input type="hidden" name="implement_done_hours[]" value="">';
    } else {
        print '<td><input class="flat" type="number" step="0.01" min="0" name="implement_done_hours[]" value="' . dol_escape_htmltag(isset($row->done_hours) && $row->done_hours > 0 ? price2num($row->done_hours) : (isset($row->planned_hours) ? price2num($row->planned_hours) : '')) . '"></td>';
    }
    print '<td class="center safra-line-actions">' . $implementHidden . '<button type="button" class="button small js-remove-row">x</button></td></tr>';
}
print '</tbody></table></div><div class="safra-actions" style="margin-top:10px"><button class="button" type="button" id="add-implement">' . $langs->trans('Add') . '</button><button class="button button-save" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_implements\';">' . $langs->trans('Save') . '</button></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'card' ? 'safra-tab-pane-active' : '') . '" data-tab="card-notes">';
print load_fiche_titre($langs->trans('Note'), '', '');
print '<textarea class="flat" name="note_public" rows="4" style="width:100%">' . dol_escape_htmltag($activity->note_public) . '</textarea>';

print '<div class="safra-actions" style="margin-top:12px">';
print '<button class="button button-save" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_main\';">' . $langs->trans('Save') . '</button>';
if ($canFinishFromCard) {
    print '<button class="button" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_complete\';return confirm(\'' . dol_escape_js($langs->transnoentities('SafraActivityFinishConfirm')) . '\');">' . $langs->trans('SafraActivityFinishNow') . '</button>';
}
print '</div>';
print '</div>';

print '</div>';
print '</form>';

if (!$isCreateMode) {
    print dol_get_fiche_end();
}

if ($activity->id) {
    print '<div class="tabsAction safra-actions">';
    if (!$activity->isInProgress() && !$activity->isCompleted() && !$activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="id" value="' . ((int) $activity->id) . '"><input type="hidden" name="action" value="start"><button class="button" type="submit">' . $langs->trans('SafraActivityStart') . '</button></form>';
    }
    if ($activity->isCompleted() || $activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="id" value="' . ((int) $activity->id) . '"><input type="hidden" name="action" value="reopen"><button class="button" type="submit">' . $langs->trans('SafraActivityReopen') . '</button></form>';
    }
    if (!$activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="id" value="' . ((int) $activity->id) . '"><input type="hidden" name="action" value="cancel"><button class="button" type="submit">' . $langs->trans('SafraActivityCancel') . '</button></form>';
    }
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" onsubmit="return confirm(\'' . dol_escape_js($langs->transnoentities('ConfirmDelete')) . '\');"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="id" value="' . ((int) $activity->id) . '"><input type="hidden" name="action" value="delete"><button class="button safra-danger" type="submit">' . $langs->trans('SafraActivityDelete') . '</button></form>';
    print '</div>';
}

print '<script>
(function(){
function n(v){v=(v||"").toString().replace(",", ".");var x=parseFloat(v);return isNaN(x)?0:x}
function bindRemove(scope){(scope||document).querySelectorAll(".js-remove-row").forEach(function(btn){if(btn.dataset.bound)return;btn.dataset.bound=1;btn.addEventListener("click",function(){var tr=btn.closest("tr");var tbody=tr&&tr.parentNode;if(!tr||!tbody)return;if(tbody.children.length>1){tr.remove();return;}tr.querySelectorAll("input").forEach(function(i){i.value="";});tr.querySelectorAll("select").forEach(function(s){s.selectedIndex=0;});});});}
function cloneFirst(tableId){var tbody=document.querySelector("#"+tableId+" tbody");if(!tbody||!tbody.children.length)return;var row=tbody.children[0].cloneNode(true);row.querySelectorAll("input").forEach(function(i){if(i.type==="hidden"){i.value="";return;}i.value="";});row.querySelectorAll("select").forEach(function(s){s.selectedIndex=0;});tbody.appendChild(row);if(tableId==="activity-lines"){var area=document.getElementById("safra-area-planned");var table=document.getElementById("activity-lines");var base=(area&&area.value)?area.value:(table?table.getAttribute("data-area-base"):"");var lineArea=row.querySelector(".js-line-area-done");if(lineArea)setAutoInput(lineArea,base,true);}bindRemove(row);bindLine(row);}
function recalc(row){var ad=row.querySelector(".js-line-area-done"),dd=row.querySelector(".js-line-dose-done"),qd=row.querySelector(".js-line-qty-done");if(ad&&dd&&qd&&n(ad.value)>0&&n(dd.value)>0)qd.value=(n(ad.value)*n(dd.value)).toFixed(4);}
function bindLine(row){if(!row||row.dataset.boundLine)return;row.dataset.boundLine=1;row.querySelectorAll(".js-line-area-done,.js-line-dose-done").forEach(function(input){input.addEventListener("input",function(){recalc(row);});});}
document.querySelectorAll(".js-line-row").forEach(bindLine);
bindRemove(document);
if(document.querySelector(".safra-tab-pane-active[data-tab=\\"card\\"]")){
    ["type","fk_fieldplot"].forEach(function(name){var el=document.querySelector("[name=\\""+name+"\\"]");if(el)el.required=true;});
}
function byId(primary,fallback){return document.getElementById(primary)||document.getElementById(fallback);}
var projectSelect=byId("safra-project-select","fk_project");
var contextBox=document.getElementById("safra-project-context");
var contextLoadedText="' . dol_escape_js($langs->transnoentities('SafraProjectContextLoaded')) . '";
function setAutoInput(el,value,force){if(!el||value===null||value===undefined||value==="")return;var val=String(value);if(force||!el.value||el.dataset.autoValue===el.value){el.value=val;el.dataset.autoValue=val;}}
function setAutoSelect(el,id,label,force){if(!el||!id)return;var val=String(id);var exists=false;Array.prototype.forEach.call(el.options,function(option){if(option.value===val)exists=true;});if(!exists){var option=document.createElement("option");option.value=val;option.textContent=label||("#"+val);el.appendChild(option);}if(force||!el.value||el.dataset.autoValue===el.value){el.value=val;el.dataset.autoValue=val;if(window.jQuery)window.jQuery(el).trigger("change");else el.dispatchEvent(new Event("change",{bubbles:true}));}}
function setLineAreas(area){if(area===null||area===undefined||area==="")return;document.querySelectorAll(".js-line-area-done").forEach(function(input){setAutoInput(input,area,true);var row=input.closest("tr");if(row)recalc(row);});}
function applyProjectContext(data){if(!data||!data.success)return;var parts=[];if(data.talhao){setAutoSelect(byId("safra-fieldplot-select","fk_fieldplot"),data.talhao.id,data.talhao.display,true);setAutoInput(document.getElementById("safra-area-planned"),data.talhao.area,true);setLineAreas(data.talhao.area);parts.push(data.talhao.display);}if(data.cultura){setAutoInput(document.getElementById("safra-crop-name"),data.cultura.label||data.cultura.display,true);parts.push(data.cultura.display);}if(data.cultivar){setAutoInput(document.getElementById("safra-cultivar-name"),data.cultivar.label||data.cultivar.display,true);parts.push(data.cultivar.display);}if(contextBox&&parts.length)contextBox.textContent=contextLoadedText+": "+parts.join(" | ");}
function fetchProjectContext(){if(!projectSelect||!projectSelect.value)return;var url=projectSelect.getAttribute("data-context-url")||"";if(!url)return;fetch(url+"?id="+encodeURIComponent(projectSelect.value),{credentials:"same-origin"}).then(function(r){return r.json();}).then(applyProjectContext).catch(function(){});}
if(projectSelect){projectSelect.addEventListener("change",fetchProjectContext);if(window.jQuery)window.jQuery(projectSelect).on("select2:select select2:clear",fetchProjectContext);fetchProjectContext();}
var addLine=document.getElementById("add-line"); if(addLine)addLine.addEventListener("click",function(){cloneFirst("activity-lines");});
var addTeam=document.getElementById("add-team"); if(addTeam)addTeam.addEventListener("click",function(){cloneFirst("team-lines");});
var addVehicle=document.getElementById("add-vehicle"); if(addVehicle)addVehicle.addEventListener("click",function(){cloneFirst("vehicle-lines");});
var addImplement=document.getElementById("add-implement"); if(addImplement)addImplement.addEventListener("click",function(){cloneFirst("implement-lines");});
function fmt(v,d){if(!isFinite(v))return "-";return v.toFixed(d||2).replace(".", ",");}
function calcMixture(){var area=n((document.getElementById("mix-area")||{}).value),rate=n((document.getElementById("mix-rate")||{}).value),tank=n((document.getElementById("mix-tank")||{}).value);var total=area*rate;var tanks=(tank>0&&total>0)?Math.ceil(total/tank):0;var areaPerTank=(rate>0&&tank>0)?Math.min(area,tank/rate):0;var totalBox=document.getElementById("mix-total-volume"),tankBox=document.getElementById("mix-tank-count"),areaBox=document.getElementById("mix-area-per-tank");if(totalBox)totalBox.textContent=total>0?fmt(total,2)+" L":"-";if(tankBox)tankBox.textContent=tanks>0?String(tanks):"-";if(areaBox)areaBox.textContent=areaPerTank>0?fmt(areaPerTank,4)+" ha":"-";document.querySelectorAll(".js-mixture-line").forEach(function(row){var qty=n(row.getAttribute("data-qty")),unit=row.getAttribute("data-unit")||"",cell=row.querySelector(".js-mixture-per-tank");if(cell)cell.textContent=(tanks>0&&qty>0)?fmt(qty/tanks,4)+" "+unit:"-";});}
["mix-area","mix-rate","mix-tank"].forEach(function(id){var el=document.getElementById(id);if(el)el.addEventListener("input",calcMixture);});
calcMixture();
})();
</script>';

llxFooter();
$db->close();
