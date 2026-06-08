<?php
/*
 * Helper for Sentinel Hub statistics with caching for Safra module
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
dol_include_once('/safra/class/talhao.class.php');
dol_include_once('/safra/lib/safra_storage.lib.php');

class SafraSatelliteStatistics
{
    private const TOKEN_FILENAME = 'token.json';
    private const CACHE_VERSION = 'v3';
    private const SPATIAL_RESOLUTION_METERS = 10;
    private static $lastCredentialError = '';

    private static $indexConfig = array(
        'ndvi' => array(
            'inputs' => array('B04', 'B08'),
            'formula' => '(samples.B08 - samples.B04) / (samples.B08 + samples.B04)',
            'range' => array('min' => -0.2, 'max' => 1),
            'color' => '#16a34a',
            'gradient' => array('rgba(22, 163, 74, 0.32)', 'rgba(22, 163, 74, 0.05)'),
            'decimals' => 3,
        ),
        'ndmi' => array(
            'inputs' => array('B08', 'B11'),
            'formula' => '(samples.B08 - samples.B11) / (samples.B08 + samples.B11)',
            'range' => array('min' => -0.2, 'max' => 1),
            'color' => '#2563eb',
            'gradient' => array('rgba(37, 99, 235, 0.32)', 'rgba(37, 99, 235, 0.06)'),
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
            'inputs' => array('B8A', 'B12'),
            'formula' => '(samples.B8A - samples.B12) / (samples.B8A + samples.B12)',
            'range' => array('min' => -0.5, 'max' => 0.6),
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
    public static function getWeeklySeries(DoliDB $db, $talhaoId, $index, $weeks = 12)
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

        $metricBounds = self::buildMetricBounds($geometry);
        if (empty($metricBounds)) {
            return array(
                'points' => array(),
                'message' => 'missing_geometry',
                'index' => $index,
                'talhaoId' => (int) $talhaoId,
            );
        }

        $cacheFile = self::getCacheFilePath($index, (int) $talhaoId, $weeks);
        $cacheData = self::readJson($cacheFile);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if (self::isUsableCache($cacheData, $now)) {
            $cacheData['index'] = $index;
            $cacheData['talhaoId'] = (int) $talhaoId;

            return $cacheData;
        }

        $token = self::getAccessToken();
        if (empty($token)) {
            return array(
                'points' => isset($cacheData['points']) && is_array($cacheData['points']) ? $cacheData['points'] : array(),
                'message' => self::$lastCredentialError ? self::$lastCredentialError : 'missing_credentials',
                'index' => $index,
                'talhaoId' => (int) $talhaoId,
            );
        }

        $to = $now->setTime(23, 59, 59);
        $from = $now->sub(new DateInterval('P' . $weeks . 'W'))->setTime(0, 0, 0);

        $config = self::$indexConfig[$index];
        $body = self::buildRequestBody($metricBounds, $from, $to, $config);

        $response = self::requestStatistics($token, $body);
        if (!is_array($response)) {
            return array(
                'points' => array(),
                'message' => 'no_data',
                'index' => $index,
                'talhaoId' => (int) $talhaoId,
            );
        }
        if (empty($response['data']) || !is_array($response['data'])) {
            return self::writeAndReturnEmptyCache($cacheFile, $now, (int) $talhaoId, $index, 'no_data');
        }

        $rawByInterval = array();
        foreach ($response['data'] as $item) {
            if (empty($item['interval']['from']) || empty($item['interval']['to'])) {
                continue;
            }

            $intervalKey = self::buildIntervalKey($item['interval']['from'], $item['interval']['to']);
            if ($intervalKey === '') {
                continue;
            }

            if (empty($item['outputs']['index']['bands']['B0']['stats'])) {
                $rawByInterval[$intervalKey] = array(
                    'from' => (string) $item['interval']['from'],
                    'to' => (string) $item['interval']['to'],
                    'stats' => null,
                );
            } else {
                $rawByInterval[$intervalKey] = array(
                    'from' => (string) $item['interval']['from'],
                    'to' => (string) $item['interval']['to'],
                    'stats' => $item['outputs']['index']['bands']['B0']['stats'],
                );
            }
        }

        $points = self::buildContinuousWeeklyPoints($rawByInterval, $from, $weeks, $config['decimals']);

        if (!self::hasNumericWeeklyPoints($points)) {
            return self::writeAndReturnEmptyCache($cacheFile, $now, (int) $talhaoId, $index, 'no_data', $points);
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
     * Check if cache can be used without calling Sentinel again.
     *
     * @param array|null        $cacheData
     * @param DateTimeImmutable $now
     *
     * @return bool
     */
    private static function isUsableCache($cacheData, DateTimeImmutable $now)
    {
        if (!is_array($cacheData) || empty($cacheData['validUntil']) || !isset($cacheData['points']) || !is_array($cacheData['points'])) {
            return false;
        }

        try {
            $validUntil = new DateTimeImmutable($cacheData['validUntil']);

            return $validUntil > $now;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Write and return a valid empty-result cache payload.
     *
     * @param string            $cacheFile
     * @param DateTimeImmutable $now
     * @param int               $talhaoId
     * @param string            $index
     * @param string            $message
     * @param array             $points
     *
     * @return array
     */
    private static function writeAndReturnEmptyCache($cacheFile, DateTimeImmutable $now, $talhaoId, $index, $message, array $points = array())
    {
        $validUntil = self::computeNextUpdate($now);
        $payload = array(
            'talhaoId' => (int) $talhaoId,
            'index' => $index,
            'generatedAt' => $now->format(DATE_ATOM),
            'validUntil' => $validUntil->format(DATE_ATOM),
            'message' => $message,
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
     * Build a Statistical API request using a fixed metric resolution.
     *
     * @param array             $metricBounds
     * @param DateTimeImmutable $from
     * @param DateTimeImmutable $to
     * @param array             $config
     *
     * @return array
     */
    private static function buildRequestBody(array $metricBounds, DateTimeImmutable $from, DateTimeImmutable $to, array $config)
    {
        $timeRange = array(
            'from' => $from->format('Y-m-d\T00:00:00\Z'),
            'to' => $to->format('Y-m-d\T23:59:59\Z'),
        );

        return array(
            'input' => array(
                'bounds' => $metricBounds,
                'data' => array(
                    array(
                        'type' => 'sentinel-2-l2a',
                        'dataFilter' => array(
                            'timeRange' => $timeRange,
                            'maxCloudCoverage' => 100,
                        ),
                    ),
                ),
            ),
            'aggregation' => array(
                'timeRange' => $timeRange,
                'aggregationInterval' => array('of' => 'P1W'),
                'resx' => self::SPATIAL_RESOLUTION_METERS,
                'resy' => self::SPATIAL_RESOLUTION_METERS,
                'evalscript' => self::buildEvalscript($config),
            ),
            'calculations' => array('default' => new stdClass()),
        );
    }

    /**
     * Project WGS84 GeoJSON geometry to the local UTM zone.
     *
     * Statistical API resx/resy values use the units of the bounds CRS. Using
     * UTM allows the request to send a literal fixed resolution in meters.
     *
     * @param array $geometry
     *
     * @return array|null
     */
    private static function buildMetricBounds(array $geometry)
    {
        if (empty($geometry['type']) || !in_array($geometry['type'], array('Polygon', 'MultiPolygon'), true) || empty($geometry['coordinates'])) {
            return null;
        }

        $positions = array();
        self::collectPositions($geometry['coordinates'], $positions);
        if (empty($positions)) {
            return null;
        }

        $longitudeTotal = 0.0;
        $latitudeTotal = 0.0;
        foreach ($positions as $position) {
            $longitudeTotal += (float) $position[0];
            $latitudeTotal += (float) $position[1];
        }

        $longitude = $longitudeTotal / count($positions);
        $latitude = $latitudeTotal / count($positions);
        if ($longitude < -180 || $longitude > 180 || $latitude < -80 || $latitude > 84) {
            return null;
        }

        $zone = self::resolveUtmZone($longitude, $latitude);
        $south = $latitude < 0;
        $projectedCoordinates = self::projectCoordinatesToUtm($geometry['coordinates'], $zone, $south);
        if (empty($projectedCoordinates)) {
            return null;
        }

        $epsg = ($south ? 32700 : 32600) + $zone;

        return array(
            'geometry' => array(
                'type' => $geometry['type'],
                'coordinates' => $projectedCoordinates,
            ),
            'properties' => array(
                'crs' => 'http://www.opengis.net/def/crs/EPSG/0/' . $epsg,
            ),
        );
    }

    /**
     * Collect valid [longitude, latitude] positions recursively.
     *
     * @param array $coordinates
     * @param array $positions
     *
     * @return void
     */
    private static function collectPositions(array $coordinates, array &$positions)
    {
        if (self::isCoordinatePosition($coordinates)) {
            $positions[] = $coordinates;

            return;
        }

        foreach ($coordinates as $child) {
            if (is_array($child)) {
                self::collectPositions($child, $positions);
            }
        }
    }

    /**
     * Resolve the standard UTM zone for a WGS84 coordinate.
     *
     * @param float $longitude
     * @param float $latitude
     *
     * @return int
     */
    private static function resolveUtmZone($longitude, $latitude)
    {
        $zone = (int) floor(((float) $longitude + 180) / 6) + 1;
        $zone = max(1, min(60, $zone));

        // Standard UTM exceptions for Norway and Svalbard.
        if ($latitude >= 56 && $latitude < 64 && $longitude >= 3 && $longitude < 12) {
            return 32;
        }
        if ($latitude >= 72 && $latitude < 84) {
            if ($longitude >= 0 && $longitude < 9) {
                return 31;
            }
            if ($longitude >= 9 && $longitude < 21) {
                return 33;
            }
            if ($longitude >= 21 && $longitude < 33) {
                return 35;
            }
            if ($longitude >= 33 && $longitude < 42) {
                return 37;
            }
        }

        return $zone;
    }

    /**
     * Project nested GeoJSON coordinates to UTM.
     *
     * @param array $coordinates
     * @param int   $zone
     * @param bool  $south
     *
     * @return array
     */
    private static function projectCoordinatesToUtm(array $coordinates, $zone, $south)
    {
        if (self::isCoordinatePosition($coordinates)) {
            return self::projectPositionToUtm($coordinates, (int) $zone, (bool) $south);
        }

        $projected = array();
        foreach ($coordinates as $child) {
            if (!is_array($child)) {
                continue;
            }
            $projected[] = self::projectCoordinatesToUtm($child, (int) $zone, (bool) $south);
        }

        return $projected;
    }

    /**
     * Check whether an array is a GeoJSON coordinate position.
     *
     * @param array $position
     *
     * @return bool
     */
    private static function isCoordinatePosition(array $position)
    {
        return count($position) >= 2 && is_numeric($position[0]) && is_numeric($position[1]);
    }

    /**
     * Project one WGS84 position to UTM using the WGS84 ellipsoid.
     *
     * @param array $position
     * @param int   $zone
     * @param bool  $south
     *
     * @return array
     */
    private static function projectPositionToUtm(array $position, $zone, $south)
    {
        $longitude = (float) $position[0];
        $latitude = (float) $position[1];
        $semiMajorAxis = 6378137.0;
        $eccentricitySquared = 0.00669438;
        $scaleFactor = 0.9996;

        $latitudeRad = deg2rad($latitude);
        $longitudeRad = deg2rad($longitude);
        $longitudeOrigin = (($zone - 1) * 6) - 180 + 3;
        $longitudeOriginRad = deg2rad($longitudeOrigin);

        $eccentricityPrimeSquared = $eccentricitySquared / (1 - $eccentricitySquared);
        $sinLatitude = sin($latitudeRad);
        $cosLatitude = cos($latitudeRad);
        $tanLatitude = tan($latitudeRad);

        $n = $semiMajorAxis / sqrt(1 - $eccentricitySquared * $sinLatitude * $sinLatitude);
        $t = $tanLatitude * $tanLatitude;
        $c = $eccentricityPrimeSquared * $cosLatitude * $cosLatitude;
        $a = $cosLatitude * ($longitudeRad - $longitudeOriginRad);

        $eccentricityFourth = $eccentricitySquared * $eccentricitySquared;
        $eccentricitySixth = $eccentricityFourth * $eccentricitySquared;
        $m = $semiMajorAxis * (
            (1 - $eccentricitySquared / 4 - 3 * $eccentricityFourth / 64 - 5 * $eccentricitySixth / 256) * $latitudeRad
            - (3 * $eccentricitySquared / 8 + 3 * $eccentricityFourth / 32 + 45 * $eccentricitySixth / 1024) * sin(2 * $latitudeRad)
            + (15 * $eccentricityFourth / 256 + 45 * $eccentricitySixth / 1024) * sin(4 * $latitudeRad)
            - (35 * $eccentricitySixth / 3072) * sin(6 * $latitudeRad)
        );

        $easting = $scaleFactor * $n * (
            $a
            + (1 - $t + $c) * pow($a, 3) / 6
            + (5 - 18 * $t + $t * $t + 72 * $c - 58 * $eccentricityPrimeSquared) * pow($a, 5) / 120
        ) + 500000.0;

        $northing = $scaleFactor * (
            $m
            + $n * $tanLatitude * (
                $a * $a / 2
                + (5 - $t + 9 * $c + 4 * $c * $c) * pow($a, 4) / 24
                + (61 - 58 * $t + $t * $t + 600 * $c - 330 * $eccentricityPrimeSquared) * pow($a, 6) / 720
            )
        );
        if ($south) {
            $northing += 10000000.0;
        }

        $projected = array(round($easting, 3), round($northing, 3));
        for ($i = 2; $i < count($position); $i++) {
            $projected[] = $position[$i];
        }

        return $projected;
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
        ));
        safra_configure_curl_ssl($ch);

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
        self::$lastCredentialError = '';
        $clientId = getDolGlobalString('SAFRA_API_SENTINELHUB_CLIENT_ID', '');
        $clientSecret = getDolGlobalString('SAFRA_API_SENTINELHUB_CLIENT_SECRET', '');
        if (empty($clientId) || empty($clientSecret)) {
            self::$lastCredentialError = 'missing_credentials';

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
        ));
        safra_configure_curl_ssl($ch);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            dol_syslog(__METHOD__ . ' curl error: ' . $error, LOG_ERR);
            curl_close($ch);
            self::$lastCredentialError = 'credential_connection_error';

            return null;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            dol_syslog(__METHOD__ . ' authentication failed HTTP ' . $httpCode . ' - ' . $response, LOG_ERR);
            self::$lastCredentialError = in_array((int) $httpCode, array(400, 401, 403), true) ? 'invalid_credentials' : 'credential_connection_error';

            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['access_token'])) {
            dol_syslog(__METHOD__ . ' invalid token response: ' . $response, LOG_ERR);
            self::$lastCredentialError = 'credential_connection_error';

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
        $base = safra_json_path('cache');
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
    private static function getCacheFilePath($index, $talhaoId, $weeks)
    {
        $dir = self::buildPath($index);
        self::ensureDirectory($dir);

        return $dir . '/talhao_' . (int) $talhaoId . '_w' . (int) $weeks . '_' . self::CACHE_VERSION . '.json';
    }

    /**
     * Build interval map key.
     *
     * @param string $from
     * @param string $to
     *
     * @return string
     */
    private static function buildIntervalKey($from, $to)
    {
        $from = (string) $from;
        $to = (string) $to;
        if ($from === '' || $to === '') {
            return '';
        }

        return $from . '|' . $to;
    }

    /**
     * Build fixed weekly points for the selected window.
     *
     * @param array             $rawByInterval
     * @param DateTimeImmutable $from
     * @param int               $weeks
     * @param int               $decimals
     *
     * @return array
     */
    private static function buildContinuousWeeklyPoints(array $rawByInterval, DateTimeImmutable $from, $weeks, $decimals)
    {
        $points = array();

        for ($i = 0; $i < (int) $weeks; $i++) {
            $start = $from->add(new DateInterval('P' . $i . 'W'))->setTime(0, 0, 0);
            $end = $start->add(new DateInterval('P1W'));
            $fromIso = $start->format('Y-m-d\T00:00:00\Z');
            $toIso = $end->format('Y-m-d\T00:00:00\Z');
            $key = self::buildIntervalKey($fromIso, $toIso);
            $entry = isset($rawByInterval[$key]) ? $rawByInterval[$key] : null;
            $stats = $entry && !empty($entry['stats']) && is_array($entry['stats']) ? $entry['stats'] : null;

            $mean = null;
            $min = null;
            $max = null;
            $stDev = null;
            $sampleCount = 0;

            if ($stats) {
                $sampleCount = !empty($stats['sampleCount']) ? (int) $stats['sampleCount'] : 0;
                $rawMean = isset($stats['mean']) ? (float) $stats['mean'] : null;
                if ($sampleCount > 0 && $rawMean !== null && is_finite($rawMean)) {
                    $mean = round($rawMean, (int) $decimals);
                    $min = isset($stats['min']) && is_finite((float) $stats['min']) ? round((float) $stats['min'], (int) $decimals) : null;
                    $max = isset($stats['max']) && is_finite((float) $stats['max']) ? round((float) $stats['max'], (int) $decimals) : null;
                    $stDev = isset($stats['stDev']) && is_finite((float) $stats['stDev']) ? round((float) $stats['stDev'], (int) $decimals) : null;
                }
            }

            $points[] = array(
                'from' => $fromIso,
                'to' => $toIso,
                'mean' => $mean,
                'min' => $min,
                'max' => $max,
                'stDev' => $stDev,
                'sampleCount' => $sampleCount,
            );
        }

        return $points;
    }

    /**
     * Check if series has at least one numeric weekly mean.
     *
     * @param array $points
     *
     * @return bool
     */
    private static function hasNumericWeeklyPoints(array $points)
    {
        foreach ($points as $point) {
            if (isset($point['mean']) && is_numeric($point['mean'])) {
                return true;
            }
        }

        return false;
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

        safra_ensure_dir($dir);
    }
}
