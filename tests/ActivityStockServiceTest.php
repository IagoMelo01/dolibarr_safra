<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/class/FvActivity.class.php';
require_once dirname(__DIR__) . '/class/FvActivityLine.class.php';
require_once dirname(__DIR__) . '/class/ActivityStockService.class.php';
require_once __DIR__ . '/stubs/product/stock/class/mouvementstock.class.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

class ActivityStockServiceTestActivity extends FvActivity
{
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

$line1 = new FvActivityLine($db);
$line1->id = 1;
$line1->fk_activity = 1001;
$line1->movement_type = 'consume';
$line1->qty_done = 12.5;
$line1->fk_product = 21;
$line1->fk_warehouse = 2;

$line2 = new FvActivityLine($db);
$line2->id = 2;
$line2->fk_activity = 1001;
$line2->movement_type = 'return';
$line2->qty_done = 3;
$line2->fk_product = 21;
$line2->fk_warehouse = 2;

$activity->lines = array($line1, $line2);
$user = new User(1);

$createResult = $service->createConsumptionMovements($activity, $user);
$assert($createResult === 1, 'createConsumptionMovements should return success');
$assert(count(MouvementStock::$movements) === 2, 'Consume and return lines must generate stock movements');
$assert((int) $line1->fk_stock_movement > 0, 'Line 1 must keep generated stock movement id');
$assert((int) $line2->fk_stock_movement > 0, 'Line 2 must keep generated stock movement id');

$firstMovement = MouvementStock::$movements[(int) $line1->fk_stock_movement];
$assert((int) $firstMovement['fk_product'] === 21, 'Movement product mismatch');
$assert((int) $firstMovement['fk_warehouse'] === 2, 'Movement warehouse mismatch');
$assert($firstMovement['movement'] === 'consume', 'Consume line must create delivery movement');
$assert((float) $firstMovement['qty'] === -12.5, 'Consume movement must store negative stock quantity');
$assert($firstMovement['origintype'] === ActivityStockService::ORIGIN_TYPE, 'Movement must keep Safra origin type');
$assert((int) $firstMovement['fk_origin'] === 1001, 'Movement must keep Safra activity origin id');

$duplicateResult = $service->createConsumptionMovements($activity, $user, false);
$assert($duplicateResult === 0, 'createConsumptionMovements must skip unchanged tracked movements');
$assert(count(MouvementStock::$movements) === 2, 'Unchanged sync must not duplicate movements');

$line1->qty_done = 10;
$updateResult = $service->syncLineMovement($activity, $line1, $user);
$assert($updateResult === 1, 'Changing quantity must replace stock movement');
$assert(count(MouvementStock::$movements) === 4, 'Quantity change must post a reversal and a new movement');
$assert((int) $line1->fk_stock_movement === 4, 'Line must point to the newest movement after update');
$assert(MouvementStock::$movements[3]['movement'] === 'return', 'Previous consume movement must be reversed with stock entry');
$assert(MouvementStock::$movements[4]['movement'] === 'consume', 'Updated line must create a new consume movement');
$assert((float) MouvementStock::$movements[4]['qty'] === -10.0, 'Updated consume movement quantity mismatch');

$removeResult = $service->removeLineMovement($activity, $line1, $user);
$assert($removeResult === 1, 'Removing a line movement must succeed');
$assert(empty($line1->fk_stock_movement), 'Removed line must clear stock movement id');
$assert(count(MouvementStock::$movements) === 5, 'Removal must post a reversal movement');
$assert(MouvementStock::$movements[5]['movement'] === 'return', 'Removed consume line must be reversed with stock entry');

$db->addMockQuery(
    'from ' . MAIN_DB_PREFIX . 'stock_mouvement',
    array(
        (object) array('rowid' => 9001, 'fk_product' => 21, 'fk_entrepot' => 2, 'qty' => -12.5),
    )
);

$legacyActivity = new ActivityStockServiceTestActivity($db);
$legacyActivity->id = 2002;
$legacyActivity->ref = 'ACT-LEGACY';
$legacyActivity->lines = array();
$legacyResult = $service->revertConsumptionMovements($legacyActivity, $user);
$assert($legacyResult === 1, 'Legacy activity-level reversal should still work');
$lastMovement = end(MouvementStock::$movements);
$assert($lastMovement['movement'] === 'return', 'Legacy reversal must create reception movement');

return true;
