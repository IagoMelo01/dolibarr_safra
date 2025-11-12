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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/SfActivity.class.php
 * \ingroup     safra
 * \brief       Bridge class exposing the Activity terminology while delegating core logic to the legacy Aplicacao object.
 */

require_once __DIR__.'/aplicacao.class.php';
require_once __DIR__.'/SfActivityLine.class.php';

/**
 * Activity domain object.
 *
 * The class proxies to the legacy implementation so that existing features keep working while
 * exposing the new naming expected by integrations. Once callers migrate we can swap the parent
 * class to a dedicated implementation hitting the renamed tables directly.
 */
class SfActivity extends Aplicacao
{
    /**
     * @var string
     */
    public $element = 'sfactivity';

    /**
     * @var string
     */
    public $class_element_line = 'SfActivityLine';

    /**
     * @var string
     */
    public $picto = 'fa-seedling';

    /**
     * @var string[]
     */
    protected static $legacyAliasMap = array(
        'operation_type' => 'activity_type',
        'date_application' => 'date_activity',
        'calda_observacao' => 'mixture_note',
    );

    /**
     * @var array<string,bool>
     */
    protected static $legacyUsageLogged = array();

    /**
     * @var string
     */
    public $activity_type;

    /**
     * @var int|string
     */
    public $date_activity;

    /**
     * @var string
     */
    public $mixture_note;

    /**
     * Constructor.
     *
     * @param DoliDB $db
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db);

        $this->element = 'sfactivity';
        $this->table_element = 'safra_aplicacao';
        $this->table_element_line = 'safra_aplicacao_line';
        $this->fk_element = 'fk_aplicacao';
        $this->class_element_line = 'SfActivityLine';
        $this->picto = 'fa-seedling';

        $this->fields = $this->remapFields($this->fields);

        $this->syncLegacyFromModern(false);
        $this->syncModernToLegacy();
    }

    /**
     * Realign the Dolibarr field definitions to the activity naming.
     *
     * @param array $fields
     * @return array
     */
    protected function remapFields(array $fields)
    {
        $map = array(
            'operation_type' => array('activity_type', 'SafraActivityType'),
            'date_application' => array('date_activity', 'SafraActivityDate'),
            'calda_observacao' => array('mixture_note', 'SafraActivityMixtureNote'),
        );

        foreach ($map as $legacyKey => $info) {
            if (!isset($fields[$legacyKey])) {
                continue;
            }

            list($newKey, $label) = $info;
            $definition = $fields[$legacyKey];
            $definition['label'] = $label;

            if ($legacyKey === 'operation_type') {
                $definition['default'] = self::OPERATION_APLICACAO;
                $definition['arrayofkeyval'] = self::getActivityTypeList($GLOBALS['langs'] instanceof Translate ? $GLOBALS['langs'] : null);
            }

            $fields[$newKey] = $definition;
            unset($fields[$legacyKey]);
        }

        return $fields;
    }

    /**
     * Create an activity.
     *
     * @param User $user
     * @param bool $notrigger
     * @return int
     */
    public function create(User $user, $notrigger = false)
    {
        $this->syncLegacyFromModern();
        $this->syncModernToLegacy();

        $result = parent::create($user, $notrigger);

        if ($result > 0) {
            $this->syncLegacyFromModern(false);
            $this->syncModernToLegacy();
        }

        return $result;
    }

    /**
     * Update an activity.
     *
     * @param User $user
     * @param bool $notrigger
     * @return int
     */
    public function update(User $user, $notrigger = false)
    {
        $this->syncLegacyFromModern();
        $this->syncModernToLegacy();

        $result = parent::update($user, $notrigger);

        if ($result >= 0) {
            $this->syncLegacyFromModern(false);
            $this->syncModernToLegacy();
        }

        return $result;
    }

    /**
     * Fetch activity.
     *
     * @param int         $id
     * @param string|null $ref
     * @param int         $noextrafields
     * @param int         $nolines
     * @return int
     */
    public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
    {
        $result = parent::fetch($id, $ref, $noextrafields, $nolines);

        if ($result > 0) {
            $this->syncLegacyFromModern(false);
            $this->syncModernToLegacy();
        }

        return $result;
    }

