<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/class/FvActivity.class.php';
require_once dirname(__DIR__) . '/class/ActivityPlanningService.class.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$now = strtotime('2026-06-09 12:00:00');
$assert(ActivityPlanningService::isOverdue(FvActivity::STATUS_PLANNED, '2026-06-08 08:00:00', null, $now), 'Past planned date must be overdue');
$assert(ActivityPlanningService::isOverdue(FvActivity::STATUS_IN_PROGRESS, null, '2026-06-09 11:00:00', $now), 'Past planned end must be overdue');
$assert(!ActivityPlanningService::isOverdue(FvActivity::STATUS_COMPLETED, null, '2026-06-08 11:00:00', $now), 'Completed activity must not be overdue');
$assert(!ActivityPlanningService::isOverdue(FvActivity::STATUS_PLANNED, '2026-06-09 08:00:00', null, $now), 'Activity planned today without end must not be overdue');

$assert(abs(ActivityPlanningService::calculatePlannedQuantity(12.5, 2, 0, 0) - 25) < 0.000001, 'Planned quantity must use target area and dose');
$assert(abs(ActivityPlanningService::calculatePlannedQuantity(20, 0, 10, 5) - 40) < 0.000001, 'Planned quantity must scale source quantity by area');
$assert(ActivityPlanningService::consumptionUnit('kg/ha') === 'kg', 'Consumption unit must remove per-hectare suffix');
$assert(ActivityPlanningService::consumptionUnit('L/ha') === 'L', 'Consumption unit must preserve unit case');

$root = dirname(__DIR__);
foreach (array(
    '/activity/activity_duplicate.php',
    '/activity/activity_kanban.php',
    '/report/input_consumption.php',
) as $relativePath) {
    $assert(is_file($root . $relativePath), 'Missing activity planning surface: ' . $relativePath);
}

$moduleContent = file_get_contents($root . '/core/modules/modSafra.class.php');
$assert($moduleContent !== false && strpos($moduleContent, '/custom/safra/activity/activity_kanban.php') !== false, 'Activity kanban menu missing');
$assert(strpos($moduleContent, '/custom/safra/report/input_consumption.php') !== false, 'Input consumption report menu missing');

$serviceContent = file_get_contents($root . '/class/ActivityPlanningService.class.php');
$assert($serviceContent !== false && strpos($serviceContent, '$line->fk_stock_movement = null') !== false, 'Duplicated lines must reset stock movement tracking');
$assert(strpos($serviceContent, '$duplicate->fk_project = null') !== false, 'Duplicated activities must reset project links');
$assert(strpos($serviceContent, '$duplicate->fk_task = null') !== false, 'Duplicated activities must reset task links');

return true;
