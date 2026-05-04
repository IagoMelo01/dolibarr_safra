<?php
/*
 * Stock movement service for Safra agricultural activities.
 */

dol_include_once('/product/stock/class/mouvementstock.class.php');
dol_include_once('/safra/class/FvActivityLine.class.php');

class ActivityStockService
{
    /** @var DoliDB */
    private $db;

    /** @var string|null */
    public $error;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create stock movements for activity input lines.
     *
     * Consumption lines create stock deliveries. Return lines create stock receptions.
     *
     * @param FvActivity $activity
     * @param User       $user
     * @param bool       $force
     * @param bool       $useTransaction
     * @return int
     */
    public function createConsumptionMovements(FvActivity $activity, User $user, $force = false, $useTransaction = true)
    {
        global $langs;

        if (empty($activity->id)) {
            $this->error = 'MissingActivityIdentifier';
            return -1;
        }

        if (!$force && $activity->hasStockMovements()) {
            return 0;
        }

        if (is_object($langs)) {
            $langs->loadLangs(array('safra@safra', 'stocks'));
        }

        if (empty($activity->lines)) {
            $activity->fetchLines();
        }

        $useLocalTransaction = $useTransaction && empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        $posted = 0;
        foreach ($activity->lines as $line) {
            $quantity = $this->getLineQuantity($line);
            $productId = isset($line->fk_product) ? (int) $line->fk_product : 0;
            $warehouseId = isset($line->fk_warehouse) ? (int) $line->fk_warehouse : (isset($line->fk_entrepot) ? (int) $line->fk_entrepot : 0);

            if ($quantity <= 0 || $productId <= 0 || $warehouseId <= 0) {
                continue;
            }

            $movementType = FvActivityLine::normalizeMovementType(isset($line->movement_type) ? $line->movement_type : FvActivityLine::MOVEMENT_CONSUME);
            $movement = new MouvementStock($this->db);
            $movement->origin = $activity;
            $movement->origin_id = (int) $activity->id;
            $movement->origintype = 'safra_activity';

            $label = $this->buildMovementLabel($activity, $line);
            if ($movementType === FvActivityLine::MOVEMENT_RETURN) {
                $result = $movement->reception($user, $productId, $warehouseId, $quantity, 0, $label);
            } else {
                $result = $movement->livraison($user, $productId, $warehouseId, $quantity, 0, $label);
            }

            if ($result < 0) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                $this->error = $movement->error ?: 'ErrorSafraActivityStockMovement';

                return -1;
            }

            $posted++;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return $posted > 0 ? 1 : 0;
    }

    /**
     * Reverse all stock movements linked to an activity.
     *
     * @param FvActivity $activity
     * @param User       $user
     * @param bool       $useTransaction
     * @return int
     */
    public function revertConsumptionMovements(FvActivity $activity, User $user, $useTransaction = true)
    {
        if (empty($activity->id)) {
            $this->error = 'MissingActivityIdentifier';
            return -1;
        }

        $sql = 'SELECT rowid, fk_product, fk_entrepot, qty FROM ' . MAIN_DB_PREFIX . 'stock_mouvement'
            . ' WHERE fk_origin = ' . ((int) $activity->id)
            . " AND origintype = 'safra_activity'";
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
            $quantity = abs($this->asNumber($movementData->qty));
            $productId = (int) $movementData->fk_product;
            $warehouseId = (int) $movementData->fk_entrepot;
            if ($quantity <= 0 || $productId <= 0 || $warehouseId <= 0) {
                continue;
            }

            $movement = new MouvementStock($this->db);
            $movement->origin = $activity;
            $movement->origin_id = (int) $activity->id;
            $movement->origintype = 'safra_activity';

            $label = $this->buildReversalLabel($activity, $movementData);
            if ($this->asNumber($movementData->qty) < 0) {
                $result = $movement->reception($user, $productId, $warehouseId, $quantity, 0, $label);
            } else {
                $result = $movement->livraison($user, $productId, $warehouseId, $quantity, 0, $label);
            }

            if ($result < 0) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                $this->error = $movement->error ?: 'ErrorSafraActivityStockMovement';

                return -1;
            }

            $posted++;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return $posted > 0 ? 1 : 0;
    }

    /**
     * Build label for stock movement.
     *
     * @param FvActivity $activity
     * @param mixed      $line
     * @return string
     */
    protected function buildMovementLabel(FvActivity $activity, $line)
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
