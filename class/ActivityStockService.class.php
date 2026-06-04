<?php
/*
 * Stock movement service for Safra agricultural activities.
 */

dol_include_once('/product/stock/class/mouvementstock.class.php');
dol_include_once('/safra/class/FvActivityLine.class.php');

class ActivityStockService
{
    const ORIGIN_TYPE = 'safra_activity';

    /** @var DoliDB */
    private $db;

    /** @var string|null */
    public $error;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Synchronize every activity line with its stock movement.
     *
     * @param FvActivity $activity
     * @param User       $user
     * @param bool       $force
     * @param bool       $useTransaction
     * @return int
     */
    public function createConsumptionMovements(FvActivity $activity, User $user, $force = false, $useTransaction = true)
    {
        if (empty($activity->id)) {
            $this->error = 'MissingActivityIdentifier';
            return -1;
        }

        if (empty($activity->lines)) {
            $activity->fetchLines();
        }

        $useLocalTransaction = $useTransaction && empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        $changed = 0;
        foreach ((array) $activity->lines as $line) {
            if ($force && !empty($line->fk_stock_movement)) {
                $remove = $this->removeLineMovement($activity, $line, $user, false);
                if ($remove < 0) {
                    if ($useLocalTransaction) {
                        $this->db->rollback();
                    }
                    return -1;
                }
                $changed += $remove;
            }

            $result = $this->syncLineMovement($activity, $line, $user, false);
            if ($result < 0) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                return -1;
            }
            $changed += $result;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return $changed > 0 ? 1 : 0;
    }

    /**
     * Reverse active line movements for an activity.
     *
     * @param FvActivity $activity
     * @param User       $user
     * @param bool       $useTransaction
     * @return int
     */
    public function removeActivityLineMovements(FvActivity $activity, User $user, $useTransaction = true)
    {
        if (empty($activity->id)) {
            $this->error = 'MissingActivityIdentifier';
            return -1;
        }

        if (empty($activity->lines)) {
            $activity->fetchLines();
        }

        $useLocalTransaction = $useTransaction && empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        $changed = 0;
        foreach ((array) $activity->lines as $line) {
            $result = $this->removeLineMovement($activity, $line, $user, false);
            if ($result < 0) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                return -1;
            }
            $changed += $result;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return $changed > 0 ? 1 : 0;
    }

    /**
     * Backward compatible activity-level reversal for legacy movements.
     *
     * New code should call removeActivityLineMovements() so only active line movements are reversed.
     *
     * @param FvActivity $activity
     * @param User       $user
     * @param bool       $useTransaction
     * @return int
     */
    public function revertConsumptionMovements(FvActivity $activity, User $user, $useTransaction = true)
    {
        $activeResult = $this->removeActivityLineMovements($activity, $user, $useTransaction);
        if ($activeResult !== 0) {
            return $activeResult;
        }

        return $this->revertLegacyActivityMovements($activity, $user, $useTransaction);
    }

    /**
     * Create, replace or clear the stock movement for one input line.
     *
     * @param FvActivity     $activity
     * @param FvActivityLine $line
     * @param User           $user
     * @param bool           $useTransaction
     * @return int
     */
    public function syncLineMovement(FvActivity $activity, FvActivityLine $line, User $user, $useTransaction = true)
    {
        if (empty($activity->id) || empty($line->id)) {
            $this->error = 'MissingActivityLineIdentifier';
            return -1;
        }

        $line->prepareForSave();

        $quantity = $this->getLineQuantity($line);
        $productId = isset($line->fk_product) ? (int) $line->fk_product : 0;
        $warehouseId = isset($line->fk_warehouse) ? (int) $line->fk_warehouse : (isset($line->fk_entrepot) ? (int) $line->fk_entrepot : 0);
        $movementType = FvActivityLine::normalizeMovementType(isset($line->movement_type) ? $line->movement_type : FvActivityLine::MOVEMENT_CONSUME);
        $existingMovement = !empty($line->fk_stock_movement) ? $this->fetchMovementById((int) $line->fk_stock_movement) : null;

        if ($quantity <= 0 || $productId <= 0) {
            return $this->removeLineMovement($activity, $line, $user, $useTransaction);
        }

        if ($warehouseId <= 0) {
            $this->error = 'ErrorSafraActivityMissingWarehouse';
            return -1;
        }

        if ($existingMovement && $this->movementMatches($existingMovement, $productId, $warehouseId, $quantity, $movementType)) {
            return $this->persistLineMovement($activity, $line, (int) $existingMovement->rowid, $quantity) < 0 ? -1 : 0;
        }

        $useLocalTransaction = $useTransaction && empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        if ($existingMovement) {
            $reverseResult = $this->reverseMovement($activity, $line, $existingMovement, $user);
            if ($reverseResult < 0) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                return -1;
            }
        }

        $movementId = $this->postMovement($activity, $line, $user, $productId, $warehouseId, $quantity, $movementType, $this->buildMovementLabel($activity, $line));
        if ($movementId <= 0) {
            if ($useLocalTransaction) {
                $this->db->rollback();
            }
            return -1;
        }

