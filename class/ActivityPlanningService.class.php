<?php
/*
 * Planning helpers for Safra agricultural activities.
 */

dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/safra/class/FvActivityLine.class.php');

class ActivityPlanningService
{
    /** @var DoliDB */
    private $db;

    /** @var string */
    public $error = '';

    /** @var string[] */
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Duplicate one activity as planned work for several field plots.
     *
     * Execution values, project/task links and stock movement tracking are
     * intentionally reset on every duplicate.
     *
     * @param FvActivity $source
     * @param int[]      $fieldplotIds
     * @param User       $user
     * @param array      $options
     * @return int[]|int
     */
    public function duplicateToFieldplots(FvActivity $source, array $fieldplotIds, User $user, array $options = array())
    {
        if (empty($source->id)) {
            $this->error = 'ErrorSafraActivityInvalidIdentifier';
            return -1;
        }

        $fieldplotIds = array_values(array_unique(array_filter(array_map('intval', $fieldplotIds))));
        if (empty($fieldplotIds)) {
            $this->error = 'ErrorSafraActivityDuplicateNoFieldPlots';
            return -1;
        }

        $fieldplots = $this->fetchFieldplots($fieldplotIds);
        if (count($fieldplots) !== count($fieldplotIds)) {
            if ($this->error === '') {
                $this->error = 'ErrorSafraActivityDuplicateInvalidFieldPlot';
            }
            return -1;
        }

        if (empty($source->lines)) {
            if ($source->fetchLines() < 0) {
                $this->error = $source->error ?: 'ErrorRecordNotFound';
                return -1;
            }
        }

        $copyResources = !isset($options['copy_resources']) || !empty($options['copy_resources']);
        $userLinks = $copyResources ? $source->fetchUserLinks() : array();
        $vehicleLinks = $copyResources ? $source->fetchVehicleLinks() : array();
        $implementLinks = $copyResources ? $source->fetchImplementLinks() : array();

        $useLocalTransaction = empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        $createdIds = array();
        foreach ($fieldplotIds as $fieldplotId) {
            $fieldplot = $fieldplots[$fieldplotId];
            $duplicate = $this->buildDuplicate($source, $fieldplot, $options);
            $result = $duplicate->create($user);
            if ($result <= 0) {
                return $this->rollbackDuplicate($useLocalTransaction, $duplicate->error ?: 'ErrorRecordNotSaved');
            }
            if (empty($duplicate->id)) {
                $duplicate->id = (int) $result;
            }

            if ($this->copyInputLines($source, $duplicate, $user) < 0) {
                return $this->rollbackDuplicate($useLocalTransaction, $this->error);
            }

            if ($copyResources) {
                if ($duplicate->setUsers($this->resetRelationExecution($userLinks, 'fk_user')) < 0
                    || $duplicate->setVehicles($this->resetRelationExecution($vehicleLinks, 'fk_vehicle')) < 0
                    || $duplicate->setImplements($this->resetRelationExecution($implementLinks, 'fk_implement')) < 0) {
                    return $this->rollbackDuplicate($useLocalTransaction, $duplicate->error ?: 'ErrorRecordNotSaved');
                }
            }

            $createdIds[] = (int) $duplicate->id;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return $createdIds;
    }

    /**
     * Decide whether an active activity missed its planned deadline.
     *
     * @param int             $status
     * @param int|string|null $plannedStart
     * @param int|string|null $plannedEnd
     * @param int|null        $now
     * @return bool
     */
    public static function isOverdue($status, $plannedStart = null, $plannedEnd = null, $now = null)
    {
        $status = FvActivity::normalizeStatus($status);
        if ($status === FvActivity::STATUS_COMPLETED || $status === FvActivity::STATUS_CANCELED) {
            return false;
        }

        $now = $now === null ? dol_now() : (int) $now;
        $end = self::asTimestamp($plannedEnd);
        if ($end > 0) {
            return $end < $now;
        }

        $start = self::asTimestamp($plannedStart);
        if ($start <= 0) {
            return false;
        }

        return date('Y-m-d', $start) < date('Y-m-d', $now);
    }

    /**
     * Return the planned quantity for a duplicate line.
     *
     * @param float $targetArea
     * @param float $plannedDose
     * @param float $sourceQuantity
     * @param float $sourceArea
     * @return float
     */
    public static function calculatePlannedQuantity($targetArea, $plannedDose, $sourceQuantity = 0, $sourceArea = 0)
    {
        $targetArea = self::asNumber($targetArea);
        $plannedDose = self::asNumber($plannedDose);
        $sourceQuantity = self::asNumber($sourceQuantity);
        $sourceArea = self::asNumber($sourceArea);

        if ($targetArea > 0 && $plannedDose > 0) {
            return $targetArea * $plannedDose;
        }
        if ($targetArea > 0 && $sourceArea > 0 && $sourceQuantity > 0) {
            return $sourceQuantity * ($targetArea / $sourceArea);
        }

        return $sourceQuantity;
    }

    /**
     * Convert dose units such as kg/ha into report quantity units.
     *
     * @param string $doseUnit
     * @return string
     */
    public static function consumptionUnit($doseUnit)
    {
        $doseUnit = trim((string) $doseUnit);
        if ($doseUnit === '') {
            return '';
        }

        return trim((string) preg_replace('~/\s*ha$~i', '', $doseUnit));
    }

    /**
     * @param FvActivity $source
     * @param object     $fieldplot
     * @param array      $options
     * @return FvActivity
     */
    protected function buildDuplicate(FvActivity $source, $fieldplot, array $options)
    {
        $duplicate = new FvActivity($this->db);
        foreach (array('type', 'priority', 'season', 'crop_name', 'cultivar_name', 'fk_thirdparty', 'weather', 'note_public', 'mixture_rate', 'mixture_tank_capacity') as $field) {
            $duplicate->{$field} = isset($source->{$field}) ? $source->{$field} : null;
        }

        $fieldplotLabel = trim((string) (($fieldplot->ref ? $fieldplot->ref . ' - ' : '') . $fieldplot->label));
        $duplicate->label = self::truncate(trim((string) $source->label . ($fieldplotLabel !== '' ? ' - ' . $fieldplotLabel : '')), 255);
        $duplicate->status = FvActivity::STATUS_PLANNED;
        $duplicate->progress = 0;
        $duplicate->fk_project = null;
        $duplicate->fk_task = null;
        $duplicate->fk_fieldplot = (int) $fieldplot->rowid;
        $duplicate->area_planned = self::asNumber($fieldplot->area) > 0 ? self::asNumber($fieldplot->area) : self::asNumber($source->area_planned);
        $duplicate->area_total = $duplicate->area_planned;
        $duplicate->area_done = 0;
        $duplicate->mixture_area = $duplicate->area_planned;
        $duplicate->date_planned_start = array_key_exists('date_planned_start', $options) ? $options['date_planned_start'] : $source->date_planned_start;
        $duplicate->date_planned_end = array_key_exists('date_planned_end', $options) ? $options['date_planned_end'] : $source->date_planned_end;
        $duplicate->date_start = null;
        $duplicate->date_end = null;
        $duplicate->note_private = '';

        return $duplicate;
    }

    /**
     * @param FvActivity $source
     * @param FvActivity $duplicate
     * @param User       $user
     * @return int
     */
    protected function copyInputLines(FvActivity $source, FvActivity $duplicate, User $user)
    {
        foreach ((array) $source->lines as $sourceLine) {
            if (empty($sourceLine->fk_product)) {
                continue;
            }

            $line = new FvActivityLine($this->db);
            $line->fk_activity = (int) $duplicate->id;
            $line->position = isset($sourceLine->position) ? (int) $sourceLine->position : 0;
            $line->fk_product = isset($sourceLine->fk_product) ? (int) $sourceLine->fk_product : 0;
            $line->fk_warehouse = isset($sourceLine->fk_warehouse) ? (int) $sourceLine->fk_warehouse : 0;
            $line->movement_type = isset($sourceLine->movement_type) ? $sourceLine->movement_type : FvActivityLine::MOVEMENT_CONSUME;
            $line->area_planned = self::asNumber($duplicate->area_planned);
            $line->area_done = 0;
            $line->dose_planned = $this->getFirstPositive($sourceLine, array('dose_planned', 'dose_done', 'dose'));
            $line->dose_done = 0;
            $line->dose_unit = isset($sourceLine->dose_unit) ? $sourceLine->dose_unit : '';
            $sourceQuantity = $this->getFirstPositive($sourceLine, array('qty_planned', 'qty_done', 'total'));
            $sourceArea = $this->getFirstPositive($sourceLine, array('area_planned', 'area_done', 'area_applied'));
            $line->qty_planned = self::calculatePlannedQuantity($line->area_planned, $line->dose_planned, $sourceQuantity, $sourceArea);
            $line->qty_done = 0;
            $line->total = 0;
            $line->area_applied = 0;
            $line->dose = 0;
            $line->unit_cost = isset($sourceLine->unit_cost) ? self::asNumber($sourceLine->unit_cost) : 0;
            $line->fk_stock_movement = null;
            $line->stock_movement_qty = 0;
            $line->note = isset($sourceLine->note) ? $sourceLine->note : '';

            if ($line->create($user) <= 0) {
                $this->error = $line->error ?: 'ErrorRecordNotSaved';
                return -1;
            }
        }

        return 1;
    }

    /**
     * @param int[] $ids
     * @return array<int,object>
     */
    protected function fetchFieldplots(array $ids)
    {
        $result = array();
        $sql = 'SELECT rowid, ref, label, area FROM ' . MAIN_DB_PREFIX . 'safra_talhao'
            . ' WHERE rowid IN (' . implode(',', array_map('intval', $ids)) . ')';
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return $result;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $result[(int) $obj->rowid] = $obj;
        }

        return $result;
    }

