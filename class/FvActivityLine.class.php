<?php
/*
 * Activity input line object for Safra.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobjectline.class.php';

class FvActivityLine extends CommonObjectLine
{
    const MOVEMENT_CONSUME = 'consume';
    const MOVEMENT_RETURN = 'return';

    /** @var string */
    public $module = 'safra';

    /** @var string */
    public $element = 'safra_activity_line';

    /** @var string */
    public $fk_element = 'fk_activity';

    /** @var string */
    public $table_element = 'safra_activity_line';

    /** @var int */
    public $ismultientitymanaged = 1;

    /** @var int */
    public $isextrafieldmanaged = 1;

    /** @var array */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'visible' => -1, 'notnull' => 1, 'position' => 10),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'default' => 1, 'position' => 20),
        'fk_activity' => array('type' => 'integer:FvActivity:custom/safra/class/FvActivity.class.php:1', 'label' => 'SafraActivity', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'index' => 1, 'position' => 30),
        'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'default' => 0, 'position' => 40),
        'fk_product' => array('type' => 'integer:Product:product/class/product.class.php:1', 'label' => 'Product', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 50),
        'fk_warehouse' => array('type' => 'integer:Entrepot:product/stock/class/entrepot.class.php:1', 'label' => 'Warehouse', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 60),
        'movement_type' => array('type' => 'varchar(16)', 'label' => 'SafraLineMovement', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => self::MOVEMENT_CONSUME, 'position' => 70),
        'area_planned' => array('type' => 'double(24,8)', 'label' => 'SafraActivityAreaPlanned', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 80),
        'area_done' => array('type' => 'double(24,8)', 'label' => 'SafraActivityAreaDone', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 90),
        'dose_planned' => array('type' => 'double(24,8)', 'label' => 'SafraActivityDosePlanned', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 100),
        'dose_done' => array('type' => 'double(24,8)', 'label' => 'SafraActivityDoseDone', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 110),
        'dose_unit' => array('type' => 'varchar(32)', 'label' => 'DoseUnit', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 120),
        'qty_planned' => array('type' => 'double(24,8)', 'label' => 'SafraActivityQtyPlanned', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 130),
        'qty_done' => array('type' => 'double(24,8)', 'label' => 'SafraActivityQtyDone', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 140),
        'total' => array('type' => 'double(24,8)', 'label' => 'Total', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'default' => '0', 'position' => 150),
        'area_applied' => array('type' => 'double(24,8)', 'label' => 'AreaApplied', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'default' => '0', 'position' => 160),
        'dose' => array('type' => 'double(24,8)', 'label' => 'Dose', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'default' => '0', 'position' => 170),
        'unit_cost' => array('type' => 'double(24,8)', 'label' => 'UnitCost', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 180),
        'note' => array('type' => 'text', 'label' => 'Note', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 190),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'position' => 200),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'position' => 210),
        'fk_user_creat' => array('type' => 'integer', 'label' => 'UserAuthor', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'position' => 220),
        'fk_user_modif' => array('type' => 'integer', 'label' => 'UserModif', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'position' => 230),
    );

    /**
     * Create activity line.
     *
     * @param User $user
     * @return int
     */
    public function create($user)
    {
        global $conf;

        $this->entity = $this->entity ?: $conf->entity;
        $this->prepareForSave();

        return $this->createCommon($user);
    }

    /**
     * Update activity line.
     *
     * @param User $user
     * @return int
     */
    public function update($user)
    {
        $this->prepareForSave();

        return $this->updateCommon($user);
    }

    /**
     * Fetch activity line by id/ref.
     *
     * @param int         $id
     * @param string|null $ref
     * @return int
     */
    public function fetch($id, $ref = null)
    {
        return $this->fetchCommon($id, $ref);
    }

    /**
     * Normalize quantities and compatibility aliases before persistence.
     *
     * @return void
     */
    public function prepareForSave()
    {
        $this->movement_type = self::normalizeMovementType($this->movement_type);

        $this->area_planned = self::asNumber($this->area_planned);
        $this->area_done = self::asNumber($this->area_done);
        $this->dose_planned = self::asNumber($this->dose_planned);
        $this->dose_done = self::asNumber($this->dose_done);
        $this->qty_planned = self::asNumber($this->qty_planned);
        $this->qty_done = self::asNumber($this->qty_done);
        $this->unit_cost = self::asNumber($this->unit_cost);

        if ($this->area_planned <= 0 && !empty($this->area_applied)) {
            $this->area_planned = self::asNumber($this->area_applied);
        }
        if ($this->area_done <= 0 && !empty($this->area_applied)) {
            $this->area_done = self::asNumber($this->area_applied);
        }
        if ($this->dose_planned <= 0 && !empty($this->dose)) {
            $this->dose_planned = self::asNumber($this->dose);
        }
        if ($this->dose_done <= 0 && !empty($this->dose)) {
            $this->dose_done = self::asNumber($this->dose);
        }
        if ($this->qty_planned <= 0 && $this->area_planned > 0 && $this->dose_planned > 0) {
            $this->qty_planned = $this->area_planned * $this->dose_planned;
        }
        if ($this->qty_done <= 0 && $this->area_done > 0 && $this->dose_done > 0) {
            $this->qty_done = $this->area_done * $this->dose_done;
        }
        if ($this->qty_done <= 0 && !empty($this->total)) {
            $this->qty_done = self::asNumber($this->total);
        }

        $this->area_applied = $this->area_done > 0 ? $this->area_done : $this->area_planned;
        $this->dose = $this->dose_done > 0 ? $this->dose_done : $this->dose_planned;
        $this->total = $this->qty_done > 0 ? $this->qty_done : $this->qty_planned;
    }

    /**
     * Delete every line linked to an activity.
     *
     * @param DoliDB $db
     * @param int    $activityId
     * @return int
     */
    public static function deleteForActivity($db, $activityId)
    {
        $activityId = (int) $activityId;
        if ($activityId <= 0) {
            return 0;
        }

        $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'safra_activity_line WHERE fk_activity = ' . $activityId;
        if (!$db->query($sql)) {
            return -1;
        }

        return 1;
    }

    /**
     * Normalize movement type.
     *
     * @param string|null $type
     * @return string
     */
    public static function normalizeMovementType($type)
    {
        $type = strtolower(trim((string) $type));
        if ($type === self::MOVEMENT_RETURN || $type === 'receive' || $type === 'reception') {
            return self::MOVEMENT_RETURN;
        }

        return self::MOVEMENT_CONSUME;
    }

    /**
     * Return movement labels.
     *
     * @param Translate|null $translator
     * @return array<string,string>
     */
    public static function getMovementOptions($translator = null)
    {
        if (!is_object($translator)) {
            global $langs;
            $translator = $langs;
        }

        return array(
            self::MOVEMENT_CONSUME => is_object($translator) ? $translator->trans('SafraLineMovementConsume') : 'Consumption',
            self::MOVEMENT_RETURN => is_object($translator) ? $translator->trans('SafraLineMovementReturn') : 'Return',
        );
    }

    /**
     * Convert any numeric input using Dolibarr number rules when available.
     *
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
}
