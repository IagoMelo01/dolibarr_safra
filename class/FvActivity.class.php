<?php
/*
 * Base activity object for Safra module.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once __DIR__ . '/FvActivityLine.class.php';

class FvActivity extends CommonObject
{
    public const STATUS_DRAFT = 0;
    public const STATUS_COMPLETED = 1;
    public const STATUS_CANCELED = 2;

    /** @var string */
    public $module = 'safra';

    /** @var string */
    public $element = 'safra_activity';

    /** @var string */
    public $table_element = 'safra_activity';

    /** @var string */
    public $table_element_line = 'safra_activity_line';

    /** @var string */
    public $fk_element = 'fk_activity';

    /** @var string */
    public $class_element_line = 'FvActivityLine';

    /** @var int */
    public $ismultientitymanaged = 1;

    /** @var int */
    public $isextrafieldmanaged = 1;

    /** @var string */
    public $picto = 'safra@safra';

    /** @var FvActivityLine[] */
    public $lines = array();

    /**
     * Field definition for automatic CRUD support.
     *
     * @var array
     */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'visible' => -1, 'notnull' => 1),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'default' => 1, 'index' => 1),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'index' => 1, 'searchall' => 1),
        'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'searchall' => 1),
        'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project', 'enabled' => "isModEnabled('project')", 'visible' => 1, 'notnull' => 0, 'index' => 1),
        'fk_task' => array('type' => 'integer:Task:projet/class/task.class.php:1', 'label' => 'Task', 'enabled' => "isModEnabled('project')", 'visible' => 1, 'notnull' => 0, 'index' => 1),
        'fk_thirdparty' => array('type' => 'integer:ThirdParty:societe/class/societe.class.php:1', 'label' => 'ThirdParty', 'enabled' => "isModEnabled('societe')", 'visible' => 1, 'notnull' => 0, 'index' => 1),
        'fk_fieldplot' => array('type' => 'integer:Talhao:custom/safra/class/talhao.class.php:1', 'label' => 'FieldPlot', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'index' => 1),
        'area_total' => array('type' => 'double(24,8)', 'label' => 'AreaTotal', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0'),
        'type' => array('type' => 'varchar(32)', 'label' => 'Type', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'index' => 1),
        'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'index' => 1),
        'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => '1', 'visible' => 1),
        'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => '1', 'visible' => 0),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'visible' => -2, 'notnull' => 1),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'visible' => -2, 'notnull' => 0),
        'fk_user_creat' => array('type' => 'integer', 'label' => 'UserAuthor', 'enabled' => '1', 'visible' => -2, 'notnull' => 1),
        'fk_user_modif' => array('type' => 'integer', 'label' => 'UserModif', 'enabled' => '1', 'visible' => -2, 'notnull' => 0),
    );

    public function __construct(DoliDB $db)
    {
        // parent::__construct($db);
        global $conf, $langs;

		$this->db = $db;

		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid']) && !empty($this->fields['ref'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		// Example to show how to set values of fields definition dynamically
		/*if ($user->hasRight('safra', 'expectativaprodutividade', 'read')) {
			$this->fields['myfield']['visible'] = 1;
			$this->fields['myfield']['noteditable'] = 0;
		}*/

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
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
     * Create activity.
     *
     * @param User $user
     * @return int
     */
    public function create($user)
    {
        global $conf;

        $this->entity = $this->entity ?: $conf->entity;

        return $this->createCommon($user);
    }

    /**
     * Fetch activity by id or ref.
     *
     * @param int         $id
     * @param string|null $ref
     * @return int
     */
    public function fetch($id, $ref = null)
    {
        $result = $this->fetchCommon($id, $ref);
        if ($result > 0) {
            $this->fetchLines();
        }

        return $result;
    }

    /**
     * Update activity.
     *
     * @param User $user
     * @return int
     */
    public function update($user)
    {
        return $this->updateCommon($user);
    }

    /**
     * Delete activity and linked data.
     *
     * @param User $user
     * @return int
     */
    public function delete($user)
    {
        return $this->deleteCommon($user);
    }

    /**
     * Load activity lines.
     *
     * @return int
     */
    public function fetchLines()
    {
        $this->lines = array();

        if (empty($this->id)) {
            return 0;
        }

        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . $this->table_element_line . ' WHERE fk_activity = ' . ((int) $this->id) . ' ORDER BY rowid';
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $line = new FvActivityLine($this->db);
            $line->fetch($obj->rowid);
            $this->lines[] = $line;
        }

        return count($this->lines);
    }

    /**
     * Placeholder for stock movements creation.
     *
     * @return int
     */
    public function createStockMovements(User $user = null, $force = false, $useTransaction = true)
    {
        dol_include_once('/safra/class/ActivityStockService.class.php');

        $service = new ActivityStockService($this->db);

        $stockUser = $user ?: $GLOBALS['user'];

        $result = $service->createConsumptionMovements($this, $stockUser, $force, $useTransaction);

        if ($result < 0) {
            $this->error = $service->error;
        }

        return $result;
    }

    /**
     * Placeholder for stock movements rollback.
     *
     * @return int
     */
    public function revertStockMovements(User $user = null, $useTransaction = true)
    {
        dol_include_once('/safra/class/ActivityStockService.class.php');

        $service = new ActivityStockService($this->db);

        $stockUser = $user ?: ($GLOBALS['user'] ?? null);
        if (!$stockUser instanceof User) {
            return -1;
        }

        $result = $service->revertConsumptionMovements($this, $stockUser, $useTransaction);

        if ($result < 0) {
            $this->error = $service->error;
        }

        return $result;
    }

    /**
     * Mark activity as completed and synchronize related elements.
     *
     * @param User $user
     * @return int
     */
    public function complete(User $user)
    {
        $this->db->begin();

        if (!$this->isCompleted()) {
            $this->status = self::STATUS_COMPLETED;
            $this->fk_user_modif = $user->id;

            $statusResult = $this->update($user);
            if ($statusResult < 0) {
                $this->db->rollback();

                return -1;
            }
        }

        $taskResult = $this->syncProjectTaskCompletion($user);
        if ($taskResult < 0) {
            $this->db->rollback();

            return -1;
        }

        $stockResult = $this->createStockMovements($user);
        if ($stockResult < 0) {
            $this->db->rollback();

            return -1;
        }

        $triggerResult = $this->call_trigger('SAFRA_ACTIVITY_CLOSE', $user);
        if ($triggerResult < 0) {
            $this->db->rollback();

            return -1;
        }

        $this->db->commit();

        return 1;
    }

    /**
     * Sync completion with linked project task if required.
     *
     * @param User $user
     * @return int
     */
    public function syncProjectTaskCompletion(User $user)
    {
        if (empty($this->fk_task)) {
            return 0;
        }

        dol_include_once('/projet/class/task.class.php');

        $task = new Task($this->db);
        if ($task->fetch($this->fk_task) <= 0) {
            return 0;
        }

        if ((int) $task->progress === 100) {
            return 0;
        }

        $task->progress = 100;
        $task->fk_user_modif = $user->id;

        return $task->update($user);
    }

    /**
     * Sync cancellation with linked project task if required.
     *
     * @param User $user
     * @return int
     */
    public function syncProjectTaskCancellation(User $user)
    {
        if (empty($this->fk_task)) {
            return 0;
        }

        dol_include_once('/projet/class/task.class.php');

        $task = new Task($this->db);
        if ($task->fetch($this->fk_task) <= 0) {
            return 0;
        }

        if ((int) $task->progress === 0) {
            return 0;
        }

        $task->progress = 0;
        $task->fk_user_modif = $user->id;

        return $task->update($user);
    }

    /**
     * Check if activity is completed.
     *
     * @return bool
     */
    public function isCompleted()
    {
        return ((int) $this->status) === self::STATUS_COMPLETED;
    }

    /**
     * Check if activity is canceled.
     *
     * @return bool
     */
    public function isCanceled()
    {
        return ((int) $this->status) === self::STATUS_CANCELED;
    }

    /**
     * Check if stock movements were already created for this activity.
     *
     * @return bool
     */
    public function hasStockMovements()
    {
        $sql = 'SELECT COUNT(rowid) as nb FROM ' . MAIN_DB_PREFIX . "stock_mouvement WHERE fk_origin = " . ((int) $this->id)
            . " AND origintype = 'safra_activity'";

        $resql = $this->db->query($sql);
        if (!$resql) {
            return false;
        }

        $obj = $this->db->fetch_object($resql);

        return $obj && ((int) $obj->nb > 0);
    }

    /**
     * Append messages to the private note with timestamp.
     *
     * @param string[] $messages
     * @return void
     */
    protected function appendPrivateNotes(array $messages)
    {
        if (empty($messages)) {
            return;
        }

        $timestamp = dol_print_date(dol_now(), 'dayhourlog');

        foreach ($messages as $message) {
            if (!trim($message)) {
                continue;
            }

            $this->note_private = trim($this->note_private . "\n" . '[' . $timestamp . '] ' . $message);
        }
    }

    /**
     * Cancel the activity with optional deletion.
     *
     * @param User $user
     * @param bool $allowDelete
     * @return int 1 when canceled, 2 when deleted
     */
    public function cancel(User $user, $allowDelete = false)
    {
        global $langs;

        $langs->load('safra@safra');

        if (empty($this->id)) {
            $this->error = $langs->trans('ErrorSafraActivityInvalidIdentifier');

            return -1;
        }

        if ($this->isCanceled()) {
            $this->error = $langs->trans('SafraActivityAlertCanceled');

            return -1;
        }

        $hasStockMovements = $this->hasStockMovements();
        $this->deletionPrevented = false;

        $this->db->begin();

        $logMessages = array();

        if ($this->isCompleted() && $hasStockMovements) {
            $revertResult = $this->revertStockMovements($user);
            if ($revertResult < 0) {
                $this->db->rollback();

                return -1;
            }

            $logMessages[] = $langs->trans('SafraActivityStockRevertedLog', $this->ref ?: $this->id);
        }

        $logMessages[] = $langs->trans('SafraActivityCanceledLog', $this->ref ?: $this->id);
        $this->appendPrivateNotes($logMessages);

        $this->status = self::STATUS_CANCELED;
        $this->fk_user_modif = $user->id;

        $statusResult = $this->update($user);
        if ($statusResult < 0) {
            $this->db->rollback();

            return -1;
        }

        $taskResult = $this->syncProjectTaskCancellation($user);
        if ($taskResult < 0) {
            $this->db->rollback();

            return -1;
        }

        $triggerResult = $this->call_trigger('SAFRA_ACTIVITY_CANCEL', $user);
        if ($triggerResult < 0) {
            $this->db->rollback();

            return -1;
        }

        if ($allowDelete && !$hasStockMovements) {
            $deleteResult = $this->delete($user);
            if ($deleteResult < 0) {
                $this->db->rollback();
                $this->error = $this->error ?: $langs->trans('ErrorRecordNotDeleted');

                return -1;
            }

            $this->db->commit();

            return 2;
        }

        if ($allowDelete && $hasStockMovements) {
            $this->deletionPrevented = true;
        }

        $this->db->commit();

        return 1;
    }

    /**
     * Fetch activity by task identifier.
     *
     * @param DoliDB $db
     * @param int    $taskId
     * @return FvActivity|null
     */
    public static function fetchByTaskId($db, $taskId)
    {
        $taskId = (int) $taskId;
        if ($taskId <= 0) {
            return null;
        }

        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'safra_activity WHERE fk_task = ' . $taskId . ' LIMIT 1';
        $resql = $db->query($sql);
        if (!$resql) {
            return null;
        }

        $obj = $db->fetch_object($resql);
        if (!$obj) {
            return null;
        }

        $activity = new self($db);
        if ($activity->fetch((int) $obj->rowid) > 0) {
            return $activity;
        }

        return null;
    }

    /**
     * Fetch related machines.
     *
     * @return int[]
     */
    public function fetchMachines()
    {
        return $this->fetchRelationIds('safra_activity_machine', 'fk_machine');
    }

    /**
     * Overwrite machine relations.
     *
     * @param int[] $ids
     * @return int
     */
    public function setMachines(array $ids)
    {
        return $this->replaceRelations('safra_activity_machine', 'fk_machine', $ids);
    }

    /**
     * Fetch related implements.
     *
     * @return int[]
     */
    public function fetchImplements()
    {
        return $this->fetchRelationIds('safra_activity_implement', 'fk_implement');
    }

    /**
     * Overwrite implement relations.
     *
     * @param int[] $ids
     * @return int
     */
    public function setImplements(array $ids)
    {
        return $this->replaceRelations('safra_activity_implement', 'fk_implement', $ids);
    }

    /**
     * Fetch related users/employees.
     *
     * @return int[]
     */
    public function fetchUsers()
    {
        return $this->fetchRelationIds('safra_activity_user', 'fk_user');
    }

    /**
     * Overwrite user relations.
     *
     * @param int[] $ids
     * @return int
     */
    public function setUsers(array $ids)
    {
        return $this->replaceRelations('safra_activity_user', 'fk_user', $ids);
    }

    /**
     * Generic helper to fetch relation ids for the current activity.
     *
     * @param string $table
     * @param string $field
     * @return int[]
     */
    protected function fetchRelationIds($table, $field)
    {
        $ids = array();

        if (empty($this->id)) {
            return $ids;
        }

        $sql = 'SELECT ' . $this->db->escape($field) . ' as target_id FROM ' . MAIN_DB_PREFIX . $this->db->escape($table) . ' WHERE fk_activity = ' . ((int) $this->id) . ' ORDER BY rowid';
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return $ids;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $ids[] = (int) $obj->target_id;
        }

        return $ids;
    }

    /**
     * Generic helper to replace relation rows for the current activity.
     *
     * @param string $table
     * @param string $field
     * @param int[]  $ids
     * @return int
     */
    protected function replaceRelations($table, $field, array $ids)
    {
        global $conf;

        if (empty($this->id)) {
            $this->error = 'MissingActivityIdentifier';
            return -1;
        }

        $cleanIds = array();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $cleanIds[$id] = $id;
            }
        }

        $this->db->begin();

        $sqlDelete = 'DELETE FROM ' . MAIN_DB_PREFIX . $this->db->escape($table) . ' WHERE fk_activity = ' . ((int) $this->id);
        if (!$this->db->query($sqlDelete)) {
            $this->db->rollback();
            $this->error = $this->db->lasterror();
            return -1;
        }

        foreach ($cleanIds as $targetId) {
            $sqlInsert = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->db->escape($table) . ' (entity, fk_activity, ' . $this->db->escape($field) . ', date_creation) VALUES ('
                . ((int) $conf->entity) . ', '
                . ((int) $this->id) . ', '
                . $targetId . ', '
                . "'" . $this->db->idate(dol_now()) . "'" . ')';
            if (!$this->db->query($sqlInsert)) {
                $this->db->rollback();
                $this->error = $this->db->lasterror();
                return -1;
            }
        }

        $this->db->commit();

        return count($cleanIds);
    }
}
