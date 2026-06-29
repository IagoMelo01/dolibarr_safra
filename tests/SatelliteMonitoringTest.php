<?php
declare(strict_types=1);

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);
$viewContent = file_get_contents($root . '/satellite_view.php');
$viewJsContent = file_get_contents($root . '/js/satellite_view.js.php');
$chartJsContent = file_get_contents($root . '/js/satellite_chart.js.php');
$compareContent = file_get_contents($root . '/satellite_compare.php');
$moduleContent = file_get_contents($root . '/core/modules/modSafra.class.php');
$ndviContent = file_get_contents($root . '/class/ndvi.class.php');

$assert($viewContent !== false, 'Unable to read satellite_view.php');
$assert($viewJsContent !== false, 'Unable to read js/satellite_view.js.php');
$assert($chartJsContent !== false, 'Unable to read js/satellite_chart.js.php');
$assert($compareContent !== false, 'Unable to read satellite_compare.php');
$assert($moduleContent !== false, 'Unable to read module descriptor');
$assert($ndviContent !== false, 'Unable to read NDVI scheduler');

$assert(strpos($viewContent, 'value="all"') !== false, 'Satellite view must expose the all-fields option');
$assert(strpos($viewContent, '$showAllTalhoes') !== false, 'Satellite view must handle the all-fields scope');
$assert(strpos($viewJsContent, "talhaoId === 'all'") !== false, 'Satellite map must resolve all field ids');
$assert(strpos($viewJsContent, 'Promise.all(dataRequests.map') !== false, 'Satellite map must load all selected field files');
$assert(strpos($viewJsContent, 'SAFRA_TALHAO_LABEL') !== false, 'Combined map must identify each field');
$assert(strpos($viewJsContent, "type: 'FeatureCollection'") !== false, 'Combined map must render a valid GeoJSON feature collection');
$assert(strpos($viewJsContent, 'L.geoJSON(geojson') === false, 'Satellite map must not reference fetch callback geojson outside its scope');
$assert(strpos($viewContent, "GETPOST('ajax', 'aZ09') === 'chart'") !== false, 'Satellite view must expose an AJAX chart payload endpoint');
$assert(strpos($viewContent, 'safra_satellite_build_weekly_chart_payload') !== false, 'Satellite chart payload must be shared by initial render and AJAX updates');
$assert(strpos($viewContent, 'satellite_chart_endpoint') !== false, 'Satellite view must expose the chart AJAX endpoint to the browser');
$assert(strpos($viewContent, "'headerTitle' => \$definition['headerTitle']") !== false, 'Satellite index client config must expose page metadata');
$assert(strpos($chartJsContent, 'window.SafraSatelliteCharts') !== false, 'Satellite chart renderer must expose a public update API');
$assert(strpos($viewJsContent, 'function updateChartData') !== false, 'Satellite view must update chart data without a full page reload');
$assert(strpos($viewJsContent, 'window.SafraSatelliteCharts.update') !== false, 'Satellite view must re-render the chart after AJAX payload changes');
$assert(strpos($viewJsContent, 'function updateIndexPageContent') !== false, 'Satellite view must update page copy when the selected index changes');
$assert(strpos($viewContent, 'id="smoothVisualization" checked') !== false, 'Smoothed satellite visualization must be enabled by default');
$assert(strpos($viewJsContent, 'smoothGeoJsonGeometry') !== false, 'Smoothed satellite visualization must process GeoJSON geometry on the client');
$assert(strpos($viewJsContent, 'smoothPolygonRing') !== false, 'Smoothed satellite visualization must smooth polygon rings directly');
$assert(strpos($viewJsContent, "lineJoin = 'round'") !== false, 'Smoothed satellite visualization must render rounded vector joins');
$assert(strpos($viewJsContent, 'continuityFeatureStyle') !== false, 'Smoothed satellite visualization must keep a raw vector fill underneath to avoid gaps');
$assert(strpos($viewJsContent, 'L.featureGroup([continuityLayer, smoothedLayer])') !== false, 'Smoothed satellite visualization must compose continuity and smoothed vector layers');
$assert(strpos($viewJsContent, 'L.imageOverlay') === false, 'Smoothed satellite visualization must not render a raster image overlay');
$assert(strpos($viewJsContent, "document.createElement('canvas'") === false, 'Smoothed satellite visualization must not generate a canvas raster');
$assert(strpos($viewJsContent, 'bicubicResampleValueGrid') === false, 'Smoothed satellite visualization must not use bicubic raster resampling');
$assert(strpos($viewJsContent, 'renderRawIndexLayer') !== false, 'Satellite map must preserve raw GeoJSON rendering when smoothing is disabled');
$assert(strpos($chartJsContent, 'isTrustedPoint') !== false, 'Satellite charts must separate cloud-affected readings from trusted readings');
$assert(strpos($chartJsContent, 'buildContinuityValues') !== false, 'Satellite charts must carry the last trusted value through cloud-affected weeks');
$assert(strpos($chartJsContent, 'buildWarningValues') !== false, 'Cloud-affected chart readings must render warning markers on the carried value');
$assert(strpos($chartJsContent, 'isTrustedPoint(point) ? parseValue(point.mean) : null') === false, 'Satellite chart curve must not break cloud-affected weeks with null values');
$assert(strpos($chartJsContent, 'isQualityWarning: true') !== false, 'Cloud-affected chart readings must remain visible as warning points');
$assert(strpos($chartJsContent, 'isFirstQualityWarningContext') !== false, 'Satellite chart tooltip must show only one cloud reliability notice per hovered period');
$assert(strpos($chartJsContent, "backgroundColor: '#f59e0b'") !== false, 'Satellite chart tooltip must render the cloud reliability notice with a yellow square');
$assert(strpos($chartJsContent, 'buildQualityNoticeLines') !== false, 'Satellite chart tooltip must render a richer cloud reliability notice');
$assert(strpos($chartJsContent, 'qualityNoticeLabel') !== false, 'Satellite chart tooltip must use a translatable cloud reliability notice');
$assert(strpos($chartJsContent, 'filter: shouldShowTooltipItem') !== false, 'Satellite chart tooltip must not duplicate warning datasets as normal series values');
$assert(strpos($chartJsContent, 'afterLabel: context') === false, 'Satellite chart tooltip must not repeat cloud warnings after each index label');
$assert(strpos($viewContent, 'SafraSatelliteWeeklyQualityNotice') !== false, 'Satellite chart configuration must expose the cloud reliability notice');
$assert(strpos($viewContent, 'SafraSatelliteWeeklyQualityNoticeDetail') !== false, 'Satellite chart configuration must expose the cloud reliability notice detail');
$assert(strpos($moduleContent, '/custom/safra/satellite_compare.php') !== false, 'Satellite comparison must be available from the monitoring menu');
$assert(strpos($compareContent, 'safra_satellite_ensure_comparison_file') !== false, 'Satellite comparison must fetch and persist missing map periods');

