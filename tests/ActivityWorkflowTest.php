<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require_once __DIR__.'/../class/aplicacao.class.php';

class DummyDoliDB
{
    public $queries = array();
    public $transactions = array();
    public $shouldQuerySucceed = true;

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

    public function query(string $sql)
    {
        $this->queries[] = $sql;
        return $this->shouldQuerySucceed;
    }

    public function escape($value): string
    {
        return addslashes((string) $value);
    }

    public function idate(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    public function lasterror(): string
    {
        return 'dummy-error';
    }

    public function DDLDescTable($table, $alias)
    {
        return array();
    }

    public function prefix(): string
    {
        return MAIN_DB_PREFIX;
    }
}

class ActivityWorkflowDouble extends Aplicacao
{
    public $setStatusCalls = array();
    public $triggerCalls = array();
    public $syncTaskCalls = array();
    public $appliedSummaries = array();
    public $rolledBackSummaries = array();
    public $stubSummary = array();
    public $shouldSetStatusReturn = 1;
    public $shouldApplyStockReturn = 1;
    public $shouldSyncTaskReturn = 1;
    public $shouldTriggerReturn = 1;
    public $simulateMissingWarehouse = false;
    public $newref = '';
    public $oldref = '';
    public $labelStatus = array();
    public $labelStatusShort = array();

    public function __construct()
    {
        $this->db = new DummyDoliDB();
        $this->lines = array();
        $this->status = self::STATUS_DRAFT;
        $this->ref = 'APP-0001';
        $this->id = 101;
        $this->fields = array();
    }

    public function setStatusCommon($user, $status, $notrigger = 0, $trigger = '')
    {
        $this->setStatusCalls[] = array(
            'status' => $status,
            'notrigger' => $notrigger,
            'trigger' => $trigger,
        );

        return $this->shouldSetStatusReturn;
    }

    public function syncTask(User $user)
    {
        $this->syncTaskCalls[] = array(
            'user' => $user,
            'status' => $this->status,
        );

        return $this->shouldSyncTaskReturn;
    }

    public function buildStockSummary(User $user = null, &$missingWarehouse = false)
    {
        $missingWarehouse = $this->simulateMissingWarehouse;
        return $this->stubSummary;
    }

    protected function applyStockMovementsFromSummary(User $user, array $summary, array &$applied = array())
    {
        $this->appliedSummaries[] = $summary;
        if ($this->shouldApplyStockReturn > 0) {
            $applied[] = array('summary' => $summary);
        }

        return $this->shouldApplyStockReturn;
    }

    protected function rollbackStockOperations(User $user, array $operations)
    {
        $this->rolledBackSummaries[] = $operations;
    }

    public function fetchLines($noextrafields = 0)
    {
        return 1;
    }

