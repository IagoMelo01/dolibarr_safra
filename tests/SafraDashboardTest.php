<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/safraindex.php');
$css = file_get_contents($root . '/css/safra.css.php');
$ptBr = file_get_contents($root . '/langs/pt_BR/safra.lang');
$enUs = file_get_contents($root . '/langs/en_US/safra.lang');

$assert($dashboard !== false && $css !== false && $ptBr !== false && $enUs !== false, 'Dashboard files must be readable');
$assert(strpos($dashboard, "dol_include_once('/safra/class/FvActivity.class.php')") !== false, 'Dashboard must use the canonical Activity domain');
$assert(strpos($dashboard, 'ActivityPlanningService::isOverdue') !== false, 'Dashboard must identify overdue activities using the planning service');
$assert(strpos($dashboard, '$canReadActivities') !== false, 'Dashboard activity data must respect read permissions');
$assert(strpos($dashboard, '$canReadSatellite') !== false, 'Dashboard satellite data must respect read permissions');
$assert(strpos($dashboard, '/safra/activity/activity_kanban.php') !== false, 'Dashboard activity agenda shortcut missing');
$assert(strpos($dashboard, '/safra/report/input_consumption.php') !== false, 'Dashboard input consumption shortcut missing');
$assert(strpos($dashboard, '/safra/manual/operator_manual.php') !== false, 'Dashboard operator manual shortcut missing');
$assert(strpos($dashboard, '/safra/satellite_compare.php') !== false, 'Dashboard satellite comparison shortcut missing');
$assert(strpos($dashboard, "dol_include_once('/safra/class/safra_satellite_health.class.php')") !== false, 'Dashboard must load the combined satellite health series');
$assert(strpos($dashboard, "SafraSatelliteHealth::getWeeklySeries") !== false, 'Dashboard must use the same health series as satellite view');
$assert(strpos($dashboard, "'series' => \$dashboardChartSeries") !== false, 'Dashboard satellite chart must render the combined series');
$assert(strpos($dashboard, 'name="dashboard_talhao"') !== false, 'Dashboard satellite chart must expose a field selector');
$assert(strpos($dashboard, 'SafraDashboardChangeField') !== false, 'Dashboard satellite chart must expose a field change button');
$assert(strpos($dashboard, 'SafraLatestNdvi') === false, 'Latest processed NDVI list must be removed from dashboard');
$assert(strpos($dashboard, 'ndviEntries') === false, 'Dashboard must not query the removed latest NDVI list');
$assert(strpos($dashboard, "safra-card--map") < strpos($dashboard, "SafraDashboardOperationsTitle"), 'Map and weather must render before operational cards');
$assert(strpos($dashboard, 'safra_evento') === false, 'Legacy event counters must not drive the operational dashboard');
$assert(strpos($dashboard, 'safra_colheita') === false, 'Legacy harvest counters must not drive the operational dashboard');
$assert(strpos($css, '.safra-operation-grid') !== false, 'Dashboard operational summary styles missing');
$assert(strpos($css, '.safra-activity-list') !== false, 'Dashboard activity agenda styles missing');
$assert(strpos($css, '.safra-dashboard-field-switcher') !== false, 'Dashboard satellite field switcher styles missing');
$assert(strpos($ptBr, 'SafraDashboardOperationsTitle =') !== false, 'Portuguese dashboard translations missing');
$assert(strpos($enUs, 'SafraDashboardOperationsTitle =') !== false, 'English dashboard translations missing');

return true;
