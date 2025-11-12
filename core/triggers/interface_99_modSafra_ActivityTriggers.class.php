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

        $map = array(
            'MYOBJECT_CREATE' => 'SAFRA_ACTIVITY_CREATE',
            'MYOBJECT_VALIDATE' => 'SAFRA_ACTIVITY_VALIDATE',
            'SAFRA_ACTIVITY_START' => 'SAFRA_ACTIVITY_START',
            'SAFRA_ACTIVITY_COMPLETE' => 'SAFRA_ACTIVITY_DONE',
            'SAFRA_ACTIVITY_CANCEL' => 'SAFRA_ACTIVITY_CANCEL',
            'MYOBJECT_DELETE' => 'SAFRA_ACTIVITY_DELETE',
        );

        if (!isset($map[$action])) {
            return 0;
        }

        $targetAction = $map[$action];

        if (!$this->markEventAsEmitted($object, $targetAction)) {
            return 0;
        }

        $interfaces = new Interfaces($this->db);
        $result = $interfaces->run_triggers($targetAction, $object, $user, $langs, $conf);

        $this->unmarkEventAsEmitted($object, $targetAction);

        if ($result < 0) {
            $this->setErrorsFromObject($interfaces);
            return -1;
        }

        return $result;
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
}
