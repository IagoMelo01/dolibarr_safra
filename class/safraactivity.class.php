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
            'fallback' => array(),
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
    public $fk_warehouse;
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
        'fk_warehouse' => array('type' => 'integer:Warehouse:product/stock/class/entrepot.class.php', 'label' => 'Warehouse', 'enabled' => '1', 'position' => 70, 'notnull' => -1, 'visible' => '1', 'index' => '1'),
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
