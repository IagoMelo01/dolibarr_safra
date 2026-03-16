<?php
/*
 * Helper for combined crop health index (NDVI + NDMI + SWIR)
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
dol_include_once('/safra/class/safra_satellite_statistics.class.php');
dol_include_once('/safra/class/talhao.class.php');

class SafraSatelliteHealth
{
    private const OUTPUT_DIR = '/custom/safra/json/saude_geral';
    private const NDVI_MIN = -0.2;
    private const NDVI_MAX = 0.9;
    private const NDMI_MIN = -0.35;
    private const NDMI_MAX = 0.55;
    private const SWIR_MIN = -0.5;
    private const SWIR_MAX = 0.6;
    private const WEIGHT_NDVI = 0.50;
    private const WEIGHT_NDMI = 0.30;
    private const WEIGHT_SWIR = 0.20;

    private static $colorValueMap = array(
        'ndvi' => array(
            '0D0D0D' => -0.65,
            'BFBFBF' => -0.35,
            'DBDBDB' => -0.15,
            'EBEBEB' => -0.05,
            'FFFACC' => 0.012,
            'EDE8B5' => 0.037,
            'DED99C' => 0.062,
            'CCC783' => 0.087,
            'BDB86B' => 0.112,
            'B0C261' => 0.137,
            'A3CC59' => 0.162,
            '91BF52' => 0.187,
            '80B347' => 0.225,
            '70A340' => 0.275,
            '619636' => 0.325,
            '4F8A2E' => 0.375,
            '407D24' => 0.425,
            '306E1C' => 0.475,
            '216112' => 0.525,
            '0F540A' => 0.575,
            '004500' => 0.65,
        ),
        'ndmi' => array(
            '800000' => -0.9,
            '990000' => -0.75,
            'BF0000' => -0.65,
            'E60000' => -0.55,
            'FF0000' => -0.45,
            'FF4000' => -0.35,
            'FF8000' => -0.25,
            'FFBF00' => -0.15,
            'FFFF00' => -0.05,
            'BFFFBF' => 0.05,
            '80FF80' => 0.15,
            '40FFBF' => 0.25,
            '00FFFF' => 0.35,
            '00DFFF' => 0.425,
            '00BFFF' => 0.475,
            '000080' => 0.6,
        ),
        'swir' => array(
            // New SWIR ramp.
            '0A1F3D' => -0.5,
            '1A365C' => -0.325,
            '332B21' => -0.175,
            '594024' => -0.05,
            '855E29' => 0.05,
            'A88030' => 0.15,
            'A3A33D' => 0.25,
            '75A840' => 0.35,
            '4A993D' => 0.45,
            '1F7836' => 0.58,
            // Backward compatibility for previously generated files.
            '0F1C30' => -0.5,
            '1F2E47' => -0.3,
            '573D26' => -0.1,
            '85572E' => 0.05,
            '8F9E38' => 0.22,
            '549438' => 0.38,
            '1A803B' => 0.52,
            '004500' => 0.58,
        ),
    );

    /**
     * Generate health geojson for one week range and all (or one) talhao.
     *
     * @param DoliDB $db
     * @param string $timeRange Format YYYY-MM-DD/YYYY-MM-DD
     * @param int    $talhaoId
     *
     * @return array
     */
    public static function generateForRange(DoliDB $db, $timeRange, $talhaoId = 0)
    {
        $result = array(
            'generated' => 0,
            'skipped' => 0,
            'range' => (string) $timeRange,
        );

        if (!self::isValidTimeRange($timeRange)) {
            $result['error'] = 'invalid_range';

            return $result;
        }

        $talhaoIds = self::resolveTalhaoIds($db, (int) $talhaoId);
        if (empty($talhaoIds)) {
            $result['error'] = 'no_talhoes';

            return $result;
        }

        foreach ($talhaoIds as $id) {
            $payload = self::buildForTalhao($timeRange, (int) $id);
            if (empty($payload) || empty($payload['features'])) {
                $result['skipped']++;
                continue;
            }

            $fileBase = self::buildFileBase($timeRange, (int) $id);
            $outputPath = self::buildOutputPath($fileBase);
            self::writeJson($outputPath, $payload);
            $result['generated']++;
        }

        return $result;
    }

    /**
     * Build weekly time series for general crop health.
     *
     * @param DoliDB $db
     * @param int    $talhaoId
     * @param int    $weeks
     *
     * @return array
     */
    public static function getWeeklySeries(DoliDB $db, $talhaoId, $weeks = 12)
    {
        $talhaoId = (int) $talhaoId;
        if ($talhaoId <= 0) {
            return array(
                'points' => array(),
                'message' => 'talhao_not_found',
                'index' => 'health',
                'talhaoId' => $talhaoId,
            );
        }

        $weeks = max(1, min(52, (int) $weeks));

        $ndviSeries = SafraSatelliteStatistics::getWeeklySeries($db, $talhaoId, 'ndvi', $weeks);
        $ndmiSeries = SafraSatelliteStatistics::getWeeklySeries($db, $talhaoId, 'ndmi', $weeks);
        $swirSeries = SafraSatelliteStatistics::getWeeklySeries($db, $talhaoId, 'swir', $weeks);

        $points = self::mergeSeriesPoints($ndviSeries, $ndmiSeries, $swirSeries);
        if (empty($points) || !self::hasNumericSeriesPoints($points)) {
            return array(
                'points' => array(),
                'message' => self::resolveSeriesMessage(array($ndviSeries, $ndmiSeries, $swirSeries)),
                'index' => 'health',
                'talhaoId' => $talhaoId,
            );
        }

        return array(
            'talhaoId' => $talhaoId,
            'index' => 'health',
            'generatedAt' => self::pickLatestDate(array($ndviSeries, $ndmiSeries, $swirSeries), 'generatedAt'),
            'validUntil' => self::pickEarliestDate(array($ndviSeries, $ndmiSeries, $swirSeries), 'validUntil'),
            'points' => $points,
            'message' => '',
        );
    }

    /**
     * Build one health payload for a talhao/week.
     *
     * @param string $timeRange
     * @param int    $talhaoId
     *
     * @return array|null
     */
    private static function buildForTalhao($timeRange, $talhaoId)
    {
        $fileBase = self::buildFileBase($timeRange, $talhaoId);
        $ndvi = self::readJson(self::buildIndexPath('ndvi', $fileBase));
        $ndmi = self::readJson(self::buildIndexPath('ndmi', $fileBase));
        $swir = self::readJson(self::buildIndexPath('swir', $fileBase));

        if (empty($ndvi['features']) || empty($ndmi['features']) || empty($swir['features'])) {
            return null;
        }

        $ndmiMap = self::buildFeatureMap($ndmi);
        $swirMap = self::buildFeatureMap($swir);
        $features = array();

        foreach ($ndvi['features'] as $position => $ndviFeature) {
            if (!is_array($ndviFeature)) {
                continue;
            }

            $key = self::featureKey($ndviFeature, $position);
            $ndmiFeature = $ndmiMap[$key] ?? ($ndmi['features'][$position] ?? null);
            $swirFeature = $swirMap[$key] ?? ($swir['features'][$position] ?? null);

            if (!is_array($ndmiFeature) || !is_array($swirFeature)) {
                continue;
            }

            $ndviValue = self::extractFeatureValue($ndviFeature, 'ndvi');
            $ndmiValue = self::extractFeatureValue($ndmiFeature, 'ndmi');
            $swirValue = self::extractFeatureValue($swirFeature, 'swir');

            if ($ndviValue === null || $ndmiValue === null || $swirValue === null) {
                continue;
            }

            $score = self::computeHealthScore($ndviValue, $ndmiValue, $swirValue);
            $classInfo = self::classifyScore($score);

            $feature = $ndviFeature;
            if (empty($feature['properties']) || !is_array($feature['properties'])) {
                $feature['properties'] = array();
            }

            $feature['properties']['HEALTH_SCORE'] = round($score, 2);
            $feature['properties']['HEALTH_CLASS'] = $classInfo['label'];
            $feature['properties']['COLOR_HEX'] = ltrim($classInfo['color'], '#');
            $feature['properties']['NDVI'] = round($ndviValue, 4);
            $feature['properties']['NDMI'] = round($ndmiValue, 4);
            $feature['properties']['SWIR'] = round($swirValue, 4);

            $features[] = $feature;
        }

        if (empty($features)) {
            return null;
        }

        $payload = array(
            'type' => 'FeatureCollection',
            'features' => $features,
        );

        if (!empty($ndvi['crs'])) {
            $payload['crs'] = $ndvi['crs'];
        }

        return $payload;
    }

    /**
     * Merge weekly points from NDVI, NDMI and SWIR.
     *
     * @param array $ndviSeries
     * @param array $ndmiSeries
     * @param array $swirSeries
     *
     * @return array
     */
    private static function mergeSeriesPoints(array $ndviSeries, array $ndmiSeries, array $swirSeries)
    {
        $buckets = array();
        $sourceMap = array(
            'ndvi' => isset($ndviSeries['points']) && is_array($ndviSeries['points']) ? $ndviSeries['points'] : array(),
            'ndmi' => isset($ndmiSeries['points']) && is_array($ndmiSeries['points']) ? $ndmiSeries['points'] : array(),
            'swir' => isset($swirSeries['points']) && is_array($swirSeries['points']) ? $swirSeries['points'] : array(),
        );

        foreach ($sourceMap as $index => $seriesPoints) {
            foreach ($seriesPoints as $point) {
                if (empty($point['from']) && empty($point['to'])) {
                    continue;
                }

                $from = isset($point['from']) ? (string) $point['from'] : '';
                $to = isset($point['to']) ? (string) $point['to'] : '';
                $key = $from . '|' . $to;

                if (!isset($buckets[$key])) {
                    $buckets[$key] = array(
                        'from' => $from,
                        'to' => $to,
                    );
                }

                $buckets[$key][$index] = $point;
            }
        }

        if (empty($buckets)) {
            return array();
        }

        ksort($buckets);
        $points = array();

        foreach ($buckets as $bucket) {
            $ndviPoint = !empty($bucket['ndvi']) && is_array($bucket['ndvi']) ? $bucket['ndvi'] : array();
            $ndmiPoint = !empty($bucket['ndmi']) && is_array($bucket['ndmi']) ? $bucket['ndmi'] : array();
            $swirPoint = !empty($bucket['swir']) && is_array($bucket['swir']) ? $bucket['swir'] : array();

            $ndviMean = self::toFloat($ndviPoint['mean'] ?? null);
            $ndmiMean = self::toFloat($ndmiPoint['mean'] ?? null);
            $swirMean = self::toFloat($swirPoint['mean'] ?? null);
            $hasCompleteMean = ($ndviMean !== null && $ndmiMean !== null && $swirMean !== null);

            $meanScore = $hasCompleteMean ? self::computeHealthScore($ndviMean, $ndmiMean, $swirMean) : null;

            $ndviMin = self::toFloat($ndviPoint['min'] ?? $ndviMean);
            $ndmiMin = self::toFloat($ndmiPoint['min'] ?? $ndmiMean);
            $swirMin = self::toFloat($swirPoint['min'] ?? $swirMean);
            $ndviMax = self::toFloat($ndviPoint['max'] ?? $ndviMean);
            $ndmiMax = self::toFloat($ndmiPoint['max'] ?? $ndmiMean);
            $swirMax = self::toFloat($swirPoint['max'] ?? $swirMean);

            $minScore = ($hasCompleteMean && $ndviMin !== null && $ndmiMin !== null && $swirMin !== null)
                ? self::computeHealthScore($ndviMin, $ndmiMin, $swirMin)
                : null;
            $maxScore = ($hasCompleteMean && $ndviMax !== null && $ndmiMax !== null && $swirMax !== null)
                ? self::computeHealthScore($ndviMax, $ndmiMax, $swirMax)
                : null;

            $sampleCount = $hasCompleteMean
                ? min(
                    (int) ($ndviPoint['sampleCount'] ?? 0),
                    (int) ($ndmiPoint['sampleCount'] ?? 0),
                    (int) ($swirPoint['sampleCount'] ?? 0)
                )
                : 0;

            $points[] = array(
                'from' => $bucket['from'],
                'to' => $bucket['to'],
                'mean' => $meanScore !== null ? round($meanScore, 2) : null,
                'min' => $minScore !== null ? round($minScore, 2) : null,
                'max' => $maxScore !== null ? round($maxScore, 2) : null,
                'sampleCount' => $sampleCount,
            );
        }

        return $points;
    }

    /**
     * Check if weekly series has at least one numeric mean value.
     *
     * @param array $points
     *
     * @return bool
     */
    private static function hasNumericSeriesPoints(array $points)
    {
        foreach ($points as $point) {
            if (isset($point['mean']) && is_numeric($point['mean'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve best message when no combined points are available.
     *
     * @param array $seriesList
     *
     * @return string
     */
    private static function resolveSeriesMessage(array $seriesList)
    {
        $priority = array(
            'missing_credentials',
            'missing_geometry',
            'talhao_not_found',
            'no_data',
        );

        foreach ($priority as $messageKey) {
            foreach ($seriesList as $series) {
                if (!empty($series['message']) && $series['message'] === $messageKey) {
                    return $messageKey;
                }
            }
        }

        return 'no_data';
    }

    /**
     * Compute score in range 0..100.
     *
     * @param float $ndvi
     * @param float $ndmi
     * @param float $swir
     *
     * @return float
     */
    private static function computeHealthScore($ndvi, $ndmi, $swir)
    {
        $ndviNorm = self::normalize($ndvi, self::NDVI_MIN, self::NDVI_MAX);
        $ndmiNorm = self::normalize($ndmi, self::NDMI_MIN, self::NDMI_MAX);
        $swirNorm = self::normalize($swir, self::SWIR_MIN, self::SWIR_MAX);

        $weightedMean = ($ndviNorm * self::WEIGHT_NDVI)
            + ($ndmiNorm * self::WEIGHT_NDMI)
            + ($swirNorm * self::WEIGHT_SWIR);
        $floorValue = min($ndviNorm, $ndmiNorm, $swirNorm);

        // Mix weighted average with the weakest index to avoid masking stress.
        $score = ((0.8 * $weightedMean) + (0.2 * $floorValue)) * 100.0;
        $stressPenalty = 0.0;

        if ($ndviNorm < 0.30) {
            $stressPenalty += (0.30 - $ndviNorm) * 40.0;
        }
        if ($ndmiNorm < 0.28) {
            $stressPenalty += (0.28 - $ndmiNorm) * 32.0;
        }
        if ($swirNorm < 0.25) {
            $stressPenalty += (0.25 - $swirNorm) * 24.0;
        }

        if ($floorValue > 0.65) {
            $score += 4.0;
        }

        return self::clamp($score - $stressPenalty, 0.0, 100.0);
    }

    /**
     * Convert score to class and color.
     *
     * @param float $score
     *
     * @return array
     */
    private static function classifyScore($score)
    {
        if ($score >= 78) {
            return array('label' => 'excelente', 'color' => '#15803d');
        }
        if ($score >= 58) {
            return array('label' => 'boa', 'color' => '#65a30d');
        }
        if ($score >= 38) {
            return array('label' => 'atencao', 'color' => '#f59e0b');
        }

        return array('label' => 'critica', 'color' => '#dc2626');
    }

    /**
     * Normalize numeric value to 0..1.
     *
     * @param float $value
     * @param float $min
     * @param float $max
     *
     * @return float
     */
    private static function normalize($value, $min, $max)
    {
        if ($max <= $min) {
            return 0.0;
        }

        $normalized = ($value - $min) / ($max - $min);
        if ($normalized < 0) {
            return 0.0;
        }
        if ($normalized > 1) {
            return 1.0;
        }

        return $normalized;
    }

    /**
     * Clamp numeric value to a min/max interval.
     *
     * @param float $value
     * @param float $min
     * @param float $max
     *
     * @return float
     */
    private static function clamp($value, $min, $max)
    {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * Resolve talhao ids.
     *
     * @param DoliDB $db
     * @param int    $talhaoId
     *
     * @return array
     */
    private static function resolveTalhaoIds(DoliDB $db, $talhaoId)
    {
        if ($talhaoId > 0) {
            return array((int) $talhaoId);
        }

        $objTalhao = new Talhao($db);
        $list = $objTalhao->fetchAll();
        $ids = array();

        foreach ((array) $list as $talhao) {
            if (!empty($talhao->id)) {
                $ids[] = (int) $talhao->id;
            }
        }

        return $ids;
    }

    /**
     * Build dictionary from feature collection.
     *
     * @param array $collection
     *
     * @return array
     */
    private static function buildFeatureMap(array $collection)
    {
        $map = array();
        if (empty($collection['features']) || !is_array($collection['features'])) {
            return $map;
        }

        foreach ($collection['features'] as $position => $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $key = self::featureKey($feature, $position);
            $map[$key] = $feature;
        }

        return $map;
    }

    /**
     * Build deterministic key for one feature.
     *
     * @param array $feature
     * @param int   $position
     *
     * @return string
     */
    private static function featureKey(array $feature, $position)
    {
        if (isset($feature['id']) && $feature['id'] !== '') {
            return 'id:' . (string) $feature['id'];
        }

        if (!empty($feature['properties']) && is_array($feature['properties'])) {
            foreach (array('id', 'ID', 'fid', 'FID') as $candidate) {
                if (isset($feature['properties'][$candidate]) && $feature['properties'][$candidate] !== '') {
                    return 'prop:' . (string) $feature['properties'][$candidate];
                }
            }
        }

        if (!empty($feature['geometry']) && is_array($feature['geometry'])) {
            return 'geom:' . md5(json_encode($feature['geometry']));
        }

        return 'idx:' . (int) $position;
    }

    /**
     * Extract one numeric index value from feature properties.
     *
     * @param array  $feature
     * @param string $indexCode
     *
     * @return float|null
     */
    private static function extractFeatureValue(array $feature, $indexCode = '')
    {
        if (empty($feature['properties']) || !is_array($feature['properties'])) {
            return null;
        }

        $indexCode = strtolower((string) $indexCode);
        $properties = $feature['properties'];
        $preferredKeys = array(
            'VALUE', 'value', 'VAL', 'val',
            'INDEX', 'index', 'B0', 'b0',
            'NDVI', 'ndvi', 'NDMI', 'ndmi', 'SWIR', 'swir',
            'MEAN', 'mean',
        );

        foreach ($preferredKeys as $key) {
            if (!array_key_exists($key, $properties)) {
                continue;
            }

            $value = self::toFloat($properties[$key]);
            if ($value === null) {
                continue;
            }

            if ($value >= -1.5 && $value <= 1.5) {
                return $value;
            }
        }

        $fallback = null;
        foreach ($properties as $key => $rawValue) {
            if (!is_scalar($rawValue)) {
                continue;
            }

            $numeric = self::toFloat($rawValue);
            if ($numeric === null) {
                continue;
            }

            $upperKey = strtoupper((string) $key);
            if (
                strpos($upperKey, 'COLOR') !== false
                || strpos($upperKey, 'CLASS') !== false
                || strpos($upperKey, 'HEX') !== false
                || strpos($upperKey, 'HEALTH') !== false
                || preg_match('/(^|_)ID$/', $upperKey)
            ) {
                continue;
            }

            if ($numeric >= -1.5 && $numeric <= 1.5) {
                return $numeric;
            }

            if ($fallback === null) {
                $fallback = $numeric;
            }
        }

        if ($fallback !== null && $fallback >= -1.5 && $fallback <= 1.5) {
            return $fallback;
        }

        $colorHex = $properties['COLOR_HEX'] ?? '';
        $colorValue = self::estimateIndexValueFromColor($indexCode, $colorHex);
        if ($colorValue !== null) {
            return $colorValue;
        }

        $hex = self::normalizeHexColor($colorHex);
        if ($hex !== null) {
            $rgb = self::hexToRgb($hex);
            if ($rgb !== null) {
                $luminance = (0.2126 * $rgb['r'] + 0.7152 * $rgb['g'] + 0.0722 * $rgb['b']) / 255;

                return ($luminance * 2) - 1;
            }
        }

        return null;
    }

    /**
     * Estimate index value from a known color map.
     *
     * @param string $indexCode
     * @param string $colorHex
     *
     * @return float|null
     */
    private static function estimateIndexValueFromColor($indexCode, $colorHex)
    {
        $indexCode = strtolower((string) $indexCode);
        if (empty($indexCode) || empty(self::$colorValueMap[$indexCode])) {
            return null;
        }

        $hex = self::normalizeHexColor($colorHex);
        if ($hex === null) {
            return null;
        }

        $palette = self::$colorValueMap[$indexCode];
        if (isset($palette[$hex])) {
            return (float) $palette[$hex];
        }

        $target = self::hexToRgb($hex);
        if ($target === null) {
            return null;
        }

        $bestValue = null;
        $bestDistance = null;
        foreach ($palette as $paletteHex => $value) {
            $paletteRgb = self::hexToRgb($paletteHex);
            if ($paletteRgb === null) {
                continue;
            }

            $distance = (($target['r'] - $paletteRgb['r']) * ($target['r'] - $paletteRgb['r']))
                + (($target['g'] - $paletteRgb['g']) * ($target['g'] - $paletteRgb['g']))
                + (($target['b'] - $paletteRgb['b']) * ($target['b'] - $paletteRgb['b']));

            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestValue = (float) $value;
            }
        }

        if ($bestDistance !== null && $bestDistance <= 2800) {
            return $bestValue;
        }

        return null;
    }

    /**
     * Normalize hexadecimal color string to RRGGBB.
     *
     * @param string $colorHex
     *
     * @return string|null
     */
    private static function normalizeHexColor($colorHex)
    {
        if (!is_scalar($colorHex)) {
            return null;
        }

        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $colorHex));
        if (strlen($hex) !== 6) {
            return null;
        }

        return $hex;
    }

    /**
     * Convert hexadecimal color to RGB.
     *
     * @param string $hex
     *
     * @return array|null
     */
    private static function hexToRgb($hex)
    {
        $hex = self::normalizeHexColor($hex);
        if ($hex === null) {
            return null;
        }

        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * Build source path for one index file.
     *
     * @param string $index
     * @param string $fileBase
     *
     * @return string
     */
    private static function buildIndexPath($index, $fileBase)
    {
        return DOL_DOCUMENT_ROOT . '/custom/safra/json/' . trim($index, '/') . '/' . $fileBase . '.json';
    }

    /**
     * Build output path for health file.
     *
     * @param string $fileBase
     *
     * @return string
     */
    private static function buildOutputPath($fileBase)
    {
        return rtrim(DOL_DOCUMENT_ROOT . self::OUTPUT_DIR, '/') . '/' . $fileBase . '.json';
    }

    /**
     * Build base filename for one range and talhao.
     *
     * @param string $timeRange
     * @param int    $talhaoId
     *
     * @return string
     */
    private static function buildFileBase($timeRange, $talhaoId)
    {
        return str_replace('/', '_', (string) $timeRange) . '_' . (int) $talhaoId;
    }

    /**
     * Validate date range string.
     *
     * @param string $timeRange
     *
     * @return bool
     */
    private static function isValidTimeRange($timeRange)
    {
        return is_string($timeRange) && preg_match('/^\d{4}-\d{2}-\d{2}\/\d{4}-\d{2}-\d{2}$/', $timeRange);
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

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Write JSON file.
     *
     * @param string $path
     * @param array  $payload
     *
     * @return void
     */
    private static function writeJson($path, array $payload)
    {
        if (empty($path)) {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            dol_mkdir($dir);
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        if (@file_put_contents($path, $encoded, LOCK_EX) === false) {
            dol_syslog(__METHOD__ . ' failed to write ' . $path, LOG_ERR);
        }
    }

    /**
     * Parse finite float.
     *
     * @param mixed $value
     *
     * @return float|null
     */
    private static function toFloat($value)
    {
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;

            return is_finite($number) ? $number : null;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));
            if ($normalized === '' || !is_numeric($normalized)) {
                return null;
            }

            $number = (float) $normalized;

            return is_finite($number) ? $number : null;
        }

        return null;
    }

    /**
     * Pick latest valid date from payloads.
     *
     * @param array  $seriesList
     * @param string $field
     *
     * @return string|null
     */
    private static function pickLatestDate(array $seriesList, $field)
    {
        $winner = null;
        $winnerTs = null;

        foreach ($seriesList as $payload) {
            if (empty($payload[$field])) {
                continue;
            }

            $timestamp = strtotime((string) $payload[$field]);
            if ($timestamp === false) {
                continue;
            }

            if ($winnerTs === null || $timestamp > $winnerTs) {
                $winnerTs = $timestamp;
                $winner = (string) $payload[$field];
            }
        }

        return $winner;
    }

    /**
     * Pick earliest valid date from payloads.
     *
     * @param array  $seriesList
     * @param string $field
     *
     * @return string|null
     */
    private static function pickEarliestDate(array $seriesList, $field)
    {
        $winner = null;
        $winnerTs = null;

        foreach ($seriesList as $payload) {
            if (empty($payload[$field])) {
                continue;
            }

            $timestamp = strtotime((string) $payload[$field]);
            if ($timestamp === false) {
                continue;
            }

            if ($winnerTs === null || $timestamp < $winnerTs) {
                $winnerTs = $timestamp;
                $winner = (string) $payload[$field];
            }
        }

        return $winner;
    }
}