    public function call_trigger($trigger, $user)
    {
        $this->triggerCalls[] = $trigger;
        return $this->shouldTriggerReturn;
    }
}

$assertions = 0;

function assertTrue($condition, string $message = ''): void
{
    global $assertions;
    if (!$condition) {
        throw new RuntimeException($message ?: 'Failed asserting that condition is true');
    }
    $assertions++;
}

function assertSame($expected, $actual, string $message = ''): void
{
    global $assertions;
    if ($expected !== $actual) {
        $exportExpected = var_export($expected, true);
        $exportActual = var_export($actual, true);
        throw new RuntimeException($message ?: "Failed asserting that {$exportActual} is identical to {$exportExpected}");
    }
    $assertions++;
}

function assertCountValue(int $expectedCount, $value, string $message = ''): void
{
    global $assertions;
    $count = is_array($value) ? count($value) : 0;
    if ($count !== $expectedCount) {
        throw new RuntimeException($message ?: "Failed asserting count {$count} matches expected {$expectedCount}");
    }
    $assertions++;
}

function assertContainsValue($needle, array $haystack, string $message = ''): void
{
    global $assertions;
    if (!in_array($needle, $haystack, true)) {
        throw new RuntimeException($message ?: "Failed asserting that array contains needle");
    }
    $assertions++;
}

try {
    $user = new User(5);

    // Validate transition
    $activity = new ActivityWorkflowDouble();
    $activity->status = Aplicacao::STATUS_DRAFT;
    $activity->ref = 'APP-0001';
    $result = $activity->validate($user);
    assertSame(1, $result, 'Validation should succeed');
    assertSame(Aplicacao::STATUS_VALIDATED, $activity->status, 'Validation should move to validated status');
    assertTrue(!empty($activity->db->queries), 'Validation must execute at least one SQL query');
    assertContainsValue('MYOBJECT_VALIDATE', $activity->triggerCalls, 'Validation should fire trigger');

    // Start transition
    $activity->status = Aplicacao::STATUS_VALIDATED;
    $activity->setStatusCalls = array();
    $activity->syncTaskCalls = array();
    $startResult = $activity->markAsInProgress($user);
    assertSame(1, $startResult, 'markAsInProgress should succeed when validated');
    assertSame(Aplicacao::STATUS_IN_PROGRESS, $activity->status, 'Status should become in progress');
    assertCountValue(1, $activity->setStatusCalls, 'setStatusCommon must be called once when starting');
    assertContainsValue('SAFRA_ACTIVITY_START', array_column($activity->setStatusCalls, 'trigger'), 'Start must use start trigger');
    assertCountValue(1, $activity->syncTaskCalls, 'syncTask should be invoked on start');

    // Prevent invalid start
    $activity->status = Aplicacao::STATUS_DRAFT;
    $activity->setStatusCalls = array();
    $invalidStart = $activity->markAsInProgress($user);
    assertSame(-1, $invalidStart, 'markAsInProgress should fail from draft');
    assertTrue(strpos($activity->error, 'ErrorSafraActivityInvalidTransitionState') !== false, 'Error message should mention invalid state');
    assertCountValue(0, $activity->setStatusCalls, 'setStatusCommon must not be called on invalid start');

    // Complete transition
    $activity->status = Aplicacao::STATUS_IN_PROGRESS;
    $activity->db = new DummyDoliDB();
    $activity->setStatusCalls = array();
    $activity->syncTaskCalls = array();
    $activity->appliedSummaries = array();
    $activity->rolledBackSummaries = array();
    $activity->stubSummary = array(
        '1:1:2' => array(
            'fk_product' => 1,
            'fk_entrepot' => 2,
            'qty' => 5.5,
            'movement' => 1,
            'labels' => array('Linha A' => true),
        ),
    );
    $completeResult = $activity->markAsCompleted($user);
    assertSame(1, $completeResult, 'markAsCompleted should succeed');
    assertSame(Aplicacao::STATUS_COMPLETED, $activity->status, 'Status should become completed');
    assertCountValue(1, $activity->setStatusCalls, 'Completion should call setStatusCommon once');
    assertContainsValue('SAFRA_ACTIVITY_COMPLETE', array_column($activity->setStatusCalls, 'trigger'), 'Completion must use complete trigger');
    assertCountValue(1, $activity->appliedSummaries, 'Completion should attempt stock movement');
    assertTrue(isset($activity->appliedSummaries[0]['1:1:2']), 'Applied summary must include aggregated key');
    assertCountValue(1, $activity->syncTaskCalls, 'Completion must sync linked task');
    assertSame(array('begin', 'commit'), $activity->db->transactions, 'Completion must wrap actions in a transaction');
    assertCountValue(0, $activity->rolledBackSummaries, 'No rollback should be triggered on success');

    // Cancel transition
    $activity->status = Aplicacao::STATUS_VALIDATED;
    $activity->setStatusCalls = array();
    $cancelResult = $activity->cancel($user);
    assertSame(1, $cancelResult, 'Cancel should succeed from validated status');
    assertSame(Aplicacao::STATUS_CANCELED, $activity->status, 'Cancel should switch to canceled status');
    assertContainsValue('SAFRA_ACTIVITY_CANCEL', array_column($activity->setStatusCalls, 'trigger'), 'Cancel must call cancel trigger');

    echo "All tests passed ({$assertions} assertions)\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Test failure: '.$e->getMessage()."\n");
    exit(1);
}
