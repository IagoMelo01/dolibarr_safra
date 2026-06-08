<?php
/*
 * Authenticated satellite JSON reader for files stored in Dolibarr documents.
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
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    http_response_code(500);
    exit('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/custom/safra/lib/safra_storage.lib.php';

$index = strtolower(GETPOST('index', 'aZ09'));
$fileBase = basename(GETPOST('file', 'alphanohtml'), '.json');

$folders = array(
    'ndvi' => 'ndvi',
    'ndmi' => 'ndmi',
    'ndwi' => 'ndwi',
    'evi' => 'evi',
    'swir' => 'swir',
    'health' => 'saude_geral',
);

if (empty($folders[$index]) || !preg_match('/^\d{4}-\d{2}-\d{2}_\d{4}-\d{2}-\d{2}_\d+$/', $fileBase)) {
    accessforbidden('Bad satellite JSON request');
}

$hasReadRight = false;
if ($index === 'health') {
    $hasReadRight = $user->hasRight('safra', 'ndvi', 'read')
        || $user->hasRight('safra', 'ndmi', 'read')
        || $user->hasRight('safra', 'swir', 'read');
} else {
    $hasReadRight = $user->hasRight('safra', $index, 'read');
}

if (!$user->admin && !$hasReadRight) {
    accessforbidden();
}

$path = safra_resolve_satellite_json_path($folders[$index], $fileBase);
$debug = (int) GETPOST('debug', 'int');
if ($debug && !empty($user->admin)) {
    top_httphead('application/json; charset=utf-8');
    echo json_encode(safra_satellite_json_file_status($folders[$index], $fileBase), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!safra_satellite_json_is_valid_file($path)) {
    http_response_code(404);
    exit('Satellite JSON not found or invalid');
}

top_httphead('application/json; charset=utf-8');
header('Content-Disposition: inline; filename="' . basename($path) . '"');
readfile($path);
