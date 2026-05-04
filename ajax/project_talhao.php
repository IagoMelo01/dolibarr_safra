<?php
/* Return Safra context linked to a project/task as JSON. */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
if (!$res && file_exists(__DIR__ . '/../../../main.inc.php')) {
    $res = @include __DIR__ . '/../../../main.inc.php';
}
if (!$res) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('success' => false, 'error' => 'bootstrap_failed'));
    exit;
}

if (!isModEnabled('safra')) {
    accessforbidden();
}

header('Content-Type: application/json; charset=utf-8');

function safraAjaxColumnExists(DoliDB $db, $tablename, $columnname)
{
    static $cache = array();

    $key = $tablename . ':' . $columnname;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $sql = 'SHOW COLUMNS FROM ' . $db->prefix() . $db->escape($tablename) . " LIKE '" . $db->escape($columnname) . "'";
    $res = $db->query($sql);
    $cache[$key] = false;
    if ($res) {
        $cache[$key] = ((int) $db->num_rows($res) > 0);
        $db->free($res);
    }

    return $cache[$key];
}

function safraAjaxTaskProjectId(DoliDB $db, $taskId)
{
    $taskId = (int) $taskId;
    if ($taskId <= 0) {
        return 0;
    }

    $projectColumn = safraAjaxColumnExists($db, 'projet_task', 'fk_projet') ? 'fk_projet' : (safraAjaxColumnExists($db, 'projet_task', 'fk_project') ? 'fk_project' : '');
    if ($projectColumn === '') {
        return 0;
    }

    $sql = 'SELECT ' . $projectColumn . ' as project_id FROM ' . $db->prefix() . 'projet_task WHERE rowid = ' . $taskId . ' LIMIT 1';
    $res = $db->query($sql);
    if (!$res) {
        return 0;
    }

    $obj = $db->fetch_object($res);
    $db->free($res);

    return $obj ? (int) $obj->project_id : 0;
}

function safraAjaxExtraValue(DoliDB $db, $table, $objectId, $columns)
{
    $objectId = (int) $objectId;
    if ($objectId <= 0) {
        return 0;
    }

    foreach ((array) $columns as $column) {
        if (!safraAjaxColumnExists($db, $table, $column)) {
            continue;
        }

        $sql = 'SELECT ' . $column . ' as value FROM ' . $db->prefix() . $table . ' WHERE fk_object = ' . $objectId . ' LIMIT 1';
        $res = $db->query($sql);
        if (!$res) {
            continue;
        }

        $obj = $db->fetch_object($res);
        $db->free($res);
        if ($obj && (int) $obj->value > 0) {
            return (int) $obj->value;
        }
    }

    return 0;
}

function safraAjaxReference(DoliDB $db, $table, $id, $extraColumns = array())
{
    $id = (int) $id;
    if ($id <= 0 || !safraAjaxColumnExists($db, $table, 'rowid')) {
        return null;
    }

    $columns = array('rowid');
    foreach (array('ref', 'label') as $column) {
        if (safraAjaxColumnExists($db, $table, $column)) {
            $columns[] = $column;
        }
    }
    foreach ((array) $extraColumns as $column) {
        if (safraAjaxColumnExists($db, $table, $column)) {
            $columns[] = $column;
        }
    }

    $sql = 'SELECT ' . implode(', ', array_unique($columns)) . ' FROM ' . $db->prefix() . $table . ' WHERE rowid = ' . $id . ' LIMIT 1';
    $res = $db->query($sql);
    if (!$res) {
        return null;
    }

    $obj = $db->fetch_object($res);
    $db->free($res);
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
    foreach ((array) $extraColumns as $column) {
        $data[$column] = isset($obj->{$column}) ? $obj->{$column} : null;
    }

    return $data;
}

$projectId = (int) GETPOST('id', 'int');
$taskId = (int) GETPOST('task_id', 'int');
if ($projectId <= 0 && $taskId > 0) {
    $projectId = safraAjaxTaskProjectId($db, $taskId);
}
if ($projectId <= 0) {
    echo json_encode(array('success' => false, 'error' => 'bad_id'));
    exit;
}

$talhaoId = safraAjaxExtraValue($db, 'projet_extrafields', $projectId, array('fk_talhao', 'options_fk_talhao'));
$culturaId = safraAjaxExtraValue($db, 'projet_extrafields', $projectId, array('fk_cultura', 'options_fk_cultura'));
$cultivarId = safraAjaxExtraValue($db, 'projet_extrafields', $projectId, array('fk_cultivar', 'options_fk_cultivar'));

$talhao = $talhaoId > 0 ? safraAjaxReference($db, 'safra_talhao', $talhaoId, array('area')) : null;
$cultura = $culturaId > 0 ? safraAjaxReference($db, 'safra_cultura', $culturaId) : null;
$cultivar = $cultivarId > 0 ? safraAjaxReference($db, 'safra_cultivar', $cultivarId, array('cultivar')) : null;
if ($cultivar && empty($cultivar['label']) && !empty($cultivar['cultivar'])) {
    $cultivar['label'] = $cultivar['cultivar'];
    $cultivar['display'] = trim(($cultivar['ref'] !== '' ? $cultivar['ref'] . ' - ' : '') . $cultivar['label']);
}

echo json_encode(array(
    'success' => !empty($talhao) || !empty($cultura) || !empty($cultivar),
    'project_id' => $projectId,
    'task_id' => $taskId,
    'talhao_id' => $talhaoId,
    'cultura_id' => $culturaId,
    'cultivar_id' => $cultivarId,
    'talhao' => $talhao,
    'cultura' => $cultura,
    'cultivar' => $cultivar,
));
exit;
