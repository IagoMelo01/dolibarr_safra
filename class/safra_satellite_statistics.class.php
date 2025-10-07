<?php
/*
 * Helper for Sentinel Hub statistics with caching for Safra module
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
dol_include_once('/safra/class/talhao.class.php');

class SafraSatelliteStatistics
{
    private const CACHE_BASE = '/custom/safra/json/cache';
    private const TOKEN_FILENAME = 'token.json';

    private static $indexConfig = array(
        'ndvi' => array(
            'inputs' => array('B04', 'B08'),
            'formula' => '(samples.B08 - samples.B04) / (samples.B08 + samples.B04)',
            'range' => array('min' => -0.2, 'max' => 1),
            'color' => '#2563eb',
            'gradient' => array('rgba(37, 99, 235, 0.32)', 'rgba(37, 99, 235, 0.06)'),
            'decimals' => 3,
        ),
        'ndmi' => array(
            'inputs' => array('B08', 'B11'),
            'formula' => '(samples.B08 - samples.B11) / (samples.B08 + samples.B11)',
            'range' => array('min' => -0.2, 'max' => 1),
            'color' => '#16a34a',
            'gradient' => array('rgba(22, 163, 74, 0.32)', 'rgba(22, 163, 74, 0.05)'),
            'decimals' => 3,
        ),
        'ndwi' => array(
            'inputs' => array('B03', 'B08'),
            'formula' => '(samples.B03 - samples.B08) / (samples.B03 + samples.B08)',
            'range' => array('min' => -0.2, 'max' => 1),
            'color' => '#0284c7',
            'gradient' => array('rgba(2, 132, 199, 0.28)', 'rgba(2, 132, 199, 0.05)'),
            'decimals' => 3,
        ),
        'evi' => array(
            'inputs' => array('B02', 'B04', 'B08'),
            'formula' => '2.5 * (samples.B08 - samples.B04) / (samples.B08 + 6 * samples.B04 - 7.5 * samples.B02 + 1)',
            'range' => array('min' => -0.1, 'max' => 1),
            'color' => '#9333ea',
            'gradient' => array('rgba(147, 51, 234, 0.28)', 'rgba(147, 51, 234, 0.05)'),
            'decimals' => 3,
        ),
        'swir' => array(
            'inputs' => array('B11', 'B12'),
            'formula' => '(samples.B11 - samples.B12) / (samples.B11 + samples.B12)',
            'range' => array('min' => -0.4, 'max' => 0.6),
            'color' => '#f97316',
            'gradient' => array('rgba(249, 115, 22, 0.28)', 'rgba(249, 115, 22, 0.05)'),
            'decimals' => 3,
        ),
    );

    /**
     * Get weekly time series for the given index and talhão.
     *
     * @param DoliDB $db
     * @param int    $talhaoId
     * @param string $index
     * @param int    $weeks
     *
     * @return array
     */
    public static function getWeeklySeries(DoliDB $db, $talhaoId, $index, $weeks = 8)
    {
        $index = strtolower((string) $index);
        if (!isset(self::$indexConfig[$index])) {
            return array('points' => array(), 'message' => 'unsupported_index', 'index' => $index, 'talhaoId' => (int) $talhaoId);
        }

        $weeks = max(1, min(52, (int) $weeks));

        $talhao = new Talhao($db);
        if ($talhao->fetch((int) $talhaoId) <= 0) {
            return array('points' => array(), 'message' => 'talhao_not_found', 'index' => $index, 'talhaoId' => (int) $talhaoId);
        }

        $geometry = self::extractGeometry($talhao);
        if (empty($geometry)) {
            return array(
                'points' => array(),
                'message' => 'missing_geometry',
                'index' => $index,
                'talhaoId' => (int) $talhaoId,
            );
        }

        $cacheFile = self::getCacheFilePath($index, (int) $talhaoId);
        $cacheData = self::readJson($cacheFile);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if (!empty($cacheData['validUntil'])) {
            try {
                $validUntil = new DateTimeImmutable($cacheData['validUntil']);
                if ($validUntil > $now && !empty($cacheData['points']) && is_array($cacheData['points'])) {
                    $cacheData['index'] = $index;
                    $cacheData['talhaoId'] = (int) $talhaoId;

                    return $cacheData;
                }
            } catch (Exception $e) {
                // Ignore invalid cache expiration date
            }
        }

        $token = self::getAccessToken();
        if (empty($token)) {
            return array(
                'points' => isset($cacheData['points']) && is_array($cacheData['points']) ? $cacheData['points'] : array(),
                'message' => 'missing_credentials',
                'index' => $index,
                'talhaoId' => (int) $talhaoId,
            );
        }

        $to = $now->setTime(23, 59, 59);
        $from = $now->sub(new DateInterval('P' . $weeks . 'W'))->setTime(0, 0, 0);

        $config = self::$indexConfig[$index];
        $body = array(
            'input' => array(
                'bounds' => array(
                    'geometry' => $geometry,
                    'properties' => array('crs' => 'http://www.opengis.net/def/crs/EPSG/0/4326'),
                ),
                'data' => array(
                    array(
                        'type' => 'sentinel-2-l2a',
                        'dataFilter' => array(
                            'timeRange' => array(
                                'from' => $from->format('Y-m-d\T00:00:00\Z'),
                                'to' => $to->format('Y-m-d\T23:59:59\Z'),
                            ),
                            'maxCloudCoverage' => 20,
                        ),
                    ),
                ),
            ),
            'aggregation' => array(
                'timeRange' => array(
                    'from' => $from->format('Y-m-d\T00:00:00\Z'),
                    'to' => $to->format('Y-m-d\T23:59:59\Z'),
                ),
                'aggregationInterval' => array('of' => 'P1W'),
                'resx' => 20,
                'resy' => 20,
                'evalscript' => self::buildEvalscript($config),
            ),
            'calculations' => array('default' => new stdClass()),
        );

        $response = self::requestStatistics($token, $body);
        if (empty($response) || empty($response['data']) || !is_array($response['data'])) {
            return array(
                'points' => array(),
                'message' => 'no_data',
                'index' => $index,
                'talhaoId' => (int) $talhaoId,
            );
        }

        $points = array();
        foreach ($response['data'] as $item) {
            if (empty($item['outputs']['index']['bands']['B0']['stats'])) {
                continue;
            }

            $stats = $item['outputs']['index']['bands']['B0']['stats'];
            if (empty($stats['sampleCount'])) {
                continue;
            }

            $mean = isset($stats['mean']) ? (float) $stats['mean'] : null;
            if ($mean === null || !is_finite($mean)) {
                continue;
            }

            $points[] = array(
                'from' => $item['interval']['from'] ?? '',
                'to' => $item['interval']['to'] ?? '',
                'mean' => round($mean, $config['decimals']),
                'min' => isset($stats['min']) ? (float) $stats['min'] : null,
                'max' => isset($stats['max']) ? (float) $stats['max'] : null,
                'stDev' => isset($stats['stDev']) ? (float) $stats['stDev'] : null,
                'sampleCount' => (int) $stats['sampleCount'],
            );
        }

        if (empty($points)) {
            return array(
                'points' => array(),
                'message' => 'no_data',
                'index' => $index,
                'talhaoId' => (int) $talhaoId,
            );
        }

        $validUntil = self::computeNextUpdate($now);
        $payload = array(
            'talhaoId' => (int) $talhaoId,
            'index' => $index,
            'generatedAt' => $now->format(DATE_ATOM),
            'validUntil' => $validUntil->format(DATE_ATOM),
            'points' => $points,
        );

        self::writeJson($cacheFile, $payload);

        return $payload;
    }

    /**
     * Build evalscript for the provided configuration.
     *
     * @param array $config
     *
     * @return string
     */
    private static function buildEvalscript($config)
    {
        $inputs = array_unique(array_merge($config['inputs'], array('dataMask')));
        $inputBands = '"' . implode('", "', $inputs) . '"';

        $formula = $config['formula'];
        $script = "//VERSION=3\n";
        $script .= "function setup() {\n";
        $script .= "  return {\n";
        $script .= "    input: [{ bands: [$inputBands] }],\n";
        $script .= "    output: [\n";
        $script .= "      { id: \"index\", bands: 1, sampleType: \"FLOAT32\" },\n";
        $script .= "      { id: \"dataMask\", bands: 1, sampleType: \"UINT8\" }\n";
        $script .= "    ]\n";
        $script .= "  };\n";
        $script .= "}\n\n";
        $script .= "function evaluatePixel(samples) {\n";
        $script .= "  let value = $formula;\n";
        $script .= "  if (!isFinite(value)) value = -1;\n";
        $script .= "  return {\n";
        $script .= "    index: [value],\n";
        $script .= "    dataMask: [samples.dataMask]\n";
        $script .= "  };\n";
        $script .= "}\n";

        return $script;
    }

    /**
     * Request statistics from Sentinel Hub API.
     *
     * @param string $token
     * @param array  $body
     *
     * @return array|null
     */
    private static function requestStatistics($token, $body)
    {
        $url = 'https://services.sentinel-hub.com/api/v1/statistics';
        $ch = curl_init($url);
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES);

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ),
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => false,
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            dol_syslog(__METHOD__ . ' curl error: ' . $error, LOG_ERR);
            curl_close($ch);

            return null;
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            dol_syslog(__METHOD__ . ' API error HTTP ' . $httpCode . ' - ' . $response, LOG_ERR);

            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            dol_syslog(__METHOD__ . ' failed to decode response: ' . json_last_error_msg(), LOG_ERR);

            return null;
        }

        if (!empty($data['error'])) {
            dol_syslog(__METHOD__ . ' API responded with error: ' . json_encode($data['error']), LOG_ERR);

            return null;
        }

        return $data;
    }

    /**
     * Retrieve cached access token or request a new one.
     *
     * @return string|null
     */
    private static function getAccessToken()
    {
        $clientId = getDolGlobalString('SAFRA_API_SENTINELHUB_CLIENT_ID', '');
        $clientSecret = getDolGlobalString('SAFRA_API_SENTINELHUB_CLIENT_SECRET', '');
        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        $tokenFile = self::buildPath(self::TOKEN_FILENAME);
        $cache = self::readJson($tokenFile);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if (!empty($cache['token']) && !empty($cache['expiresAt'])) {
            try {
                $expiresAt = new DateTimeImmutable($cache['expiresAt']);
                if ($expiresAt > $now->add(new DateInterval('PT2M'))) {
                    return (string) $cache['token'];
                }
            } catch (Exception $e) {
                // Ignore invalid cache entry
            }
        }

        $url = 'https://services.sentinel-hub.com/oauth/token';
        $postData = http_build_query(array(
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ));

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ));

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            dol_syslog(__METHOD__ . ' curl error: ' . $error, LOG_ERR);
            curl_close($ch);

            return null;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            dol_syslog(__METHOD__ . ' authentication failed HTTP ' . $httpCode . ' - ' . $response, LOG_ERR);

            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['access_token'])) {
            dol_syslog(__METHOD__ . ' invalid token response: ' . $response, LOG_ERR);

            return null;
        }

        $expiresIn = !empty($data['expires_in']) ? (int) $data['expires_in'] : 3500;
        $expiresAt = $now->add(new DateInterval('PT' . max(60, $expiresIn - 60) . 'S'));

        $cacheData = array(
            'token' => $data['access_token'],
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        );
        self::writeJson($tokenFile, $cacheData);

        return (string) $data['access_token'];
    }

    /**
     * Extract geometry from talhão record.
     *
     * @param Talhao $talhao
     *
     * @return array|null
     */
    private static function extractGeometry(Talhao $talhao)
    {
        if (!empty($talhao->geo_json)) {
            $decoded = json_decode($talhao->geo_json, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                if (isset($decoded['type']) && $decoded['type'] === 'FeatureCollection' && !empty($decoded['features'][0]['geometry'])) {
                    return $decoded['features'][0]['geometry'];
                }
                if (isset($decoded['type']) && $decoded['type'] === 'Feature' && !empty($decoded['geometry'])) {
                    return $decoded['geometry'];
                }
                if (isset($decoded['type']) && in_array($decoded['type'], array('Polygon', 'MultiPolygon'), true)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Compute next update date (start of next week).
     *
     * @param DateTimeImmutable $now
     *
     * @return DateTimeImmutable
     */
    private static function computeNextUpdate(DateTimeImmutable $now)
    {
        $dayOfWeek = (int) $now->format('N'); // 1 (Mon) to 7 (Sun)
        $daysToAdd = 8 - $dayOfWeek;
        if ($daysToAdd <= 0) {
            $daysToAdd = 7;
        }

        return $now->add(new DateInterval('P' . $daysToAdd . 'D'))->setTime(0, 0, 0);
    }

    /**
     * Build cache path.
     *
     * @param string $file
     *
     * @return string
     */
    private static function buildPath($file = '')
    {
        $base = rtrim(DOL_DOCUMENT_ROOT . self::CACHE_BASE, '/');
        if ($file !== '') {
            return $base . '/' . ltrim($file, '/');
        }

        return $base;
    }

    /**
     * Get cache file path for index and talhão.
     *
     * @param string $index
     * @param int    $talhaoId
     *
     * @return string
     */
    private static function getCacheFilePath($index, $talhaoId)
    {
        $dir = self::buildPath($index);
        self::ensureDirectory($dir);

        return $dir . '/talhao_' . (int) $talhaoId . '.json';
    }

    /**
     * Read JSON file.
     *
     * @param string $path
     *
     * @return array|null
     */
    private static function readJson($path)
    {
        if (empty($path) || !is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Write JSON file.
     *
     * @param string $path
     * @param array  $data
     *
     * @return void
     */
    private static function writeJson($path, array $data)
    {
        if (empty($path)) {
            return;
        }

        self::ensureDirectory(dirname($path));

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        if (@file_put_contents($path, $payload, LOCK_EX) === false) {
            dol_syslog(__METHOD__ . ' failed to write cache ' . $path, LOG_ERR);
        }
    }

    /**
     * Ensure directory exists.
     *
     * @param string $dir
     *
     * @return void
     */
    private static function ensureDirectory($dir)
    {
        if (empty($dir) || is_dir($dir)) {
            return;
        }

        dol_mkdir($dir);
    }
}