    /**
     * @param array  $rows
     * @param string $targetField
     * @return array
     */
    protected function resetRelationExecution(array $rows, $targetField)
    {
        $result = array();
        foreach ($rows as $row) {
            $data = (array) $row;
            unset($data['rowid'], $data['id']);
            $data[$targetField] = isset($data[$targetField]) ? (int) $data[$targetField] : 0;
            $data['done_hours'] = 0;
            $result[] = $data;
        }

        return $result;
    }

    /**
     * @param object   $object
     * @param string[] $fields
     * @return float
     */
    protected function getFirstPositive($object, array $fields)
    {
        foreach ($fields as $field) {
            if (isset($object->{$field}) && self::asNumber($object->{$field}) > 0) {
                return self::asNumber($object->{$field});
            }
        }

        return 0;
    }

    /**
     * @param bool   $useLocalTransaction
     * @param string $error
     * @return int
     */
    protected function rollbackDuplicate($useLocalTransaction, $error)
    {
        if ($useLocalTransaction) {
            $this->db->rollback();
        }
        $this->error = $error ?: 'ErrorRecordNotSaved';
        $this->errors[] = $this->error;

        return -1;
    }

    /**
     * @param mixed $value
     * @return int
     */
    protected static function asTimestamp($value)
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? (int) $timestamp : 0;
    }

    /**
     * @param mixed $value
     * @return float
     */
    protected static function asNumber($value)
    {
        if (function_exists('price2num')) {
            return (float) price2num($value, 'MT');
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    /**
     * @param string $value
     * @param int    $length
     * @return string
     */
    protected static function truncate($value, $length)
    {
        if (function_exists('mb_substr')) {
            return mb_substr((string) $value, 0, (int) $length, 'UTF-8');
        }

        return substr((string) $value, 0, (int) $length);
    }
}
