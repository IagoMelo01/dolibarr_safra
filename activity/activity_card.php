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
$activityTypes = array(
    'Preparo do solo' => 'Preparo do solo',
    'Tratamento de semente' => 'Tratamento de semente',
    'Plantio' => 'Plantio',
    'Fertilização' => 'Fertilização',
    'Aplicação' => 'Aplicação',
    'Colheita' => 'Colheita',
    'Monitoramento' => 'Monitoramento',
    'Instalação de armadilhas' => 'Instalação de armadilhas',
    'Leitura de armadilhas' => 'Leitura de armadilhas',
    'Outro' => 'Outro',
);

$activity = new FvActivity($db);
if ($id > 0) {
    $activity->fetch($id);
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

$warehouses = safra_load_options($db, 'entrepot', 'lieu');

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
$sqlProducts = 'SELECT p.rowid, p.ref, p.label'
    . ' FROM ' . MAIN_DB_PREFIX . 'product p'
    . ' INNER JOIN ' . MAIN_DB_PREFIX . "product_stock ps ON ps.fk_product = p.rowid"
    . ' WHERE p.entity IN (0, ' . ((int) $conf->entity) . ')'
    . ' AND ps.reel > 0'
    . ' GROUP BY p.rowid, p.ref, p.label'
    . ' ORDER BY p.label';
$resProducts = $db->query($sqlProducts);
if ($resProducts) {
    while ($obj = $db->fetch_object($resProducts)) {
        $productOptions[$obj->rowid] = $obj->ref . ' - ' . $obj->label;
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
    $result = $activity->cancel($user, true);
    if ($result > 0) {
        if ($result === 2) {
            setEventMessages($langs->trans('SafraActivityDeleted'), null, 'mesgs');
            header('Location: ' . dol_buildpath('/safra/safraindex.php', 1));
            exit;
        }

        if (!empty($activity->deletionPrevented)) {
            setEventMessages($langs->trans('ErrorSafraActivityDeleteWithStock'), null, 'warnings');
        } else {
            setEventMessages($langs->trans('SafraActivityCanceled'), null, 'mesgs');
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $activity->id);
        exit;
    }

    $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
}

if ($action === 'save') {
    $isNew = empty($activity->id);
    $wasCompleted = !$isNew && $activity->isCompleted();
    $hadStockMovements = !$isNew && $activity->hasStockMovements();
    $activity->label = GETPOST('label', 'alpha');
    $activity->type = GETPOST('type', 'alpha');
    $activity->fk_project = GETPOSTINT('fk_project');
    $activity->fk_fieldplot = GETPOSTINT('fk_fieldplot');
    $activity->area_total = price2num(GETPOST('area_total', 'alpha'), 'MT');
    $activity->note_public = GETPOST('note_public', 'restricthtml');

    if (empty($activity->label)) {
        $errors[] = $langs->trans('ErrorFieldRequired', $langs->trans('Label'));
    }

    if (!$errors) {
        $db->begin();

        $stockReverted = false;
        $stockRecreated = false;
        $result = 0;

        if (!$isNew && $wasCompleted && $hadStockMovements) {
            $revertResult = $activity->revertStockMovements($user, false);
            if ($revertResult < 0) {
                $errors[] = $activity->error ?: $langs->trans('ErrorRecordNotSaved');
            } else {
                $stockReverted = $revertResult > 0;
            }
        }

        if (!$errors) {
            $result = $isNew ? $activity->create($user) : $activity->update($user);
        }

        if (!$errors && $result > 0) {
            // Create project task when creating activity
            if ($isNew && $activity->fk_project > 0 && isModEnabled('project')) {
                dol_include_once('/projet/class/task.class.php');
                $task = new Task($db);
                $task->fk_project = $activity->fk_project;
                $task->label = $activity->label;
                $task->date_c = dol_now();
                $task->date_start = dol_now();
                $task->progress = 0;
                $task->description = $langs->trans('Activity') . ' #' . $activity->id;
                $taskId = $task->create($user);
                if ($taskId > 0) {
                    $activity->fk_task = $taskId;
                    $activity->update($user);

                    $activityUrl = dol_buildpath('/safra/activity/activity_card.php?id=' . $activity->id, 1);
                    $task->fetch($taskId);
                    $task->note_private .= "\n" . $langs->trans('Link') . ': ' . $activityUrl;
                    $task->update($user);
                }
            }

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

            if (!$errors && !$isNew && $activity->isCompleted()) {
                $stockResult = $activity->createStockMovements($user, true, false);
                if ($stockResult < 0) {
                    $errors[] = $activity->error ?: $langs->trans('ErrorSafraActivityStockMovement');
                } else {
                    $stockRecreated = $stockResult > 0;
                }
            }

            if (!$errors) {
                $messages = array();
                if ($stockRecreated) {
                    $messages[] = $langs->trans('SafraActivityStockRecalculated');
                } elseif ($stockReverted) {
                    $messages[] = $langs->trans('SafraActivityStockReverted');
                }

                $messages[] = $langs->trans('SafraActivitySaved');

                $db->commit();
                setEventMessages($messages, null, 'mesgs');
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

// Modern styles for a cleaner card layout
print <<<'HTML'
<style>
    .safra-activity-card {
        border-radius: 18px;
        overflow: hidden;
        background: #fff;
        border: 1px solid #e5e7eb;
    }
    .safra-activity-card .card-header {
        background: linear-gradient(140deg, #0b1224, #0f172a 45%, #1d4ed8);
        color: #fff;
        border-bottom: none;
        padding: 1.1rem 1.25rem;
    }
    .safra-activity-card .card-subtitle {
        color: rgba(255,255,255,0.75);
        font-size: 0.9rem;
    }
    .safra-activity-card .card-header .btn-light,
    .safra-activity-card .btn-soft {
        color: #0f172a;
        background: #eef2ff;
        border: 1px solid #d7ddf7;
        font-weight: 700;
        letter-spacing: 0.01em;
        box-shadow: 0 8px 24px rgba(30, 64, 175, 0.18);
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .safra-activity-card .btn-soft:hover {
        background: #e4e9ff;
        transform: translateY(-1px);
        box-shadow: 0 12px 30px rgba(30, 64, 175, 0.22);
    }
    .safra-activity-card .btn-soft .mixture-dot {
        display: inline-block;
        width: 9px;
        height: 9px;
        margin-right: 6px;
        border-radius: 50%;
        background: linear-gradient(120deg, #22d3ee, #a855f7);
        box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
    }
    .safra-activity-card .section-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        letter-spacing: 0.01em;
    }
    .safra-activity-card .section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.75rem;
    }
    .safra-activity-card .section-heading small {
        color: #475569;
        font-weight: 600;
    }
    .safra-activity-card .section-title .badge {
        font-weight: 500;
    }
    .safra-activity-card .section-block {
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1rem 1.1rem;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.05);
    }
    .safra-activity-card .section-block + .section-block {
        margin-top: 0.85rem;
    }
    .safra-activity-card .section-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, #cbd5e1, transparent);
        margin: 1.2rem 0;
    }
    .safra-activity-card .floating-box {
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fafc, #f1f5f9);
        border: 1px solid #e2e8f0;
        padding: 1rem;
    }
    .safra-activity-card .form-control, .safra-activity-card select {
        border-radius: 0.65rem;
        border-color: #e5e7eb;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05);
        width: 100%;
    }
    .safra-activity-card .form-control:focus, .safra-activity-card select:focus {
        border-color: #1d4ed8;
        box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
    }
    .safra-activity-card .summary-pill {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        padding: 0.35rem 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #0f172a;
    }
    .safra-activity-card .table-modern {
        border-radius: 0.75rem;
        overflow: hidden;
    }
    .safra-activity-card .summary-pill .dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
        background: #22d3ee;
    }
    .safra-activity-card .note-area {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 0.75rem;
        min-height: 140px;
    }
    .safra-activity-card .table-modern thead th {
        background: #0f172a;
        color: #fff;
        border: none;
        text-transform: uppercase;
        letter-spacing: 0.01em;
        font-size: 0.78rem;
    }
    .safra-activity-card .table-modern tbody tr {
        transition: background 0.2s ease, transform 0.2s ease;
    }
    .safra-activity-card .table-modern tbody tr:hover {
        background: #f8fafc;
        transform: translateY(-1px);
    }
    .safra-activity-card .table-modern td {
        vertical-align: middle;
    }
    .safra-activity-card .btn-icon {
        border-radius: 50%;
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .safra-activity-card .helper-text {
        color: #475569;
        font-size: 0.85rem;
    }
    .safra-activity-card .info-strip {
        background: linear-gradient(120deg, #f8fafc, #eef2ff);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.65rem 0.85rem;
        min-height: 42px;
        display: flex;
        align-items: center;
        font-weight: 600;
        color: #0f172a;
    }
    .safra-activity-card .select2-container .select2-selection--multiple,
    .safra-activity-card .select2-container .select2-selection--single {
        min-height: 40px;
        border-radius: 0.65rem;
        border-color: #e5e7eb;
    }
    .safra-activity-card .table-modern td select.form-select,
    .safra-activity-card .table-modern td .form-control {
        min-width: 130px;
    }
    .safra-activity-card .btn-primary,
    .safra-activity-card .btn-success,
    .safra-activity-card .btn-outline-danger,
    .safra-activity-card .btn-outline-warning {
        border-radius: 10px;
        padding: 0.6rem 1rem;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .safra-activity-card .btn-primary,
    .safra-activity-card .btn-success {
        box-shadow: 0 14px 30px rgba(15, 118, 110, 0.22);
    }
    .safra-activity-card .btn-outline-danger,
    .safra-activity-card .btn-outline-warning {
        box-shadow: none;
    }
    .safra-activity-card .product-toolbar {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 0.75rem 1rem;
    }
    .safra-activity-card .product-toolbar .text-muted {
        font-size: 0.9rem;
    }
    .mixture-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1090;
        background: radial-gradient(130% 130% at 50% 18%, rgba(47, 72, 122, 0.18), rgba(6, 17, 38, 0.85));
        overflow-y: auto;
        padding: 2.4rem 1.25rem;
    }
    .mixture-modal.show {
        display: flex;
        backdrop-filter: blur(6px);
    }
    .mixture-modal .modal-dialog {
        margin: 0 auto;
        width: 100%;
        max-width: 900px;
    }
    .mixture-modal .modal-content {
        border-radius: 18px;
        border: 1px solid #d9e5f6;
        box-shadow: 0 26px 80px rgba(7, 11, 26, 0.5);
        position: relative;
        overflow: hidden;
        background: linear-gradient(180deg, #0c1a36 0%, #0c1a36 16%, #0b1a3c 28%, #f7f9fc 28%);
    }
    .mixture-modal .modal-content:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 9px;
        background: linear-gradient(120deg, #22c55e, #1f8ef1, #2563eb);
    }
    .mixture-modal .modal-header {
        border: none;
        padding: 1.65rem 1.6rem 1.25rem;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.94), rgba(30, 64, 175, 0.82));
        align-items: flex-start;
        min-height: 96px;
        color: #f8fafc;
    }
    .mixture-modal .modal-title {
        letter-spacing: 0.01em;
        font-weight: 800;
        color: #f8fafc;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        font-size: 1.22rem;
    }
    .mixture-modal .modal-title:before {
        content: '';
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: linear-gradient(140deg, #22c55e, #1f8ef1);
        box-shadow: 0 0 0 6px rgba(37, 99, 235, 0.12);
    }
    .mixture-modal .modal-body {
        padding: 1.15rem 1.35rem 1.4rem;
    }
    .mixture-modal .modal-footer {
        padding: 1.05rem 1.35rem 1.35rem;
        background: linear-gradient(180deg, #f7f9fc, #eef2f7);
        gap: 0.85rem;
    }
    .mixture-modal .form-control {
        border-radius: 12px;
        border-color: #d4def0;
        background: #f8fbff;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05);
        padding: 0.6rem 0.75rem;
    }
    .mixture-modal .mixture-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.05rem;
        margin-bottom: 1.2rem;
    }
    .mixture-modal .mixture-fieldset {
        background: linear-gradient(180deg, #ffffff, #f8fbff);
        border: 1px solid #dfe7f3;
        border-radius: 14px;
        padding: 1rem 1.05rem;
        box-shadow: 0 10px 26px rgba(12, 26, 60, 0.08);
        height: 100%;
    }
    .mixture-modal .mixture-stat {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        background: linear-gradient(120deg, #f5f8ff, #eef2f7);
        border: 1px solid #dde6f4;
        border-radius: 12px;
        padding: 0.95rem 1.05rem;
        margin-bottom: 0.35rem;
    }
    .safra-activity-card .mixture-summary {
        background: #f8fafc;
        border: 1px dashed #d8e3f0;
        border-radius: 12px;
        padding: 0.75rem 0.9rem;
        max-height: 220px;
        overflow-y: auto;
    }
    .safra-activity-card .mixture-summary .line {
        padding: 0.45rem 0.6rem;
        border-radius: 10px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        border: 1px solid #e5e7eb;
        margin-bottom: 0.35rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.65rem;
    }
    .safra-activity-card .mixture-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #0f172a;
    }
    .safra-activity-card .mixture-meta {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.55rem 0.65rem;
        font-weight: 600;
        color: #0f172a;
    }
    .safra-activity-card .mixture-helper {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #1f8ef1;
        font-weight: 700;
    }
    .safra-activity-card .mixture-helper .dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: linear-gradient(120deg, #22c55e, #1f8ef1);
    }
    .safra-activity-card .mixture-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(120deg, rgba(37, 99, 235, 0.08), rgba(34, 197, 94, 0.12));
        border: 1px solid rgba(37, 99, 235, 0.18);
        border-radius: 12px;
        padding: 0.65rem 0.95rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }
    .safra-activity-card .mixture-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: linear-gradient(120deg, #1f4e9f, #2563eb);
        color: #f8fafc;
        padding: 0.42rem 0.7rem;
        border-radius: 999px;
        font-weight: 700;
        letter-spacing: 0.01em;
        box-shadow: 0 12px 26px rgba(12, 26, 60, 0.32);
    }
    .safra-activity-card .mixture-chip .dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: linear-gradient(140deg, #22c55e, #1f8ef1);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.14);
    }
    .mixture-modal .btn-outline-secondary {
        border-radius: 12px;
        font-weight: 700;
        padding: 0.7rem 1.3rem;
        border-color: #d5deeb;
        color: #0f172a;
        background: #f7f9fc;
        box-shadow: 0 10px 20px rgba(12, 26, 60, 0.08);
    }
    .mixture-modal .btn-primary {
        border-radius: 12px;
        font-weight: 800;
        padding: 0.85rem 1.65rem;
        letter-spacing: 0.01em;
        background: linear-gradient(120deg, #1f4e9f, #2563eb);
        border: none;
        box-shadow: 0 14px 32px rgba(31, 78, 159, 0.32);
    }
    .safra-activity-card .product-footer {
        background: linear-gradient(120deg, #0f1b34, #1f4e9f);
        border-radius: 0 0 14px 14px;
        padding: 0.9rem 1.1rem;
        color: #e7edf7;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        border-top: 1px solid rgba(255,255,255,0.12);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
    }
    .safra-activity-card .product-footer .footer-note {
        color: #cbd5e1;
        font-weight: 600;
    }
    .safra-activity-card .product-footer .left-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .safra-activity-card .btn-add-line {
        background: linear-gradient(120deg, #22c55e, #16a34a);
        border: none;
        color: #fff;
        font-weight: 800;
        letter-spacing: 0.02em;
        padding: 0.65rem 1.2rem;
        border-radius: 12px;
        box-shadow: 0 16px 32px rgba(22, 163, 74, 0.25);
        transition: transform 0.15s ease, box-shadow 0.2s ease;
    }
    .safra-activity-card .btn-add-line:hover {
        transform: translateY(-2px);
        box-shadow: 0 22px 38px rgba(22, 163, 74, 0.28);
    }
    .safra-activity-card .safra-save-action {
        min-width: 190px;
        text-align: center;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: 0.01em;
        padding: 0.8rem 1.5rem;
        border-radius: 12px;
        border: none;
        box-shadow: 0 12px 26px rgba(37, 99, 235, 0.25);
        background: var(--safra-save-color, linear-gradient(120deg, #2c7be5, #2563eb));
        color: #fff;
        cursor: pointer;
    }
    .safra-activity-card .safra-save-action:hover {
        filter: brightness(1.05);
        box-shadow: 0 16px 32px rgba(37, 99, 235, 0.28);
    }
</style>
HTML;

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';
if ($activity->id) {
    print '<input type="hidden" name="id" value="' . $activity->id . '">';
}

print '<div class="card shadow-sm border-0 safra-activity-card">';
print '<div class="card-header d-flex flex-wrap align-items-center justify-content-between">';
print '<div class="d-flex flex-column">
        <span class="text-uppercase small opacity-75">' . $langs->trans('SafraActivity') . '</span>
        <span class="fw-semibold fs-5">' . ($activity->label ? dol_escape_htmltag($activity->label) : $langs->trans('NewActivity')) . '</span>
        <span class="card-subtitle">' . $langs->trans('KeepYourActivityDetailsOrganized') . '</span>
    </div>';

print '<div class="d-flex align-items-center gap-2">';
if ($activity->fk_task) {
    $taskUrl = dol_buildpath('/projet/tasks/task.php?id=' . $activity->fk_task, 1);
    print '<a class="badge bg-light text-dark text-decoration-none" href="' . $taskUrl . '">' . $langs->trans('Task') . '</a>';
}

print '<span class="summary-pill">';
print '<span class="dot"></span>';
print '<span class="fw-semibold">' . $langs->trans('Status') . ':</span> ';
switch ((int) $activity->status) {
    case FvActivity::STATUS_COMPLETED:
        print $langs->trans('SafraActivityStatusCompleted');
        break;
    case FvActivity::STATUS_CANCELED:
        print $langs->trans('SafraActivityStatusCanceled');
        break;
    default:
        print $langs->trans('Draft');
}
print '</span>';
print '</div>';
print '</div>';

print '<div class="card-body">';
print '<div class="section-block">';
print '<div class="section-heading"><span>' . $langs->trans('Details') . '</span><small>' . $langs->trans('KeepYourActivityDetailsOrganized') . '</small></div>';
print '<div class="row g-4">';
print '<div class="col-lg-6">';
print '<label class="section-title">' . $langs->trans('Label') . '</label>';
print '<input class="form-control" name="label" value="' . dol_escape_htmltag($activity->label) . '" required placeholder="' . dol_escape_htmltag($langs->trans('Label')) . '">';
print '</div>';
print '<div class="col-lg-3">';
print '<label class="section-title">' . $langs->trans('Type') . '</label>';
if ($activity->type && !isset($activityTypes[$activity->type])) {
    $activityTypes = array($activity->type => $activity->type) + $activityTypes;
}
print $form->selectarray('type', $activityTypes, $activity->type, 1, 0, 0, '', 0, 0, 0, '', 'form-control minwidth200');
print '</div>';
print '<div class="col-lg-3">';
print '<label class="section-title">' . $langs->trans('Project') . '</label>';
print $formproject->select_projects(-1, $activity->fk_project, 'fk_project', 0, 0, 1, 0, 0, 0, '', '', 1, 0, 1);
print '<div class="helper-text mt-1">' . $langs->trans('SelectProjectToAutoFillFieldPlot') . '</div>';
print '</div>';
print '</div>';
print '</div>';

print '<div class="section-block">';
print '<div class="section-heading"><span>' . $langs->trans('FieldPlot') . '</span><small>' . $langs->trans('AutoFilledFromFieldPlot') . '</small></div>';
print '<div class="row g-4">';
print '<div class="col-lg-8">';
print '<div class="floating-box h-100">';
print '<input type="hidden" name="fk_fieldplot" value="' . ((int) $activity->fk_fieldplot) . '">';
$talhaoLabel = $talhaoPlaceholder;
if (!empty($activity->fk_fieldplot) && !empty($talhaoDetails[$activity->fk_fieldplot])) {
    $t = $talhaoDetails[$activity->fk_fieldplot];
    $pieces = array();
    if (!empty($t['ref'])) $pieces[] = $t['ref'];
    if (!empty($t['label'])) $pieces[] = $t['label'];
    $areaText = isset($t['area']) ? price2num($t['area']) . ' ha' : '';
    if ($areaText) $pieces[] = $areaText;
    if (!empty($t['municipio'])) $pieces[] = $t['municipio'];
    $talhaoLabel = implode(' • ', array_filter($pieces));
}
print '<div class="info-strip" id="talhao-area-info">' . dol_escape_htmltag($talhaoLabel) . '</div>';
print '<div class="helper-text mt-1">' . $langs->trans('SelectProjectToAutoFillFieldPlot') . '</div>';
print '</div>';
print '</div>';
print '<div class="col-lg-4">';
print '<div class="floating-box h-100">';
print '<input type="hidden" name="area_base" id="area-base" value="' . dol_escape_htmltag(price2num($talhaoDetails[$activity->fk_fieldplot]['area'] ?? '')) . '">';
print '<label class="section-title mb-1">' . $langs->trans('Area') . ' (%)</label>';
print '<input class="form-control" id="area-percentage" name="area_percentage" value="' . dol_escape_htmltag(price2num($areaPercentage*100)) . '" inputmode="decimal" placeholder="100">';
print '<div class="helper-text mt-2">' . $langs->trans('AutoFilledFromFieldPlot') . '</div>';
print '<label class="section-title mt-3 mb-1">' . $langs->trans('Area') . ' (ha)</label>';
print '<input class="form-control" id="area-total" name="area_total" value="' . dol_escape_htmltag(price2num($activity->area_total)) . '" step="0.0001" type="number" min="0" placeholder="0.00" readonly>';
print '</div>';
print '</div>';
print '</div>';
print '</div>';

print '<div class="section-divider"></div>';

print '<div class="section-block">';
print '<div class="section-heading"><span>' . $langs->trans('SafraAplicacaoResources') . '</span><small>' . $langs->trans('SafraMachineLabel') . ' • ' . $langs->trans('SafraImplementsLabel') . ' • ' . $langs->trans('SafraEmployeesLabel') . '</small></div>';
print '<div class="row g-4">';
print '<div class="col-lg-4">';
print '<label class="section-title">' . $langs->trans('SafraMachineLabel') . '</label>';
print $form->multiselectarray('machine_ids', $machines, $selectedMachines, '', 0, '', 1);
print '</div>';
print '<div class="col-lg-4">';
print '<label class="section-title">' . $langs->trans('SafraImplementsLabel') . '</label>';
print $form->multiselectarray('implement_ids', $implements, $selectedImplements, '', 0, '', 1);
print '</div>';
print '<div class="col-lg-4">';
print '<label class="section-title">' . $langs->trans('SafraEmployeesLabel') . '</label>';
print $form->multiselectarray('user_ids', $userOptions, $selectedUsers, '', 0, '', 1);
print '</div>';
print '</div>';
print '</div>';

print '<div class="section-block">';
print '<div class="section-heading"><span>' . $langs->trans('Note') . '</span><small>' . $langs->trans('Optional') . '</small></div>';
print '<div class="row g-3">';
print '<div class="col-12">';
print '<textarea class="form-control note-area" name="note_public" id="note_public" rows="4" placeholder="' . dol_escape_htmltag($langs->trans('Note')) . '">' . dol_escape_htmltag($activity->note_public) . '</textarea>';
print '</div>';
print '</div>';
print '</div>';
print '</div>'; // card-body
print '</div>'; // card

print '<div class="card shadow-sm border-0 mt-3 safra-activity-card">';
print '<div class="card-header d-flex justify-content-between align-items-center">';
print '<div>
        <div class="text-uppercase small opacity-75">' . $langs->trans('Products') . '</div>
        <h5 class="mb-0">' . $langs->trans('Products') . '</h5>
    </div>';
print '<span class="badge bg-light text-dark">' . $langs->trans('MixtureCalculation') . '</span>';
print '</div>';
print '<div class="card-body">';
print '<div class="product-toolbar d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">';
print '<div>
        <div class="fw-semibold">' . $langs->trans('MixtureCalculation') . '</div>
        <div class="text-muted">' . $langs->trans('Products') . ' + ' . $langs->trans('MixtureCalculation') . '</div>
    </div>';
print '<div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-soft btn-sm" id="open-mixture" data-bs-toggle="modal" data-bs-target="#mixtureModal"><span class="mixture-dot"></span>' . $langs->trans('MixtureCalculation') . '</button>
    </div>';
print '</div>';

print '<div class="table-responsive">';
print '<table class="table table-modern align-middle" id="products-table">';
print '<thead><tr>';
print '<th>' . $langs->trans('Product') . '</th>';
print '<th>' . $langs->trans('Area') . ' (ha)</th>';
print '<th>' . $langs->trans('Dose') . '</th>';
print '<th>' . $langs->trans('Unit') . '</th>';
print '<th>' . $langs->trans('Total') . '</th>';
print '<th>' . $langs->trans('Movement') . '</th>';
print '<th>' . $langs->trans('Warehouse') . '</th>';
print '<th></th>';
print '</tr></thead>';
print '<tbody>';

if (!empty($activity->lines)) {
    foreach ($activity->lines as $line) {
        $unitOptsForLine = $unitOptions;
        if (!empty($line->dose_unit) && !isset($unitOptsForLine[$line->dose_unit])) {
            $unitOptsForLine[$line->dose_unit] = $line->dose_unit;
        }
        print '<tr>';
        print '<td>' . $form->selectarray('product_id[]', $productOptions, $line->fk_product, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
        print '<td><input type="number" name="line_area[]" class="form-control area-input" step="0.0001" value="' . dol_escape_htmltag(price2num($line->area_applied)) . '" readonly></td>';
        print '<td><input type="text" name="line_dose[]" class="form-control dose-input" inputmode="decimal" value="' . dol_escape_htmltag(price2num($line->dose)) . '"></td>';
        print '<td>' . $form->selectarray('line_unit[]', $unitOptsForLine, $line->dose_unit, 1, 0, 0, '', 0, 0, 0, '', 'form-select unit-input') . '</td>';
        print '<td><input type="number" name="line_total[]" class="form-control total-input" step="0.0001" value="' . dol_escape_htmltag(price2num($line->total)) . '" readonly></td>';
        print '<td>' . $form->selectarray('line_movement[]', $movementTypes, $line->movement_type, 1) . '</td>';
        print '<td>' . $form->selectarray('line_warehouse[]', $warehouses, $line->fk_warehouse, 1, 0, 0, '', 0, 0, 0, '', 'minwidth150') . '</td>';
        print '<td><button type="button" class="btn btn-outline-danger btn-icon remove-line" aria-label="' . dol_escape_htmltag($langs->trans('Delete')) . '">&times;</button></td>';
        print '</tr>';
    }
} else {
    print '<tr>';
    print '<td>' . $form->selectarray('product_id[]', $productOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
    print '<td><input type="number" name="line_area[]" class="form-control area-input" step="0.0001" value="0" readonly></td>';
    print '<td><input type="text" name="line_dose[]" class="form-control dose-input" inputmode="decimal" value="0"></td>';
    print '<td>' . $form->selectarray('line_unit[]', $unitOptions, 'L/ha', 1, 0, 0, '', 0, 0, 0, '', 'form-select unit-input') . '</td>';
    print '<td><input type="number" name="line_total[]" class="form-control total-input" step="0.0001" value="0" readonly></td>';
    print '<td>' . $form->selectarray('line_movement[]', $movementTypes, 'consume', 1) . '</td>';
    print '<td>' . $form->selectarray('line_warehouse[]', $warehouses, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth150') . '</td>';
    print '<td><button type="button" class="btn btn-outline-danger btn-icon remove-line" aria-label="' . dol_escape_htmltag($langs->trans('Delete')) . '">&times;</button></td>';
    print '</tr>';
}

print '</tbody>';
print '</table>';
print '<template id="product-line-template">';
print '<tr>';
print '<td>' . $form->selectarray('product_id[]', $productOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
print '<td><input type="number" name="line_area[]" class="form-control area-input" step="0.0001" value="0" readonly></td>';
print '<td><input type="text" name="line_dose[]" class="form-control dose-input" inputmode="decimal" value="0"></td>';
print '<td>' . $form->selectarray('line_unit[]', $unitOptions, 'L/ha', 1, 0, 0, '', 0, 0, 0, '', 'form-select unit-input') . '</td>';
print '<td><input type="number" name="line_total[]" class="form-control total-input" step="0.0001" value="0" readonly></td>';
print '<td>' . $form->selectarray('line_movement[]', $movementTypes, 'consume', 1) . '</td>';
print '<td>' . $form->selectarray('line_warehouse[]', $warehouses, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth150') . '</td>';
print '<td><button type="button" class="btn btn-outline-danger btn-icon remove-line" aria-label="' . dol_escape_htmltag($langs->trans('Delete')) . '">&times;</button></td>';
print '</tr>';
print '</template>';
print '</div>'; // responsive
print '<div class="product-footer">';
print '<div class="left-group">';
print '<button type="button" class="btn btn-add-line" id="add-line">+ ' . $langs->trans('Add') . '</button>';
print '<span class="footer-note">' . $langs->trans('Add') . ' ' . $langs->trans('Products') . '</span>';
print '</div>';
print '<button class="butAction safra-save-action" type="submit" style="text-decoration:none;">💾 ' . $langs->trans('Save') . '</button>';
print '</div>';
print '</div>'; // card-body
print '</div>'; // card

print '</form>';

if ($activity->id) {
    print '<div class="d-flex flex-wrap gap-2 mt-2">';

    if (!$activity->isCompleted() && !$activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" class="mb-0">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="complete">';
        print '<input type="hidden" name="id" value="' . $activity->id . '">';
        print '<button class="btn btn-outline-success" type="submit">' . $langs->trans('SafraActivityComplete') . '</button>';
        print '</form>';
    }

    if (!$activity->isCanceled()) {
        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" class="mb-0">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="cancel">';
        print '<input type="hidden" name="id" value="' . $activity->id . '">';
        print '<button class="btn btn-outline-warning" type="submit">' . $langs->trans('SafraActivityCancel') . '</button>';
        print '</form>';
    }

    $confirmMessage = dol_escape_js($langs->transnoentities('ConfirmDelete'));
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" class="mb-0" onsubmit="return confirm(\'' . $confirmMessage . '\');">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="delete">';
    print '<input type="hidden" name="id" value="' . $activity->id . '">';
    print '<button class="btn btn-outline-danger" type="submit">' . $langs->trans('SafraActivityDelete') . '</button>';
    print '</form>';

    print '</div>';
}

// Modal for mixture calculation
?>
<div class="modal fade mixture-modal" id="mixtureModal" tabindex="-1" role="dialog" aria-modal="true" style="display:none;">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo dol_escape_htmltag($langs->trans('MixtureCalculation')); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mixture-topbar mb-3">
                    <div class="mixture-helper"><span class="dot"></span><?php echo $langs->trans('MixtureCalculation'); ?></div>
                    <div class="mixture-chip"><span class="dot"></span>L/ha · L</div>
                </div>
                <div class="mixture-grid">
                    <div class="mixture-fieldset">
                        <label class="mixture-label"><?php echo $langs->trans('ApplicationRate'); ?> (L/ha)</label>
                        <input type="text" class="form-control" id="application-rate" inputmode="decimal" value="0">
                        <div class="helper-text mt-2"><?php echo dol_escape_htmltag($langs->trans('EnterRatePerHectare')); ?></div>
                    </div>
                    <div class="mixture-fieldset">
                        <label class="mixture-label"><?php echo $langs->trans('TankCapacity'); ?> (L)</label>
                        <input type="text" class="form-control" id="tank-capacity" inputmode="decimal" value="0">
                        <div class="helper-text mt-2"><?php echo dol_escape_htmltag($langs->trans('EnterTankCapacity')); ?></div>
                    </div>
                </div>
                <div class="mixture-fieldset mb-3">
                    <div class="mixture-label mb-2"><?php echo $langs->trans('AppliedAreaPerTank'); ?></div>
                    <div class="mixture-stat">
                        <div class="text-muted"><?php echo $langs->trans('Area'); ?></div>
                        <div class="mixture-meta mb-0" id="area-per-tank">0,00 ha</div>
                    </div>
                    <div class="mixture-label mb-2 mt-3"><?php echo $langs->trans('QuantityPerTank'); ?></div>
                    <div class="mixture-summary" id="mixture-lines"></div>
                </div>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php echo $langs->trans('Cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="save-mixture-note"><?php echo $langs->trans('SaveAsNote'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php
print '<script>';
?>
var talhaoData = <?php echo json_encode($talhaoDetails); ?>;
var talhaoPlaceholderText = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('SelectProjectToAutoFillFieldPlot')); ?>';
var talhaoAreaDefault = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('Area')); ?>';
var mixtureEmptyText = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('NoProductForMixture')); ?>';
var perTankSuffix = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('PerTankSuffix')); ?>';
var noteHeader = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('SafraCaldaNoteHeader')); ?>';
var noteRateTpl = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('SafraCaldaNoteRate')); ?>';
var noteTankTpl = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('SafraCaldaNoteTank')); ?>';
var noteAreaTpl = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('SafraCaldaNoteArea')); ?>';
var noteItemsHeader = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('SafraCaldaNoteItemsHeader')); ?>';
var noteItemTpl = '<?php echo dol_escape_js($langs->transnoentitiesnoconv('SafraCaldaNoteItemPerTank')); ?>';
var areaBaseInput = document.getElementById('area-base');
var areaPercentageInput = document.getElementById('area-percentage');
var areaTotalInput = document.getElementById('area-total');

function normalizeNumber(value) {
    var normalized = (value || '').toString().replace(',', '.');
    var num = parseFloat(normalized);
    return isNaN(num) ? 0 : num;
}

function parseDecimalFromMask(value, decimals) {
    var digits = (value || '').toString().replace(/\D/g, '');
    if (!digits) return 0;
    return parseInt(digits, 10) / Math.pow(10, decimals);
}

function formatDecimalToMask(number, decimals) {
    if (number === null || number === undefined || isNaN(number)) {
        return '';
    }

    var negative = number < 0;
    var absolute = Math.abs(number);
    var fixed = absolute.toFixed(decimals);
    var parts = fixed.split('.');
    var formatted = (parts[0] || '0') + ',' + (parts[1] || ''.padStart(decimals, '0'));
    return negative ? '-' + formatted : formatted;
}

function maskInputValue(input, decimals, clampMax) {
    if (!input) return 0;

    var digits = (input.value || '').toString().replace(/\D/g, '');
    if (!digits) {
        input.value = '';
        return 0;
    }

    while (digits.length <= decimals) {
        digits = '0' + digits;
    }

    var numeric = parseInt(digits, 10) / Math.pow(10, decimals);
    if (numeric < 0) numeric = 0;
    if (typeof clampMax === 'number' && clampMax >= 0) {
        numeric = Math.min(clampMax, numeric);
    }

    input.value = formatDecimalToMask(numeric, decimals);
    return numeric;
}

function recalcLineTotal(row) {
    var area = normalizeNumber(row.querySelector('.area-input').value) || 0;
    var dose = parseDecimalFromMask(row.querySelector('.dose-input').value, 4) || 0;
    var total = dose * area;
    row.querySelector('.total-input').value = total.toFixed(4);
}

function applyAreaToLines(areaValue) {
    var normalized = normalizeNumber(areaValue);
    if (isNaN(normalized)) normalized = 0;
    document.querySelectorAll('#products-table tbody tr').forEach(function (row) {
        var areaInput = row.querySelector('.area-input');
        if (!areaInput) return;
        areaInput.value = normalized.toFixed(4);
        recalcLineTotal(row);
    });
}

function bindLine(row) {
    row.querySelectorAll('.area-input').forEach(function (input) {
        input.addEventListener('input', function () {
            recalcLineTotal(row);
        });
    });

    var doseInput = row.querySelector('.dose-input');
    if (doseInput) {
        maskInputValue(doseInput, 4);
        doseInput.addEventListener('input', function () {
            maskInputValue(doseInput, 4);
            recalcLineTotal(row);
        });
    }
    var removeBtn = row.querySelector('.remove-line');
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            if (document.querySelectorAll('#products-table tbody tr').length > 1) {
                row.remove();
            }
        });
    }
}

function updateTalhaoArea(talhaoId) {
    var areaField = areaTotalInput;
    var baseField = areaBaseInput;
    var percentField = areaPercentageInput;
    var info = document.getElementById('talhao-area-info');
    var hiddenField = document.querySelector('input[name="fk_fieldplot"]');
    var data = talhaoData[talhaoId] || null;

    console.log('[Safra] updateTalhaoArea', { talhaoId: talhaoId, data: data });

    if (hiddenField) {
        hiddenField.value = data ? talhaoId : '';
    }

    var normalizedArea = data && data.area ? normalizeNumber(data.area) || 0 : 0;
    if (baseField) {
        baseField.value = normalizedArea ? normalizedArea.toFixed(4) : '';
    }

    if (percentField && !percentField.value) {
        percentField.value = formatDecimalToMask(100, 2);
    }

    if (areaField) {
        var computedArea = percentField ? normalizedArea * ((parseDecimalFromMask(percentField.value, 2) || 0) / 100) : normalizedArea;
        if (isNaN(computedArea)) computedArea = 0;
        areaField.value = computedArea.toFixed(4);
        applyAreaToLines(computedArea);
    }

    if (info) {
        if (data) {
            var labelParts = [];
            if (data.ref) labelParts.push(data.ref);
            if (data.label) labelParts.push(data.label);
            var baseLabel = labelParts.join(' - ');
            var areaText = data.area ? (normalizeNumber(data.area).toFixed(4) + ' ha') : talhaoAreaDefault;
            var extras = [];
            if (data.municipio) extras.push(data.municipio);
            var infoPieces = [];
            if (baseLabel) infoPieces.push(baseLabel);
            infoPieces.push(areaText);
            if (extras.length) infoPieces.push(extras.join(' • '));
            info.textContent = infoPieces.join(' • ');
        } else {
            info.textContent = talhaoPlaceholderText;
        }
    }

    syncAreaFromPercentage();
}

function syncAreaFromPercentage() {
    if (!areaTotalInput || !areaBaseInput) {
        return;
    }

    var pct = parseDecimalFromMask(areaPercentageInput ? areaPercentageInput.value : '', 2) || 0;
    if (pct < 0) pct = 0;
    if (pct > 100) pct = 100;
    if (areaPercentageInput) {
        areaPercentageInput.value = formatDecimalToMask(pct, 2);
    }

    var baseArea = normalizeNumber(areaBaseInput.value) || 0;
    var computed = baseArea * (pct / 100);
    areaTotalInput.value = computed.toFixed(4);
    applyAreaToLines(computed);
}

function fetchTalhaoForProject(projectId) {
    var numericProjectId = parseInt(projectId, 10) || 0;
    console.log('[Safra] fetchTalhaoForProject', { raw: projectId, normalized: numericProjectId });

    if (!numericProjectId) {
        console.log('[Safra] No valid project selected, clearing talhão/area');
        updateTalhaoArea('');
        return;
    }

    updateTalhaoArea('');

    var url = '<?php echo dol_buildpath('/safra/ajax/project_talhao.php', 1); ?>?id=' + encodeURIComponent(numericProjectId);
    console.log('[Safra] Requesting talhão from project', url);

    fetch(url, { credentials: 'same-origin' })
        .then(function (response) {
            console.log('[Safra] talhão response status', response.status);
            return response.json().catch(function (err) {
                console.error('[Safra] Error parsing talhão JSON', err);
                throw err;
            });
        })
        .then(function (data) {
            console.log('[Safra] talhão payload', data);
            if (!data || !data.success || !data.talhao_id) {
                console.warn('[Safra] Talhão missing in response, clearing selection');
                updateTalhaoArea('');
                return;
            }

            var talhaoId = String(data.talhao_id);
            if (data.talhao) {
                talhaoData[talhaoId] = {
                    ref: data.talhao.ref || '',
                    label: data.talhao.label || '',
                    area: data.talhao.area,
                    municipio: data.talhao.municipio || ''
                };
            }

            updateTalhaoArea(talhaoId);
        })
        .catch(function (err) {
            console.error('[Safra] talhão fetch failed', err);
            updateTalhaoArea('');
        });
}

function getProjectId() {
    var projectSelect = document.querySelector('select[name="fk_project"]');
    if (!projectSelect) {
        return '';
    }

    var currentValue = projectSelect.value || '';

    if (window.jQuery) {
        var jqValue = jQuery(projectSelect).val();
        if (jqValue !== null && jqValue !== undefined) {
            currentValue = jqValue;
        }
    }

    console.log('[Safra] read project id', { domValue: projectSelect.value, jqValue: window.jQuery ? jQuery(projectSelect).val() : null, resolved: currentValue });
    return currentValue;
}

document.addEventListener('DOMContentLoaded', function () {
    var lineTemplate = document.getElementById('product-line-template');
    document.querySelectorAll('#products-table tbody tr').forEach(function (row) {
        if (!row.querySelector('.area-input')) {
            return;
        }
        if (!row.querySelector('.unit-input').value) {
            row.querySelector('.unit-input').value = 'L/ha';
        }
        bindLine(row);
        recalcLineTotal(row);
    });

    if (areaPercentageInput) {
        maskInputValue(areaPercentageInput, 2, 100);
        areaPercentageInput.addEventListener('input', function () {
            maskInputValue(areaPercentageInput, 2, 100);
            syncAreaFromPercentage();
        });
    }

    document.getElementById('add-line').addEventListener('click', function () {
        var tbody = document.querySelector('#products-table tbody');
        var baseRow = null;
        if (lineTemplate && 'content' in lineTemplate) {
            baseRow = lineTemplate.content.querySelector('tr');
        }
        if (!baseRow) {
            baseRow = tbody.querySelector('tr');
        }
        var clone = baseRow ? baseRow.cloneNode(true) : document.createElement('tr');
        clone.querySelectorAll('input').forEach(function (input) {
            var defaultValue = input.getAttribute('value') || '';
            if (input.classList.contains('unit-input')) {
                input.value = defaultValue || 'L/ha';
            } else {
                input.value = defaultValue || '0';
            }
        });
        clone.querySelectorAll('select').forEach(function (select) {
            select.selectedIndex = 0;
        });
        tbody.appendChild(clone);
        bindLine(clone);
        recalcLineTotal(clone);
        applyAreaToLines(areaTotalInput ? areaTotalInput.value : 0);
    });

    var projectSelect = document.querySelector('select[name="fk_project"]');
    if (projectSelect) {
        var triggerFetch = function () {
            fetchTalhaoForProject(getProjectId());
        };

        projectSelect.addEventListener('change', triggerFetch, { passive: true });

        if (window.jQuery) {
            jQuery(projectSelect).on('select2:select select2:clear', triggerFetch);
        }

        fetchTalhaoForProject(getProjectId());
    }

    updateTalhaoArea(document.querySelector('input[name="fk_fieldplot"]').value || '');
    syncAreaFromPercentage();
    applyAreaToLines(areaTotalInput ? areaTotalInput.value : 0);

    var mixtureButton = document.getElementById('open-mixture');
    var mixtureModal = document.getElementById('mixtureModal');
    if (mixtureButton) {
        mixtureButton.addEventListener('click', function (event) {
            updateMixtureModal();
            showMixtureModal();
            event.preventDefault();
        });
    }

    if (mixtureModal) {
        ['show.bs.modal', 'hidden.bs.modal'].forEach(function (evName) {
            mixtureModal.addEventListener(evName, function (evt) {
                if (evName === 'show.bs.modal') {
                    updateMixtureModal();
                    mixtureModal.setAttribute('aria-hidden', 'false');
                } else {
                    mixtureModal.setAttribute('aria-hidden', 'true');
                    removeMixtureBackdrop();
                }
            });
        });
    }

    document.querySelectorAll('#mixtureModal [data-bs-dismiss="modal"], #mixtureModal .btn-close').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            hideMixtureModal();
        });
    });

    document.addEventListener('keydown', function (evt) {
        if (evt.key === 'Escape') {
            hideMixtureModal();
        }
    });

    var appRateInput = document.getElementById('application-rate');
    if (appRateInput) {
        maskInputValue(appRateInput, 2);
        appRateInput.addEventListener('input', function () {
            maskInputValue(appRateInput, 2);
            updateMixtureModal();
        });
    }

    var tankInput = document.getElementById('tank-capacity');
    if (tankInput) {
        maskInputValue(tankInput, 2);
        tankInput.addEventListener('input', function () {
            maskInputValue(tankInput, 2);
            updateMixtureModal();
        });
    }

    var saveMixtureBtn = document.getElementById('save-mixture-note');
    if (saveMixtureBtn) {
        saveMixtureBtn.addEventListener('click', function () {
            var noteField = document.getElementById('note_public');
            var summary = buildMixtureSummary();
            if (summary) {
                noteField.value = (noteField.value ? noteField.value + "\n\n" : '') + summary;
            }
            hideMixtureModal();
        });
    }
});

function showMixtureModal() {
    var modalEl = document.getElementById('mixtureModal');
    if (!modalEl) return;

    if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        return;
    }

    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');

    addMixtureBackdrop();
}

function hideMixtureModal() {
    var modalEl = document.getElementById('mixtureModal');
    if (!modalEl) return;

    if (window.bootstrap && bootstrap.Modal) {
        var instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) {
            instance.hide();
        }
        return;
    }

    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');

    removeMixtureBackdrop();
}

