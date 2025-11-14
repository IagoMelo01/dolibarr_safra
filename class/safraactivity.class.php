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
 * \file        class/safraactivity.class.php
 * \ingroup     safra
 * \brief       CRUD classes for Safra activities and lines.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobjectline.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/price.lib.php';

/**
 * Safra activity business object.
 */
class SafraActivity extends CommonObject
{
    /**
     * @var string Module identifier.
     */
    public $module = 'safra';

    /**
     * @var string Element identifier.
     */
    public $element = 'sfactivity';

    /**
     * @var string Table name without prefix.
     */
    public $table_element = 'safra_activity';

    /**
     * @var string Table name for line objects.
     */
    public $table_element_line = 'safra_activity_line';

    /**
     * @var string Parent foreign key field on line table.
     */
    public $fk_element = 'fk_activity';

    /**
     * @var string Class name used for line objects.
     */
    public $class_element_line = 'SafraActivityLine';

    /**
     * @var int Multicompany support flag.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Extrafield support flag.
     */
    public $isextrafieldmanaged = 1;

    /**
     * @var string Pictogram code.
     */
    public $picto = 'safra@safra';

    /**
     * @var array Activity lines.
     */
    public $lines = array();

    /**
     * @var SafraActivityFleetResource[] Linked fleet resources.
     */
    public $fleet_resources = array();

    /**
     * @var SafraActivityTeamMember[] Linked team members.
     */
    public $team_members = array();

    /**
     * @var array Activity log buffer.
     */
    public $log = array();

    /**
     * @var float Cached progress percentage.
     */
    public $progress = 0.0;

    public $rowid;
    public $entity;
    public $ref;
    public $fk_project;
    public $fk_talhao;
    public $label;
    public $activity_type;
    public $date_planned_start;
    public $date_planned_end;
    public $date_real_start;
    public $date_real_end;
    public $status;
    public $note_public;
    public $note_private;
    public $date_creation;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;
    public $last_main_doc;
    public $import_key;
    public $model_pdf;

    const STATUS_DRAFT = 0;
    const STATUS_VALIDATED = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_CANCELED = 9;

    const MOVEMENT_CONSUME = 'consume';
    const MOVEMENT_RETURN = 'return';
    const MOVEMENT_TRANSFER = 'transfer';

    /**
     * @var array Status to code mapping.
     */
    public $statusCodes = array(
        self::STATUS_DRAFT => 'draft',
        self::STATUS_VALIDATED => 'validated',
        self::STATUS_IN_PROGRESS => 'in_progress',
        self::STATUS_COMPLETED => 'completed',
        self::STATUS_CANCELED => 'canceled',
    );

    /**
     * @var array Workflow transition map.
     */
    public $workflowTransitions = array(
        self::STATUS_DRAFT => array(
            'forward' => array(self::STATUS_VALIDATED),
            'fallback' => array(),
            'cancel' => array(self::STATUS_CANCELED),
        ),
        self::STATUS_VALIDATED => array(
            'forward' => array(self::STATUS_IN_PROGRESS),
            'fallback' => array(self::STATUS_DRAFT),
            'cancel' => array(self::STATUS_CANCELED),
        ),
        self::STATUS_IN_PROGRESS => array(
            'forward' => array(self::STATUS_COMPLETED),
            'fallback' => array(self::STATUS_VALIDATED),
            'cancel' => array(self::STATUS_CANCELED),
        ),
        self::STATUS_COMPLETED => array(
            'forward' => array(),
            'fallback' => array(self::STATUS_IN_PROGRESS),
            'cancel' => array(),
        ),
        self::STATUS_CANCELED => array(
            'forward' => array(),
            'fallback' => array(self::STATUS_VALIDATED),
            'cancel' => array(),
        ),
    );

