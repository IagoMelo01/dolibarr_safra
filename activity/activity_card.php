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
dol_include_once('/safra/lib/safra_activity.lib.php');

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

$mutatingActions = array('save', 'save_main', 'save_complete', 'save_inputs', 'save_input_line', 'delete_input_line', 'save_mixture', 'save_team', 'save_team_line', 'delete_team_line', 'save_vehicles', 'save_vehicle_line', 'delete_vehicle_line', 'save_implements', 'save_implement_line', 'delete_implement_line', 'start', 'complete', 'cancel', 'reopen', 'delete');
if (in_array($action, $mutatingActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        accessforbidden();
    }
    $token = GETPOST('token', 'alphanohtml');
    if (function_exists('checkToken') && !checkToken($token)) {
        accessforbidden('Invalid security token.');
    }
}
if (in_array($action, array('save', 'save_main', 'save_complete', 'save_inputs', 'save_input_line', 'delete_input_line', 'save_mixture', 'save_team', 'save_team_line', 'delete_team_line', 'save_vehicles', 'save_vehicle_line', 'delete_vehicle_line', 'save_implements', 'save_implement_line', 'delete_implement_line', 'start', 'complete', 'cancel', 'reopen'), true) && !$permissiontowrite) {
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

function safra_activity_option_label(array $options, $id)
{
    $id = (int) $id;
    return isset($options[$id]) ? $options[$id] : ($id > 0 ? '#' . $id : '-');
}

function safra_activity_data_attr($name, $value)
{
    return ' data-' . $name . '="' . dol_escape_htmltag((string) $value) . '"';
}

function safra_activity_resource_label($row, $field, array $options)
{
    return safra_activity_option_label($options, isset($row->{$field}) ? (int) $row->{$field} : 0);
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

if ($action === 'save_input_line') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $line = new FvActivityLine($db);
        $lineId = GETPOSTINT('line_id');
        if ($lineId > 0) {
            $line->id = $lineId;
            $line->rowid = $lineId;
        }
        $line->fk_activity = (int) $activity->id;
        $line->fk_product = GETPOSTINT('line_product_id');
        $line->fk_warehouse = GETPOSTINT('line_warehouse_id');
        $line->movement_type = GETPOST('line_movement_type', 'alphanohtml') ?: FvActivityLine::MOVEMENT_CONSUME;
        $line->area_planned = price2num(GETPOST('line_area_planned', 'alphanohtml'), 'MT');
        $line->area_done = price2num(GETPOST('line_area_done', 'alphanohtml'), 'MT');
        $line->dose_planned = price2num(GETPOST('line_dose_planned', 'alphanohtml'), 'MT');
        $line->dose_done = price2num(GETPOST('line_dose_done', 'alphanohtml'), 'MT');
        $line->dose_unit = GETPOST('line_dose_unit', 'alphanohtml') ?: 'kg/ha';
        $line->qty_planned = price2num(GETPOST('line_qty_planned', 'alphanohtml'), 'MT');
        $line->qty_done = price2num(GETPOST('line_qty_done', 'alphanohtml'), 'MT');
        $line->unit_cost = price2num(GETPOST('line_unit_cost', 'alphanohtml'), 'MT');
        $line->note = GETPOST('line_note', 'restricthtml');

        $result = $activity->saveInputLine($line, $user);
        if ($result < 0) {
            $errors[] = $langs->trans($activity->error ?: 'ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=inputs');
            exit;
        }
    }
}

if ($action === 'delete_input_line') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $result = $activity->deleteInputLine(GETPOSTINT('line_id'), $user);
        if ($result < 0) {
            $errors[] = $langs->trans($activity->error ?: 'ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=inputs');
            exit;
        }
    }
}

if ($action === 'save_mixture') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $activity->mixture_area = price2num(GETPOST('mixture_area', 'alphanohtml'), 'MT');
        $activity->mixture_rate = price2num(GETPOST('mixture_rate', 'alphanohtml'), 'MT');
        $activity->mixture_tank_capacity = price2num(GETPOST('mixture_tank_capacity', 'alphanohtml'), 'MT');
        $activity->mixture_updated_at = dol_now();
        if ($activity->update($user) <= 0) {
            $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraMixtureUpdated'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=mixture');
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

if ($action === 'save_team_line') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $row = array(
            'rowid' => GETPOSTINT('team_rowid'),
            'fk_user' => GETPOSTINT('team_user_id'),
            'role' => GETPOST('team_role', 'alphanohtml'),
            'planned_hours' => price2num(GETPOST('team_planned_hours', 'alphanohtml'), 'MT'),
            'done_hours' => price2num(GETPOST('team_done_hours', 'alphanohtml'), 'MT'),
            'note' => GETPOST('team_note', 'restricthtml'),
        );
        if ($activity->saveUserLink($row) < 0) {
            $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=team');
            exit;
        }
    }
}

if ($action === 'delete_team_line') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        if ($activity->deleteUserLink(GETPOSTINT('team_rowid')) < 0) {
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

if ($action === 'save_vehicle_line') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $row = array(
            'rowid' => GETPOSTINT('vehicle_rowid'),
            'fk_vehicle' => GETPOSTINT('vehicle_id'),
            'vehicle_class' => 'Veiculo',
            'planned_hours' => price2num(GETPOST('vehicle_planned_hours', 'alphanohtml'), 'MT'),
            'done_hours' => price2num(GETPOST('vehicle_done_hours', 'alphanohtml'), 'MT'),
            'note' => GETPOST('vehicle_note', 'restricthtml'),
        );
        if ($activity->saveVehicleLink($row) < 0) {
            $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=vehicles');
            exit;
        }
    }
}

