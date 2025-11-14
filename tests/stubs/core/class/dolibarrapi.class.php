<?php
class DolibarrApi
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    protected function _cleanObjectDatas($object)
    {
        return $object;
    }

    protected function getAccessForbiddenError()
    {
        return array('error' => 'access forbidden');
    }
}
