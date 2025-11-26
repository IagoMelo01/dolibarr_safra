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

$movementTypes = array(
    'consume' => $langs->trans('Consume'),
    'return' => $langs->trans('Return'),
    'transfer' => $langs->trans('Transfer'),
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
print '<style>
    .safra-activity-card {
        border-radius: 18px;
        overflow: hidden;
    }
    .safra-activity-card .card-header {
        background: linear-gradient(120deg, #0d6efd, #20c997);
        color: #fff;
        border-bottom: none;
        padding: 1rem 1.25rem;
    }
    .safra-activity-card .card-header .btn-light {
        color: #0d6efd;
        background: #e7f1ff;
        border: none;
        font-weight: 600;
        box-shadow: 0 6px 18px rgba(13, 110, 253, 0.18);
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
    .safra-activity-card .section-title .badge {
        font-weight: 500;
    }
    .safra-activity-card .floating-box {
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 1rem;
    }
    .safra-activity-card .form-control, .safra-activity-card select {
        border-radius: 0.65rem;
        border-color: #e5e7eb;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05);
    }
    .safra-activity-card .form-control:focus, .safra-activity-card select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
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
        background: #20c997;
    }
    .safra-activity-card .note-area {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 0.75rem;
    }
    .safra-activity-card .table-modern thead th {
        background: #0f172a;
        color: #fff;
        border: none;
    }
    .safra-activity-card .table-modern tbody tr {
        transition: background 0.2s ease, transform 0.2s ease;
    }
    .safra-activity-card .table-modern tbody tr:hover {
        background: #f1f5f9;
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
</style>';

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

print '<div class="row g-4 mt-1">';
print '<div class="col-lg-8">';
print '<div class="floating-box h-100">';
print '<label class="section-title mb-1">' . $langs->trans('FieldPlot') . '</label>';
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
print '<div class="helper-text mt-1">' . $langs->trans('AutoFilledFromFieldPlot') . '</div>';
print '</div>';
print '</div>';
print '<div class="col-lg-4">';
print '<div class="floating-box h-100">';
print '<label class="section-title mb-1">' . $langs->trans('Area') . ' (ha)</label>';
print '<input class="form-control" name="area_total" value="' . dol_escape_htmltag(price2num($activity->area_total)) . '" step="0.0001" type="number" min="0" placeholder="0.00" readonly>';
print '<div class="helper-text mt-2">' . $langs->trans('AutoFilledFromFieldPlot') . '</div>';
print '</div>';
print '</div>';
print '</div>';

print '<div class="row g-4 mt-1">';
print '<div class="col-lg-4">';
print '<label class="section-title">' . $langs->trans('Machine') . '</label>';
print $form->multiselectarray('machine_ids', $machines, $selectedMachines, '', 0, '', 1);
print '</div>';
print '<div class="col-lg-4">';
print '<label class="section-title">' . $langs->trans('Implements') . '</label>';
print $form->multiselectarray('implement_ids', $implements, $selectedImplements, '', 0, '', 1);
print '</div>';
print '<div class="col-lg-4">';
print '<label class="section-title">' . $langs->trans('Employees') . '</label>';
print $form->multiselectarray('user_ids', $userOptions, $selectedUsers, '', 0, '', 1);
print '</div>';
print '</div>';

print '<div class="row g-3 mt-3">';
print '<div class="col-12">';
print '<label class="section-title">' . $langs->trans('Note') . '</label>';
print '<textarea class="form-control note-area" name="note_public" id="note_public" rows="3" placeholder="' . dol_escape_htmltag($langs->trans('Note')) . '">' . dol_escape_htmltag($activity->note_public) . '</textarea>';
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
print '<button type="button" class="btn btn-light btn-sm" id="open-mixture">' . $langs->trans('MixtureCalculation') . '</button>';
print '</div>';
print '<div class="card-body">';

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
        print '<tr>';
        print '<td>' . $form->selectarray('product_id[]', $productOptions, $line->fk_product, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
        print '<td><input type="number" name="line_area[]" class="form-control area-input" step="0.0001" value="' . dol_escape_htmltag(price2num($line->area_applied)) . '"></td>';
        print '<td><input type="number" name="line_dose[]" class="form-control dose-input" step="0.0001" value="' . dol_escape_htmltag(price2num($line->dose)) . '"></td>';
        print '<td><input type="text" name="line_unit[]" class="form-control unit-input" value="' . dol_escape_htmltag($line->dose_unit) . '"></td>';
        print '<td><input type="number" name="line_total[]" class="form-control total-input" step="0.0001" value="' . dol_escape_htmltag(price2num($line->total)) . '" readonly></td>';
        print '<td>' . $form->selectarray('line_movement[]', $movementTypes, $line->movement_type, 1) . '</td>';
        print '<td>' . $form->selectarray('line_warehouse[]', $warehouses, $line->fk_warehouse, 1, 0, 0, '', 0, 0, 0, '', 'minwidth150') . '</td>';
        print '<td><button type="button" class="btn btn-outline-danger btn-icon remove-line" aria-label="' . dol_escape_htmltag($langs->trans('Delete')) . '">&times;</button></td>';
        print '</tr>';
    }
} else {
    print '<tr>';
    print '<td>' . $form->selectarray('product_id[]', $productOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200') . '</td>';
    print '<td><input type="number" name="line_area[]" class="form-control area-input" step="0.0001" value="0"></td>';
    print '<td><input type="number" name="line_dose[]" class="form-control dose-input" step="0.0001" value="0"></td>';
    print '<td><input type="text" name="line_unit[]" class="form-control unit-input" value="L/ha"></td>';
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
print '<td><input type="number" name="line_area[]" class="form-control area-input" step="0.0001" value="0"></td>';
print '<td><input type="number" name="line_dose[]" class="form-control dose-input" step="0.0001" value="0"></td>';
print '<td><input type="text" name="line_unit[]" class="form-control unit-input" value="L/ha"></td>';
print '<td><input type="number" name="line_total[]" class="form-control total-input" step="0.0001" value="0" readonly></td>';
print '<td>' . $form->selectarray('line_movement[]', $movementTypes, 'consume', 1) . '</td>';
print '<td>' . $form->selectarray('line_warehouse[]', $warehouses, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth150') . '</td>';
print '<td><button type="button" class="btn btn-outline-danger btn-icon remove-line" aria-label="' . dol_escape_htmltag($langs->trans('Delete')) . '">&times;</button></td>';
print '</tr>';
print '</template>';
print '</div>'; // responsive

print '<button type="button" class="btn btn-primary btn-sm" id="add-line">+ ' . $langs->trans('Add') . '</button>';
print '</div>'; // card-body
print '</div>'; // card

print '<div class="mt-3">';
print '<button class="btn btn-success" type="submit">' . $langs->trans('Save') . '</button>';
print '</div>';

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
<div class="modal fade" id="mixtureModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo dol_escape_htmltag($langs->trans('MixtureCalculation')); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo $langs->trans('ApplicationRate'); ?> (L/ha)</label>
                    <input type="number" class="form-control" id="application-rate" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label><?php echo $langs->trans('TankCapacity'); ?> (L)</label>
                    <input type="number" class="form-control" id="tank-capacity" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label><?php echo $langs->trans('AppliedAreaPerTank'); ?></label>
                    <input type="text" class="form-control" id="area-per-tank" readonly>
                </div>
                <div class="form-group">
                    <label><?php echo $langs->trans('QuantityPerTank'); ?></label>
                    <div id="mixture-lines"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $langs->trans('Cancel'); ?></button>
                <button type="button" class="btn btn-success" id="save-mixture-note"><?php echo $langs->trans('SaveAsNote'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php
print '<script>';
?>
var talhaoData = <?php echo json_encode($talhaoDetails); ?>;
var talhaoPlaceholderText = '<?php echo dol_escape_js($langs->trans('SelectProjectToAutoFillFieldPlot')); ?>';
var talhaoAreaDefault = '<?php echo dol_escape_js($langs->trans('Area')); ?>';

function recalcLineTotal(row) {
    var area = parseFloat(row.querySelector('.area-input').value) || 0;
    var dose = parseFloat(row.querySelector('.dose-input').value) || 0;
    var total = dose * area;
    row.querySelector('.total-input').value = total.toFixed(4);
}

function bindLine(row) {
    row.querySelectorAll('.area-input, .dose-input').forEach(function (input) {
        input.addEventListener('input', function () {
            recalcLineTotal(row);
        });
    });
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
    var areaField = document.querySelector('input[name="area_total"]');
    var info = document.getElementById('talhao-area-info');
    var hiddenField = document.querySelector('input[name="fk_fieldplot"]');
    var data = talhaoData[talhaoId] || null;

    if (hiddenField) {
        hiddenField.value = data ? talhaoId : '';
    }

    if (areaField) {
        var normalizedArea = data && data.area ? parseFloat(data.area) || 0 : 0;
        areaField.value = normalizedArea ? normalizedArea.toFixed(4) : '';
    }

    if (info) {
        if (data) {
            var labelParts = [];
            if (data.ref) labelParts.push(data.ref);
            if (data.label) labelParts.push(data.label);
            var baseLabel = labelParts.join(' - ');
            var areaText = data.area ? (parseFloat(data.area).toFixed(4) + ' ha') : talhaoAreaDefault;
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
}

function fetchTalhaoForProject(projectId) {
    if (!projectId) {
        updateTalhaoArea('');
        return;
    }

    updateTalhaoArea('');

    fetch('<?php echo dol_buildpath('/safra/ajax/project_talhao.php', 1); ?>?id=' + encodeURIComponent(projectId), {
        credentials: 'same-origin'
    })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (!data || !data.success || !data.talhao_id) {
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
        .catch(function () {
            updateTalhaoArea('');
        });
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
        recalcLineTotal(row);
        bindLine(row);
    });

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
        recalcLineTotal(clone);
        tbody.appendChild(clone);
        bindLine(clone);
    });

    var projectSelect = document.querySelector('select[name="fk_project"]');
    if (projectSelect) {
        projectSelect.addEventListener('change', function () {
            fetchTalhaoForProject(projectSelect.value);
        });
        fetchTalhaoForProject(projectSelect.value || '');
    }

    updateTalhaoArea(document.querySelector('input[name="fk_fieldplot"]').value || '');

    var mixtureButton = document.getElementById('open-mixture');
    if (mixtureButton) {
        mixtureButton.addEventListener('click', function () {
            updateMixtureModal();
            if (window.bootstrap && bootstrap.Modal) {
                var modal = new bootstrap.Modal(document.getElementById('mixtureModal'));
                modal.show();
            }
        });
    }

    document.getElementById('application-rate').addEventListener('input', updateMixtureModal);
    document.getElementById('tank-capacity').addEventListener('input', updateMixtureModal);

    document.getElementById('save-mixture-note').addEventListener('click', function () {
        var noteField = document.getElementById('note_public');
        var summary = buildMixtureSummary();
        if (summary) {
            noteField.value = (noteField.value ? noteField.value + "\n\n" : '') + summary;
        }
        var modal = bootstrap.Modal.getInstance(document.getElementById('mixtureModal'));
        modal.hide();
    });
});

function updateMixtureModal() {
    var rate = parseFloat(document.getElementById('application-rate').value) || 0;
    var capacity = parseFloat(document.getElementById('tank-capacity').value) || 0;
    var areaPerTank = rate > 0 ? (capacity / rate) : 0;
    document.getElementById('area-per-tank').value = areaPerTank.toFixed(2);

    var container = document.getElementById('mixture-lines');
    container.innerHTML = '';

    document.querySelectorAll('#products-table tbody tr').forEach(function (row) {
        var productSelect = row.querySelector('select[name="product_id[]"]');
        var productLabel = productSelect.options[productSelect.selectedIndex] ? productSelect.options[productSelect.selectedIndex].text : '';
        var dose = parseFloat(row.querySelector('.dose-input').value) || 0;
        var unit = row.querySelector('.unit-input').value || '';
        if (!productLabel || dose === 0) {
            return;
        }
        var qty = dose * areaPerTank;
        var item = document.createElement('div');
        item.textContent = productLabel + ': ' + qty.toFixed(2) + ' ' + (unit || '');
        container.appendChild(item);
    });
}

function buildMixtureSummary() {
    var rate = parseFloat(document.getElementById('application-rate').value) || 0;
    var capacity = parseFloat(document.getElementById('tank-capacity').value) || 0;
    var areaPerTank = rate > 0 ? (capacity / rate) : 0;
    var lines = [];
    document.querySelectorAll('#products-table tbody tr').forEach(function (row) {
        var productSelect = row.querySelector('select[name="product_id[]"]');
        var productLabel = productSelect.options[productSelect.selectedIndex] ? productSelect.options[productSelect.selectedIndex].text : '';
        var dose = parseFloat(row.querySelector('.dose-input').value) || 0;
        var unit = row.querySelector('.unit-input').value || '';
        if (!productLabel || dose === 0) {
            return;
        }
        var qty = dose * areaPerTank;
        lines.push(productLabel + ' = ' + qty.toFixed(2) + ' ' + (unit || ''));
    });
    if (!lines.length) {
        return '';
    }
    var summary = 'Taxa: ' + rate.toFixed(2) + ' L/ha; Capacidade: ' + capacity.toFixed(2) + ' L; Área/tanque: ' + areaPerTank.toFixed(2) + ' ha';
    summary += "\n" + lines.join("\n");
    return summary;
}
<?php
print '</script>';

llxFooter();
$db->close();
