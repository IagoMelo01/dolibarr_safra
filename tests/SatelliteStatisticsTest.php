<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/class/safra_satellite_statistics.class.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$geometry = array(
    'type' => 'Polygon',
    'coordinates' => array(
        array(
            array(-50.0000, -20.0000),
            array(-49.9999, -20.0000),
            array(-49.9999, -20.0001),
            array(-50.0000, -20.0001),
            array(-50.0000, -20.0000),
        ),
    ),
);

$metricBoundsMethod = new ReflectionMethod(SafraSatelliteStatistics::class, 'buildMetricBounds');
$metricBoundsMethod->setAccessible(true);
$metricBounds = $metricBoundsMethod->invoke(null, $geometry);

$assert(is_array($metricBounds), 'WGS84 satellite geometry must be projected to metric bounds');
$assert(
    ($metricBounds['properties']['crs'] ?? '') === 'http://www.opengis.net/def/crs/EPSG/0/32722',
    'Brazilian field geometry at longitude -50 must use UTM zone 22S'
);

$first = $metricBounds['geometry']['coordinates'][0][0] ?? null;
$second = $metricBounds['geometry']['coordinates'][0][1] ?? null;
$assert(is_array($first) && is_array($second), 'Projected UTM positions must be available');
$assert($first[0] > 100000 && $first[0] < 900000, 'Projected UTM easting must be expressed in meters');
$assert($first[1] > 0 && $first[1] < 10000000, 'Projected UTM northing must be expressed in meters');

$projectedDistance = sqrt(pow($second[0] - $first[0], 2) + pow($second[1] - $first[1], 2));
$assert($projectedDistance > 8 && $projectedDistance < 13, 'Projected distance must preserve approximately 10 meters');

$multiPolygonBounds = $metricBoundsMethod->invoke(null, array(
    'type' => 'MultiPolygon',
    'coordinates' => array($geometry['coordinates']),
));
$assert(is_array($multiPolygonBounds), 'MultiPolygon satellite geometry must be projected to metric bounds');
$assert(($multiPolygonBounds['geometry']['type'] ?? '') === 'MultiPolygon', 'Metric projection must preserve MultiPolygon type');

$requestBodyMethod = new ReflectionMethod(SafraSatelliteStatistics::class, 'buildRequestBody');
$requestBodyMethod->setAccessible(true);
$requestBody = $requestBodyMethod->invoke(
    null,
    $metricBounds,
    new DateTimeImmutable('2026-05-01T00:00:00Z'),
    new DateTimeImmutable('2026-06-01T00:00:00Z'),
    array(
        'inputs' => array('B04', 'B08'),
        'formula' => '(samples.B08 - samples.B04) / (samples.B08 + samples.B04)',
    )
);

$assert(($requestBody['aggregation']['resx'] ?? null) === 10, 'Statistical API resx must be fixed at 10 meters');
$assert(($requestBody['aggregation']['resy'] ?? null) === 10, 'Statistical API resy must be fixed at 10 meters');
$assert(
    ($requestBody['input']['bounds']['properties']['crs'] ?? '') === 'http://www.opengis.net/def/crs/EPSG/0/32722',
    'Statistical API metric resolution must be paired with a metric UTM CRS'
);
$assert(
    ($requestBody['input']['data'][0]['dataFilter']['mosaickingOrder'] ?? '') === 'leastCC',
    'Statistical API must prefer the least cloudy Sentinel tile'
);
$assert(
    ($requestBody['input']['data'][0]['dataFilter']['maxCloudCoverage'] ?? null) === 80,
    'Statistical API must reject heavily clouded Sentinel tiles'
);
$evalscript = $requestBody['aggregation']['evalscript'] ?? '';
$assert(strpos($evalscript, '"SCL"') !== false, 'Cloud masking must request the Sentinel scene classification band');
$assert(strpos($evalscript, '"CLM"') !== false, 'Cloud masking must request the Sentinel cloud mask band');
$assert(strpos($evalscript, 'samples.SCL !== 3') !== false, 'Cloud masking must reject cloud-shadow pixels');
$assert(strpos($evalscript, 'samples.CLM === 0') !== false, 'Cloud masking must reject pixels marked as cloud');

$pointsMethod = new ReflectionMethod(SafraSatelliteStatistics::class, 'buildContinuousWeeklyPoints');
$pointsMethod->setAccessible(true);
$points = $pointsMethod->invoke(
    null,
    array(
        '2026-05-01T00:00:00Z|2026-05-08T00:00:00Z' => array(
            'stats' => array('mean' => 0.5, 'min' => 0.2, 'max' => 0.7, 'sampleCount' => 600, 'noDataCount' => 400),
        ),
        '2026-05-08T00:00:00Z|2026-05-15T00:00:00Z' => array(
            'stats' => array('mean' => 0.8, 'min' => 0.5, 'max' => 0.9, 'sampleCount' => 300, 'noDataCount' => 700),
        ),
    ),
    new DateTimeImmutable('2026-05-01T00:00:00Z'),
    2,
    3
);
$assert(($points[0]['quality'] ?? '') === 'good', 'Weeks with at least 55% cloud-free coverage must be trusted');
$assert(($points[1]['quality'] ?? '') === 'low', 'Weeks with limited cloud-free coverage must be flagged');
$assert(abs(($points[0]['validPixelRatio'] ?? 0) - 0.6) < 0.0001, 'Weekly point must expose its cloud-free pixel ratio');

$source = file_get_contents(dirname(__DIR__) . '/class/safra_satellite_statistics.class.php');
$assert($source !== false, 'Unable to read satellite statistics source');
$assert(strpos($source, "private const CACHE_VERSION = 'v4';") !== false, 'Cloud masking change must invalidate legacy statistics cache');
$assert(strpos($source, "private const SPATIAL_RESOLUTION_METERS = 10;") !== false, 'Fixed 10 meter resolution constant is required');
$assert(strpos($source, "'resx' => 20") === false, 'Legacy 20 degree statistical resolution must be removed');
$assert(strpos($source, "'resy' => 20") === false, 'Legacy 20 degree statistical resolution must be removed');

$wmsIndexClasses = array('ndvi', 'ndmi', 'ndwi', 'evi', 'swir');
foreach ($wmsIndexClasses as $indexClass) {
    $indexSource = file_get_contents(dirname(__DIR__) . '/class/' . $indexClass . '.class.php');
    $assert($indexSource !== false, 'Unable to read ' . strtoupper($indexClass) . ' source');
    $assert(strpos($indexSource, "'RESX' => '10m'") !== false, strtoupper($indexClass) . ' WMS RESX must remain fixed at 10m');
    $assert(strpos($indexSource, "'RESY' => '10m'") !== false, strtoupper($indexClass) . ' WMS RESY must remain fixed at 10m');
}

return true;
