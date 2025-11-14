<?php
class Product
{
    public $db;
    public $id;
    public $ref;
    public $pmp = 0.0;
    public $cost_price = 0.0;
    public $price = 0.0;
    public static $repository = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function fetch($id)
    {
        if (!isset(self::$repository[$id])) {
            return 0;
        }
        $data = self::$repository[$id];
        $this->id = $id;
        $this->ref = isset($data['ref']) ? $data['ref'] : 'P'.$id;
        $this->pmp = isset($data['pmp']) ? (float) $data['pmp'] : 0.0;
        $this->cost_price = isset($data['cost_price']) ? (float) $data['cost_price'] : 0.0;
        $this->price = isset($data['price']) ? (float) $data['price'] : 0.0;
        return 1;
    }
}
