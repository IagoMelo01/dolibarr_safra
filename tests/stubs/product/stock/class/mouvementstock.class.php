<?php
class MouvementStock
{
    public $db;
    public $id = 0;
    public $error = '';
    public static $movements = array();
    public static $autoIncrement = 1;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function livraison($user, $productId, $warehouseId, $qty, $price = 0, $label = '', $originType = '', $originId = 0)
    {
        return $this->recordMovement('consume', $productId, $warehouseId, $qty, $label, $originType, $originId);
    }

    public function reception($user, $productId, $warehouseId, $qty, $price = 0, $label = '', $originType = '', $originId = 0)
    {
        return $this->recordMovement('return', $productId, $warehouseId, $qty, $label, $originType, $originId);
    }

    protected function recordMovement($type, $productId, $warehouseId, $qty, $label, $originType, $originId)
    {
        if ($qty <= 0) {
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
            'origintype' => $originType,
            'fk_origin' => $originId,
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