    /**
     * @var array Field definitions.
     */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => '-1', 'noteditable' => '1', 'index' => '1'),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => '-2', 'index' => '1', 'default' => '1'),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => '1', 'index' => '1', 'searchall' => '1', 'showoncombobox' => '1', 'validate' => '1'),
        'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => '1', 'position' => 30, 'notnull' => 0, 'visible' => '1', 'alwayseditable' => '1', 'searchall' => '1', 'css' => 'minwidth300', 'cssview' => 'wordbreak'),
        'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project', 'picto' => 'project', 'enabled' => "isModEnabled('project')", 'position' => 40, 'notnull' => -1, 'visible' => '-1', 'index' => '1'),
        'fk_talhao' => array('type' => 'integer:Talhao:custom/safra/class/talhao.class.php:1', 'label' => 'SafraTalhao', 'picto' => 'safra@safra', 'enabled' => '1', 'position' => 50, 'notnull' => -1, 'visible' => '1', 'index' => '1', 'csslist' => 'tdoverflowmax150'),
        'activity_type' => array('type' => 'varchar(32)', 'label' => 'SafraActivityType', 'enabled' => '1', 'position' => 60, 'notnull' => 1, 'visible' => '1', 'default' => 'application', 'searchall' => '1', 'csslist' => 'tdoverflowmax150'),
        'date_planned_start' => array('type' => 'datetime', 'label' => 'SafraActivityDatePlannedStart', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => '1', 'index' => '1'),
        'date_planned_end' => array('type' => 'datetime', 'label' => 'SafraActivityDatePlannedEnd', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => '1'),
        'date_real_start' => array('type' => 'datetime', 'label' => 'SafraActivityDateRealStart', 'enabled' => '1', 'position' => 90, 'notnull' => 0, 'visible' => '1', 'index' => '1'),
        'date_real_end' => array('type' => 'datetime', 'label' => 'SafraActivityDateRealEnd', 'enabled' => '1', 'position' => 100, 'notnull' => 0, 'visible' => '1'),
        'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => '1', 'position' => 110, 'notnull' => 1, 'visible' => '1', 'index' => '1', 'arrayofkeyval' => array(
            self::STATUS_DRAFT => 'SafraActivityStatusDraft',
            self::STATUS_VALIDATED => 'SafraActivityStatusValidated',
            self::STATUS_IN_PROGRESS => 'SafraActivityStatusInProgress',
            self::STATUS_COMPLETED => 'SafraActivityStatusCompleted',
            self::STATUS_CANCELED => 'SafraActivityStatusCanceled',
        )),
        'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => '1', 'position' => 120, 'notnull' => 0, 'visible' => '0', 'cssview' => 'wordbreak'),
        'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => '1', 'position' => 130, 'notnull' => 0, 'visible' => '0', 'cssview' => 'wordbreak'),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => '-2'),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => '-2'),
        'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => '1', 'position' => 510, 'notnull' => 1, 'visible' => '-2'),
        'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => '1', 'position' => 511, 'notnull' => -1, 'visible' => '-2'),
        'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => '1', 'position' => 600, 'notnull' => 0, 'visible' => '0'),
        'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => '1', 'position' => 1000, 'notnull' => -1, 'visible' => '-2'),
        'model_pdf' => array('type' => 'varchar(255)', 'label' => 'ModelPDF', 'enabled' => '1', 'position' => 1010, 'notnull' => -1, 'visible' => '0'),
    );

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs;

        $this->db = $db;

        if (!is_array($this->log)) {
            $this->log = array();
        }

        if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
            $this->fields['rowid']['visible'] = 0;
        }
        if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
        }

        foreach ($this->fields as $key => $val) {
            if (isset($val['enabled']) && empty($val['enabled'])) {
                unset($this->fields[$key]);
            }
        }

        if (is_object($langs)) {
            foreach ($this->fields as $key => $val) {
                if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
                    foreach ($val['arrayofkeyval'] as $key2 => $val2) {
                        $this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
                    }
                }
            }
        }

        $this->movement_type = SafraActivity::MOVEMENT_CONSUME;
    }

    /**
     * Create a new activity.
     */
    public function create(User $user, $notrigger = 0)
    {
        $this->logMessage('debug', 'Creating Safra activity', array('ref' => $this->ref));

        if (!method_exists($this, 'createCommon')) {
            $this->logMessage('warning', 'createCommon not available, skipping DB create');
            return 0;
        }

        $result = $this->createCommon($user, $notrigger);

        if ($result > 0) {
            $this->logMessage('info', 'Safra activity created', array('id' => $result));
        } else {
            $this->logMessage('error', 'Failed to create Safra activity', array('error' => $this->error, 'errors' => $this->errors));
        }

        return $result;
    }

    /**
     * Fetch an activity.
     */
    public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
    {
        if (!method_exists($this, 'fetchCommon')) {
            $this->logMessage('warning', 'fetchCommon not available, skipping DB fetch');
            return 0;
        }

        $result = $this->fetchCommon($id, $ref, '', $noextrafields);

        if ($result > 0 && empty($nolines)) {
            $this->fetchLines($noextrafields);
            $this->fetchFleetResources();
            $this->fetchTeamMembers();
        }

        if ($result > 0) {
            $this->refreshProgressFromExtraFields();
            $this->logMessage('debug', 'Fetched Safra activity', array('id' => $this->id ?: $id));
        } elseif ($result === 0) {
            $this->logMessage('warning', 'Safra activity not found', array('id' => $id, 'ref' => $ref));
        } else {
            $this->logMessage('error', 'Failed to fetch Safra activity', array('error' => $this->error));
        }

        return $result;
    }

    /**
     * Load activity lines.
     */
    public function fetchLines($noextrafields = 0)
    {
        $this->lines = array();

        if (!method_exists($this, 'fetchLinesCommon')) {
            $this->logMessage('warning', 'fetchLinesCommon not available, skipping line fetch');
            return 0;
        }

        $result = $this->fetchLinesCommon('', $noextrafields);

        if ($result >= 0) {
            $this->logMessage('debug', 'Loaded activity lines', array('count' => is_array($this->lines) ? count($this->lines) : 0));
        } else {
            $this->logMessage('error', 'Failed to load activity lines', array('error' => $this->error));
        }

        return $result;
    }

    /**
     * Load fleet resources.
     *
     * @return int
     */
    public function fetchFleetResources()
    {
        $this->fleet_resources = array();

        if (empty($this->id)) {
            return 0;
        }

        $sql = 'SELECT rowid, entity, fk_activity, resource_type, fk_fleet_equipment, fk_user_responsible, planned_hours, note, date_creation, tms';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'safra_activity_fleet';
        $sql .= ' WHERE fk_activity = ' . ((int) $this->id);
        $sql .= ' ORDER BY rowid';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            $this->logMessage('error', 'Failed to load fleet resources', array('error' => $this->error));
            return -1;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $resource = new SafraActivityFleetResource($this->db);
            $resource->id = (int) $obj->rowid;
            $resource->rowid = (int) $obj->rowid;
            $resource->entity = (int) $obj->entity;
            $resource->fk_activity = (int) $obj->fk_activity;
            $resource->resource_type = $obj->resource_type;
            $resource->fk_fleet_equipment = (int) $obj->fk_fleet_equipment;
            $resource->fk_user_responsible = $obj->fk_user_responsible !== null ? (int) $obj->fk_user_responsible : null;
            $resource->planned_hours = $obj->planned_hours !== null ? (float) $obj->planned_hours : null;
            $resource->note = $obj->note;
            $resource->date_creation = $this->db->jdate($obj->date_creation);
            $resource->tms = $this->db->jdate($obj->tms);

            $this->fleet_resources[] = $resource;
        }

        $this->db->free($resql);
        $this->logMessage('debug', 'Loaded fleet resources', array('count' => count($this->fleet_resources)));

        return count($this->fleet_resources);
    }

    /**
     * Load team members.
     *
     * @return int
     */
    public function fetchTeamMembers()
    {
        $this->team_members = array();

        if (empty($this->id)) {
            return 0;
        }

        $sql = 'SELECT rowid, entity, fk_activity, fk_user, planned_hours, is_responsible, note, date_creation, tms';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'safra_activity_team';
        $sql .= ' WHERE fk_activity = ' . ((int) $this->id);
        $sql .= ' ORDER BY is_responsible DESC, rowid';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            $this->logMessage('error', 'Failed to load team members', array('error' => $this->error));
            return -1;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $member = new SafraActivityTeamMember($this->db);
            $member->id = (int) $obj->rowid;
            $member->rowid = (int) $obj->rowid;
            $member->entity = (int) $obj->entity;
            $member->fk_activity = (int) $obj->fk_activity;
            $member->fk_user = (int) $obj->fk_user;
            $member->planned_hours = $obj->planned_hours !== null ? (float) $obj->planned_hours : null;
            $member->is_responsible = (int) $obj->is_responsible;
            $member->note = $obj->note;
            $member->date_creation = $this->db->jdate($obj->date_creation);
            $member->tms = $this->db->jdate($obj->tms);

            $this->team_members[] = $member;
        }

        $this->db->free($resql);
        $this->logMessage('debug', 'Loaded team members', array('count' => count($this->team_members)));

        return count($this->team_members);
    }

    /**
     * Add multiple input lines.
     *
     * @param User  $user             User performing the action
     * @param array $entries          Array of input rows
     * @param bool  $replaceExisting  When true existing lines are removed before insert
     *
     * @return int Number of inserted rows or <0 on error
     */
    public function addInputLines(User $user, array $entries, $replaceExisting = false)
    {
        global $conf;

        if (empty($this->id)) {
            $this->error = 'ErrorSafraActivityInvalidIdentifier';
            return -1;
        }

        if (empty($entries)) {
            return 0;
        }

        $now = dol_now();
        $entity = (int) (!empty($this->entity) ? $this->entity : $conf->entity);
        $inserted = 0;

        $this->db->begin();

        if ($replaceExisting) {
            $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'safra_activity_line WHERE fk_activity = ' . ((int) $this->id);
            if (!$this->db->query($sql)) {
                $this->error = $this->db->lasterror();
                $this->db->rollback();
                $this->logMessage('error', 'Failed to reset activity inputs', array('error' => $this->error));
                return -1;
            }
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $fkProduct = isset($entry['fk_product']) ? ((int) $entry['fk_product']) : null;
            $label = isset($entry['label']) ? trim((string) $entry['label']) : '';

            if ($fkProduct <= 0 && $label === '') {
                $this->logMessage('warning', 'Skipping input line without product or label');
                continue;
            }

            $qty = isset($entry['qty']) ? price2num($entry['qty'], 'MS') : null;
            if ($qty === null) {
                $this->logMessage('warning', 'Skipping input line without quantity');
                continue;
            }

            $unit = isset($entry['unit']) ? trim((string) $entry['unit']) : '';
            $fkUnit = isset($entry['fk_unit']) ? ((int) $entry['fk_unit']) : null;
            $fkWarehouse = isset($entry['fk_warehouse']) ? ((int) $entry['fk_warehouse']) : null;
            $movement = self::normalizeMovementType(isset($entry['movement_type']) ? $entry['movement_type'] : (isset($entry['movement']) ? $entry['movement'] : ''));
            $note = isset($entry['note']) ? trim((string) $entry['note']) : '';

            $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . "safra_activity_line (entity, fk_activity, fk_product, label, qty, unit, fk_unit, fk_warehouse, movement_type, note, date_creation, tms) VALUES (";
            $sql .= $entity . ', ' . ((int) $this->id) . ', ';
            $sql .= $fkProduct > 0 ? $fkProduct : 'NULL';
            $sql .= ', ' . ($label !== '' ? "'" . $this->db->escape($label) . "'" : 'NULL');
            $sql .= ', ' . ($qty !== null ? $qty : 'NULL');
            $sql .= ', ' . ($unit !== '' ? "'" . $this->db->escape($unit) . "'" : 'NULL');
            $sql .= ', ' . ($fkUnit > 0 ? $fkUnit : 'NULL');
            $sql .= ', ' . ($fkWarehouse > 0 ? $fkWarehouse : 'NULL');
            $sql .= ", '" . $this->db->escape($movement) . "', ";
            $sql .= $note !== '' ? "'" . $this->db->escape($note) . "'" : 'NULL';
            $sql .= ', ' . $this->db->idate($now) . ', ' . $this->db->idate($now) . ')';

            if (!$this->db->query($sql)) {
                $this->error = $this->db->lasterror();
                $this->db->rollback();
                $this->logMessage('error', 'Failed to insert input line', array('error' => $this->error));
                return -1;
            }

            $inserted++;
        }

        $this->db->commit();
        $this->logMessage('info', 'Added activity input lines', array('count' => $inserted));
        $this->fetchLines();

        return $inserted;
    }

    /**
     * Replace fleet resources for the activity.
     *
     * @param User  $user       Acting user
     * @param array $resources  Resource definitions
     *
     * @return int Number of inserted rows or <0 on error
     */
    public function replaceFleetResources(User $user, array $resources)
    {
        global $conf;

        if (empty($this->id)) {
            $this->error = 'ErrorSafraActivityInvalidIdentifier';
            return -1;
        }

        $this->db->begin();

        $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'safra_activity_fleet WHERE fk_activity = ' . ((int) $this->id);
        if (!$this->db->query($sql)) {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            $this->logMessage('error', 'Failed to reset fleet resources', array('error' => $this->error));
            return -1;
        }

        if (empty($resources)) {
            $this->db->commit();
            $this->fleet_resources = array();
            return 0;
        }

        $now = dol_now();
        $entity = (int) (!empty($this->entity) ? $this->entity : $conf->entity);
        $inserted = 0;

        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }

            $equipmentId = isset($resource['fk_fleet_equipment']) ? (int) $resource['fk_fleet_equipment'] : 0;
            if ($equipmentId <= 0) {
                $this->logMessage('warning', 'Skipping fleet resource without equipment identifier');
                continue;
            }

            $resourceType = isset($resource['resource_type']) ? trim((string) $resource['resource_type']) : 'vehicle';
            $plannedHours = isset($resource['planned_hours']) ? price2num($resource['planned_hours'], 'MS') : null;
            $responsible = isset($resource['fk_user_responsible']) ? (int) $resource['fk_user_responsible'] : null;
            $note = isset($resource['note']) ? trim((string) $resource['note']) : '';

            $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . "safra_activity_fleet (entity, fk_activity, resource_type, fk_fleet_equipment, fk_user_responsible, planned_hours, note, date_creation, tms) VALUES (";
            $sql .= $entity . ', ' . ((int) $this->id) . ', ';
            $sql .= "'" . $this->db->escape($resourceType !== '' ? $resourceType : 'vehicle') . "', ";
            $sql .= $equipmentId . ', ';
            $sql .= $responsible > 0 ? $responsible : 'NULL';
            $sql .= ', ' . ($plannedHours !== null ? $plannedHours : 'NULL');
            $sql .= ', ' . ($note !== '' ? "'" . $this->db->escape($note) . "'" : 'NULL');
            $sql .= ', ' . $this->db->idate($now) . ', ' . $this->db->idate($now) . ')';

            if (!$this->db->query($sql)) {
                $this->error = $this->db->lasterror();
                $this->db->rollback();
                $this->logMessage('error', 'Failed to insert fleet resource', array('error' => $this->error));
                return -1;
            }

            $inserted++;
        }

        $this->db->commit();
        $this->logMessage('info', 'Updated fleet resources', array('count' => $inserted));
        $this->fetchFleetResources();

        return $inserted;
    }

    /**
     * Replace team members for the activity.
     *
     * @param User  $user     Acting user
     * @param array $members  Member definitions
     *
     * @return int Number of inserted rows or <0 on error
     */
    public function replaceTeamMembers(User $user, array $members)
    {
        global $conf;

        if (empty($this->id)) {
            $this->error = 'ErrorSafraActivityInvalidIdentifier';
            return -1;
        }

        $this->db->begin();

        $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'safra_activity_team WHERE fk_activity = ' . ((int) $this->id);
        if (!$this->db->query($sql)) {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            $this->logMessage('error', 'Failed to reset team members', array('error' => $this->error));
            return -1;
        }

        if (empty($members)) {
            $this->db->commit();
            $this->team_members = array();
            return 0;
        }

        $now = dol_now();
        $entity = (int) (!empty($this->entity) ? $this->entity : $conf->entity);
        $inserted = 0;

        foreach ($members as $member) {
            if (!is_array($member)) {
                continue;
            }

            $userId = isset($member['fk_user']) ? (int) $member['fk_user'] : 0;
            if ($userId <= 0) {
                $this->logMessage('warning', 'Skipping team member without user identifier');
                continue;
            }

            $plannedHours = isset($member['planned_hours']) ? price2num($member['planned_hours'], 'MS') : null;
            $isResponsible = !empty($member['is_responsible']) ? 1 : 0;
            $note = isset($member['note']) ? trim((string) $member['note']) : '';

            $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . "safra_activity_team (entity, fk_activity, fk_user, planned_hours, is_responsible, note, date_creation, tms) VALUES (";
            $sql .= $entity . ', ' . ((int) $this->id) . ', ';
            $sql .= $userId . ', ';
            $sql .= ($plannedHours !== null ? $plannedHours : 'NULL');
            $sql .= ', ' . $isResponsible;
            $sql .= ', ' . ($note !== '' ? "'" . $this->db->escape($note) . "'" : 'NULL');
            $sql .= ', ' . $this->db->idate($now) . ', ' . $this->db->idate($now) . ')';

            if (!$this->db->query($sql)) {
                $this->error = $this->db->lasterror();
                $this->db->rollback();
                $this->logMessage('error', 'Failed to insert team member', array('error' => $this->error));
                return -1;
            }

            $inserted++;
        }

        $this->db->commit();
        $this->logMessage('info', 'Updated team members', array('count' => $inserted));
        $this->fetchTeamMembers();

        return $inserted;
    }

    /**
     * Return the available stock movement types.
     *
     * @return array
     */
    public static function getMovementTypes()
    {
        return array(
            self::MOVEMENT_CONSUME => 'SafraActivityMovementConsume',
            self::MOVEMENT_RETURN => 'SafraActivityMovementReturn',
            self::MOVEMENT_TRANSFER => 'SafraActivityMovementTransfer',
        );
    }

    /**
     * Normalize a movement type string.
     *
     * @param string $movement Raw movement string
     *
     * @return string
     */
    public static function normalizeMovementType($movement)
    {
        $normalized = strtolower(trim((string) $movement));

        if ($normalized === '' || $normalized === 'consumo' || $normalized === 'saida' || $normalized === 'consumption') {
            return self::MOVEMENT_CONSUME;
        }

        if (in_array($normalized, array('return', 'retorno', 'entrada', 'back'), true)) {
            return self::MOVEMENT_RETURN;
        }

        if (in_array($normalized, array('transfer', 'transferencia', 'move'), true)) {
            return self::MOVEMENT_TRANSFER;
        }

        return self::MOVEMENT_CONSUME;
    }

    /**
     * Update an activity.
     */
    public function update($id, User $user, $notrigger = 0)
    {
        if (!method_exists($this, 'updateCommon')) {
            $this->logMessage('warning', 'updateCommon not available, skipping DB update');
            return 0;
        }

        $this->logMessage('debug', 'Updating Safra activity', array('id' => $id));
        $result = $this->updateCommon($user, $notrigger);

        if ($result > 0) {
            $this->logMessage('info', 'Safra activity updated', array('id' => $id));
        } else {
            $this->logMessage('error', 'Failed to update Safra activity', array('id' => $id, 'error' => $this->error));
        }

        return $result;
    }

    /**
     * Delete an activity.
     */
    public function delete(User $user, $notrigger = 0)
    {
        if (!method_exists($this, 'deleteCommon')) {
            $this->logMessage('warning', 'deleteCommon not available, skipping DB delete');
            return 0;
        }

        $this->logMessage('debug', 'Deleting Safra activity', array('id' => $this->id));
        $result = $this->deleteCommon($user, $notrigger);

        if ($result > 0) {
            $this->logMessage('info', 'Safra activity deleted', array('id' => $this->id));
        } else {
            $this->logMessage('error', 'Failed to delete Safra activity', array('id' => $this->id, 'error' => $this->error));
        }

        return $result;
    }

    /**
     * Return allowed transitions from a given status.
     */
    public function getAllowedTransitions($fromStatus = null)
    {
        $status = ($fromStatus === null) ? $this->status : $fromStatus;
        return isset($this->workflowTransitions[$status]) ? $this->workflowTransitions[$status] : array('forward' => array(), 'fallback' => array(), 'cancel' => array());
    }

    /**
     * Check whether a transition is allowed.
     */
    public function canTransitionTo($targetStatus, $fromStatus = null)
    {
        $allowed = $this->getAllowedTransitions($fromStatus);
        return in_array($targetStatus, array_merge($allowed['forward'], $allowed['fallback'], $allowed['cancel']), true);
    }

    /**
     * Validate the activity document.
     */
    public function validate(User $user, $notrigger = 0)
    {
        $this->resetErrors();

        if (!$this->ensureValidIdentifier()) {
            return $this->failWorkflow('ErrorSafraActivityInvalidIdentifier');
        }

        if (!$this->userHasWorkflowRight($user, 'validate')) {
            return $this->failWorkflow('ErrorSafraActivityNoRights');
        }

        if ((int) $this->status === self::STATUS_VALIDATED) {
            return 0;
        }

        if (!$this->canTransitionTo(self::STATUS_VALIDATED)) {
            return $this->failWorkflow('ErrorSafraActivityInvalidTransitionState', array($this->getStatusLabelForErrors()));
        }

        $result = $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'SAFRA_ACTIVITY_VALIDATE');

        if ($result > 0) {
            $this->status = self::STATUS_VALIDATED;
            $this->logMessage('info', 'Activity validated', array('user' => $user->id, 'status' => $this->status));
        }

        return $result;
    }

    /**
     * Start the execution of an activity.
     */
    public function start(User $user, $notrigger = 0)
    {
        $this->resetErrors();

        if (!$this->ensureValidIdentifier()) {
            return $this->failWorkflow('ErrorSafraActivityInvalidIdentifier');
        }

        if (!$this->userHasWorkflowRight($user, 'start')) {
            return $this->failWorkflow('ErrorSafraActivityNoRights');
        }

        if ((int) $this->status === self::STATUS_IN_PROGRESS) {
            return 0;
        }

        if (!$this->canTransitionTo(self::STATUS_IN_PROGRESS)) {
            return $this->failWorkflow('ErrorSafraActivityInvalidTransitionState', array($this->getStatusLabelForErrors()));
        }

        $result = $this->setStatusCommon($user, self::STATUS_IN_PROGRESS, $notrigger, 'SAFRA_ACTIVITY_START');

        if ($result > 0) {
            $this->status = self::STATUS_IN_PROGRESS;
            if (empty($this->date_real_start)) {
                $this->date_real_start = dol_now();
            }
            $this->logMessage('info', 'Activity started', array('user' => $user->id, 'status' => $this->status));
        }

        return $result;
    }

    /**
     * Mark the activity as completed.
     */
    public function complete(User $user, $notrigger = 0)
    {
        $this->resetErrors();

        if (!$this->ensureValidIdentifier()) {
            return $this->failWorkflow('ErrorSafraActivityInvalidIdentifier');
        }

        if (!$this->userHasWorkflowRight($user, 'complete')) {
            return $this->failWorkflow('ErrorSafraActivityNoRights');
        }

        if ((int) $this->status === self::STATUS_COMPLETED) {
            return 0;
        }

        if (!$this->canTransitionTo(self::STATUS_COMPLETED)) {
            return $this->failWorkflow('ErrorSafraActivityInvalidTransitionState', array($this->getStatusLabelForErrors()));
        }

        $result = $this->setStatusCommon($user, self::STATUS_COMPLETED, $notrigger, 'SAFRA_ACTIVITY_COMPLETE');

        if ($result > 0) {
            $now = dol_now();
            $this->status = self::STATUS_COMPLETED;
            if (empty($this->date_real_start)) {
                $this->date_real_start = $now;
            }
            $this->date_real_end = $now;
            $this->logMessage('info', 'Activity completed', array('user' => $user->id, 'status' => $this->status));
        }

        return $result;
    }

    /**
     * Cancel the activity.
     */
    public function cancel(User $user, $notrigger = 0)
    {
        $this->resetErrors();

        if (!$this->ensureValidIdentifier()) {
            return $this->failWorkflow('ErrorSafraActivityInvalidIdentifier');
        }

        if (!$this->userHasWorkflowRight($user, 'cancel')) {
            return $this->failWorkflow('ErrorSafraActivityNoRights');
        }

        if ((int) $this->status === self::STATUS_CANCELED) {
            return 0;
        }

        if (!$this->canTransitionTo(self::STATUS_CANCELED)) {
            return $this->failWorkflow('ErrorSafraActivityInvalidTransitionState', array($this->getStatusLabelForErrors()));
        }

        $result = $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, 'SAFRA_ACTIVITY_CANCEL');

        if ($result > 0) {
            $this->status = self::STATUS_CANCELED;
            $this->date_real_end = dol_now();
            $this->logMessage('info', 'Activity canceled', array('user' => $user->id, 'status' => $this->status));
        }

        return $result;
    }

    /**
     * Reopen a previously closed activity.
     */
    public function reopen(User $user, $notrigger = 0)
    {
        $this->resetErrors();

        if (!$this->ensureValidIdentifier()) {
            return $this->failWorkflow('ErrorSafraActivityInvalidIdentifier');
        }

        if (!$this->userHasWorkflowRight($user, 'reopen')) {
            return $this->failWorkflow('ErrorSafraActivityNoRights');
        }

        $targetStatus = $this->getFallbackStatus();

        if ($targetStatus === null) {
            return $this->failWorkflow('ErrorSafraActivityInvalidTransitionState', array($this->getStatusLabelForErrors()));
        }

        if ((int) $this->status === (int) $targetStatus) {
            return 0;
        }

        $previousStatus = $this->status;
        $result = $this->setStatusCommon($user, $targetStatus, $notrigger, 'SAFRA_ACTIVITY_REOPEN');

        if ($result > 0) {
            $this->status = $targetStatus;
            if ((int) $previousStatus === self::STATUS_CANCELED) {
                $this->date_real_end = null;
            }
            $this->logMessage('info', 'Activity reopened', array('user' => $user->id, 'status' => $this->status, 'previous' => $previousStatus));
        }

        return $result;
    }

    /**
     * Register workflow event into the in-memory log buffer.
     */
    public function registerWorkflowEvent($action, User $user = null, array $context = array())
    {
        $context = array_merge(
            array(
                'action' => $action,
                'user' => $user ? (int) $user->id : null,
                'status' => $this->status,
                'id' => $this->id,
                'ref' => $this->ref,
            ),
            $context
        );

        $this->logMessage('info', 'Workflow event recorded', $context);
    }

    /**
     * Refresh cached progress value from extrafields.
     */
    public function refreshProgressFromExtraFields()
    {
        if (!isset($this->array_options) || !is_array($this->array_options)) {
            $this->array_options = array();
        }

        if (isset($this->array_options['options_progress'])) {
            $this->progress = (float) $this->array_options['options_progress'];
        }
    }

    /**
     * Persist progress into extrafields.
     */
    public function syncProgressToExtraFields()
    {
        if (!isset($this->array_options) || !is_array($this->array_options)) {
            $this->array_options = array();
        }

        $this->array_options['options_progress'] = $this->progress;
    }

    /**
     * Set activity progress.
     */
    public function setProgress($progress)
    {
        $normalized = max(0, min(100, (float) $progress));
        $this->progress = $normalized;
        $this->syncProgressToExtraFields();
        $this->logMessage('debug', 'Progress updated', array('progress' => $this->progress));
        return $this->progress;
    }

    /**
     * Ensure lines are loaded before aggregation.
     */
    protected function ensureLinesLoaded($forceReload = false)
    {
        if ($forceReload || empty($this->lines)) {
            if ($this->id > 0) {
                $this->fetchLines();
            }
        }
    }

    /**
     * Compute planned totals.
     */
    public function computePlannedTotals($forceReload = false)
    {
        $this->ensureLinesLoaded($forceReload);

        $total = 0.0;
        $count = 0;

        if (is_array($this->lines)) {
            foreach ($this->lines as $line) {
                if ($line instanceof SafraActivityLine) {
                    $total += $line->getPlannedQuantity();
                    $count++;
                }
            }
        }

        $this->logMessage('debug', 'Computed planned totals', array('planned_qty' => $total, 'line_count' => $count));

        return array(
            'qty' => $total,
            'lines' => $count,
        );
    }

    /**
     * Compute actual totals.
     */
    public function computeActualTotals($forceReload = false)
    {
        $this->ensureLinesLoaded($forceReload);

        $total = 0.0;
        $count = 0;

        if (is_array($this->lines)) {
            foreach ($this->lines as $line) {
                if ($line instanceof SafraActivityLine) {
                    $total += $line->getActualQuantity();
                    $count++;
                }
            }
        }

        $this->logMessage('debug', 'Computed actual totals', array('actual_qty' => $total, 'line_count' => $count));

        return array(
            'qty' => $total,
            'lines' => $count,
        );
    }

    /**
     * Recalculate progress using planned and actual totals.
     */
    public function recalculateProgress($forceReload = false)
    {
        $planned = $this->computePlannedTotals($forceReload);
        $actual = $this->computeActualTotals(false);

        if ($planned['qty'] <= 0) {
            return $this->setProgress($this->progress);
        }

        $ratio = ($actual['qty'] / $planned['qty']) * 100;
        return $this->setProgress($ratio);
    }

    /**
     * Register a log entry.
     */
    protected function logMessage($level, $message, array $context = array())
    {
        if (!is_array($this->log)) {
            $this->log = array();
        }

        $entry = array(
            'timestamp' => dol_now(),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        );

        $this->log[] = $entry;

        $dolLevel = LOG_DEBUG;
        if ($level === 'error') {
            $dolLevel = LOG_ERR;
        } elseif ($level === 'warning') {
            $dolLevel = LOG_WARNING;
        } elseif ($level === 'info') {
            $dolLevel = LOG_INFO;
        }

        $contextString = $context ? ' ' . json_encode($context) : '';
        dol_syslog(static::class . ': ' . $message . $contextString, $dolLevel);
    }

    /**
     * Reset error buffers.
     */
    protected function resetErrors()
    {
        $this->error = '';
        $this->errors = array();
    }

    /**
     * Ensure the object identifier is valid.
     */
    protected function ensureValidIdentifier()
    {
        if (!empty($this->id)) {
            return true;
        }

        if (!empty($this->rowid)) {
            $this->id = $this->rowid;
            return true;
        }

        return false;
    }

    /**
     * Return the fallback status from workflow configuration.
     */
    protected function getFallbackStatus()
    {
        $allowed = $this->getAllowedTransitions();
        if (!empty($allowed['fallback'])) {
            $fallback = reset($allowed['fallback']);
            return ($fallback === false) ? null : (int) $fallback;
        }

        return null;
    }

    /**
     * Resolve current status label for error rendering.
     */
    protected function getStatusLabelForErrors()
    {
        $label = '';

        if (method_exists($this, 'getLibStatut')) {
            $libStatut = $this->getLibStatut(0);
            if (is_array($libStatut) && isset($libStatut['label'])) {
                $label = $libStatut['label'];
            } elseif (is_scalar($libStatut)) {
                $label = (string) $libStatut;
            }
        }

        if ($label === '' && isset($this->statusCodes[$this->status])) {
            $label = $this->statusCodes[$this->status];
        }

        if ($label === '') {
            $label = (string) $this->status;
        }

        return $label;
    }

    /**
     * Report workflow error with translated message and logging.
     */
    protected function failWorkflow($translationKey, array $params = array())
    {
        global $langs;

        if (is_object($langs)) {
            $langs->load('safra@safra');
            $message = call_user_func_array(array($langs, 'trans'), array_merge(array($translationKey), $params));
        } else {
            $message = $translationKey;
        }

        $this->error = $message;
        $this->errors = array($message);
        $this->logMessage('error', 'Workflow transition failed', array('error' => $translationKey, 'params' => $params, 'status' => $this->status));

        return -1;
    }

    /**
     * Check if user owns permission for workflow operation.
     */
    protected function userHasWorkflowRight(User $user, $operation)
    {
        if (!is_object($user)) {
            return false;
        }

        $default = $this->invokeHasRight($user, 'safra', 'atividade', 'write');

        if (!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS')) {
            return $default;
        }

        $map = array(
            'validate' => array('atividade_advance', 'validate'),
            'start' => array('atividade_advance', 'start'),
            'complete' => array('atividade_advance', 'complete'),
            'cancel' => array('atividade_advance', 'cancel'),
            'reopen' => array('atividade_advance', 'reopen'),
        );

        if (isset($map[$operation])) {
            list($section, $right) = $map[$operation];
            $advanced = $this->invokeHasRight($user, 'safra', $section, $right);
            if ($advanced) {
                return true;
            }
        }

        return $default;
    }

    /**
     * Invoke Dolibarr hasRight with flexible signature.
     */
    protected function invokeHasRight(User $user, $module, $permission, $operation)
    {
        if (!is_object($user) || !method_exists($user, 'hasRight')) {
            return false;
        }

        try {
            $reflection = new \ReflectionMethod($user, 'hasRight');
        } catch (\ReflectionException $e) {
            return false;
        }

        $paramCount = $reflection->getNumberOfParameters();
        $args = array();

        if ($paramCount >= 1) {
            $args[] = $module;
        }
        if ($paramCount >= 2) {
            $args[] = $permission;
        }
        if ($paramCount >= 3) {
            $args[] = $operation;
        }

        try {
            return (bool) $reflection->invokeArgs($user, $args);
        } catch (\ArgumentCountError $e) {
            return false;
        }
    }
}

