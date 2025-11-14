<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modSafra_ActivityTriggers.class.php
 * \ingroup safra
 * \brief   Trigger bridge that re-emits legacy Aplicacao actions using the new Activity event codes.
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';

/**
 * Propagate Safra activity workflow events using the new Activity event codes.
 */
class InterfaceActivityTriggers extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = 'safra';
        $this->description = 'Safra activity workflow event bridge.';
        $this->version = 'development';
        $this->picto = 'safra@safra';
    }

    /**
     * Forward Aplicacao/SfActivity lifecycle actions to the new Activity trigger codes.
     *
     * @param string       $action Trigger action code
     * @param CommonObject $object Triggered object
     * @param User         $user   Current user
     * @param Translate    $langs  Translations handler
     * @param Conf         $conf   Global configuration
     *
     * @return int
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (!isModEnabled('safra')) {
            return 0;
        }

        if (!$this->isActivityObject($object)) {
            return 0;
        }

        $result = 0;

        $workflowResult = $this->handleActivityWorkflowEvent($action, $object, $user, $langs, $conf);
        if ($workflowResult < 0) {
            return -1;
        }
        $result += $workflowResult;

        $bridgeResult = $this->bridgeLegacyEvents($action, $object, $user, $langs, $conf);
        if ($bridgeResult === null) {
            return $result;
        }

        if ($bridgeResult < 0) {
            return -1;
        }

        return $result + $bridgeResult;
    }

    /**
     * Check if the trigger object represents a Safra activity record.
     *
     * @param object $object Trigger object
     *
     * @return bool
     */
    protected function isActivityObject($object)
    {
        if (!is_object($object)) {
            return false;
        }

        if (!property_exists($object, 'element')) {
            return false;
        }

        $element = strtolower((string) $object->element);

        return in_array($element, array('sfactivity', 'aplicacao'), true);
    }

    /**
     * Set a guard on the object context to avoid recursive emissions.
     *
     * @param object $object       Trigger object
     * @param string $targetAction Event code to emit
     *
     * @return bool True when emission should proceed
     */
    protected function markEventAsEmitted($object, $targetAction)
    {
        if (!is_object($object)) {
            return false;
        }

        if (!property_exists($object, 'context') || !is_array($object->context)) {
            $object->context = array();
        }

        if (!isset($object->context['safra_activity_emitted'])) {
            $object->context['safra_activity_emitted'] = array();
        }

        if (!empty($object->context['safra_activity_emitted'][$targetAction])) {
            return false;
        }

        $object->context['safra_activity_emitted'][$targetAction] = true;

        return true;
    }

    /**
     * Remove the guard flag after emitting the event.
     *
     * @param object $object       Trigger object
     * @param string $targetAction Event code
     *
     * @return void
     */
    protected function unmarkEventAsEmitted($object, $targetAction)
    {
        if (!is_object($object)) {
            return;
        }

        if (!property_exists($object, 'context') || !is_array($object->context)) {
            return;
        }

        if (!isset($object->context['safra_activity_emitted'])) {
            return;
        }

        unset($object->context['safra_activity_emitted'][$targetAction]);
    }

    /**
     * Execute workflow-specific side effects.
     */
    protected function handleActivityWorkflowEvent($action, $object, User $user, Translate $langs, Conf $conf)
    {
        $events = array(
            'SAFRA_ACTIVITY_VALIDATE',
            'SAFRA_ACTIVITY_START',
            'SAFRA_ACTIVITY_COMPLETE',
            'SAFRA_ACTIVITY_CANCEL',
            'SAFRA_ACTIVITY_REOPEN',
        );

        if (!in_array($action, $events, true)) {
            return 0;
        }

        $this->registerActivityLog($action, $object, $user);

        $dateResult = $this->updateActivityDates($action, $object, $user);
        if ($dateResult < 0) {
            return -1;
        }

        return 1;
    }

    /**
     * Bridge legacy actions into new activity events.
     */
    protected function bridgeLegacyEvents($action, $object, User $user, Translate $langs, Conf $conf)
    {
        $map = array(
            'MYOBJECT_CREATE' => array('SAFRA_ACTIVITY_CREATE'),
            'MYOBJECT_VALIDATE' => array('SAFRA_ACTIVITY_VALIDATE'),
            'MYOBJECT_START' => array('SAFRA_ACTIVITY_START'),
            'MYOBJECT_COMPLETE' => array('SAFRA_ACTIVITY_COMPLETE'),
            'MYOBJECT_CANCEL' => array('SAFRA_ACTIVITY_CANCEL'),
            'MYOBJECT_REOPEN' => array('SAFRA_ACTIVITY_REOPEN'),
            'MYOBJECT_DELETE' => array('SAFRA_ACTIVITY_DELETE'),
            'SAFRA_ACTIVITY_VALIDATE' => array('SAFRA_ACTIVITY_VALIDATE'),
            'SAFRA_ACTIVITY_START' => array('SAFRA_ACTIVITY_START'),
            'SAFRA_ACTIVITY_COMPLETE' => array('SAFRA_ACTIVITY_COMPLETE', 'SAFRA_ACTIVITY_DONE'),
            'SAFRA_ACTIVITY_CANCEL' => array('SAFRA_ACTIVITY_CANCEL'),
            'SAFRA_ACTIVITY_REOPEN' => array('SAFRA_ACTIVITY_REOPEN'),
        );

        if (!isset($map[$action])) {
            return null;
        }

        $targets = (array) $map[$action];
        $aggregate = 0;

        foreach ($targets as $targetAction) {
            if (!$this->markEventAsEmitted($object, $targetAction)) {
                continue;
            }

            $interfaces = new Interfaces($this->db);
            $result = $interfaces->run_triggers($targetAction, $object, $user, $langs, $conf);

            $this->unmarkEventAsEmitted($object, $targetAction);

            if ($result < 0) {
                $this->setErrorsFromObject($interfaces);
                return -1;
            }

            $aggregate += (int) $result;
        }

        return $aggregate;
    }

    /**
     * Register workflow log either through the object helper or system log.
     */
    protected function registerActivityLog($action, $object, User $user)
    {
        $context = array(
            'action' => $action,
            'user' => $user->id,
        );

        if (method_exists($object, 'registerWorkflowEvent')) {
            $object->registerWorkflowEvent($action, $user, $context);
            return;
        }

        dol_syslog(static::class . ': workflow event ' . $action . ' for activity #' . (int) $object->id, LOG_INFO);
    }

    /**
     * Update real date markers according to workflow transitions.
     */
    protected function updateActivityDates($action, $object, User $user)
    {
        if (!property_exists($object, 'id') && property_exists($object, 'rowid')) {
            $object->id = $object->rowid;
        }

        $id = (int) $object->id;
        if ($id <= 0) {
            return 0;
        }

        $fields = array();
        $now = dol_now();

        if ($action === 'SAFRA_ACTIVITY_START') {
            if (empty($object->date_real_start)) {
                $fields['date_real_start'] = $this->db->idate($now);
                $object->date_real_start = $now;
            }
        } elseif ($action === 'SAFRA_ACTIVITY_COMPLETE') {
            if (empty($object->date_real_start)) {
                $fields['date_real_start'] = $this->db->idate($now);
                $object->date_real_start = $now;
            }
            $fields['date_real_end'] = $this->db->idate($now);
            $object->date_real_end = $now;
        } elseif ($action === 'SAFRA_ACTIVITY_CANCEL') {
            $fields['date_real_end'] = $this->db->idate($now);
            $object->date_real_end = $now;
        } elseif ($action === 'SAFRA_ACTIVITY_REOPEN') {
            if (!empty($object->date_real_end)) {
                $fields['date_real_end'] = null;
                $object->date_real_end = null;
            }
        }

        if (empty($fields)) {
            return 0;
        }

        if ($user->id > 0) {
            $fields['fk_user_modif'] = (int) $user->id;
        }

        $sqlParts = array();
        foreach ($fields as $field => $value) {
            if ($value === null) {
                $sqlParts[] = $field . ' = NULL';
            } else {
                $sqlParts[] = $field . " = '" . $this->db->escape($value) . "'";
            }
        }

        if (empty($sqlParts)) {
            return 0;
        }

        $sql = 'UPDATE ' . MAIN_DB_PREFIX . "safra_activity SET " . implode(', ', $sqlParts) . ' WHERE rowid = ' . $id;
        dol_syslog(__METHOD__ . ': ' . $sql, LOG_DEBUG);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->errors[] = $this->db->lasterror();
            return -1;
        }

        return 1;
    }
}
