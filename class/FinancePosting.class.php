<?php

class FinancePosting
{
    /** @var DoliDB */
    protected $db;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    public function createCustomerInvoiceFromNFeOut($nfeOutId)
    {
        dol_syslog(__METHOD__ . ' placeholder for invoice creation from NF-e OUT ' . $nfeOutId, LOG_DEBUG);
        return 0;
    }

    public function createSupplierInvoiceFromNFeIn($nfeInId)
    {
        dol_syslog(__METHOD__ . ' placeholder for supplier invoice creation from NF-e IN ' . $nfeInId, LOG_DEBUG);
        return 0;
    }
}