if ($action === 'delete_vehicle_line') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        if ($activity->deleteVehicleLink(GETPOSTINT('vehicle_rowid')) < 0) {
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

if ($action === 'save_implement_line') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        $row = array(
            'rowid' => GETPOSTINT('implement_rowid'),
            'fk_implement' => GETPOSTINT('implement_id'),
            'implement_class' => 'Implemento',
            'planned_hours' => price2num(GETPOST('implement_planned_hours', 'alphanohtml'), 'MT'),
            'done_hours' => price2num(GETPOST('implement_done_hours', 'alphanohtml'), 'MT'),
            'note' => GETPOST('implement_note', 'restricthtml'),
        );
        if ($activity->saveImplementLink($row) < 0) {
            $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
        } else {
            setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id . '&tab=implements');
            exit;
        }
    }
}

if ($action === 'delete_implement_line') {
    if (empty($activity->id)) {
        $errors[] = $langs->trans('ErrorSafraActivityInvalidIdentifier');
    }

    if (!$errors) {
        if ($activity->deleteImplementLink(GETPOSTINT('implement_rowid')) < 0) {
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
$mixtureArea = price2num($activity->mixture_area ?: $baseArea, 'MT');
$mixtureRate = price2num($activity->mixture_rate, 'MT');
$mixtureTankCapacity = price2num($activity->mixture_tank_capacity, 'MT');
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
.button.safra-btn-primary{background:#2e7d32!important;border-color:#1f6b25!important;color:#fff!important}
.button.safra-btn-secondary{background:#eef2f6!important;border-color:#c6d0da!important;color:#17202a!important}
.button.safra-btn-danger{background:#b42318!important;border-color:#971b12!important;color:#fff!important}
.button.safra-btn-ghost{background:#fff!important;border-color:#ccd3da!important;color:#243447!important}
.button.safra-icon-btn{min-width:32px;padding-left:8px!important;padding-right:8px!important}
.safra-danger{color:#b42318}
.safra-line-actions{width:86px;white-space:nowrap;text-align:right}
.safra-mixture-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:12px 0}
.safra-mixture-summary div{border:1px solid #d9e0e6;background:#f8fafb;padding:10px}
.safra-mixture-summary strong{display:block;font-size:18px}
.safra-modal{display:none;position:fixed;z-index:100000;inset:0;align-items:flex-start;justify-content:center;background:rgba(0,0,0,.38);padding:42px 12px;overflow:auto;pointer-events:auto}
.safra-modal.safra-modal-open{display:flex}
.safra-modal-panel{position:relative;z-index:100001;width:min(720px,100%);background:#fff;border:1px solid #cfd6dd;box-shadow:0 12px 32px rgba(0,0,0,.22);pointer-events:auto}
.safra-modal-title{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid #d8dee4;font-weight:600}
.safra-modal-body{padding:14px}
.safra-modal-actions{display:flex;justify-content:flex-end;gap:8px;padding:12px 14px;border-top:1px solid #d8dee4;background:#f8fafb}
.safra-modal input,.safra-modal select,.safra-modal textarea,.safra-modal button,.safra-modal .select2-container{pointer-events:auto!important}
.safra-modal .select2-container{width:100%!important;max-width:100%}
body.safra-modal-is-open .select2-container--open{z-index:100010!important}
body.safra-modal-is-open .select2-dropdown{z-index:100011!important}
.safra-empty{padding:14px;color:#68717a}
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
print '<button class="button button-save safra-btn-primary" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_main\';">' . $langs->trans('Save') . '</button>';
if ($canFinishFromCard) {
    print '<button class="button safra-btn-secondary" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_complete\';return confirm(\'' . dol_escape_js($langs->transnoentities('SafraActivityFinishConfirm')) . '\');">' . $langs->trans('SafraActivityFinishNow') . '</button>';
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
print '<div class="safra-actions safra-primary-actions" style="margin-bottom:10px"><button class="button safra-btn-primary js-open-modal" type="button" data-modal="input" data-mode="new">' . $langs->trans('Add') . '</button></div>';
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste" id="activity-lines" data-area-base="' . dol_escape_htmltag($baseArea) . '"><thead><tr class="liste_titre">';
print '<th>' . $langs->trans('Product') . '</th><th>' . $langs->trans('Warehouse') . '</th><th>' . $langs->trans('SafraLineMovement') . '</th>';
print '<th>' . $langs->trans('SafraActivityAreaDone') . '</th><th>' . $langs->trans('SafraActivityDoseDone') . '</th><th>' . $langs->trans('SafraActivityQtyDone') . '</th>';
print '<th>' . $langs->trans('Unit') . '</th><th>' . $langs->trans('StockMovement') . '</th><th></th>';
print '</tr></thead><tbody>';

$lines = !empty($activity->lines) ? $activity->lines : array();
if (empty($lines)) {
    print '<tr class="oddeven"><td colspan="9" class="safra-empty">' . $langs->trans('NoRecordFound') . '</td></tr>';
} else {
    foreach ($lines as $line) {
        $lineId = !empty($line->id) ? (int) $line->id : (!empty($line->rowid) ? (int) $line->rowid : 0);
        $areaDone = $line->area_done ?: $line->area_planned ?: $line->area_applied ?: $activity->area_done ?: $activity->area_planned;
        $areaPlanned = $line->area_planned ?: $areaDone;
        $doseDone = $line->dose_done ?: $line->dose_planned ?: $line->dose;
        $dosePlanned = $line->dose_planned ?: $doseDone;
        $qtyDone = $line->qty_done ?: $line->total ?: $line->qty_planned;
        $qtyPlanned = $line->qty_planned ?: $qtyDone;
        $unit = $line->dose_unit ?: 'kg/ha';
        $stockMovementLabel = !empty($line->fk_stock_movement) ? '#' . ((int) $line->fk_stock_movement) : '-';
        $editAttrs = ''
            . safra_activity_data_attr('modal', 'input')
            . safra_activity_data_attr('mode', 'edit')
            . safra_activity_data_attr('line-id', $lineId)
            . safra_activity_data_attr('product-id', (int) $line->fk_product)
            . safra_activity_data_attr('warehouse-id', (int) $line->fk_warehouse)
            . safra_activity_data_attr('movement-type', FvActivityLine::normalizeMovementType($line->movement_type))
            . safra_activity_data_attr('area-planned', price2num($areaPlanned, 'MT'))
            . safra_activity_data_attr('area-done', price2num($areaDone, 'MT'))
            . safra_activity_data_attr('dose-planned', price2num($dosePlanned, 'MT'))
            . safra_activity_data_attr('dose-done', price2num($doseDone, 'MT'))
            . safra_activity_data_attr('qty-planned', price2num($qtyPlanned, 'MT'))
            . safra_activity_data_attr('qty-done', price2num($qtyDone, 'MT'))
            . safra_activity_data_attr('dose-unit', $unit)
            . safra_activity_data_attr('unit-cost', price2num($line->unit_cost, 'MT'))
            . safra_activity_data_attr('note', $line->note);
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag(safra_activity_option_label($productOptions, $line->fk_product)) . '</td>';
        print '<td>' . dol_escape_htmltag(safra_activity_option_label($warehouseOptions, $line->fk_warehouse)) . '</td>';
        print '<td>' . dol_escape_htmltag(isset($movementOptions[FvActivityLine::normalizeMovementType($line->movement_type)]) ? $movementOptions[FvActivityLine::normalizeMovementType($line->movement_type)] : $line->movement_type) . '</td>';
        print '<td class="right">' . price($areaDone, 0, '', 1, 4) . '</td>';
        print '<td class="right">' . price($doseDone, 0, '', 1, 4) . '</td>';
        print '<td class="right">' . price($qtyDone, 0, '', 1, 4) . '</td>';
        print '<td>' . dol_escape_htmltag($unit) . '</td>';
        print '<td class="center nowrap">' . dol_escape_htmltag($stockMovementLabel) . '</td>';
        print '<td class="safra-line-actions"><button type="button" class="button safra-btn-secondary safra-icon-btn js-open-modal" title="' . dol_escape_htmltag($langs->trans('Edit')) . '"' . $editAttrs . '><span class="fas fa-pencil-alt"></span></button> ';
        print '<button type="button" class="button safra-btn-danger safra-icon-btn js-delete-line" title="' . dol_escape_htmltag($langs->trans('Delete')) . '" data-action="delete_input_line" data-target="line_id" data-id="' . $lineId . '"><span class="fas fa-trash"></span></button></td>';
        print '</tr>';
    }
}
print '</tbody></table></div>';

print '<div class="safra-modal" id="safra-modal-input" aria-hidden="true">';
print '<div class="safra-modal-panel">';
print '<div class="safra-modal-title"><span data-title-new="' . dol_escape_htmltag($langs->trans('SafraInputAdd')) . '" data-title-edit="' . dol_escape_htmltag($langs->trans('SafraInputEdit')) . '">' . $langs->trans('SafraInputAdd') . '</span><button type="button" class="button safra-btn-ghost safra-icon-btn js-close-modal"><span class="fas fa-times"></span></button></div>';
print '<div class="safra-modal-body"><table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('Product') . '</td><td><input type="hidden" name="line_id" value="">' . $form->selectarray('line_product_id', array(0 => '') + $productOptions, 0, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300', 1) . '</td></tr>';
print '<tr><td class="fieldrequired">' . $langs->trans('Warehouse') . '</td><td>' . $form->selectarray('line_warehouse_id', array(0 => '') + $warehouseOptions, 0, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300', 1) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraLineMovement') . '</td><td>' . $form->selectarray('line_movement_type', $movementOptions, FvActivityLine::MOVEMENT_CONSUME, 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityAreaDone') . '</td><td><input type="hidden" name="line_area_planned" value="' . dol_escape_htmltag($baseArea) . '"><input class="flat js-modal-area" type="number" step="0.0001" min="0" name="line_area_done" value="' . dol_escape_htmltag($baseArea) . '"></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDoseDone') . '</td><td><input type="hidden" name="line_dose_planned" value=""><input class="flat js-modal-dose" type="number" step="0.0001" min="0" name="line_dose_done" value=""></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityQtyDone') . '</td><td><input type="hidden" name="line_qty_planned" value=""><input class="flat js-modal-qty" type="number" step="0.0001" min="0" name="line_qty_done" value=""></td></tr>';
print '<tr><td>' . $langs->trans('Unit') . '</td><td>' . $form->selectarray('line_dose_unit', $unitOptions, 'kg/ha', 0, 0, 0, '', 0, 0, 0, '', 'flat', 1) . '<input type="hidden" name="line_unit_cost" value="0"></td></tr>';
print '<tr><td>' . $langs->trans('Note') . '</td><td><textarea class="flat" name="line_note" rows="3" style="width:100%"></textarea></td></tr>';
print '</table></div>';
print '<div class="safra-modal-actions"><button type="button" class="button safra-btn-ghost js-close-modal">' . $langs->trans('Cancel') . '</button><button type="submit" class="button safra-btn-primary" onclick="document.getElementById(\'safra-form-action\').value=\'save_input_line\';">' . $langs->trans('Save') . '</button></div>';
print '</div></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'mixture' ? 'safra-tab-pane-active' : '') . '" data-tab="mixture">';
print load_fiche_titre($langs->trans('SafraAplicacaoCaldaCalculation'), '', '');
print '<div class="opacitymedium" style="margin-bottom:10px">' . $langs->trans('SafraActivityMixtureHelp') . '</div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate">' . $langs->trans('SafraActivityAreaDone') . '</td><td><input class="flat" id="mix-area" name="mixture_area" type="number" step="0.0001" min="0" value="' . dol_escape_htmltag($mixtureArea) . '"></td></tr>';
print '<tr><td>' . $langs->trans('ApplicationRate') . '</td><td><input class="flat" id="mix-rate" name="mixture_rate" type="number" step="0.01" min="0" value="' . dol_escape_htmltag($mixtureRate) . '" placeholder="' . dol_escape_htmltag($langs->trans('EnterRatePerHectare')) . '"></td></tr>';
print '<tr><td>' . $langs->trans('TankCapacity') . '</td><td><input class="flat" id="mix-tank" name="mixture_tank_capacity" type="number" step="0.01" min="0" value="' . dol_escape_htmltag($mixtureTankCapacity) . '" placeholder="' . dol_escape_htmltag($langs->trans('EnterTankCapacity')) . '"></td></tr>';
if (!empty($activity->mixture_updated_at)) {
    print '<tr><td>' . $langs->trans('DateModification') . '</td><td>' . dol_print_date($activity->mixture_updated_at, 'dayhour') . '</td></tr>';
}
print '</table>';
print '<div class="safra-mixture-summary">';
print '<div><span class="opacitymedium">' . $langs->trans('SafraMixtureTotalVolume') . '</span><strong id="mix-total-volume">-</strong></div>';
print '<div><span class="opacitymedium">' . $langs->trans('SafraMixtureTankCount') . '</span><strong id="mix-tank-count">-</strong></div>';
print '<div><span class="opacitymedium">' . $langs->trans('AppliedAreaPerTank') . '</span><strong id="mix-area-per-tank">-</strong></div>';
print '</div>';
print '<div class="safra-actions safra-primary-actions" style="margin-bottom:10px"><button type="submit" class="button safra-btn-primary" onclick="document.getElementById(\'safra-form-action\').value=\'save_mixture\';">' . $langs->trans('SafraUpdateMixture') . '</button></div>';
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
print '<div class="safra-actions safra-primary-actions" style="margin-bottom:10px"><button class="button safra-btn-primary js-open-modal" type="button" data-modal="team" data-mode="new">' . $langs->trans('Add') . '</button></div>';
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste" id="team-lines"><thead><tr class="liste_titre"><th>' . $langs->trans('User') . '</th><th>' . $langs->trans('Role') . '</th><th class="right">' . $langs->trans('SafraActivityPlannedHours') . '</th><th class="right">' . $langs->trans('SafraActivityDoneHours') . '</th><th></th></tr></thead><tbody>';
if (empty($teamLinks)) {
    print '<tr class="oddeven"><td colspan="5" class="safra-empty">' . $langs->trans('NoRecordFound') . '</td></tr>';
} else {
    foreach ($teamLinks as $row) {
        $rowid = isset($row->rowid) ? (int) $row->rowid : 0;
        $editAttrs = ''
            . safra_activity_data_attr('modal', 'team')
            . safra_activity_data_attr('mode', 'edit')
            . safra_activity_data_attr('rowid', $rowid)
            . safra_activity_data_attr('user-id', isset($row->fk_user) ? (int) $row->fk_user : 0)
            . safra_activity_data_attr('role', isset($row->role) ? $row->role : '')
            . safra_activity_data_attr('planned-hours', isset($row->planned_hours) ? price2num($row->planned_hours, 'MT') : '')
            . safra_activity_data_attr('done-hours', isset($row->done_hours) ? price2num($row->done_hours, 'MT') : '')
            . safra_activity_data_attr('note', isset($row->note) ? $row->note : '');
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag(safra_activity_resource_label($row, 'fk_user', $userOptions)) . '</td>';
        print '<td>' . dol_escape_htmltag(isset($row->role) ? $row->role : '') . '</td>';
        print '<td class="right">' . price(isset($row->planned_hours) ? $row->planned_hours : 0, 0, '', 1, 2) . '</td>';
        print '<td class="right">' . price(isset($row->done_hours) ? $row->done_hours : 0, 0, '', 1, 2) . '</td>';
        print '<td class="safra-line-actions"><button type="button" class="button safra-btn-secondary safra-icon-btn js-open-modal" title="' . dol_escape_htmltag($langs->trans('Edit')) . '"' . $editAttrs . '><span class="fas fa-pencil-alt"></span></button> ';
        print '<button type="button" class="button safra-btn-danger safra-icon-btn js-delete-line" title="' . dol_escape_htmltag($langs->trans('Delete')) . '" data-action="delete_team_line" data-target="team_rowid" data-id="' . $rowid . '"><span class="fas fa-trash"></span></button></td>';
        print '</tr>';
    }
}
print '</tbody></table></div>';
print '<div class="safra-modal" id="safra-modal-team" aria-hidden="true"><div class="safra-modal-panel">';
print '<div class="safra-modal-title"><span data-title-new="' . dol_escape_htmltag($langs->trans('SafraTeamAdd')) . '" data-title-edit="' . dol_escape_htmltag($langs->trans('SafraTeamEdit')) . '">' . $langs->trans('SafraTeamAdd') . '</span><button type="button" class="button safra-btn-ghost safra-icon-btn js-close-modal"><span class="fas fa-times"></span></button></div>';
print '<div class="safra-modal-body"><table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('User') . '</td><td><input type="hidden" name="team_rowid" value="">' . $form->selectarray('team_user_id', array(0 => '') + $userOptions, 0, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300', 1) . '</td></tr>';
print '<tr><td>' . $langs->trans('Role') . '</td><td><input class="flat minwidth300" type="text" name="team_role" value=""></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityPlannedHours') . '</td><td><input class="flat" type="number" step="0.01" min="0" name="team_planned_hours" value=""></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDoneHours') . '</td><td><input class="flat" type="number" step="0.01" min="0" name="team_done_hours" value=""></td></tr>';
print '<tr><td>' . $langs->trans('Note') . '</td><td><textarea class="flat" name="team_note" rows="3" style="width:100%"></textarea></td></tr>';
print '</table></div><div class="safra-modal-actions"><button type="button" class="button safra-btn-ghost js-close-modal">' . $langs->trans('Cancel') . '</button><button type="submit" class="button safra-btn-primary" onclick="document.getElementById(\'safra-form-action\').value=\'save_team_line\';">' . $langs->trans('Save') . '</button></div>';
print '</div></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'vehicles' ? 'safra-tab-pane-active' : '') . '" data-tab="vehicles">';
print load_fiche_titre($langs->trans('SafraVehicleLabel'), '', '');
print '<div class="safra-actions safra-primary-actions" style="margin-bottom:10px"><button class="button safra-btn-primary js-open-modal" type="button" data-modal="vehicle" data-mode="new">' . $langs->trans('Add') . '</button></div>';
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste" id="vehicle-lines"><thead><tr class="liste_titre"><th>' . $langs->trans('SafraVehicleLabel') . '</th><th class="right">' . $langs->trans('SafraActivityPlannedHours') . '</th><th class="right">' . $langs->trans('SafraActivityDoneHours') . '</th><th></th></tr></thead><tbody>';
if (empty($vehicleLinks)) {
    print '<tr class="oddeven"><td colspan="4" class="safra-empty">' . $langs->trans('NoRecordFound') . '</td></tr>';
} else {
    foreach ($vehicleLinks as $row) {
        $rowid = isset($row->rowid) ? (int) $row->rowid : 0;
        $editAttrs = ''
            . safra_activity_data_attr('modal', 'vehicle')
            . safra_activity_data_attr('mode', 'edit')
            . safra_activity_data_attr('rowid', $rowid)
            . safra_activity_data_attr('vehicle-id', isset($row->fk_vehicle) ? (int) $row->fk_vehicle : 0)
            . safra_activity_data_attr('planned-hours', isset($row->planned_hours) ? price2num($row->planned_hours, 'MT') : '')
            . safra_activity_data_attr('done-hours', isset($row->done_hours) ? price2num($row->done_hours, 'MT') : '')
            . safra_activity_data_attr('note', isset($row->note) ? $row->note : '');
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag(safra_activity_resource_label($row, 'fk_vehicle', $vehicleOptions)) . '</td>';
        print '<td class="right">' . price(isset($row->planned_hours) ? $row->planned_hours : 0, 0, '', 1, 2) . '</td>';
        print '<td class="right">' . price(isset($row->done_hours) ? $row->done_hours : 0, 0, '', 1, 2) . '</td>';
        print '<td class="safra-line-actions"><button type="button" class="button safra-btn-secondary safra-icon-btn js-open-modal" title="' . dol_escape_htmltag($langs->trans('Edit')) . '"' . $editAttrs . '><span class="fas fa-pencil-alt"></span></button> ';
        print '<button type="button" class="button safra-btn-danger safra-icon-btn js-delete-line" title="' . dol_escape_htmltag($langs->trans('Delete')) . '" data-action="delete_vehicle_line" data-target="vehicle_rowid" data-id="' . $rowid . '"><span class="fas fa-trash"></span></button></td>';
        print '</tr>';
    }
}
print '</tbody></table></div>';
print '<div class="safra-modal" id="safra-modal-vehicle" aria-hidden="true"><div class="safra-modal-panel">';
print '<div class="safra-modal-title"><span data-title-new="' . dol_escape_htmltag($langs->trans('SafraVehicleAdd')) . '" data-title-edit="' . dol_escape_htmltag($langs->trans('SafraVehicleEdit')) . '">' . $langs->trans('SafraVehicleAdd') . '</span><button type="button" class="button safra-btn-ghost safra-icon-btn js-close-modal"><span class="fas fa-times"></span></button></div>';
print '<div class="safra-modal-body"><table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('SafraVehicleLabel') . '</td><td><input type="hidden" name="vehicle_rowid" value="">' . $form->selectarray('vehicle_id', array(0 => '') + $vehicleOptions, 0, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300', 1) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityPlannedHours') . '</td><td><input class="flat" type="number" step="0.01" min="0" name="vehicle_planned_hours" value=""></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDoneHours') . '</td><td><input class="flat" type="number" step="0.01" min="0" name="vehicle_done_hours" value=""></td></tr>';
print '<tr><td>' . $langs->trans('Note') . '</td><td><textarea class="flat" name="vehicle_note" rows="3" style="width:100%"></textarea></td></tr>';
print '</table></div><div class="safra-modal-actions"><button type="button" class="button safra-btn-ghost js-close-modal">' . $langs->trans('Cancel') . '</button><button type="submit" class="button safra-btn-primary" onclick="document.getElementById(\'safra-form-action\').value=\'save_vehicle_line\';">' . $langs->trans('Save') . '</button></div>';
print '</div></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'implements' ? 'safra-tab-pane-active' : '') . '" data-tab="implements">';
print load_fiche_titre($langs->trans('SafraImplementsLabel'), '', '');
print '<div class="safra-actions safra-primary-actions" style="margin-bottom:10px"><button class="button safra-btn-primary js-open-modal" type="button" data-modal="implement" data-mode="new">' . $langs->trans('Add') . '</button></div>';
print '<div class="div-table-responsive safra-table-wrap"><table class="noborder centpercent liste" id="implement-lines"><thead><tr class="liste_titre"><th>' . $langs->trans('SafraImplementsLabel') . '</th><th class="right">' . $langs->trans('SafraActivityPlannedHours') . '</th><th class="right">' . $langs->trans('SafraActivityDoneHours') . '</th><th></th></tr></thead><tbody>';
if (empty($implementLinks)) {
    print '<tr class="oddeven"><td colspan="4" class="safra-empty">' . $langs->trans('NoRecordFound') . '</td></tr>';
} else {
    foreach ($implementLinks as $row) {
        $rowid = isset($row->rowid) ? (int) $row->rowid : 0;
        $editAttrs = ''
            . safra_activity_data_attr('modal', 'implement')
            . safra_activity_data_attr('mode', 'edit')
            . safra_activity_data_attr('rowid', $rowid)
            . safra_activity_data_attr('implement-id', isset($row->fk_implement) ? (int) $row->fk_implement : 0)
            . safra_activity_data_attr('planned-hours', isset($row->planned_hours) ? price2num($row->planned_hours, 'MT') : '')
            . safra_activity_data_attr('done-hours', isset($row->done_hours) ? price2num($row->done_hours, 'MT') : '')
            . safra_activity_data_attr('note', isset($row->note) ? $row->note : '');
        print '<tr class="oddeven">';
        print '<td>' . dol_escape_htmltag(safra_activity_resource_label($row, 'fk_implement', $implementOptions)) . '</td>';
        print '<td class="right">' . price(isset($row->planned_hours) ? $row->planned_hours : 0, 0, '', 1, 2) . '</td>';
        print '<td class="right">' . price(isset($row->done_hours) ? $row->done_hours : 0, 0, '', 1, 2) . '</td>';
        print '<td class="safra-line-actions"><button type="button" class="button safra-btn-secondary safra-icon-btn js-open-modal" title="' . dol_escape_htmltag($langs->trans('Edit')) . '"' . $editAttrs . '><span class="fas fa-pencil-alt"></span></button> ';
        print '<button type="button" class="button safra-btn-danger safra-icon-btn js-delete-line" title="' . dol_escape_htmltag($langs->trans('Delete')) . '" data-action="delete_implement_line" data-target="implement_rowid" data-id="' . $rowid . '"><span class="fas fa-trash"></span></button></td>';
        print '</tr>';
    }
}
print '</tbody></table></div>';
print '<div class="safra-modal" id="safra-modal-implement" aria-hidden="true"><div class="safra-modal-panel">';
print '<div class="safra-modal-title"><span data-title-new="' . dol_escape_htmltag($langs->trans('SafraImplementAdd')) . '" data-title-edit="' . dol_escape_htmltag($langs->trans('SafraImplementEdit')) . '">' . $langs->trans('SafraImplementAdd') . '</span><button type="button" class="button safra-btn-ghost safra-icon-btn js-close-modal"><span class="fas fa-times"></span></button></div>';
print '<div class="safra-modal-body"><table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('SafraImplementsLabel') . '</td><td><input type="hidden" name="implement_rowid" value="">' . $form->selectarray('implement_id', array(0 => '') + $implementOptions, 0, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300', 1) . '</td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityPlannedHours') . '</td><td><input class="flat" type="number" step="0.01" min="0" name="implement_planned_hours" value=""></td></tr>';
print '<tr><td>' . $langs->trans('SafraActivityDoneHours') . '</td><td><input class="flat" type="number" step="0.01" min="0" name="implement_done_hours" value=""></td></tr>';
print '<tr><td>' . $langs->trans('Note') . '</td><td><textarea class="flat" name="implement_note" rows="3" style="width:100%"></textarea></td></tr>';
print '</table></div><div class="safra-modal-actions"><button type="button" class="button safra-btn-ghost js-close-modal">' . $langs->trans('Cancel') . '</button><button type="submit" class="button safra-btn-primary" onclick="document.getElementById(\'safra-form-action\').value=\'save_implement_line\';">' . $langs->trans('Save') . '</button></div>';
print '</div></div>';
print '</div>';

print '<div class="safra-tab-pane ' . ($activeTab === 'card' ? 'safra-tab-pane-active' : '') . '" data-tab="card-notes">';
print load_fiche_titre($langs->trans('Note'), '', '');
print '<textarea class="flat" name="note_public" rows="4" style="width:100%">' . dol_escape_htmltag($activity->note_public) . '</textarea>';

print '<div class="safra-actions" style="margin-top:12px">';
print '<button class="button button-save safra-btn-primary" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_main\';">' . $langs->trans('Save') . '</button>';
if ($canFinishFromCard) {
    print '<button class="button safra-btn-secondary" type="submit" onclick="document.getElementById(\'safra-form-action\').value=\'save_complete\';return confirm(\'' . dol_escape_js($langs->transnoentities('SafraActivityFinishConfirm')) . '\');">' . $langs->trans('SafraActivityFinishNow') . '</button>';
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
    if ($permissiontowrite) {
        print '<a class="button safra-btn-secondary" href="' . dol_buildpath('/safra/activity/activity_duplicate.php', 1) . '?id=' . ((int) $activity->id) . '">' . $langs->trans('SafraActivityDuplicate') . '</a>';
    }
    if (!$activity->isInProgress() && !$activity->isCompleted() && !$activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="id" value="' . ((int) $activity->id) . '"><input type="hidden" name="action" value="start"><button class="button safra-btn-primary" type="submit">' . $langs->trans('SafraActivityStart') . '</button></form>';
    }
    if ($activity->isCompleted() || $activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="id" value="' . ((int) $activity->id) . '"><input type="hidden" name="action" value="reopen"><button class="button safra-btn-secondary" type="submit">' . $langs->trans('SafraActivityReopen') . '</button></form>';
    }
    if (!$activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="id" value="' . ((int) $activity->id) . '"><input type="hidden" name="action" value="cancel"><button class="button safra-btn-secondary" type="submit">' . $langs->trans('SafraActivityCancel') . '</button></form>';
    }
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" onsubmit="return confirm(\'' . dol_escape_js($langs->transnoentities('ConfirmDelete')) . '\');"><input type="hidden" name="token" value="' . newToken() . '"><input type="hidden" name="id" value="' . ((int) $activity->id) . '"><input type="hidden" name="action" value="delete"><button class="button safra-btn-danger" type="submit">' . $langs->trans('SafraActivityDelete') . '</button></form>';
    print '</div>';
}

print '<script>
(function(){
function n(v){v=(v||"").toString().replace(",", ".");var x=parseFloat(v);return isNaN(x)?0:x}
var form=document.getElementById("safra-activity-form");
function field(name){return document.querySelector("[name=\\""+name+"\\"]");}
function setField(name,value){var el=field(name);if(!el)return;el.value=(value===undefined||value===null)?"":String(value);if(window.jQuery&&el.tagName==="SELECT")window.jQuery(el).trigger("change");else el.dispatchEvent(new Event("change",{bubbles:true}));}
function modalBaseArea(){var table=document.getElementById("activity-lines");return table?table.getAttribute("data-area-base"):"";}
function recalcModalLine(){var area=field("line_area_done"),dose=field("line_dose_done"),qty=field("line_qty_done");if(area&&dose&&qty&&n(area.value)>0&&n(dose.value)>0){qty.value=(n(area.value)*n(dose.value)).toFixed(4);setField("line_area_planned",area.value);setField("line_dose_planned",dose.value);setField("line_qty_planned",qty.value);}}
function prepareModalControls(modal){if(window.jQuery&&window.jQuery.fn&&window.jQuery.fn.select2){window.jQuery(modal).find("select").each(function(){var select=window.jQuery(this);try{if(select.data("select2"))select.select2("destroy");}catch(e){}try{select.select2({dropdownParent:window.jQuery(modal),width:"100%"});}catch(e){}});}setTimeout(function(){var first=modal.querySelector("select,input:not([type=hidden]),textarea");if(first&&typeof first.focus==="function")first.focus();},0);}
function openModal(button){var modalName=button.getAttribute("data-modal");var modal=document.getElementById("safra-modal-"+modalName);if(!modal)return;var title=modal.querySelector(".safra-modal-title span");if(title){title.textContent=(button.getAttribute("data-mode")==="edit")?title.getAttribute("data-title-edit"):title.getAttribute("data-title-new");}
    if(modalName==="input"){var base=modalBaseArea();setField("line_id",button.getAttribute("data-line-id")||"");setField("line_product_id",button.getAttribute("data-product-id")||"0");setField("line_warehouse_id",button.getAttribute("data-warehouse-id")||"0");setField("line_movement_type",button.getAttribute("data-movement-type")||"consume");setField("line_area_planned",button.getAttribute("data-area-planned")||base);setField("line_area_done",button.getAttribute("data-area-done")||base);setField("line_dose_planned",button.getAttribute("data-dose-planned")||"");setField("line_dose_done",button.getAttribute("data-dose-done")||"");setField("line_qty_planned",button.getAttribute("data-qty-planned")||"");setField("line_qty_done",button.getAttribute("data-qty-done")||"");setField("line_dose_unit",button.getAttribute("data-dose-unit")||"kg/ha");setField("line_unit_cost",button.getAttribute("data-unit-cost")||"0");setField("line_note",button.getAttribute("data-note")||"");recalcModalLine();}
    if(modalName==="team"){setField("team_rowid",button.getAttribute("data-rowid")||"");setField("team_user_id",button.getAttribute("data-user-id")||"0");setField("team_role",button.getAttribute("data-role")||"");setField("team_planned_hours",button.getAttribute("data-planned-hours")||"");setField("team_done_hours",button.getAttribute("data-done-hours")||"");setField("team_note",button.getAttribute("data-note")||"");}
    if(modalName==="vehicle"){setField("vehicle_rowid",button.getAttribute("data-rowid")||"");setField("vehicle_id",button.getAttribute("data-vehicle-id")||"0");setField("vehicle_planned_hours",button.getAttribute("data-planned-hours")||"");setField("vehicle_done_hours",button.getAttribute("data-done-hours")||"");setField("vehicle_note",button.getAttribute("data-note")||"");}
    if(modalName==="implement"){setField("implement_rowid",button.getAttribute("data-rowid")||"");setField("implement_id",button.getAttribute("data-implement-id")||"0");setField("implement_planned_hours",button.getAttribute("data-planned-hours")||"");setField("implement_done_hours",button.getAttribute("data-done-hours")||"");setField("implement_note",button.getAttribute("data-note")||"");}
    modal.classList.add("safra-modal-open");modal.setAttribute("aria-hidden","false");document.body.classList.add("safra-modal-is-open");prepareModalControls(modal);
}
function closeModals(){document.querySelectorAll(".safra-modal").forEach(function(modal){if(window.jQuery&&window.jQuery.fn&&window.jQuery.fn.select2){window.jQuery(modal).find("select").each(function(){try{var select=window.jQuery(this);if(select.data("select2"))select.select2("close");}catch(e){}});}modal.classList.remove("safra-modal-open");modal.setAttribute("aria-hidden","true");});document.body.classList.remove("safra-modal-is-open");}
document.querySelectorAll(".js-open-modal").forEach(function(button){button.addEventListener("click",function(){openModal(button);});});
document.querySelectorAll(".js-close-modal").forEach(function(button){button.addEventListener("click",closeModals);});
document.querySelectorAll(".safra-modal-panel").forEach(function(panel){panel.addEventListener("click",function(e){e.stopPropagation();});});
document.querySelectorAll(".safra-modal").forEach(function(modal){modal.addEventListener("click",function(e){if(e.target===modal)closeModals();});});
document.querySelectorAll(".js-delete-line").forEach(function(button){button.addEventListener("click",function(){if(!confirm("' . dol_escape_js($langs->transnoentities('ConfirmDelete')) . '"))return;setField(button.getAttribute("data-target"),button.getAttribute("data-id"));document.getElementById("safra-form-action").value=button.getAttribute("data-action");form.submit();});});
["line_area_done","line_dose_done"].forEach(function(name){var el=field(name);if(el)el.addEventListener("input",recalcModalLine);});
if(document.querySelector(".safra-tab-pane-active[data-tab=\\"card\\"]")){
    ["type","fk_fieldplot"].forEach(function(name){var el=document.querySelector("[name=\\""+name+"\\"]");if(el)el.required=true;});
}
function byId(primary,fallback){return document.getElementById(primary)||document.getElementById(fallback);}
var projectSelect=byId("safra-project-select","fk_project");
var contextBox=document.getElementById("safra-project-context");
var contextLoadedText="' . dol_escape_js($langs->transnoentities('SafraProjectContextLoaded')) . '";
function setAutoInput(el,value,force){if(!el||value===null||value===undefined||value==="")return;var val=String(value);if(force||!el.value||el.dataset.autoValue===el.value){el.value=val;el.dataset.autoValue=val;}}
function setAutoSelect(el,id,label,force){if(!el||!id)return;var val=String(id);var exists=false;Array.prototype.forEach.call(el.options,function(option){if(option.value===val)exists=true;});if(!exists){var option=document.createElement("option");option.value=val;option.textContent=label||("#"+val);el.appendChild(option);}if(force||!el.value||el.dataset.autoValue===el.value){el.value=val;el.dataset.autoValue=val;if(window.jQuery)window.jQuery(el).trigger("change");else el.dispatchEvent(new Event("change",{bubbles:true}));}}
function setLineAreas(area){if(area===null||area===undefined||area==="")return;var table=document.getElementById("activity-lines");if(table)table.setAttribute("data-area-base",area);if(!field("line_id")||!field("line_id").value){setField("line_area_planned",area);setField("line_area_done",area);recalcModalLine();}}
function applyProjectContext(data){if(!data||!data.success)return;var parts=[];if(data.talhao){setAutoSelect(byId("safra-fieldplot-select","fk_fieldplot"),data.talhao.id,data.talhao.display,true);setAutoInput(document.getElementById("safra-area-planned"),data.talhao.area,true);setLineAreas(data.talhao.area);parts.push(data.talhao.display);}if(data.cultura){setAutoInput(document.getElementById("safra-crop-name"),data.cultura.label||data.cultura.display,true);parts.push(data.cultura.display);}if(data.cultivar){setAutoInput(document.getElementById("safra-cultivar-name"),data.cultivar.label||data.cultivar.display,true);parts.push(data.cultivar.display);}if(contextBox&&parts.length)contextBox.textContent=contextLoadedText+": "+parts.join(" | ");}
function fetchProjectContext(){if(!projectSelect||!projectSelect.value)return;var url=projectSelect.getAttribute("data-context-url")||"";if(!url)return;fetch(url+"?id="+encodeURIComponent(projectSelect.value),{credentials:"same-origin"}).then(function(r){return r.json();}).then(applyProjectContext).catch(function(){});}
if(projectSelect){projectSelect.addEventListener("change",fetchProjectContext);if(window.jQuery)window.jQuery(projectSelect).on("select2:select select2:clear",fetchProjectContext);fetchProjectContext();}
function fmt(v,d){if(!isFinite(v))return "-";return v.toFixed(d||2).replace(".", ",");}
function calcMixture(){var area=n((document.getElementById("mix-area")||{}).value),rate=n((document.getElementById("mix-rate")||{}).value),tank=n((document.getElementById("mix-tank")||{}).value);var total=area*rate;var tanks=(tank>0&&total>0)?Math.ceil(total/tank):0;var areaPerTank=(rate>0&&tank>0)?Math.min(area,tank/rate):0;var totalBox=document.getElementById("mix-total-volume"),tankBox=document.getElementById("mix-tank-count"),areaBox=document.getElementById("mix-area-per-tank");if(totalBox)totalBox.textContent=total>0?fmt(total,2)+" L":"-";if(tankBox)tankBox.textContent=tanks>0?String(tanks):"-";if(areaBox)areaBox.textContent=areaPerTank>0?fmt(areaPerTank,4)+" ha":"-";document.querySelectorAll(".js-mixture-line").forEach(function(row){var qty=n(row.getAttribute("data-qty")),unit=row.getAttribute("data-unit")||"",cell=row.querySelector(".js-mixture-per-tank");if(cell)cell.textContent=(tanks>0&&qty>0)?fmt(qty/tanks,4)+" "+unit:"-";});}
["mix-area","mix-rate","mix-tank"].forEach(function(id){var el=document.getElementById(id);if(el)el.addEventListener("input",calcMixture);});
calcMixture();
})();
</script>';

llxFooter();
$db->close();
