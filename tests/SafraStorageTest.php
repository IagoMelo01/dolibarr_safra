<?php
declare(strict_types=1);

require_once __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/lib/safra_storage.lib.php';

$previousOutput = $conf->safra->dir_output;
$testRoot = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/safra_storage_test_'.uniqid();
$conf->safra->dir_output = $testRoot;

$expectedRoot = $testRoot;
if (safra_storage_root() !== $expectedRoot) {
    throw new RuntimeException('Safra storage root must use conf->safra->dir_output.');
}

$jsonPath = safra_json_path('cache/token.json');
if ($jsonPath !== $expectedRoot.'/json/cache/token.json') {
    throw new RuntimeException('Safra JSON cache path is not under documents storage.');
}

$satellitePath = safra_satellite_json_path('ndvi', '2026-05-17_2026-05-23_1');
if ($satellitePath !== $expectedRoot.'/json/ndvi/2026-05-17_2026-05-23_1.json') {
    throw new RuntimeException('Safra satellite JSON path is not under documents storage.');
}

$rawWkt = 'POLYGON((-50.00000000 -20.00000000, -50.10000000 -20.00000000, -50.10000000 -20.10000000, -50.00000000 -20.00000000))';
$encodedWkt = rawurlencode($rawWkt);
if (safra_normalize_wkt($encodedWkt) !== $rawWkt) {
    throw new RuntimeException('Encoded talhao WKT must be normalized before Sentinel requests.');
}
if (safra_satellite_talhao_wkt((object) array('wkt' => $encodedWkt)) !== $rawWkt) {
    throw new RuntimeException('Talhao WKT helper must accept legacy encoded WKT.');
}

$geoJsonTalhao = (object) array(
    'wkt' => '',
    'geo_json' => '{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[-50,-20],[-50.1,-20],[-50.1,-20.1],[-50,-20]]]},"properties":{}}',
);
$expectedGeoJsonWkt = 'POLYGON((-50.00000000 -20.00000000, -50.10000000 -20.00000000, -50.10000000 -20.10000000, -50.00000000 -20.00000000))';
if (safra_satellite_talhao_wkt($geoJsonTalhao) !== $expectedGeoJsonWkt) {
    throw new RuntimeException('Talhao WKT helper must derive WKT from GeoJSON when WKT is empty.');
}

$featureCollectionTalhao = (object) array(
    'wkt' => '',
    'geo_json' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[-50,-20],[-50.1,-20],[-50.1,-20.1],[-50,-20]]]},"properties":{}}]}',
);
if (safra_satellite_talhao_wkt($featureCollectionTalhao) !== $expectedGeoJsonWkt) {
    throw new RuntimeException('Talhao WKT helper must derive WKT from GeoJSON FeatureCollection.');
}

safra_ensure_dir(dirname($satellitePath));
if (!is_dir(dirname($satellitePath))) {
    throw new RuntimeException('Safra storage helper did not create target directory.');
}

$validPayload = '{"type":"FeatureCollection","features":[]}';
$invalidPayload = '';

if (safra_satellite_json_is_valid_payload($invalidPayload)) {
    throw new RuntimeException('Empty satellite JSON payload must be rejected.');
}
if (!safra_satellite_json_is_valid_payload($validPayload)) {
    throw new RuntimeException('Valid satellite GeoJSON payload must be accepted.');
}
if (safra_satellite_json_is_valid_payload('{"error":"provider failure"}')) {
    throw new RuntimeException('Provider error JSON must not be accepted as satellite GeoJSON.');
}

$writeError = '';
$invalidWrite = safra_write_satellite_json_file('ndvi', '2026-05-24_2026-05-30_1', $invalidPayload, $writeError);
if ($invalidWrite !== false || $writeError !== 'empty_response') {
    throw new RuntimeException('Empty satellite JSON payload must not be written.');
}

$writtenPath = safra_write_satellite_json_file('ndvi', '2026-05-24_2026-05-30_1', $validPayload, $writeError);
if ($writtenPath === false || !safra_satellite_json_is_valid_file($writtenPath)) {
    throw new RuntimeException('Valid satellite JSON payload must be written under documents storage.');
}

$legacyBase = '2026-05-31_2026-06-06_1';
$emptyDocumentsPath = safra_satellite_json_path('ndvi', $legacyBase);
safra_ensure_dir(dirname($emptyDocumentsPath));
file_put_contents($emptyDocumentsPath, '');

$legacyPath = safra_legacy_satellite_json_path('ndvi', $legacyBase);
safra_ensure_dir(dirname($legacyPath));
file_put_contents($legacyPath, $validPayload);

$resolvedPath = safra_resolve_satellite_json_path('ndvi', $legacyBase);
if ($resolvedPath !== $emptyDocumentsPath || !safra_satellite_json_is_valid_file($resolvedPath)) {
    throw new RuntimeException('Invalid documents satellite JSON must be replaced from a valid legacy file.');
}

@unlink($satellitePath);
@unlink($writtenPath);
@unlink($emptyDocumentsPath);
@unlink($legacyPath);
@rmdir(dirname($legacyPath));
@rmdir(dirname(dirname($legacyPath)));
@rmdir(dirname(dirname(dirname($legacyPath))));
@rmdir(dirname($satellitePath));
@rmdir($expectedRoot.'/json');
@rmdir($expectedRoot);

$conf->safra->dir_output = $previousOutput;

return true;
