<?php
class MouvementStock
{
    public $db;
    public $id = 0;
    public $error = '';
    public $origin;
    public $origin_id = 0;
    public $origin_type = '';
    public $origintype = '';
    public $fk_origin = 0;
    public static $movements = array();
    public static $autoIncrement = 1;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function livraison($user, $productId, $warehouseId, $qty, $price = 0, $label = '', $originType = '', $originId = 0)
    {
        return $this->recordMovement('consume', $productId, $warehouseId, 0 - abs((float) $qty), $label, $originType, $originId);
    }

    public function reception($user, $productId, $warehouseId, $qty, $price = 0, $label = '', $originType = '', $originId = 0)
    {
        return $this->recordMovement('return', $productId, $warehouseId, abs((float) $qty), $label, $originType, $originId);
    }

    protected function recordMovement($type, $productId, $warehouseId, $qty, $label, $originType, $originId)
    {
        if (abs((float) $qty) <= 0) {
            $this->error = 'InvalidQty';
            return -1;
        }
        $this->id = self::$autoIncrement++;
        $entry = array(
            'rowid' => $this->id,
            'movement' => $type,
            'fk_product' => (int) $productId,
            'fk_warehouse' => (int) $warehouseId,
            'qty' => (float) $qty,
            'label' => $label,
            'origintype' => $originType ?: $this->origin_type ?: $this->origintype,
            'fk_origin' => $originId ?: $this->origin_id ?: $this->fk_origin,
        );
        self::$movements[$this->id] = $entry;
        if (is_object($this->db)) {
            if (!isset($this->db->stockMovements)) {
                $this->db->stockMovements = array();
            }
            $this->db->stockMovements[$this->id] = $entry;
        }
        return 1;
    }

    public function setOrigin($originType, $originId, $lineIdSrc = 0, $lineIdOrigin = 0)
    {
        $this->origin_type = $originType;
        $this->origin_id = (int) $originId;
        $this->origintype = $originType;
        $this->fk_origin = (int) $originId;
    }

    public function fetch($id)
    {
        if (!isset(self::$movements[$id])) {
            return 0;
        }
        $this->id = $id;
        return 1;
    }

    public function delete($user)
    {
        if (!$this->id) {
            return 0;
        }
        unset(self::$movements[$this->id]);
        if (is_object($this->db) && isset($this->db->stockMovements[$this->id])) {
            unset($this->db->stockMovements[$this->id]);
        }
        return 1;
    }
}
