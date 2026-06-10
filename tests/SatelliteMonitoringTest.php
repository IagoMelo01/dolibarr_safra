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
$compareContent = file_get_contents($root . '/satellite_compare.php');
$moduleContent = file_get_contents($root . '/core/modules/modSafra.class.php');
$ndviContent = file_get_contents($root . '/class/ndvi.class.php');

$assert($viewContent !== false, 'Unable to read satellite_view.php');
$assert($viewJsContent !== false, 'Unable to read js/satellite_view.js.php');
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