    /**
     * Load lines using the SfActivityLine wrapper.
     *
     * @param int $noextrafields
     * @return int
     */
    public function fetchLines($noextrafields = 0)
    {
        $result = parent::fetchLines($noextrafields);

        if ($result > 0 && is_array($this->lines)) {
            $converted = array();
            foreach ($this->lines as $legacyLine) {
                $converted[] = SfActivityLine::fromLegacy($this->db, $legacyLine);
            }
            $this->lines = $converted;
        }

        return $result;
    }

    /**
     * Return lines array compatible with the legacy signature while exposing activity naming.
     *
     * @return array|int
     */
    public function getLinesArray()
    {
        $result = parent::getLinesArray();

        if (is_array($result)) {
            $converted = array();
            foreach ($result as $legacyLine) {
                $converted[] = SfActivityLine::fromLegacy($this->db, $legacyLine);
            }
            $this->lines = $converted;

            return $this->lines;
        }

        if ($result > 0 && is_array($this->lines)) {
            $converted = array();
            foreach ($this->lines as $legacyLine) {
                $converted[] = SfActivityLine::fromLegacy($this->db, $legacyLine);
            }
            $this->lines = $converted;
        }

        return $result;
    }

    /**
     * Replace lines accepting activity naming.
     *
     * @param array        $lines
     * @param User|null    $user
     * @return int
     */
    public function replaceLines(array $lines, User $user = null)
    {
        $legacyPayload = array();
        foreach ($lines as $line) {
            if ($line instanceof SfActivityLine) {
                $legacyPayload[] = $line->toLegacyArray();
                continue;
            }

            if (is_object($line)) {
                $line = (array) $line;
            }

            if (is_array($line)) {
                $legacyPayload[] = SfActivityLine::fromModernArray($this->db, $line)->toLegacyArray();
                continue;
            }

            $legacyPayload[] = $line;
        }

        $result = parent::replaceLines($legacyPayload, $user);

        if ($result >= 0) {
            $this->fetchLines();
        }

        return $result;
    }

    /**
     * Ensure modern properties are copied back to legacy fields.
     *
     * @return void
     */
    protected function syncModernToLegacy()
    {
        foreach (self::$legacyAliasMap as $legacy => $modern) {
            if (property_exists($this, $modern)) {
                $this->$legacy = $this->$modern;
            }
        }
    }

    /**
     * Ensure legacy assignments hydrate the modern properties and emit deprecation logs.
     *
     * @param bool $withWarning
     * @return void
     */
    protected function syncLegacyFromModern($withWarning = true)
    {
        foreach (self::$legacyAliasMap as $legacy => $modern) {
            if (!property_exists($this, $legacy)) {
                continue;
            }

            $legacyValue = $this->$legacy;
            $modernValue = property_exists($this, $modern) ? $this->$modern : null;

            if ($legacyValue === null || $legacyValue === '') {
                continue;
            }

            if ($modernValue === null || $modernValue === '' || $modernValue !== $legacyValue) {
                if ($withWarning) {
                    $this->logLegacyUsage($legacy);
                }
                $this->$modern = $legacyValue;
            }
        }
    }

    /**
     * Emit a deprecation warning for legacy property usage.
     *
     * @param string $property
     * @return void
     */
    protected function logLegacyUsage($property)
    {
        $key = static::class.'::'.$property;
        if (!isset(self::$legacyUsageLogged[$key])) {
            dol_syslog('SfActivity legacy alias "'.$property.'" accessed - please migrate to the Activity naming.', LOG_WARNING);
            self::$legacyUsageLogged[$key] = true;
        }
    }

    /**
     * Return activity types.
     *
     * @param Translate|null $langs
     * @return array<string,string>
     */
    public static function getActivityTypeList($langs = null)
    {
        return parent::getOperationTypeList($langs);
    }

    /**
     * Normalize an activity type.
     *
     * @param string $value
     * @return string
     */
    public static function normalizeActivityType($value)
    {
        return parent::normalizeOperationType($value);
    }

    /**
     * Get label for activity type.
     *
     * @param string         $value
     * @param Translate|null $langs
     * @return string
     */
    public static function getActivityTypeLabel($value, $langs = null)
    {
        return parent::getOperationTypeLabel($value, $langs);
    }
}