/**
 * Safra activity line business object.
 */
class SafraActivityLine extends CommonObjectLine
{
    /**
     * @var string Module identifier.
     */
    public $module = 'safra';

    /**
     * @var string Element identifier.
     */
    public $element = 'safraactivityline';

    /**
     * @var string Table name without prefix.
     */
    public $table_element = 'safra_activity_line';

    /**
     * @var int Multicompany support flag.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Extrafield support flag.
     */
    public $isextrafieldmanaged = 1;

    /**
     * @var array Log buffer for line-level operations.
     */
    public $log = array();

    public $rowid;
    public $entity;
    public $fk_activity;
    public $fk_product;
    public $label;
    public $qty;
    public $unit;
    public $fk_unit;
    public $fk_warehouse;
    public $movement_type;
    public $note;
    public $date_creation;
    public $tms;

    /**
     * @var array Field definitions.
     */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => '-1', 'noteditable' => '1', 'index' => '1'),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => '-2', 'index' => '1', 'default' => '1'),
        'fk_activity' => array('type' => 'integer', 'label' => 'SafraActivity', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => '-1', 'index' => '1', 'foreignkey' => 'safra_activity.rowid'),
        'fk_product' => array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'Product', 'enabled' => '1', 'position' => 30, 'notnull' => -1, 'visible' => '1', 'index' => '1'),
        'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => '1', 'position' => 40, 'notnull' => 0, 'visible' => '1', 'searchall' => '1', 'css' => 'minwidth200'),
        'qty' => array('type' => 'double(24,8)', 'label' => 'Qty', 'enabled' => '1', 'position' => 50, 'notnull' => 1, 'visible' => '1', 'isameasure' => '1'),
        'unit' => array('type' => 'varchar(10)', 'label' => 'Unit', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => '1'),
        'fk_unit' => array('type' => 'integer', 'label' => 'UnitID', 'enabled' => '1', 'position' => 65, 'notnull' => -1, 'visible' => '0', 'index' => '1'),
        'fk_warehouse' => array('type' => 'integer:Warehouse:product/stock/class/entrepot.class.php', 'label' => 'Warehouse', 'enabled' => '1', 'position' => 70, 'notnull' => -1, 'visible' => '1', 'index' => '1'),
        'movement_type' => array('type' => 'varchar(16)', 'label' => 'Movement', 'enabled' => '1', 'position' => 75, 'notnull' => 1, 'visible' => '1', 'arrayofkeyval' => array(
            SafraActivity::MOVEMENT_CONSUME => 'SafraActivityMovementConsume',
            SafraActivity::MOVEMENT_RETURN => 'SafraActivityMovementReturn',
            SafraActivity::MOVEMENT_TRANSFER => 'SafraActivityMovementTransfer',
        )),
        'note' => array('type' => 'text', 'label' => 'Note', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => '0'),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => '-2'),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => '-2'),
    );

    /**
     * Constructor.
     */
    public function __construct($db)
    {
        global $langs;

        $this->db = $db;

        if (!is_array($this->log)) {
            $this->log = array();
        }

        if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
            $this->fields['rowid']['visible'] = 0;
        }
        if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
        }

        foreach ($this->fields as $key => $val) {
            if (isset($val['enabled']) && empty($val['enabled'])) {
                unset($this->fields[$key]);
            }
        }

        if (is_object($langs)) {
            foreach ($this->fields as $key => $val) {
                if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
                    foreach ($val['arrayofkeyval'] as $key2 => $val2) {
                        $this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
                    }
                }
            }
        }
    }

    /**
     * Get planned quantity for the line.
     */
    public function getPlannedQuantity()
    {
        if (!isset($this->array_options) || !is_array($this->array_options)) {
            $this->array_options = array();
        }

        if (isset($this->array_options['options_planned_qty'])) {
            return (float) $this->array_options['options_planned_qty'];
        }

        return (float) $this->qty;
    }

    /**
     * Get actual quantity for the line.
     */
    public function getActualQuantity()
    {
        if (!isset($this->array_options) || !is_array($this->array_options)) {
            $this->array_options = array();
        }

        if (isset($this->array_options['options_actual_qty'])) {
            return (float) $this->array_options['options_actual_qty'];
        }

        return (float) $this->qty;
    }

    /**
     * Get progress percentage for the line.
     */
    public function getProgressValue()
    {
        if (!isset($this->array_options) || !is_array($this->array_options)) {
            $this->array_options = array();
        }

        if (isset($this->array_options['options_progress'])) {
            return (float) $this->array_options['options_progress'];
        }

        $planned = $this->getPlannedQuantity();
        if ($planned <= 0) {
            return 0.0;
        }

        $ratio = ($this->getActualQuantity() / $planned) * 100;
        return max(0.0, min(100.0, $ratio));
    }

    /**
     * Update progress extrafield for the line.
     */
    public function setProgressValue($progress)
    {
        if (!isset($this->array_options) || !is_array($this->array_options)) {
            $this->array_options = array();
        }

        $normalized = max(0, min(100, (float) $progress));
        $this->array_options['options_progress'] = $normalized;
        $this->logMessage('debug', 'Line progress updated', array('progress' => $normalized));

        return $normalized;
    }

    /**
     * Register a log entry.
     */
    protected function logMessage($level, $message, array $context = array())
    {
        if (!is_array($this->log)) {
            $this->log = array();
        }

        $entry = array(
            'timestamp' => dol_now(),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        );

        $this->log[] = $entry;

        $dolLevel = LOG_DEBUG;
        if ($level === 'error') {
            $dolLevel = LOG_ERR;
        } elseif ($level === 'warning') {
            $dolLevel = LOG_WARNING;
        } elseif ($level === 'info') {
            $dolLevel = LOG_INFO;
        }

        $contextString = $context ? ' ' . json_encode($context) : '';
        dol_syslog(static::class . ': ' . $message . $contextString, $dolLevel);
    }
}

