<?php
class CommonObjectLine
{
    public $db;
    public $array_options = array();
    public function __construct($db = null)
    {
        $this->db = $db;
    }
}