function addMixtureBackdrop() {
    if (document.getElementById('mixture-backdrop')) return;
    var backdrop = document.createElement('div');
    backdrop.id = 'mixture-backdrop';
    backdrop.className = 'modal-backdrop fade show';
    document.body.appendChild(backdrop);
}

function removeMixtureBackdrop() {
    var backdrop = document.getElementById('mixture-backdrop');
    if (backdrop && backdrop.parentNode) {
        backdrop.parentNode.removeChild(backdrop);
    }
}

function normalizeUnitForTank(unit) {
    if (!unit) return '';

    var base = unit.toString();
    if (base.indexOf('/') !== -1) {
        base = base.split('/')[0];
    }

    return base.trim();
}

function updateMixtureModal() {
    var rate = parseDecimalFromMask(document.getElementById('application-rate').value, 2) || 0;
    var capacity = parseDecimalFromMask(document.getElementById('tank-capacity').value, 2) || 0;
    var areaPerTank = rate > 0 ? (capacity / rate) : 0;
    document.getElementById('area-per-tank').textContent = formatDecimalToMask(areaPerTank, 2) + ' ha';

    var container = document.getElementById('mixture-lines');
    container.innerHTML = '';
    var hasLine = false;

    document.querySelectorAll('#products-table tbody tr').forEach(function (row) {
        var productSelect = row.querySelector('select[name="product_id[]"]');
        var productLabel = productSelect.options[productSelect.selectedIndex] ? productSelect.options[productSelect.selectedIndex].text : '';
        var dose = parseDecimalFromMask(row.querySelector('.dose-input').value, 4) || 0;
        var unit = row.querySelector('.unit-input').value || '';
        if (!productLabel || dose === 0) {
            return;
        }
        var qty = dose * areaPerTank;
        var item = document.createElement('div');
        item.className = 'line';
        var name = document.createElement('span');
        name.textContent = productLabel;
        name.className = 'text-truncate';
        var amount = document.createElement('span');
        var unitForTank = normalizeUnitForTank(unit);
        var amountText = formatDecimalToMask(qty, 2);
        if (unitForTank) {
            amountText += ' ' + unitForTank;
        }
        amountText += ' ' + perTankSuffix;
        amount.textContent = amountText;
        amount.className = 'fw-semibold';
        item.appendChild(name);
        item.appendChild(amount);
        container.appendChild(item);
        hasLine = true;
    });

    if (!hasLine) {
        var empty = document.createElement('div');
        empty.className = 'text-muted';
        empty.textContent = mixtureEmptyText;
        container.appendChild(empty);
    }
}

