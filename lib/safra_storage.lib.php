<?php
/*
 * Filesystem helpers for Safra persistent module data.
 */

/**
 * Return the Safra documents root.
 *
 * @return string
 */
function safra_storage_root()
{
    global $conf;

    if (!empty($conf->safra->dir_output)) {
        return rtrim(str_replace('\\', '/', $conf->safra->dir_output), '/');
    }

    if (defined('DOL_DATA_ROOT')) {
        return rtrim(str_replace('\\', '/', DOL_DATA_ROOT), '/') . '/safra';
    }

    return rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/safra';
}

/**
 * Build an absolute path inside Safra documents storage.
 *
 * @param string $relativePath Relative path under the Safra documents root.
 *
 * @return string
 */
function safra_storage_path($relativePath = '')
{
    $relativePath = trim(str_replace('\\', '/', (string) $relativePath), '/');
    $root = safra_storage_root();

    return $relativePath === '' ? $root : $root . '/' . $relativePath;
}

/**
 * Build an absolute path inside Safra JSON storage.
 *
 * @param string $relativePath Relative path under json/.
 *
 * @return string
 */
function safra_json_path($relativePath = '')
{
    $relativePath = trim(str_replace('\\', '/', (string) $relativePath), '/');

    return safra_storage_path($relativePath === '' ? 'json' : 'json/' . $relativePath);
}

/**
 * Ensure a directory exists.
 *
 * @param string $dir Absolute directory path.
 *
 * @return void
 */
function safra_ensure_dir($dir)
{
    if (empty($dir) || is_dir($dir)) {
        return;
    }

    if (function_exists('dol_mkdir')) {
        dol_mkdir($dir);
        if (is_dir($dir)) {
            return;
        }
    }

    @mkdir($dir, 0777, true);
    if (!is_dir($dir) && function_exists('dol_syslog')) {
        dol_syslog(__FUNCTION__ . ' failed to create directory ' . $dir, LOG_ERR);
    }
}

/**
 * Configure cURL SSL verification using the PHP CA config or Dolibarr bundled CA file.
 *
 * @param resource|\CurlHandle $ch cURL handle.
 *
 * @return void
 */
function safra_configure_curl_ssl($ch)
{
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $caCandidates = array(
        ini_get('curl.cainfo'),
        ini_get('openssl.cafile'),
    );

    if (defined('DOL_DOCUMENT_ROOT')) {
        $caCandidates[] = rtrim(str_replace('\\', '/', DOL_DOCUMENT_ROOT), '/') . '/includes/stripe/stripe-php/data/ca-certificates.crt';
    }

    foreach ($caCandidates as $caFile) {
        if (!empty($caFile) && is_readable($caFile)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caFile);
            return;
        }
    }
}

/**
 * Build an absolute path for one satellite JSON file.
 *
 * @param string $folder   Satellite folder name.
 * @param string $fileBase Filename without extension.
 *
 * @return string
 */
function safra_satellite_json_path($folder, $fileBase)
{
    $folder = trim(str_replace('\\', '/', (string) $folder), '/');
    $fileBase = basename((string) $fileBase, '.json');

    return safra_json_path($folder . '/' . $fileBase . '.json');
}

/**
 * Normalize WKT before it is sent to Sentinel Hub.
 *
 * @param string $wkt Raw or URL-encoded WKT.
 *
 * @return string
 */
function safra_normalize_wkt($wkt)
{
    $wkt = trim((string) $wkt);
    if ($wkt === '') {
        return '';
    }

    for ($i = 0; $i < 3 && strpos($wkt, '%') !== false; $i++) {
        $decoded = rawurldecode($wkt);
        if ($decoded === $wkt) {
            break;
        }
        $wkt = trim($decoded);
    }

    $wkt = html_entity_decode($wkt, ENT_QUOTES, 'UTF-8');
    $wktUpper = strtoupper($wkt);
    if (strpos($wktUpper, 'POLYGON') !== 0 && strpos($wktUpper, 'MULTIPOLYGON') !== 0) {
        return '';
    }

    return $wkt;
}

/**
 * Resolve a WKT geometry from a talhao object, falling back to geo_json when needed.
 *
 * @param object $talhao Talhao object.
 *
 * @return string
 */
