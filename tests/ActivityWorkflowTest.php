<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require_once __DIR__.'/../class/safraactivity.class.php';
require_once __DIR__.'/../class/api_sfactivities.class.php';

class DummyResult
{
    private $rows;
    private $index = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function fetchObject()
    {
        if ($this->index >= count($this->rows)) {
            return false;
        }
        $row = $this->rows[$this->index++];
        $object = new stdClass();
        foreach ($row as $key => $value) {
            $object->{$key} = $value;
        }
        return $object;
    }
}

class DummyDoliDB
{
    public $transactions = array();
    public $queries = array();
    public $stockMovements = array();
    public $updatedCosts = array();
    public $selectActivities = array();

    public function begin(): void
    {
        $this->transactions[] = 'begin';
    }

    public function commit(): void
    {
        $this->transactions[] = 'commit';
    }

    public function rollback(): void
    {
        $this->transactions[] = 'rollback';
    }

    public function escape($value): string
    {
        return (string) $value;
    }

    public function plimit($limit, $offset = 0): string
    {
        return 'LIMIT '.((int) $limit).' OFFSET '.((int) $offset);
    }

    public function idate($timestamp): string
    {
        return date('Y-m-d H:i:s', (int) $timestamp);
    }

    public function query(string $sql)
    {
        $this->queries[] = $sql;

        if (stripos($sql, 'SELECT t.rowid FROM llx_safra_activity') === 0) {
            $rows = array();
            foreach ($this->selectActivities as $id) {
                $rows[] = array('rowid' => $id);
            }
            return new DummyResult($rows);
        }

        if (stripos($sql, 'SELECT rowid FROM llx_stock_mouvement') === 0) {
            $rows = array();
            foreach ($this->stockMovements as $id => $row) {
                $rows[] = array('rowid' => $id);
            }
            return new DummyResult($rows);
        }

        if (stripos($sql, 'UPDATE llx_safra_activity SET planned_cost') === 0) {
            $this->updatedCosts[] = $sql;
            return true;
        }

        return true;
    }

    public function fetch_object($resql)
    {
        if ($resql instanceof DummyResult) {
            return $resql->fetchObject();
        }
        return false;
    }

    public function free($resql): void
    {
        // No-op for dummy result sets.
    }

    public function lasterror(): string
    {
        return 'dummy-error';
    }
}

class ActivityWorkflowDouble extends SafraActivity
{
    public $setStatusCalls = array();
    public $triggerCalls = array();
    public $syncCalls = 0;

    public function __construct()
    {
        $this->db = new DummyDoliDB();
        $this->status = self::STATUS_DRAFT;
        $this->ref = 'ACT-TEST';
        $this->id = 1;
        $this->fields = array();
    }

    public function setStatusCommon($user, $status, $notrigger = 0, $trigger = '')
    {
        $this->setStatusCalls[] = array('status' => $status, 'trigger' => $trigger);
        $this->status = $status;
        return 1;
    }

    public function call_trigger($trigger, $user)
    {
        $this->triggerCalls[] = $trigger;
        return 1;
    }

    public function syncStockMovements(User $user)
    {
        $this->syncCalls++;
        return parent::syncStockMovements($user);
    }

    public function fetchLines($noextrafields = 0)
    {
        // Lines are manually injected in tests.
        return count($this->lines);
    }
}

class ApiDouble extends SafraActivitiesApi
{
    public $loadedActivity;

    public function __construct($db, SafraActivity $activity)
    {
        parent::__construct($db);
        $this->loadedActivity = $activity;
    }

    protected function loadActivity($id, $includeLines = false)
    {
        return $this->loadedActivity;
    }

    protected function populateActivity(SafraActivity $activity, array $data)
    {
        $activity->label = isset($data['label']) ? $data['label'] : $activity->label;
    }
}

$assertions = 0;

function assertSame($expected, $actual, string $message = ''): void
{
    global $assertions;
    if ($expected !== $actual) {
        throw new RuntimeException($message ?: 'Expected '.var_export($expected, true).' got '.var_export($actual, true));
    }
    $assertions++;
}

function assertTrue($condition, string $message = ''): void
{
    global $assertions;
    if (!$condition) {
        throw new RuntimeException($message ?: 'Condition is false');
    }
    $assertions++;
}

try {
    $user = new User(5);

    // Validation workflow.
    $activity = new ActivityWorkflowDouble();
    $result = $activity->validate($user);
    assertSame(1, $result, 'Validate should succeed');
    assertSame(SafraActivity::STATUS_VALIDATED, $activity->status, 'Status should change to validated');

    // Start workflow.
    $activity->status = SafraActivity::STATUS_VALIDATED;
    $activity->setStatusCalls = array();
    $startResult = $activity->start($user);
    assertSame(1, $startResult, 'Start should succeed');
    assertSame(SafraActivity::STATUS_IN_PROGRESS, $activity->status, 'Status becomes in progress');

    // Prepare lines for stock sync and cost update.
    require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
    require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
    Product::$repository = array(
        1 => array('pmp' => 10),
    );
    MouvementStock::$movements = array();
    MouvementStock::$autoIncrement = 1;

    $line = new SafraActivityLine($activity->db);
    $line->fk_product = 1;
    $line->qty = 2;
    $line->fk_warehouse = 1;
    $line->movement_type = SafraActivity::MOVEMENT_CONSUME;
    $line->array_options = array();
    $activity->lines = array($line);

    $syncResult = $activity->syncStockMovements($user);
    assertSame(1, $syncResult, 'A single stock movement applied');
    assertSame(1, count(MouvementStock::$movements), 'Movement stored in stub');

    $costResult = $activity->updateCostTotals();
    assertSame(1, $costResult, 'Cost totals updated');
    assertSame(20.0, $activity->planned_cost, 'Planned cost computed');
    assertSame(20.0, $activity->actual_cost, 'Actual cost computed');

    // Complete workflow with transaction control.
    $activity->status = SafraActivity::STATUS_IN_PROGRESS;
    $activity->setStatusCalls = array();
    $activity->db->transactions = array();
    $completeResult = $activity->complete($user);
    assertSame(1, $completeResult, 'Complete should succeed');
    assertTrue(in_array('begin', $activity->db->transactions, true), 'Transaction begun');
    assertTrue(in_array('commit', $activity->db->transactions, true), 'Transaction committed');
    assertSame(SafraActivity::STATUS_COMPLETED, $activity->status, 'Status becomes completed');

    // Sync should have been called during completion.
    assertTrue($activity->syncCalls > 0, 'Stock sync executed during completion');

    // Missing warehouse handling.
    $badActivity = new ActivityWorkflowDouble();
    $badLine = new SafraActivityLine($badActivity->db);
    $badLine->fk_product = 1;
    $badLine->qty = 1;
    $badLine->fk_warehouse = 0;
    $badLine->movement_type = SafraActivity::MOVEMENT_CONSUME;
    $badLine->array_options = array();
    $badActivity->lines = array($badLine);
    $missingResult = $badActivity->syncStockMovements($user);
    assertSame(-1, $missingResult, 'Missing warehouse must fail');

    // API status change wiring.
    $apiActivity = new ActivityWorkflowDouble();
    $apiActivity->status = SafraActivity::STATUS_VALIDATED;
    $api = new ApiDouble($apiActivity->db, $apiActivity);
    $api->postStatus(1, array('action' => 'start'));
    assertSame(SafraActivity::STATUS_IN_PROGRESS, $apiActivity->status, 'API triggered workflow');

    echo "All tests passed ({$assertions} assertions)\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Test failure: '.$e->getMessage()."\n");
    exit(1);
}