function buildMixtureSummary() {
    var rate = parseDecimalFromMask(document.getElementById('application-rate').value, 2) || 0;
    var capacity = parseDecimalFromMask(document.getElementById('tank-capacity').value, 2) || 0;
    var areaPerTank = rate > 0 ? (capacity / rate) : 0;
    var summaryLines = [];
    var itemLines = [];

    summaryLines.push(noteHeader);
    summaryLines.push(noteRateTpl.replace('%s', formatDecimalToMask(rate, 2)));
    summaryLines.push(noteTankTpl.replace('%s', formatDecimalToMask(capacity, 2)));
    summaryLines.push(noteAreaTpl.replace('%s', formatDecimalToMask(areaPerTank, 2)));

    document.querySelectorAll('#products-table tbody tr').forEach(function (row) {
        var productSelect = row.querySelector('select[name="product_id[]"]');
        var productLabel = productSelect.options[productSelect.selectedIndex] ? productSelect.options[productSelect.selectedIndex].text : '';
        var dose = parseDecimalFromMask(row.querySelector('.dose-input').value, 4) || 0;
        var unit = row.querySelector('.unit-input').value || '';
        if (!productLabel || dose === 0) {
            return;
        }
        var qty = dose * areaPerTank;
        var unitForTank = normalizeUnitForTank(unit);
        var amountText = formatDecimalToMask(qty, 2);
        if (unitForTank) {
            amountText += ' ' + unitForTank;
        }
        amountText += ' ' + perTankSuffix;
        itemLines.push(noteItemTpl.replace('%s', productLabel).replace('%s', amountText));
    });

    if (itemLines.length) {
        summaryLines.push(noteItemsHeader);
        summaryLines = summaryLines.concat(itemLines);
    }

    return summaryLines.join("\n");
}
<?php
print '</script>';

llxFooter();
$db->close();