/**
 * Fleet resource linked to an activity.
 */
class SafraActivityFleetResource extends CommonObjectLine
{
    /**
     * @var string Module identifier.
     */
    public $module = 'safra';

    /**
     * @var string Element identifier.
     */
    public $element = 'safraactivityfleet';

    /**
     * @var string Table name without prefix.
     */
    public $table_element = 'safra_activity_fleet';

    /**
     * @var int Multicompany support flag.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Extrafield support flag.
     */
    public $isextrafieldmanaged = 1;

    public $rowid;
    public $entity;
    public $fk_activity;
    public $resource_type;
    public $fk_fleet_equipment;
    public $fk_user_responsible;
    public $planned_hours;
    public $note;
    public $date_creation;
    public $tms;

    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => '-1', 'noteditable' => '1', 'index' => '1'),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => '-2', 'index' => '1', 'default' => '1'),
        'fk_activity' => array('type' => 'integer', 'label' => 'SafraActivity', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => '-1', 'index' => '1', 'foreignkey' => 'safra_activity.rowid'),
        'resource_type' => array('type' => 'varchar(16)', 'label' => 'Type', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => '1'),
        'fk_fleet_equipment' => array('type' => 'integer', 'label' => 'Fleet', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => '1', 'index' => '1'),
        'fk_user_responsible' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Responsible', 'enabled' => '1', 'position' => 50, 'notnull' => -1, 'visible' => '1', 'index' => '1'),
        'planned_hours' => array('type' => 'double(24,8)', 'label' => 'SafraActivityPlannedHours', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => '1'),
        'note' => array('type' => 'text', 'label' => 'SafraActivityResourceNote', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => '0'),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => '-2'),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => '-2'),
    );

    public function __construct($db)
    {
        global $langs;

        $this->db = $db;

        if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
            $this->fields['rowid']['visible'] = 0;
        }
        if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
        }

        foreach ($this->fields as $key => $val) {
            if (isset($val['enabled']) && empty($val['enabled'])) {
                unset($this->fields[$key]);
            }
        }

        if (is_object($langs)) {
            foreach ($this->fields as $key => $val) {
                if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
                    foreach ($val['arrayofkeyval'] as $key2 => $val2) {
                        $this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
                    }
                }
            }
        }
    }
}

