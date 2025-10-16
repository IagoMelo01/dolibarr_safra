<?php

class StockIntegrationService
{
    /** @var DoliDB */
    protected $db;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    public function applyFromNFeOut($nfeOutId)
    {
        dol_syslog(__METHOD__ . ' not implemented fully', LOG_DEBUG);
        return 0;
    }

    public function applyFromNFeIn($nfeInId)
    {
        dol_syslog(__METHOD__ . ' not implemented fully', LOG_DEBUG);
        return 0;
    }
}