        if ($this->persistLineMovement($activity, $line, $movementId, $quantity) < 0) {
            if ($useLocalTransaction) {
                $this->db->rollback();
            }
            return -1;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return 1;
    }

    /**
     * Reverse and clear the movement tracked by a line.
     *
     * @param FvActivity     $activity
     * @param FvActivityLine $line
     * @param User           $user
     * @param bool           $useTransaction
     * @return int
     */
    public function removeLineMovement(FvActivity $activity, FvActivityLine $line, User $user, $useTransaction = true)
    {
        $movementId = !empty($line->fk_stock_movement) ? (int) $line->fk_stock_movement : 0;
        if ($movementId <= 0) {
            return 0;
        }

        $useLocalTransaction = $useTransaction && empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        $movement = $this->fetchMovementById($movementId);
        if ($movement) {
            $reverseResult = $this->reverseMovement($activity, $line, $movement, $user);
            if ($reverseResult < 0) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                return -1;
            }
        }

        if ($this->persistLineMovement($activity, $line, 0, 0) < 0) {
            if ($useLocalTransaction) {
                $this->db->rollback();
            }
            return -1;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return 1;
    }

    /**
     * Reverse old activity-level movements that predate line tracking.
     *
     * @param FvActivity $activity
     * @param User       $user
     * @param bool       $useTransaction
     * @return int
     */
    protected function revertLegacyActivityMovements(FvActivity $activity, User $user, $useTransaction = true)
    {
        if (empty($activity->id)) {
            $this->error = 'MissingActivityIdentifier';
            return -1;
        }

        $sql = 'SELECT rowid, fk_product, fk_entrepot, value as qty FROM ' . MAIN_DB_PREFIX . 'stock_mouvement'
            . ' WHERE fk_origin = ' . ((int) $activity->id)
            . " AND origintype = '" . $this->db->escape(self::ORIGIN_TYPE) . "'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }

        $movements = array();
        while ($obj = $this->db->fetch_object($resql)) {
            $movements[] = $obj;
        }

        if (empty($movements)) {
            return 0;
        }

        $useLocalTransaction = $useTransaction && empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        $posted = 0;
        foreach ($movements as $movementData) {
            $line = new FvActivityLine($this->db);
            $line->id = 0;
            $result = $this->reverseMovement($activity, $line, $movementData, $user);
            if ($result < 0) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                return -1;
            }
            $posted += $result;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return $posted > 0 ? 1 : 0;
    }

    /**
     * Reverse one movement by posting the opposite Dolibarr movement.
     *
     * @param FvActivity     $activity
     * @param FvActivityLine $line
     * @param mixed          $movementData
     * @param User           $user
     * @return int
     */
    protected function reverseMovement(FvActivity $activity, FvActivityLine $line, $movementData, User $user)
    {
        $quantity = abs($this->asNumber(isset($movementData->qty) ? $movementData->qty : 0));
        $productId = isset($movementData->fk_product) ? (int) $movementData->fk_product : 0;
        $warehouseId = isset($movementData->fk_entrepot) ? (int) $movementData->fk_entrepot : (isset($movementData->fk_warehouse) ? (int) $movementData->fk_warehouse : 0);

        if ($quantity <= 0 || $productId <= 0 || $warehouseId <= 0) {
            return 0;
        }

        $reverseType = $this->asNumber($movementData->qty) < 0 ? FvActivityLine::MOVEMENT_RETURN : FvActivityLine::MOVEMENT_CONSUME;
        $movementId = $this->postMovement($activity, $line, $user, $productId, $warehouseId, $quantity, $reverseType, $this->buildReversalLabel($activity, $movementData));

        return $movementId > 0 ? 1 : -1;
    }

    /**
     * Post one Dolibarr stock movement and return its id.
     *
     * @param FvActivity     $activity
     * @param FvActivityLine $line
     * @param User           $user
     * @param int            $productId
     * @param int            $warehouseId
     * @param float          $quantity
     * @param string         $movementType
     * @param string         $label
     * @return int
     */
    protected function postMovement(FvActivity $activity, FvActivityLine $line, User $user, $productId, $warehouseId, $quantity, $movementType, $label)
    {
        global $langs;

        if (is_object($langs)) {
            $langs->loadLangs(array('safra@safra', 'stocks'));
        }

        $movement = new MouvementStock($this->db);
        $movement->origin = $activity;
        if (method_exists($movement, 'setOrigin')) {
            $movement->setOrigin(self::ORIGIN_TYPE, (int) $activity->id, (int) $line->id, (int) $line->id);
        } else {
            $movement->origin_type = self::ORIGIN_TYPE;
            $movement->origin_id = (int) $activity->id;
            $movement->origintype = self::ORIGIN_TYPE;
            $movement->fk_origin = (int) $activity->id;
        }

        if ($movementType === FvActivityLine::MOVEMENT_RETURN) {
            $result = $movement->reception($user, $productId, $warehouseId, $quantity, 0, $label);
        } else {
            $result = $movement->livraison($user, $productId, $warehouseId, $quantity, 0, $label);
        }

        if ($result < 0 || empty($movement->id)) {
            $this->error = $movement->error ?: 'ErrorSafraActivityStockMovement';
            return -1;
        }

        return (int) $movement->id;
    }

