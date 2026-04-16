<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/class/FvActivity.class.php';
require_once dirname(__DIR__) . '/class/ActivityStockService.class.php';
require_once __DIR__ . '/stubs/product/stock/class/mouvementstock.class.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

class ActivityStockServiceTestActivity extends FvActivity
{
    /** @var bool */
    private $hasMovements = false;

    public function setHasMovements(bool $value): void
    {
        $this->hasMovements = $value;
    }

    public function hasStockMovements()
    {
        return $this->hasMovements;
    }

    public function fetchLines()
    {
        return is_array($this->lines) ? count($this->lines) : 0;
    }
}

MouvementStock::$movements = array();
MouvementStock::$autoIncrement = 1;

$db = new DoliDB();
$service = new ActivityStockService($db);
$activity = new ActivityStockServiceTestActivity($db);
$activity->id = 1001;
$activity->ref = 'ACT-1001';
$activity->lines = array(
    (object) array('id' => 1, 'movement_type' => 'consume', 'total' => 12.5, 'fk_product' => 21, 'fk_warehouse' => 2),
    (object) array('id' => 2, 'movement_type' => 'return', 'total' => 3, 'fk_product' => 21, 'fk_warehouse' => 2),
);

$user = new User(1);

$createResult = $service->createConsumptionMovements($activity, $user);
$assert($createResult === 1, 'createConsumptionMovements should return success');
$assert(count(MouvementStock::$movements) === 1, 'Only consume lines must generate stock movement');

$firstMovement = array_values(MouvementStock::$movements)[0];
$assert((int) $firstMovement['fk_product'] === 21, 'Movement product mismatch');
$assert((int) $firstMovement['fk_warehouse'] === 2, 'Movement warehouse mismatch');

$activity->setHasMovements(true);
$duplicateResult = $service->createConsumptionMovements($activity, $user, false);
$assert($duplicateResult === 0, 'createConsumptionMovements must skip when activity already has stock movement');

$db->addMockQuery(
    'from ' . MAIN_DB_PREFIX . 'stock_mouvement',
    array(
        (object) array('rowid' => 9001, 'fk_product' => 21, 'fk_entrepot' => 2, 'qty' => -12.5),
    )
);

$revertResult = $service->revertConsumptionMovements($activity, $user);
$assert($revertResult === 1, 'revertConsumptionMovements should return success');
$assert(count(MouvementStock::$movements) === 2, 'Revert should create one reception movement');

$lastMovement = array_values(MouvementStock::$movements)[1];
$assert($lastMovement['movement'] === 'return', 'Revert must create reception movement');

return true;
