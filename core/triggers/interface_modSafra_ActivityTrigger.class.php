<?php
/*
 * Safra activity project trigger.
 *
 * Agricultural activities no longer mirror Dolibarr project tasks. A task can
 * keep an optional extrafield link to the Safra activity, but task lifecycle
 * events must not create, update, close or delete Safra activity records.
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

class InterfaceModSafraActivityTrigger extends DolibarrTriggers
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = 'safra';
        $this->description = 'Keeps Safra agricultural activities independent from Dolibarr project task workflow.';
        $this->version = 'dolibarr';
        $this->picto = 'safra@safra';
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDesc()
    {
        return $this->description;
    }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        return 0;
    }
}
