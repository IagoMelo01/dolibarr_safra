<?php
/*
 * Helper to manage stock movements for Safra activities.
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
     * Create stock consumption movements for an activity.
     *
     * @param FvActivity $activity
     * @param User       $user
     * @return int
     */
    public function createConsumptionMovements(FvActivity $activity, User $user)
    {
        global $langs;

        if (empty($activity->id)) {
            $this->error = 'MissingActivityIdentifier';
            return -1;
        }

        if ($activity->hasStockMovements()) {
            return 0;
        }

        $langs->loadLangs(array('safra@safra', 'stocks'));

        if (empty($activity->lines)) {
            $activity->fetchLines();
        }

        $this->db->begin();

        foreach ($activity->lines as $line) {
            if ($line->movement_type && $line->movement_type !== 'consume') {
                continue;
            }

            $quantity = price2num($line->total, 'MS');
            if ($quantity <= 0 || empty($line->fk_product) || empty($line->fk_warehouse)) {
                continue;
            }

            $movement = new MouvementStock($this->db);
            $movement->origin = $activity;
            $movement->origin_id = $activity->id;
            $movement->origintype = 'safra_activity';

            $label = $langs->transnoentitiesnoconv('SafraActivity') . ' #' . ($activity->ref ?: $activity->id)
                . ' - ' . $langs->transnoentitiesnoconv('Line') . ' #' . $line->id;

            $result = $movement->livraison($user, $line->fk_product, $line->fk_warehouse, $quantity, 0, $label);
            if ($result < 0) {
                $this->db->rollback();
                $this->error = $movement->error ?: $langs->trans('ErrorRecordNotSaved');

                return -1;
            }
        }

        $this->db->commit();

        return 1;
    }

    /**
     * Revert stock consumption movements for an activity.
     *
     * @param FvActivity $activity
     * @param User       $user
     * @return int
     */
    public function revertConsumptionMovements(FvActivity $activity, User $user)
    {
        global $langs;

        if (empty($activity->id)) {
            $this->error = 'MissingActivityIdentifier';

            return -1;
        }

        $langs->loadLangs(array('safra@safra', 'stocks'));

        $sql = 'SELECT rowid, fk_product, fk_entrepot, qty FROM ' . MAIN_DB_PREFIX . "stock_mouvement"
            . " WHERE fk_origin = " . ((int) $activity->id)
            . " AND origintype = 'safra_activity'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();

            return -1;
        }

        $movements = array();
        while ($obj = $this->db->fetch_object($resql)) {
            if ($obj->qty >= 0) {
                continue;
            }

            $movements[] = $obj;
        }

        if (empty($movements)) {
            return 0;
        }

        $this->db->begin();

        foreach ($movements as $movementData) {
            $quantity = abs(price2num($movementData->qty, 'MS'));
            if ($quantity <= 0 || empty($movementData->fk_product) || empty($movementData->fk_entrepot)) {
                continue;
            }

            $movement = new MouvementStock($this->db);
            $movement->origin = $activity;
            $movement->origin_id = $activity->id;
            $movement->origintype = 'safra_activity';

            $label = $langs->transnoentitiesnoconv(
                'SafraActivityRevertMovementLabel',
                $activity->ref ?: $activity->id,
                $movementData->rowid
            );

            $result = $movement->reception($user, $movementData->fk_product, $movementData->fk_entrepot, $quantity, 0, $label);
            if ($result < 0) {
                $this->db->rollback();
                $this->error = $movement->error ?: $langs->trans('ErrorRecordNotSaved');

                return -1;
            }
        }

        $this->db->commit();

        return 1;
    }
}
