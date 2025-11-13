<?php
class CommonObject
{
    public $db;
    public $id = 0;
    public $ref = '';
    public $status = 0;
    public $fields = array();
    public $errors = array();
    public $error = '';
    public $element = '';

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function setStatusCommon($user, $status, $notrigger = 0, $trigger = '')
    {
        return 1;
    }

    public function call_trigger($trigger, $user)
    {
        return 1;
    }

    public function getLibStatut($mode = 0)
    {
        return 'Status';
    }

    public function fetchLines()
    {
        return 1;
    }
}
