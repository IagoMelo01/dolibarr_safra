<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/class/FvActivity.class.php';
require_once dirname(__DIR__) . '/class/FvActivityLine.class.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(FvActivity::normalizeType('input_application') === FvActivity::TYPE_INPUT_APPLICATION, 'normalizeType(input_application) failed');
$assert(FvActivity::normalizeType('Fertilization') === FvActivity::TYPE_FERTILIZATION, 'normalizeType(Fertilization) failed');
$assert(FvActivity::normalizeStatus(9) === FvActivity::STATUS_CANCELED, 'normalizeStatus(9) must map to canceled');
$assert(FvActivity::mapTaskToActivityStatus(3, 100) === FvActivity::STATUS_COMPLETED, 'Closed task must map to completed');
$assert(FvActivity::mapTaskToActivityStatus(2, 40) === FvActivity::STATUS_IN_PROGRESS, 'Ongoing task must map to in-progress');

$statusOptions = FvActivity::getStatusOptions();
$assert(isset($statusOptions[FvActivity::STATUS_DRAFT]), 'Draft status option missing');
$assert(isset($statusOptions[FvActivity::STATUS_IN_PROGRESS]), 'In progress status option missing');

$line = new FvActivityLine($db);
$assert(isset($line->fields['fk_activity']), 'Activity line field fk_activity missing');
$assert(isset($line->fields['total']), 'Activity line field total missing');
$assert(isset($line->fields['movement_type']), 'Activity line field movement_type missing');

return true;
