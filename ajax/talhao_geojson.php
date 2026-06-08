<?php
// Returns a field plot geometry as JSON.

require_once __DIR__ . '/../../../main.inc.php';

if (!defined('NOSCAN')) {
    define('NOSCAN', 1);
}

if (empty($user->id)) {
    safraAjaxTalhaoJson(array('error' => 'Acesso negado.'));
}

$rawId = trim((string) GETPOST('id', 'alphanohtml'));
$rawLabel = trim((string) GETPOST('label', 'alphanohtml'));
$id = (int) $rawId;

if ($rawId === '') {
    safraAjaxTalhaoJson(array('error' => 'ID invalido.'));
}

dol_include_once('/safra/class/talhao.class.php');

$talhao = new Talhao($db);
$result = $id > 0 ? $talhao->fetch($id) : 0;
if ($result <= 0) {
    $talhao = safraAjaxFetchTalhao($db, $rawId, $rawLabel);
}

if (!empty($talhao) && empty($talhao->rowid) && !empty($talhao->id)) {
    $talhao->rowid = $talhao->id;
}

if (empty($talhao) || (empty($talhao->rowid) && empty($talhao->id))) {
    safraAjaxTalhaoJson(array('error' => 'Talhao nao encontrado.'));
}

$geometryRaw = trim((string) $talhao->geo_json);
if ($geometryRaw === '' && !empty($talhao->wkt)) {
    $geometryRaw = trim((string) $talhao->wkt);
}

$response = array(
    'id' => (int) (!empty($talhao->rowid) ? $talhao->rowid : $talhao->id),
    'ref' => (string) $talhao->ref,
    'label' => (string) $talhao->label,
    'geometry' => $geometryRaw,
);

if ($geometryRaw !== '') {
    $decoded = json_decode($geometryRaw, true);
    if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
        $response['geojson'] = $decoded;
        $response['format'] = 'geojson';
    } else {
        $response['format'] = 'wkt';
    }
} else {
    $response['format'] = 'empty';
}

safraAjaxTalhaoJson($response);

/**
 * Fetch a field plot even when the Dolibarr select sends a ref-like value.
 *
 * @param DoliDB $db
 * @param string $rawId
 * @param string $rawLabel
 * @return stdClass|null
 */
function safraAjaxFetchTalhao($db, $rawId, $rawLabel)
{
    $candidates = array();
    foreach (array($rawId, $rawLabel) as $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $candidates[$value] = $value;
        }
    }

    if ($rawLabel !== '') {
        foreach (array(' - ', ' | ', ' / ') as $separator) {
            if (strpos($rawLabel, $separator) !== false) {
                $parts = explode($separator, $rawLabel);
                $first = trim((string) $parts[0]);
                if ($first !== '') {
                    $candidates[$first] = $first;
                }
            }
        }
    }

    $where = array();
    if ((int) $rawId > 0) {
        $where[] = 'rowid = '.((int) $rawId);
    }

    foreach ($candidates as $candidate) {
        $escaped = $db->escape($candidate);
        $where[] = "ref = '".$escaped."'";
        $where[] = "label = '".$escaped."'";
        $where[] = "CONCAT(ref, ' - ', label) = '".$escaped."'";
    }

    if (empty($where)) {
        return null;
    }

    $sql = 'SELECT rowid, ref, label, geo_json, wkt, bbox, center';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'safra_talhao';
    $sql .= ' WHERE '.implode(' OR ', $where);
    $sql .= ' LIMIT 1';

    $resql = $db->query($sql);
    if (!$resql) {
        return null;
    }

    $obj = $db->fetch_object($resql);
    $db->free($resql);

    return $obj ?: null;
}

/**
 * Output JSON and stop.
 *
 * @param array $payload
 * @return void
 */
function safraAjaxTalhaoJson(array $payload)
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