    /**
     * Fetch a stock movement by id.
     *
     * @param int $movementId
     * @return object|null
     */
    protected function fetchMovementById($movementId)
    {
        $movementId = (int) $movementId;
        if ($movementId <= 0) {
            return null;
        }

        if (is_object($this->db) && isset($this->db->stockMovements[$movementId])) {
            $entry = $this->db->stockMovements[$movementId];
            $qty = isset($entry['qty']) ? $this->asNumber($entry['qty']) : 0;
            if (!empty($entry['movement']) && $entry['movement'] === 'consume' && $qty > 0) {
                $qty = 0 - $qty;
            }
            return (object) array(
                'rowid' => $movementId,
                'fk_product' => isset($entry['fk_product']) ? (int) $entry['fk_product'] : 0,
                'fk_entrepot' => isset($entry['fk_warehouse']) ? (int) $entry['fk_warehouse'] : 0,
                'qty' => $qty,
            );
        }

        $sql = 'SELECT rowid, fk_product, fk_entrepot, value as qty FROM ' . MAIN_DB_PREFIX . 'stock_mouvement'
            . ' WHERE rowid = ' . $movementId
            . " AND origintype = '" . $this->db->escape(self::ORIGIN_TYPE) . "'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return null;
        }

        $obj = $this->db->fetch_object($resql);

        return $obj ?: null;
    }

    /**
     * Persist movement tracking fields on the activity line.
     *
     * @param FvActivity     $activity
     * @param FvActivityLine $line
     * @param int            $movementId
     * @param float          $quantity
     * @return int
     */
    protected function persistLineMovement(FvActivity $activity, FvActivityLine $line, $movementId, $quantity)
    {
        if (empty($line->id)) {
            return 0;
        }

        $movementId = (int) $movementId;
        $quantity = $this->asNumber($quantity);
        $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'safra_activity_line'
            . ' SET fk_stock_movement = ' . ($movementId > 0 ? $movementId : 'NULL')
            . ', stock_movement_qty = ' . ((float) $quantity)
            . ' WHERE rowid = ' . ((int) $line->id)
            . ' AND fk_activity = ' . ((int) $activity->id);

        if (!$this->db->query($sql)) {
            $this->error = $this->db->lasterror();
            return -1;
        }

        $line->fk_stock_movement = $movementId > 0 ? $movementId : null;
        $line->stock_movement_qty = $quantity;

        return 1;
    }

    /**
     * Compare current stock movement with desired line state.
     *
     * @param mixed  $movement
     * @param int    $productId
     * @param int    $warehouseId
     * @param float  $quantity
     * @param string $movementType
     * @return bool
     */
    protected function movementMatches($movement, $productId, $warehouseId, $quantity, $movementType)
    {
        if ((int) $movement->fk_product !== (int) $productId) {
            return false;
        }
        if ((int) $movement->fk_entrepot !== (int) $warehouseId) {
            return false;
        }

        $movementQty = $this->asNumber($movement->qty);
        if ($movementType === FvActivityLine::MOVEMENT_RETURN) {
            if ($movementQty <= 0) {
                return false;
            }
        } elseif ($movementQty >= 0) {
            return false;
        }

        return abs(abs($movementQty) - $this->asNumber($quantity)) < 0.000001;
    }

    /**
     * Build label for stock movement.
     *
     * @param FvActivity     $activity
     * @param FvActivityLine $line
     * @return string
     */
    protected function buildMovementLabel(FvActivity $activity, FvActivityLine $line)
    {
        $lineId = isset($line->id) && $line->id ? $line->id : (isset($line->rowid) ? $line->rowid : '');

        return 'Safra activity ' . ($activity->ref ?: $activity->id) . ($lineId !== '' ? ' line ' . $lineId : '');
    }

    /**
     * Build reversal label.
     *
     * @param FvActivity $activity
     * @param mixed      $movement
     * @return string
     */
    protected function buildReversalLabel(FvActivity $activity, $movement)
    {
        return 'Safra activity ' . ($activity->ref ?: $activity->id) . ' reversal #' . (isset($movement->rowid) ? $movement->rowid : '');
    }

    /**
     * Get movement quantity from new or legacy line fields.
     *
     * @param mixed $line
     * @return float
     */
    protected function getLineQuantity($line)
    {
        foreach (array('qty_done', 'total', 'qty_planned') as $field) {
            if (isset($line->{$field}) && $this->asNumber($line->{$field}) > 0) {
                return $this->asNumber($line->{$field});
            }
        }

        return 0;
    }

    /**
     * Convert number using Dolibarr helper when available.
     *
     * @param mixed $value
     * @return float
     */
    protected function asNumber($value)
    {
        if (function_exists('price2num')) {
            return (float) price2num($value, 'MS');
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }
}