/**
 * Team member linked to an activity.
 */
class SafraActivityTeamMember extends CommonObjectLine
{
    /**
     * @var string Module identifier.
     */
    public $module = 'safra';

    /**
     * @var string Element identifier.
     */
    public $element = 'safraactivityteam';

    /**
     * @var string Table name without prefix.
     */
    public $table_element = 'safra_activity_team';

    /**
     * @var int Multicompany support flag.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Extrafield support flag.
     */
    public $isextrafieldmanaged = 1;

    public $rowid;
    public $entity;
    public $fk_activity;
    public $fk_user;
    public $planned_hours;
    public $is_responsible;
    public $note;
    public $date_creation;
    public $tms;

    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => '-1', 'noteditable' => '1', 'index' => '1'),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => '-2', 'index' => '1', 'default' => '1'),
        'fk_activity' => array('type' => 'integer', 'label' => 'SafraActivity', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => '-1', 'index' => '1', 'foreignkey' => 'safra_activity.rowid'),
        'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => '1', 'index' => '1'),
        'planned_hours' => array('type' => 'double(24,8)', 'label' => 'SafraActivityPlannedHours', 'enabled' => '1', 'position' => 40, 'notnull' => 0, 'visible' => '1'),
        'is_responsible' => array('type' => 'integer', 'label' => 'SafraActivityResponsible', 'enabled' => '1', 'position' => 50, 'notnull' => 1, 'visible' => '1'),
        'note' => array('type' => 'text', 'label' => 'SafraActivityResourceNote', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => '0'),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => '-2'),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => '-2'),
    );

    public function __construct($db)
    {
        global $langs;

        $this->db = $db;

        if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
            $this->fields['rowid']['visible'] = 0;
        }
        if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
        }

        foreach ($this->fields as $key => $val) {
            if (isset($val['enabled']) && empty($val['enabled'])) {
                unset($this->fields[$key]);
            }
        }

        if (is_object($langs)) {
            foreach ($this->fields as $key => $val) {
                if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
                    foreach ($val['arrayofkeyval'] as $key2 => $val2) {
                        $this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
                    }
                }
            }
        }
    }
}