function safra_satellite_talhao_wkt($talhao)
{
    $wkt = safra_normalize_wkt(isset($talhao->wkt) ? $talhao->wkt : '');
    if ($wkt !== '') {
        return $wkt;
    }

    $geoJson = isset($talhao->geo_json) ? (string) $talhao->geo_json : '';
    if ($geoJson === '') {
        return '';
    }

    dol_include_once('/safra/lib/talhao_geo.lib.php');
    if (!function_exists('safra_talhao_extract_polygons_from_geojson') || !function_exists('safra_talhao_polygons_to_wkt')) {
        return '';
    }

    $polygons = safra_talhao_extract_polygons_from_geojson($geoJson);
    if (empty($polygons)) {
        return '';
    }

    return safra_talhao_polygons_to_wkt($polygons);
}

/**
 * Check if a satellite JSON payload is valid and non-empty.
 *
 * @param mixed $payload JSON payload returned by the satellite provider.
 *
 * @return bool
 */
function safra_satellite_json_is_valid_payload($payload)
{
    if (!is_string($payload) || trim($payload) === '') {
        return false;
    }

    $decoded = json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return false;
    }

    return isset($decoded['type'], $decoded['features'])
        && strtolower((string) $decoded['type']) === 'featurecollection'
        && is_array($decoded['features']);
}

/**
 * Check if a satellite JSON file can be safely served to the browser.
 *
 * @param string $path Absolute file path.
 *
 * @return bool
 */
function safra_satellite_json_is_valid_file($path)
{
    if (empty($path) || !is_file($path) || @filesize($path) === 0) {
        return false;
    }

    $payload = @file_get_contents($path);

    return safra_satellite_json_is_valid_payload($payload);
}

/**
 * Write a validated satellite JSON file.
 *
 * @param string $folder       Satellite folder name.
 * @param string $fileBase     Filename without extension.
 * @param mixed  $payload      JSON payload returned by the satellite provider.
 * @param string $errorMessage Filled with a short error code when the write fails.
 *
 * @return string|false Absolute written path or false on failure.
 */
function safra_write_satellite_json_file($folder, $fileBase, $payload, &$errorMessage = '')
{
    $errorMessage = '';

    if (!safra_satellite_json_is_valid_payload($payload)) {
        $errorMessage = is_string($payload) && trim($payload) !== '' ? 'invalid_json' : 'empty_response';
        return false;
    }

    $path = safra_satellite_json_path($folder, $fileBase);
    safra_ensure_dir(dirname($path));

    if (@file_put_contents($path, $payload, LOCK_EX) === false) {
        $errorMessage = 'write_failed';
        if (function_exists('dol_syslog')) {
            dol_syslog(__FUNCTION__ . ' failed to write satellite JSON ' . $path, LOG_ERR);
        }
        return false;
    }

    return $path;
}

/**
 * Build the legacy satellite JSON path used before persistent documents storage.
 *
 * @param string $folder   Satellite folder name.
 * @param string $fileBase Filename without extension.
 *
 * @return string
 */
function safra_legacy_satellite_json_path($folder, $fileBase)
{
    $folder = trim(str_replace('\\', '/', (string) $folder), '/');
    $fileBase = basename((string) $fileBase, '.json');

    return rtrim(str_replace('\\', '/', DOL_DOCUMENT_ROOT), '/') . '/custom/safra/json/' . $folder . '/' . $fileBase . '.json';
}

/**
 * Resolve a satellite JSON path, migrating a legacy htdocs file on first read.
 *
 * @param string $folder   Satellite folder name.
 * @param string $fileBase Filename without extension.
 *
 * @return string
 */
function safra_resolve_satellite_json_path($folder, $fileBase)
{
    $path = safra_satellite_json_path($folder, $fileBase);
    if (safra_satellite_json_is_valid_file($path)) {
        return $path;
    }

    $legacyPath = safra_legacy_satellite_json_path($folder, $fileBase);
    if (safra_satellite_json_is_valid_file($legacyPath)) {
        safra_ensure_dir(dirname($path));
        if (@copy($legacyPath, $path) && safra_satellite_json_is_valid_file($path)) {
            return $path;
        }
    }

    return $path;
}

/**
 * Return the authenticated URL used by browser code to read a satellite JSON file.
 *
 * @param string $index    Satellite index code.
 * @param string $fileBase Filename without extension.
 *
 * @return string
 */
function safra_satellite_json_url($index, $fileBase)
{
    $index = strtolower((string) $index);
    $fileBase = basename((string) $fileBase, '.json');
    $endpoint = function_exists('dol_buildpath') ? dol_buildpath('/safra/satellite_json.php', 1) : './satellite_json.php';

    return $endpoint . '?index=' . rawurlencode($index) . '&file=' . rawurlencode($fileBase);
}