$assert(strpos($moduleContent, "'label' => 'Sync satellite layers'") !== false, 'Satellite sync cron must be declared');
$assert(strpos($moduleContent, "'objectname' => 'ndvi'") !== false, 'Satellite sync cron must use the NDVI orchestrator');
$assert(strpos($moduleContent, "'method' => 'doScheduledJob'") !== false, 'Satellite sync cron must call doScheduledJob');
$assert(strpos($moduleContent, "'unitfrequency' => 86400") !== false, 'Satellite sync cron must be checked daily');
$assert(strpos($moduleContent, "'status' => 1") !== false, 'Satellite sync cron must be enabled by default');

$assert(strpos($ndviContent, "format('N') !== 3") !== false, 'Satellite scheduler must execute processing only on Wednesday');
$assert(strpos($ndviContent, '$this->requestNDVIData(null, $timeRange, null)') !== false, 'Weekly scheduler must fetch NDVI for all fields');
$assert(strpos($ndviContent, '$ndmi->requestNDMIData(null, $timeRange, null)') !== false, 'Weekly scheduler must fetch NDMI for all fields');
$assert(strpos($ndviContent, '$swir->requestSWIRData(null, $timeRange, null)') !== false, 'Weekly scheduler must fetch SWIR for all fields');
$assert(strpos($ndviContent, 'SafraSatelliteHealth::generateForRange($this->db, $timeRange, 0)') !== false, 'Weekly scheduler must generate health files for all fields');

return true;
