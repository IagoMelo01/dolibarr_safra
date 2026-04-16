<?php
/*
 * Activity card for Safra module.
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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

$veiculoClassFile = DOL_DOCUMENT_ROOT . '/custom/frota/class/veiculo.class.php';
if (file_exists($veiculoClassFile)) {
    require_once $veiculoClassFile;
}

$implementoClassFile = DOL_DOCUMENT_ROOT . '/custom/frota/class/implemento.class.php';
if (file_exists($implementoClassFile)) {
    require_once $implementoClassFile;
}
dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/safra/class/FvActivityLine.class.php');
dol_include_once('/safra/class/talhao.class.php');
dol_include_once('/projet/class/task.class.php');

global $db, $langs, $user, $conf;

$langs->loadLangs(array('safra@safra', 'projects', 'stocks', 'companies', 'users'));

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

$form = new Form($db);
$formproject = new FormProjets($db);
$formproduct = new FormProduct($db);
$activityTypes = FvActivity::getTypeOptions($langs);

$activity = new FvActivity($db);
if ($id > 0) {
    $activity->fetch($id);
}

$permissiontoread = $user->rights->safra->SafraActivity->read ?? 0;
$permissiontowrite = $user->rights->safra->SafraActivity->write ?? 0;
$permissiontodelete = $user->rights->safra->SafraActivity->delete ?? 0;

if (!$permissiontoread) {
    accessforbidden();
}

$mutatingActions = array('save', 'start', 'complete', 'cancel', 'delete');
if (in_array($action, $mutatingActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        accessforbidden();
    }

    $token = GETPOST('token', 'alphanohtml');
    if (function_exists('checkToken') && !checkToken($token)) {
        accessforbidden('Invalid security token.');
    }
}

if (in_array($action, array('save', 'start', 'complete', 'cancel'), true) && !$permissiontowrite) {
    accessforbidden();
}

if ($action === 'delete' && !$permissiontodelete) {
    accessforbidden();
}

$areaPercentage = price2num(GETPOST('area_percentage', 'alpha'), 'MT');

// Helpers
function safra_load_options($db, $table, $labelField)
{
    global $conf;

    $options = array();
    $sql = 'SELECT rowid, ' . $labelField . ' as label FROM ' . MAIN_DB_PREFIX . $db->escape($table)
        . ' WHERE entity IN (0, ' . ((int) $conf->entity) . ') ORDER BY label';
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $options[$obj->rowid] = $obj->label;
        }
    }

    return $options;
}

function safra_table_has_column($db, $table, $column)
{
    static $cache = array();

    $table = trim((string) $table);
    $column = trim((string) $column);
    if ($table === '' || $column === '') {
        return false;
    }

    $cacheKey = $table . '::' . $column;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = 'SHOW COLUMNS FROM ' . MAIN_DB_PREFIX . $db->escape($table)
        . " LIKE '" . $db->escape($column) . "'";
    $resql = $db->query($sql);
    $exists = ($resql && ((int) $db->num_rows($resql) > 0));

    $cache[$cacheKey] = $exists;

    return $exists;
}

function safra_pick_label_column($db, $table, $candidates = array(), $fallback = 'rowid')
{
    foreach ($candidates as $column) {
        if (safra_table_has_column($db, $table, $column)) {
            return $column;
        }
    }

    return $fallback;
}

function safra_load_from_class($db, $className, $labelFields = array())
{
    $options = array();

    if (!class_exists($className)) {
        return $options;
    }

    $object = new $className($db);
    if (method_exists($object, 'fetchAll')) {
        $records = $object->fetchAll('', '', 0, 0, array('customsql' => '1=1'));
        if (is_array($records)) {
            foreach ($records as $record) {
                $pieces = array();
                foreach ($labelFields as $field) {
                    if (!empty($record->$field)) {
                        $pieces[] = $record->$field;
                    }
                }
                $label = implode(' - ', $pieces);
                if (!empty($label)) {
                    $options[$record->id] = $label;
                }
            }
        }
    }

    return $options;
}

function safra_project_talhao_option($db, $projectId)
{
    if (empty($projectId)) {
        return null;
    }

    $sql = 'SELECT fk_talhao as talhao_id FROM ' . MAIN_DB_PREFIX . 'projet_extrafields WHERE fk_object = ' . ((int) $projectId) . ' LIMIT 1';
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if (!empty($obj->talhao_id)) {
            $talhao = new Talhao($db);
            if ($talhao->fetch((int) $obj->talhao_id) > 0) {
                $label = trim(($talhao->ref ? $talhao->ref . ' - ' : '') . $talhao->label);
                return array($talhao->id => $label ?: $talhao->id);
            }
        }
    }

    return null;
}

$machines = safra_load_options($db, 'vehicule', 'CONCAT(immatriculation, " - ", label)');
$implements = $machines;

$machinesFromModule = safra_load_from_class($db, 'Veiculo', array('placa', 'label', 'ref'));
if (!empty($machinesFromModule)) {
    $machines = $machinesFromModule;
}

$implementsFromModule = safra_load_from_class($db, 'Implemento', array('label', 'ref'));
if (!empty($implementsFromModule)) {
    $implements = $implementsFromModule;
}


$projectIdForTalhao = $activity->fk_project ?: GETPOSTINT('fk_project');
$talhaoFromProject = safra_project_talhao_option($db, $projectIdForTalhao);
$talhaoPlaceholder = $langs->trans('SelectAProjectFirst');

$talhaoDetails = array();
$sqlTalhaoDetails = 'SELECT t.rowid, t.ref, t.label, t.area, m.label as municipio_label'
    . ' FROM ' . MAIN_DB_PREFIX . 'safra_talhao as t'
    . ' LEFT JOIN ' . MAIN_DB_PREFIX . 'safra_municipio as m ON m.rowid = t.municipio';
$resTalhao = $db->query($sqlTalhaoDetails);
if ($resTalhao) {
    while ($obj = $db->fetch_object($resTalhao)) {
        $talhaoDetails[$obj->rowid] = array(
            'ref' => $obj->ref,
            'label' => $obj->label,
            'area' => price2num($obj->area),
            'municipio' => $obj->municipio_label,
        );
    }
}

if ($talhaoFromProject && empty($activity->fk_fieldplot) && $activity->id > 0) {
    $activity->fk_fieldplot = key($talhaoFromProject);
}

if (!empty($talhaoDetails[$activity->fk_fieldplot]) && empty($activity->area_total)) {
    $activity->area_total = $talhaoDetails[$activity->fk_fieldplot]['area'];
}

if (empty($areaPercentage)) {
    $baseAreaForPercent = (!empty($talhaoDetails[$activity->fk_fieldplot]['area'])) ? price2num($talhaoDetails[$activity->fk_fieldplot]['area'], 'MT') : 0;
    if ($activity->area_total > 0 && $baseAreaForPercent > 0) {
        $areaPercentage = min(100, max(0, round(($activity->area_total / $baseAreaForPercent) * 100, 2)));
    } else {
        $areaPercentage = 100;
    }
}

$warehouseLabelColumn = safra_pick_label_column($db, 'entrepot', array('lieu', 'label', 'ref'), 'rowid');
$warehouses = safra_load_options($db, 'entrepot', $warehouseLabelColumn);

$userOptions = array();
$sqlUsers = 'SELECT rowid, lastname, firstname FROM ' . MAIN_DB_PREFIX . 'user WHERE statut = 1 AND entity IN (0, ' . ((int) $conf->entity) . ') ORDER BY lastname, firstname';
$resUsers = $db->query($sqlUsers);
if ($resUsers) {
    while ($obj = $db->fetch_object($resUsers)) {
        $fullname = trim(($obj->firstname ? $obj->firstname . ' ' : '') . $obj->lastname);
        $userOptions[$obj->rowid] = $fullname ?: $obj->rowid;
    }
}

$productOptions = array();
$productLabelColumn = safra_pick_label_column($db, 'product', array('label', 'lieu', 'description'), 'ref');
$productTypeFilter = '';
if (safra_table_has_column($db, 'product', 'fk_product_type')) {
    $productTypeFilter = ' AND p.fk_product_type = 0';
} elseif (safra_table_has_column($db, 'product', 'type')) {
    $productTypeFilter = ' AND p.type = 0';
}

$sqlProducts = 'SELECT p.rowid, p.ref, p.' . $db->escape($productLabelColumn) . ' as product_label'
    . ' FROM ' . MAIN_DB_PREFIX . 'product p'
    . ' WHERE p.entity IN (0, ' . ((int) $conf->entity) . ')'
    . $productTypeFilter
    . ' ORDER BY p.' . $db->escape($productLabelColumn) . ', p.ref';
$resProducts = $db->query($sqlProducts);
if ($resProducts) {
    while ($obj = $db->fetch_object($resProducts)) {
        $productRef = trim((string) $obj->ref);
        $productLabel = trim((string) $obj->product_label);

        $labelParts = array();
        if ($productRef !== '') {
            $labelParts[] = $productRef;
        }
        if ($productLabel !== '' && $productLabel !== $productRef) {
            $labelParts[] = $productLabel;
        }

        $displayLabel = implode(' - ', $labelParts);
        if ($displayLabel === '') {
            $displayLabel = '#' . ((int) $obj->rowid);
        }

        $productOptions[$obj->rowid] = $displayLabel;
    }
}

$unitOptions = array(
    'L/ha' => 'L/ha',
    'kg/ha' => 'kg/ha',
    'g/ha' => 'g/ha',
    'mL/ha' => 'mL/ha',
    'un/ha' => 'un/ha',
);

$movementTypes = array(
    'consume' => $langs->trans('SafraLineMovementConsume'),
    'return' => $langs->trans('SafraLineMovementReturn'),
    'transfer' => $langs->trans('SafraLineMovementTransfer'),
);

$errors = array();

if ($action === 'start' && $activity->id) {
    $result = $activity->start($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityStart'), null, 'mesgs');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
        exit;
    } else {
        $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
    }
}

if ($action === 'complete' && $activity->id) {
    $result = $activity->complete($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityComplete'), null, 'mesgs');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
        exit;
    } else {
        $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
    }
}

if ($action === 'cancel' && $activity->id) {
    $result = $activity->cancel($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityCanceled'), null, 'mesgs');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
        exit;
    }

    $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
}

if ($action === 'delete' && $activity->id) {
    $result = $activity->delete($user);
    if ($result > 0) {
        setEventMessages($langs->trans('SafraActivityDeleted'), null, 'mesgs');
        header('Location: ' . dol_buildpath('/safra/safraindex.php', 1));
        exit;
    }

    $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
}

if ($action === 'save') {
    $isNew = empty($activity->id);
    $activity->label = GETPOST('label', 'alphanohtml');
    $activity->type = GETPOST('type', 'alphanohtml');
    $activity->fk_project = GETPOSTINT('fk_project');
    $activity->fk_fieldplot = GETPOSTINT('fk_fieldplot');
    $activity->area_total = price2num(GETPOST('area_total', 'alphanohtml'), 'MT');
    $activity->note_public = GETPOST('note_public', 'restricthtml');

    if (empty($activity->label)) {
        $errors[] = $langs->trans('ErrorFieldRequired', $langs->trans('Label'));
    }

    if (!$errors) {
        $db->begin();

        $result = $isNew ? $activity->create($user) : $activity->update($user);

        if (!$errors && $result > 0) {
            // Replace relations
            $machinesSelected = GETPOST('machine_ids', 'array');
            $implementsSelected = GETPOST('implement_ids', 'array');
            $usersSelected = GETPOST('user_ids', 'array');
            $relMachine = $activity->setMachines($machinesSelected ?: array());
            $relImplement = $activity->setImplements($implementsSelected ?: array());
            $relUsers = $activity->setUsers($usersSelected ?: array());

            // Replace lines
            $deleteRes = FvActivityLine::deleteForActivity($db, $activity->id);
            if ($deleteRes < 0) {
                $errors[] = $langs->trans('ErrorRecordNotSaved');
            }
            $lineProducts = GETPOST('product_id', 'array');
            $lineAreas = GETPOST('line_area', 'array');
            $lineDoses = GETPOST('line_dose', 'array');
            $lineUnits = GETPOST('line_unit', 'array');
            $lineTotals = GETPOST('line_total', 'array');
            $lineMovements = GETPOST('line_movement', 'array');
            $lineWarehouses = GETPOST('line_warehouse', 'array');
            if (!$errors) {
                $activity->lines = array();
                foreach ($lineProducts as $idx => $productId) {
                    $productId = (int) $productId;
                    if ($productId <= 0) {
                        continue;
                    }
                    $line = new FvActivityLine($db);
                    $line->fk_activity = $activity->id;
                    $line->fk_product = $productId;
                    $line->area_applied = price2num($lineAreas[$idx] ?? 0, 'MT');
                    $line->dose = price2num($lineDoses[$idx] ?? 0, 'MT');
                    $line->dose_unit = $lineUnits[$idx] ?? '';
                    $line->total = price2num(($lineTotals[$idx] ?? 0) ?: $line->dose * $line->area_applied, 'MT');
                    $line->movement_type = $lineMovements[$idx] ?? 'consume';
                    $line->fk_warehouse = (int) ($lineWarehouses[$idx] ?? 0);
                    $lineResult = $line->create($user);
                    if ($lineResult < 0) {
                        $errors[] = $line->error ?: $langs->trans('ErrorRecordNotSaved');
                        break;
                    }

                    $activity->lines[] = $line;
                }
            }

            if ($relMachine < 0 || $relImplement < 0 || $relUsers < 0) {
                $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
            }

            if (!$errors) {
                $db->commit();
                setEventMessages($langs->trans('SafraActivitySaved'), null, 'mesgs');
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
                exit;
            } else {
                $db->rollback();
            }
        } else {
            $db->rollback();
            if (!$errors) {
                $errors[] = $activity->error ?: $langs->trans('ErrorUnknown');
            }
        }
    }
}

$selectedMachines = $activity->id ? $activity->fetchMachines() : array();
$selectedImplements = $activity->id ? $activity->fetchImplements() : array();
$selectedUsers = $activity->id ? $activity->fetchUsers() : array();

llxHeader('', $langs->trans('SafraActivity'), '', '', '', array(), array('/safra/css/safra.css.php'));

print load_fiche_titre($langs->trans('SafraActivity'));

if ($errors) {
    setEventMessages(null, $errors, 'errors');
}

$statusCode = FvActivity::normalizeStatus((int) $activity->status);
$statusLabel = FvActivity::getStatusLabel($statusCode, $langs);
$statusClassMap = array(
    FvActivity::STATUS_DRAFT => 'sf-status-draft',
    FvActivity::STATUS_IN_PROGRESS => 'sf-status-progress',
    FvActivity::STATUS_COMPLETED => 'sf-status-completed',
    FvActivity::STATUS_CANCELED => 'sf-status-canceled',
);
$statusCss = isset($statusClassMap[$statusCode]) ? $statusClassMap[$statusCode] : 'sf-status-draft';
$selectedType = FvActivity::normalizeType($activity->type);
$defaultLineArea = ($activity->area_total > 0) ? price2num($activity->area_total) : 0;

print <<<'HTML'
<style>
.sf-page {
    --sf-green-950: #0b2f24;
    --sf-green-900: #114235;
    --sf-green-700: #1f7a5a;
    --sf-green-600: #2e8f6d;
    --sf-green-500: #3ea97d;
    --sf-green-100: #e8f6ef;
    --sf-green-050: #f4fbf7;
    --sf-text: #183329;
    --sf-muted: #4f6f62;
    color: var(--sf-text);
}
.sf-shell {
    display: grid;
    gap: 16px;
}
.sf-card {
    background: #fff;
    border: 1px solid #d5eadf;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 14px 34px rgba(17, 66, 53, 0.08);
}
.sf-card-header {
    padding: 18px 20px;
    background: linear-gradient(140deg, var(--sf-green-950), var(--sf-green-900), var(--sf-green-700));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.sf-card-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
}
.sf-card-subtitle {
    margin: 2px 0 0;
    font-size: 0.92rem;
    opacity: 0.88;
}
.sf-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 0.85rem;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.28);
    color: #fff;
}
.sf-status:before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}
.sf-status-draft { color: #f3f4f6; background: rgba(17, 24, 39, 0.32); }
.sf-status-progress { color: #d1fae5; background: rgba(20, 184, 166, 0.28); }
.sf-status-completed { color: #dcfce7; background: rgba(34, 197, 94, 0.28); }
.sf-status-canceled { color: #fee2e2; background: rgba(239, 68, 68, 0.28); }
.sf-card-body {
    padding: 18px;
    background: linear-gradient(180deg, #ffffff, var(--sf-green-050));
}
.sf-grid {
    display: grid;
    gap: 14px;
}
.sf-grid-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.sf-grid-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.sf-box {
    background: #fff;
    border: 1px solid #d8ece1;
    border-radius: 12px;
    padding: 14px;
}
.sf-box-title {
    margin: 0 0 12px;
    font-size: 0.96rem;
    color: var(--sf-green-900);
    font-weight: 700;
}
.sf-field label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.85rem;
    color: var(--sf-muted);
    font-weight: 600;
}
.sf-input,
.sf-page .form-control,
.sf-page .form-select,
.sf-page select {
    width: 100%;
    border: 1px solid #c9dfd3;
    border-radius: 10px;
    padding: 9px 10px;
    background: #fff;
}
.sf-page .form-control:focus,
.sf-page select:focus {
    border-color: var(--sf-green-500);
    box-shadow: 0 0 0 0.2rem rgba(62, 169, 125, 0.18);
}
.sf-page .select2-container {
    width: 100% !important;
    max-width: 100%;
}
.sf-page .select2-container .select2-selection--single,
.sf-page .select2-container .select2-selection--multiple {
    min-height: 40px;
    border: 1px solid #c9dfd3;
    border-radius: 10px;
}
.sf-page .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 38px;
    color: var(--sf-text);
    padding-left: 10px;
}
.sf-page .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px;
    right: 8px;
}
.sf-page .select2-container--default .select2-selection--multiple {
    padding: 4px 6px;
}
.sf-page .select2-container--default.select2-container--focus .select2-selection--single,
.sf-page .select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: var(--sf-green-500);
    box-shadow: 0 0 0 0.2rem rgba(62, 169, 125, 0.18);
}
.sf-hint {
    margin-top: 6px;
    font-size: 0.78rem;
    color: #5a7c6e;
}
.sf-info {
    min-height: 42px;
    border-radius: 10px;
    border: 1px dashed #b9d8c8;
    background: var(--sf-green-100);
    color: var(--sf-green-900);
    display: flex;
    align-items: center;
    padding: 8px 10px;
    font-size: 0.87rem;
    font-weight: 600;
}
.sf-products-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.sf-products-head p {
    margin: 0;
    color: var(--sf-muted);
    font-size: 0.85rem;
}
.sf-table-wrap {
    border: 1px solid #d8ece1;
    border-radius: 12px;
    overflow: auto;
    background: #fff;
}
.sf-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 980px;
}
.sf-table thead th {
    background: var(--sf-green-900);
    color: #f0fdf4;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 10px;
    border-bottom: 1px solid #0f3a2d;
}
.sf-table td {
    border-bottom: 1px solid #e2f0e8;
    padding: 8px;
    vertical-align: middle;
}
.sf-table td:first-child {
    min-width: 240px;
}
.sf-table td:nth-child(7) {
    min-width: 190px;
}
.sf-table td:last-child {
    width: 48px;
    text-align: center;
}
.sf-table td > .sf-input,
.sf-table td > select,
.sf-table td .select2-container {
    width: 100% !important;
}
.sf-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
    flex-wrap: wrap;
}
.sf-btn {
    border: 1px solid transparent;
    border-radius: 10px;
    padding: 8px 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
}
.sf-btn-save {
    background: linear-gradient(140deg, var(--sf-green-700), var(--sf-green-500));
    color: #fff;
}
.sf-btn-add {
    background: #ecfdf5;
    border-color: #b7dcc9;
    color: var(--sf-green-900);
}
.sf-btn-inline {
    border: 1px solid #c6dece;
    background: #fff;
    color: var(--sf-green-900);
    border-radius: 10px;
    padding: 7px 12px;
    font-weight: 700;
}
.sf-btn-danger {
    border-color: #f5b8b8;
    color: #7f1d1d;
    background: #fff1f2;
}
.sf-remove {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #f0c1c1;
    background: #fff4f4;
    color: #9f1239;
    cursor: pointer;
}
.sf-toolbar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}
.sf-task-link {
    color: #d1fae5;
    text-decoration: none;
    border-bottom: 1px dashed rgba(209, 250, 229, 0.7);
    font-size: 0.85rem;
    font-weight: 600;
}
@media (max-width: 1100px) {
    .sf-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 760px) {
    .sf-grid-3,
    .sf-grid-2 { grid-template-columns: 1fr; }
    .sf-card-header,
    .sf-actions { align-items: flex-start; }
}
</style>
HTML;

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" class="sf-page">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';
if ($activity->id) {
    print '<input type="hidden" name="id" value="' . $activity->id . '">';
}

print '<div class="sf-shell">';
print '<div class="sf-card">';
print '<div class="sf-card-header">';
print '<div>';
print '<h2 class="sf-card-title">' . dol_escape_htmltag($activity->label ?: $langs->trans('SafraActivity')) . '</h2>';
print '<p class="sf-card-subtitle">' . $langs->trans('SafraActivity') . '</p>';
if ($activity->fk_task > 0) {
    $taskUrl = dol_buildpath('/projet/tasks/task.php?id=' . (int) $activity->fk_task, 1);
    print '<a class="sf-task-link" href="' . $taskUrl . '">' . $langs->trans('Task') . ' #' . ((int) $activity->fk_task) . '</a>';
}
print '</div>';
print '<span class="sf-status ' . $statusCss . '">' . dol_escape_htmltag($statusLabel) . '</span>';
print '</div>';

print '<div class="sf-card-body">';

print '<div class="sf-grid sf-grid-3">';
print '<div class="sf-field">';
print '<label>' . $langs->trans('Label') . '</label>';
print '<input class="sf-input" type="text" name="label" value="' . dol_escape_htmltag($activity->label) . '" required>';
print '</div>';
print '<div class="sf-field">';
print '<label>' . $langs->trans('Type') . '</label>';
print $form->selectarray('type', $activityTypes, $selectedType, 1, 0, 0, '', 0, 0, 0, '', 'sf-input');
print '</div>';
print '<div class="sf-field">';
print '<label>' . $langs->trans('Project') . '</label>';
print $formproject->select_projects(-1, $activity->fk_project, 'fk_project', 0, 0, 1, 0, 0, 0, '', '', 1, 0, 1);
print '<div class="sf-hint">' . $langs->trans('SelectProjectToAutoFillFieldPlot') . '</div>';
print '</div>';
print '</div>';

print '<div class="sf-grid sf-grid-2" style="margin-top:14px;">';
print '<div class="sf-box">';
print '<h3 class="sf-box-title">' . $langs->trans('FieldPlot') . '</h3>';
print '<input type="hidden" name="fk_fieldplot" value="' . ((int) $activity->fk_fieldplot) . '">';
$talhaoLabel = $talhaoPlaceholder;
if (!empty($activity->fk_fieldplot) && !empty($talhaoDetails[$activity->fk_fieldplot])) {
    $talhaoDataItem = $talhaoDetails[$activity->fk_fieldplot];
    $labelBits = array();
    if (!empty($talhaoDataItem['ref'])) {
        $labelBits[] = $talhaoDataItem['ref'];
    }
    if (!empty($talhaoDataItem['label'])) {
        $labelBits[] = $talhaoDataItem['label'];
    }
    if (!empty($talhaoDataItem['area'])) {
        $labelBits[] = price2num($talhaoDataItem['area']) . ' ha';
    }
    if (!empty($talhaoDataItem['municipio'])) {
        $labelBits[] = $talhaoDataItem['municipio'];
    }
    if (!empty($labelBits)) {
        $talhaoLabel = implode(' | ', $labelBits);
    }
}
print '<div id="talhao-area-info" class="sf-info">' . dol_escape_htmltag($talhaoLabel) . '</div>';
print '<div class="sf-hint">' . $langs->trans('AutoFilledFromFieldPlot') . '</div>';
print '</div>';

print '<div class="sf-box">';
print '<h3 class="sf-box-title">' . $langs->trans('Area') . '</h3>';
print '<input type="hidden" id="area-base" value="' . dol_escape_htmltag(price2num($talhaoDetails[$activity->fk_fieldplot]['area'] ?? 0)) . '">';
print '<div class="sf-field">';
print '<label>' . $langs->trans('Area') . ' (%)</label>';
print '<input class="sf-input" type="number" min="0" max="100" step="0.01" id="area-percentage" name="area_percentage" value="' . dol_escape_htmltag(price2num($areaPercentage)) . '">';
print '</div>';
print '<div class="sf-field" style="margin-top:8px;">';
print '<label>' . $langs->trans('Area') . ' (ha)</label>';
print '<input class="sf-input" type="number" min="0" step="0.0001" id="area-total" name="area_total" value="' . dol_escape_htmltag(price2num($activity->area_total)) . '" readonly>';
print '</div>';
print '</div>';
print '</div>';

print '<div class="sf-box" style="margin-top:14px;">';
print '<h3 class="sf-box-title">' . $langs->trans('SafraAplicacaoResources') . '</h3>';
print '<div class="sf-grid sf-grid-3">';
print '<div class="sf-field">';
print '<label>' . $langs->trans('SafraMachineLabel') . '</label>';
print $form->multiselectarray('machine_ids', $machines, $selectedMachines, '', 0, '', 1);
print '</div>';
print '<div class="sf-field">';
print '<label>' . $langs->trans('SafraImplementsLabel') . '</label>';
print $form->multiselectarray('implement_ids', $implements, $selectedImplements, '', 0, '', 1);
print '</div>';
print '<div class="sf-field">';
print '<label>' . $langs->trans('SafraEmployeesLabel') . '</label>';
print $form->multiselectarray('user_ids', $userOptions, $selectedUsers, '', 0, '', 1);
print '</div>';
print '</div>';
print '</div>';

print '<div class="sf-box" style="margin-top:14px;">';
print '<h3 class="sf-box-title">' . $langs->trans('Note') . '</h3>';
print '<textarea class="sf-input" name="note_public" id="note_public" rows="4" placeholder="' . dol_escape_htmltag($langs->trans('Note')) . '">' . dol_escape_htmltag($activity->note_public) . '</textarea>';
print '</div>';

print '</div>'; // sf-card-body
print '</div>'; // sf-card

print '<div class="sf-card">';
print '<div class="sf-card-body">';
print '<div class="sf-products-head">';
print '<div>';
print '<h3 class="sf-box-title" style="margin-bottom:4px;">' . $langs->trans('Products') . '</h3>';
print '<p>' . $langs->trans('SafraLineMovement') . '</p>';
print '</div>';
print '</div>';

print '<div class="sf-table-wrap">';
print '<table class="sf-table" id="products-table">';
print '<thead><tr>';
print '<th>' . $langs->trans('Product') . '</th>';
print '<th>' . $langs->trans('Area') . ' (ha)</th>';
print '<th>' . $langs->trans('Dose') . '</th>';
print '<th>' . $langs->trans('Unit') . '</th>';
print '<th>' . $langs->trans('Total') . '</th>';
print '<th>' . $langs->trans('Movement') . '</th>';
print '<th>' . $langs->trans('Warehouse') . '</th>';
print '<th></th>';
print '</tr></thead><tbody>';

if (!empty($activity->lines)) {
    foreach ($activity->lines as $line) {
        $lineUnitOptions = $unitOptions;
        if (!empty($line->dose_unit) && !isset($lineUnitOptions[$line->dose_unit])) {
            $lineUnitOptions[$line->dose_unit] = $line->dose_unit;
        }
        print '<tr>';
        print '<td>' . $form->selectarray('product_id[]', $productOptions, $line->fk_product, 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-select-product', 0) . '</td>';
        print '<td><input type="number" name="line_area[]" class="sf-input sf-line-area" value="' . dol_escape_htmltag(price2num($line->area_applied)) . '" step="0.0001" min="0" readonly></td>';
        print '<td><input type="number" name="line_dose[]" class="sf-input sf-line-dose" value="' . dol_escape_htmltag(price2num($line->dose)) . '" step="0.0001" min="0"></td>';
        print '<td>' . $form->selectarray('line_unit[]', $lineUnitOptions, $line->dose_unit, 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-line-unit', 0) . '</td>';
        print '<td><input type="number" name="line_total[]" class="sf-input sf-line-total" value="' . dol_escape_htmltag(price2num($line->total)) . '" step="0.0001" min="0" readonly></td>';
        print '<td>' . $form->selectarray('line_movement[]', $movementTypes, $line->movement_type, 1, 0, 0, '', 0, 0, 0, '', 'sf-input', 0) . '</td>';
        print '<td>' . $form->selectarray('line_warehouse[]', $warehouses, $line->fk_warehouse, 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-select-warehouse', 0) . '</td>';
        print '<td><button type="button" class="sf-remove sf-remove-line" aria-label="' . dol_escape_htmltag($langs->trans('Delete')) . '">x</button></td>';
        print '</tr>';
    }
} else {
    print '<tr>';
    print '<td>' . $form->selectarray('product_id[]', $productOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-select-product', 0) . '</td>';
    print '<td><input type="number" name="line_area[]" class="sf-input sf-line-area" value="' . dol_escape_htmltag($defaultLineArea) . '" step="0.0001" min="0" readonly></td>';
    print '<td><input type="number" name="line_dose[]" class="sf-input sf-line-dose" value="0" step="0.0001" min="0"></td>';
    print '<td>' . $form->selectarray('line_unit[]', $unitOptions, 'L/ha', 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-line-unit', 0) . '</td>';
    print '<td><input type="number" name="line_total[]" class="sf-input sf-line-total" value="0" step="0.0001" min="0" readonly></td>';
    print '<td>' . $form->selectarray('line_movement[]', $movementTypes, 'consume', 1, 0, 0, '', 0, 0, 0, '', 'sf-input', 0) . '</td>';
    print '<td>' . $form->selectarray('line_warehouse[]', $warehouses, '', 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-select-warehouse', 0) . '</td>';
    print '<td><button type="button" class="sf-remove sf-remove-line" aria-label="' . dol_escape_htmltag($langs->trans('Delete')) . '">x</button></td>';
    print '</tr>';
}

print '</tbody></table>';
print '<template id="product-line-template">';
print '<tr>';
print '<td>' . $form->selectarray('product_id[]', $productOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-select-product', 0) . '</td>';
print '<td><input type="number" name="line_area[]" class="sf-input sf-line-area" value="0" step="0.0001" min="0" readonly></td>';
print '<td><input type="number" name="line_dose[]" class="sf-input sf-line-dose" value="0" step="0.0001" min="0"></td>';
print '<td>' . $form->selectarray('line_unit[]', $unitOptions, 'L/ha', 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-line-unit', 0) . '</td>';
print '<td><input type="number" name="line_total[]" class="sf-input sf-line-total" value="0" step="0.0001" min="0" readonly></td>';
print '<td>' . $form->selectarray('line_movement[]', $movementTypes, 'consume', 1, 0, 0, '', 0, 0, 0, '', 'sf-input', 0) . '</td>';
print '<td>' . $form->selectarray('line_warehouse[]', $warehouses, '', 1, 0, 0, '', 0, 0, 0, '', 'sf-input sf-select-warehouse', 0) . '</td>';
print '<td><button type="button" class="sf-remove sf-remove-line" aria-label="' . dol_escape_htmltag($langs->trans('Delete')) . '">x</button></td>';
print '</tr>';
print '</template>';
print '</div>';

print '<div class="sf-actions">';
print '<button type="button" class="sf-btn sf-btn-add" id="add-line">+ ' . $langs->trans('Add') . '</button>';
print '<button type="submit" class="sf-btn sf-btn-save">' . $langs->trans('Save') . '</button>';
print '</div>';

print '</div>'; // sf-card-body
print '</div>'; // sf-card
print '</div>'; // sf-shell
print '</form>';

if ($activity->id) {
    print '<div class="sf-toolbar">';

    if (!$activity->isCompleted() && !$activity->isCanceled() && !$activity->isInProgress()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="start">';
        print '<input type="hidden" name="id" value="' . $activity->id . '">';
        print '<button class="sf-btn-inline" type="submit">' . $langs->trans('SafraActivityStart') . '</button>';
        print '</form>';
    }

    if (!$activity->isCompleted() && !$activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="complete">';
        print '<input type="hidden" name="id" value="' . $activity->id . '">';
        print '<button class="sf-btn-inline" type="submit">' . $langs->trans('SafraActivityComplete') . '</button>';
        print '</form>';
    }

    if (!$activity->isCanceled() && !$activity->isCompleted()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="cancel">';
        print '<input type="hidden" name="id" value="' . $activity->id . '">';
        print '<button class="sf-btn-inline" type="submit">' . $langs->trans('SafraActivityCancel') . '</button>';
        print '</form>';
    }

    $confirmMessage = dol_escape_js($langs->transnoentities('ConfirmDelete'));
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" onsubmit="return confirm(\'' . $confirmMessage . '\');">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="delete">';
    print '<input type="hidden" name="id" value="' . $activity->id . '">';
    print '<button class="sf-btn-inline sf-btn-danger" type="submit">' . $langs->trans('SafraActivityDelete') . '</button>';
    print '</form>';

    print '</div>';
}

print '<script>';
?>
var talhaoData = <?php echo json_encode($talhaoDetails); ?>;
var talhaoPlaceholderText = '<?php echo dol_escape_js($talhaoPlaceholder); ?>';
var talhaoInfoEl = document.getElementById('talhao-area-info');
var areaBaseInput = document.getElementById('area-base');
var areaPctInput = document.getElementById('area-percentage');
var areaTotalInput = document.getElementById('area-total');
var sfSelectSeq = 0;

function sfToNumber(value) {
    if (value === null || value === undefined) return 0;
    var normalized = value.toString().replace(',', '.').trim();
    var parsed = parseFloat(normalized);
    return isNaN(parsed) ? 0 : parsed;
}

function sfRecalcLine(row) {
    if (!row) return;
    var areaInput = row.querySelector('.sf-line-area');
    var doseInput = row.querySelector('.sf-line-dose');
    var totalInput = row.querySelector('.sf-line-total');
    if (!areaInput || !doseInput || !totalInput) return;
    var area = sfToNumber(areaInput.value);
    var dose = sfToNumber(doseInput.value);
    totalInput.value = (area * dose).toFixed(4);
}

function sfNextSelectId(prefix) {
    sfSelectSeq += 1;
    return prefix + '_' + sfSelectSeq;
}

function sfEnsureUniqueSelectId(select) {
    if (!select) return;

    var base = (select.name || 'sf_select').replace(/\[\]/g, '').replace(/[^a-zA-Z0-9_]/g, '_');
    var currentId = select.id || '';

    if (!currentId) {
        select.id = sfNextSelectId(base);
        return;
    }

    var countSameId = 0;
    document.querySelectorAll('[id]').forEach(function (node) {
        if (node.id === currentId) {
            countSameId += 1;
        }
    });

    if (countSameId > 1) {
        select.id = sfNextSelectId(base);
    }
}

function sfEnhanceLineSelects(row) {
    var hasJquery = !!(window.jQuery && window.jQuery.fn);
    var canSelect2 = hasJquery && typeof window.jQuery.fn.select2 === 'function';
    var canCombobox = hasJquery && typeof window.jQuery.fn.combobox === 'function';

    if (!row || !hasJquery || (!canSelect2 && !canCombobox)) {
        return;
    }

    var $row = window.jQuery(row);
    var selector = 'select[name="product_id[]"], select[name="line_unit[]"], select[name="line_movement[]"], select[name="line_warehouse[]"]';

    $row.find(selector).each(function () {
        var $select = window.jQuery(this);

        sfEnsureUniqueSelectId(this);

        if ($select.hasClass('select2-hidden-accessible')) {
            return;
        }

        if (canSelect2) {
            $select.select2({
                dir: 'ltr',
                width: 'resolve',
                minimumResultsForSearch: 0,
                minimumInputLength: 0,
                language: (typeof select2arrayoflanguage === 'undefined') ? 'en' : select2arrayoflanguage,
                containerCssClass: ':all:',
                selectionCssClass: ':all:',
                dropdownCssClass: 'ui-dialog',
                matcher: function (params, data) {
                    if (!data || typeof data.text === 'undefined') return data;
                    if (window.jQuery.trim(params.term || '') === '') return data;

                    var term = (params.term || '').toLowerCase();
                    var text = (data.text || '').toLowerCase();
                    var keywords = term.split(' ');

                    for (var i = 0; i < keywords.length; i++) {
                        if (!keywords[i]) continue;
                        if (text.indexOf(keywords[i]) === -1) return null;
                    }

                    return data;
                },
                escapeMarkup: function (markup) { return markup; }
            });
        } else if (canCombobox && $select.next('.ui-combobox').length === 0) {
            $select.combobox();
        }
    });
}

function sfBindLine(row) {
    if (!row) return;

    sfEnhanceLineSelects(row);

    var areaInput = row.querySelector('.sf-line-area');
    var doseInput = row.querySelector('.sf-line-dose');

    if (doseInput) {
        doseInput.addEventListener('input', function () { sfRecalcLine(row); });
        doseInput.addEventListener('change', function () { sfRecalcLine(row); });
    }

    if (areaInput) {
        areaInput.addEventListener('change', function () { sfRecalcLine(row); });
    }

    var removeBtn = row.querySelector('.sf-remove-line');
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            var rows = document.querySelectorAll('#products-table tbody tr');
            if (rows.length > 1) {
                row.remove();
            }
        });
    }

    sfRecalcLine(row);
}

function sfApplyAreaToLines(areaValue) {
    var normalizedArea = sfToNumber(areaValue);
    document.querySelectorAll('#products-table tbody tr').forEach(function (row) {
        var areaInput = row.querySelector('.sf-line-area');
        if (!areaInput) return;
        areaInput.value = normalizedArea.toFixed(4);
        sfRecalcLine(row);
    });
}

function sfSyncAreaFromPercentage() {
    if (!areaBaseInput || !areaPctInput || !areaTotalInput) return;

    var baseArea = sfToNumber(areaBaseInput.value);
    var pct = sfToNumber(areaPctInput.value);

    if (pct < 0) pct = 0;
    if (pct > 100) pct = 100;

    areaPctInput.value = pct.toFixed(2);

    var computed = baseArea * (pct / 100);
    areaTotalInput.value = computed.toFixed(4);
    sfApplyAreaToLines(computed);
}

function sfUpdateTalhaoArea(talhaoId) {
    var hiddenField = document.querySelector('input[name="fk_fieldplot"]');
    var info = talhaoInfoEl;
    var data = talhaoData[talhaoId] || null;

    if (hiddenField) {
        hiddenField.value = data ? talhaoId : '';
    }

    if (areaBaseInput) {
        var baseArea = data ? sfToNumber(data.area) : 0;
        areaBaseInput.value = baseArea ? baseArea.toFixed(4) : '';
    }

    if (info) {
        if (data) {
            var labels = [];
            if (data.ref) labels.push(data.ref);
            if (data.label) labels.push(data.label);
            if (data.area) labels.push(sfToNumber(data.area).toFixed(4) + ' ha');
            if (data.municipio) labels.push(data.municipio);
            info.textContent = labels.join(' | ');
        } else {
            info.textContent = talhaoPlaceholderText;
        }
    }

    if (!areaPctInput.value) {
        areaPctInput.value = '100.00';
    }

    sfSyncAreaFromPercentage();
}

function sfFetchTalhaoForProject(projectId) {
    var numericProjectId = parseInt(projectId, 10) || 0;
    if (!numericProjectId) {
        sfUpdateTalhaoArea('');
        return;
    }

    var url = '<?php echo dol_buildpath('/safra/ajax/project_talhao.php', 1); ?>?id=' + encodeURIComponent(numericProjectId);
    fetch(url, { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (!data || !data.success || !data.talhao_id) {
                sfUpdateTalhaoArea('');
                return;
            }

            var talhaoId = String(data.talhao_id);
            if (data.talhao) {
                talhaoData[talhaoId] = {
                    ref: data.talhao.ref || '',
                    label: data.talhao.label || '',
                    area: data.talhao.area || 0,
                    municipio: data.talhao.municipio || ''
                };
            }

            sfUpdateTalhaoArea(talhaoId);
        })
        .catch(function () {
            sfUpdateTalhaoArea('');
        });
}

function sfGetProjectId() {
    var projectSelect = document.querySelector('select[name="fk_project"]');
    if (!projectSelect) return '';

    var selected = projectSelect.value || '';
    if (window.jQuery) {
        var jqValue = window.jQuery(projectSelect).val();
        if (jqValue !== null && jqValue !== undefined) {
            selected = jqValue;
        }
    }

    return selected;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#products-table tbody tr').forEach(sfBindLine);

    var addLineBtn = document.getElementById('add-line');
    if (addLineBtn) {
        addLineBtn.addEventListener('click', function () {
            var tbody = document.querySelector('#products-table tbody');
            var template = document.getElementById('product-line-template');
            var source = template && template.content ? template.content.querySelector('tr') : null;
            if (!source) return;

            var clone = source.cloneNode(true);

            clone.querySelectorAll('script').forEach(function (node) {
                node.remove();
            });

            tbody.appendChild(clone);

            var currentArea = areaTotalInput ? sfToNumber(areaTotalInput.value) : 0;
            var areaInput = clone.querySelector('.sf-line-area');
            if (areaInput) {
                areaInput.value = currentArea.toFixed(4);
            }

            sfBindLine(clone);
        });
    }

    if (areaPctInput) {
        areaPctInput.addEventListener('input', sfSyncAreaFromPercentage);
        areaPctInput.addEventListener('change', sfSyncAreaFromPercentage);
    }

    var projectSelect = document.querySelector('select[name="fk_project"]');
    if (projectSelect) {
        var handler = function () {
            sfFetchTalhaoForProject(sfGetProjectId());
        };

        projectSelect.addEventListener('change', handler, { passive: true });

        if (window.jQuery) {
            window.jQuery(projectSelect).on('select2:select select2:clear', handler);
        }

        handler();
    }

    var initialTalhao = document.querySelector('input[name="fk_fieldplot"]');
    if (initialTalhao && initialTalhao.value && talhaoData[initialTalhao.value]) {
        sfUpdateTalhaoArea(initialTalhao.value);
    } else {
        sfSyncAreaFromPercentage();
    }
});
<?php
print '</script>';

llxFooter();
$db->close();
