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
    public const STATUS_IN_PROGRESS = 3;

    public const TYPE_SOIL_PREPARATION = 'preparo_solo';
    public const TYPE_SEED_PREPARATION = 'preparo_semente';
    public const TYPE_PLANTING = 'plantio';
    public const TYPE_FERTILIZATION = 'fertilizacao';
    public const TYPE_INPUT_APPLICATION = 'aplicacao';
    public const TYPE_HARVEST = 'colheita';
    public const TYPE_MONITORING = 'monitoramento';
    public const TYPE_OTHER = 'outro';

    private const TASK_STATUS_DRAFT = 0;
    private const TASK_STATUS_TODO = 1;
    private const TASK_STATUS_ONGOING = 2;
    private const TASK_STATUS_CLOSED = 3;
    private const TASK_STATUS_CANCELED = 9;

    private const TASK_LINK_FIELD = 'fk_activity';
    private const TASK_LINK_FIELD_LEGACY = 'fk_aplicacao';

    /** @var array<string, string[]> */
    protected static $taskLinkColumnsCache = array();

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

    /** @var bool */
    public $deletionPrevented = false;

    /**
     * Field definition for automatic CRUD support.
     *
     * @var array
     */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'visible' => -1, 'notnull' => 1, 'position' => 10),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'default' => 1, 'index' => 1, 'position' => 20),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'position' => 30),
        'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'searchall' => 1, 'position' => 40),
        'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project', 'enabled' => "isModEnabled('project')", 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 50),
        'fk_task' => array('type' => 'integer:Task:projet/class/task.class.php:1', 'label' => 'Task', 'enabled' => "isModEnabled('project')", 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 60),
        'fk_thirdparty' => array('type' => 'integer:ThirdParty:societe/class/societe.class.php:1', 'label' => 'ThirdParty', 'enabled' => "isModEnabled('societe')", 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 70),
        'fk_fieldplot' => array('type' => 'integer:Talhao:custom/safra/class/talhao.class.php:1', 'label' => 'FieldPlot', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 80),
        'area_total' => array('type' => 'double(24,8)', 'label' => 'AreaTotal', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 90),
        'type' => array(
            'type' => 'varchar(32)',
            'label' => 'Type',
            'enabled' => '1',
            'visible' => 1,
            'notnull' => 1,
            'default' => self::TYPE_OTHER,
            'index' => 1,
            'position' => 100,
            'arrayofkeyval' => array(
                self::TYPE_SOIL_PREPARATION => 'SafraOperationPreparoSolo',
                self::TYPE_SEED_PREPARATION => 'SafraOperationTratamentoSemente',
                self::TYPE_PLANTING => 'SafraOperationPlantio',
                self::TYPE_FERTILIZATION => 'SafraOperationFertilizacao',
                self::TYPE_INPUT_APPLICATION => 'SafraOperationAplicacao',
                self::TYPE_HARVEST => 'SafraOperationColheita',
                self::TYPE_MONITORING => 'SafraOperationMonitoramento',
                self::TYPE_OTHER => 'SafraOperationOutro',
            ),
        ),
        'status' => array(
            'type' => 'integer',
            'label' => 'Status',
            'enabled' => '1',
            'visible' => 1,
            'notnull' => 1,
            'default' => '0',
            'index' => 1,
            'position' => 110,
            'arrayofkeyval' => array(
                self::STATUS_DRAFT => 'Draft',
                self::STATUS_IN_PROGRESS => 'SafraActivityStatusInProgress',
                self::STATUS_COMPLETED => 'SafraActivityStatusCompleted',
                self::STATUS_CANCELED => 'SafraActivityStatusCanceled',
            ),
        ),
        'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => '1', 'visible' => 1, 'position' => 120),
        'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => '1', 'visible' => 0, 'position' => 130),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'position' => 140),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'position' => 150),
        'fk_user_creat' => array('type' => 'integer', 'label' => 'UserAuthor', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'position' => 160),
        'fk_user_modif' => array('type' => 'integer', 'label' => 'UserModif', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'position' => 170),
    );

    public function __construct(DoliDB $db)
    {
        global $langs;

        $this->db = $db;

        if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid']) && !empty($this->fields['ref'])) {
            $this->fields['rowid']['visible'] = 0;
        }
        if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
        }

        // Unset disabled fields
        foreach ($this->fields as $key => $val) {
            if (isset($val['enabled']) && empty($val['enabled'])) {
                unset($this->fields[$key]);
            }
        }

        // Module Builder expects every field to have a position index.
        $nextPosition = 10;
        foreach ($this->fields as $key => $val) {
            $pos = isset($val['position']) ? (int) $val['position'] : 0;
            if ($pos <= 0) {
                $pos = $nextPosition;
                $this->fields[$key]['position'] = $pos;
            }
            $nextPosition = $pos + 10;
        }

        // Translate array values
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
        $this->ref = trim((string) $this->ref);
        if ($this->ref === '') {
            $this->ref = '(PROV)';
        }

        $this->type = self::normalizeType($this->type);
        $this->status = self::normalizeStatus($this->status);

        $result = $this->createCommon($user);
        if ($result > 0) {
            $syncResult = $this->syncTask($user, true);
            if ($syncResult < 0) {
                return -1;
            }

            $this->call_trigger('SAFRA_ACTIVITY_CREATE', $user);
        }

        return $result;
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
            $this->type = self::normalizeType($this->type);
            $this->status = self::normalizeStatus($this->status);
            $this->syncStatusFromTask(null, false);
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
        $this->type = self::normalizeType($this->type);
        $this->status = self::normalizeStatus($this->status);

        $skipTaskSync = !empty($this->context['skip_task_sync']);
        $result = $this->updateCommon($user);

        if ($result > 0 && !$skipTaskSync) {
            $syncResult = $this->syncTask($user, true);
            if ($syncResult < 0) {
                return -1;
            }
        }

        return $result;
    }

    /**
     * Delete activity and linked data.
     *
     * @param User $user
     * @param bool $deleteLinkedTask
     * @return int
     */
    public function delete($user, $deleteLinkedTask = true)
    {
        if ($deleteLinkedTask && empty($this->context['skip_task_delete'])) {
            $taskDelete = $this->deleteLinkedTask($user);
            if ($taskDelete < 0) {
                return -1;
            }
        }

        $result = $this->deleteCommon($user);
        if ($result > 0) {
            $this->call_trigger('SAFRA_ACTIVITY_DELETE', $user);
        }

        return $result;
    }

    /**
     * Mark activity as in progress.
     *
     * @param User $user
     * @return int
     */
    public function start(User $user)
    {
        global $langs;

        $langs->load('safra@safra');

        if (empty($this->id)) {
            $this->error = $langs->trans('ErrorSafraActivityInvalidIdentifier');

            return -1;
        }

        if ($this->isCompleted() || $this->isCanceled()) {
            $this->error = $langs->trans('ErrorSafraActivityInvalidTransitionState', self::getStatusLabel($this->status, $langs));

            return -1;
        }

        $this->status = self::STATUS_IN_PROGRESS;
        $this->fk_user_modif = $user->id;

        $result = $this->update($user);
        if ($result > 0) {
            $this->call_trigger('SAFRA_ACTIVITY_START', $user);

            return 1;
        }

        return -1;
    }

    /**
     * Mark activity as completed.
     *
     * @param User $user
     * @return int
     */
    public function complete(User $user)
    {
        global $langs;

        $langs->load('safra@safra');

        if (empty($this->id)) {
            $this->error = $langs->trans('ErrorSafraActivityInvalidIdentifier');

            return -1;
        }

        if ($this->isCanceled()) {
            $this->error = $langs->trans('ErrorSafraActivityInvalidTransitionState', self::getStatusLabel($this->status, $langs));

            return -1;
        }

        $this->status = self::STATUS_COMPLETED;
        $this->fk_user_modif = $user->id;

        $result = $this->update($user);
        if ($result > 0) {
            $this->call_trigger('SAFRA_ACTIVITY_DONE', $user);
            $this->call_trigger('SAFRA_ACTIVITY_CLOSE', $user);

            return 1;
        }

        return -1;
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

        if ($allowDelete) {
            $deleteResult = $this->delete($user);
            if ($deleteResult > 0) {
                return 2;
            }

            return -1;
        }

        if ($this->isCanceled()) {
            $this->error = $langs->trans('SafraActivityAlertCanceled');

            return -1;
        }

        $this->status = self::STATUS_CANCELED;
        $this->fk_user_modif = $user->id;

        $result = $this->update($user);
        if ($result > 0) {
            $this->appendPrivateNotes(array($langs->trans('SafraActivityCanceledLog', $this->ref ?: $this->id)));
            $this->call_trigger('SAFRA_ACTIVITY_CANCEL', $user);

            return 1;
        }

        return -1;
    }

    /**
     * Sync current activity status from linked task status.
     *
     * @param User|null $user
     * @param bool      $persist
     * @return int
     */
    public function syncStatusFromTask($user = null, $persist = true)
    {
        if (empty($this->fk_task) || !isModEnabled('project')) {
            return 0;
        }

        dol_include_once('/projet/class/task.class.php');
        if (!class_exists('Task')) {
            return 0;
        }

        $task = new Task($this->db);
        if ($task->fetch((int) $this->fk_task) <= 0) {
            return 0;
        }

        $mappedStatus = self::mapTaskToActivityStatus((int) $task->status, (int) $task->progress);
        if ((int) $this->status === $mappedStatus) {
            return 0;
        }

        $this->status = $mappedStatus;
        if (!$persist || !($user instanceof User)) {
            return 1;
        }

        $this->context['skip_task_sync'] = 1;
        $result = $this->updateCommon($user, 1);
        unset($this->context['skip_task_sync']);

        return ($result > 0) ? 1 : -1;
    }

    /**
     * Sync linked task from current activity data.
     *
     * @param User $user
     * @param bool $createIfMissing
     * @return int
     */
    protected function syncTask(User $user, $createIfMissing = true)
    {
        if (!isModEnabled('project')) {
            return 0;
        }

        if (empty($this->fk_project) && empty($this->fk_task)) {
            return 0;
        }

        dol_include_once('/projet/class/task.class.php');
        if (!class_exists('Task')) {
            return 0;
        }

        $task = new Task($this->db);
        $isNewTask = false;

        if (!empty($this->fk_task)) {
            if ($task->fetch((int) $this->fk_task) <= 0) {
                if (!$createIfMissing || empty($this->fk_project)) {
                    return 0;
                }

                $task = new Task($this->db);
                $isNewTask = true;
            }
        } else {
            if (!$createIfMissing || empty($this->fk_project)) {
                return 0;
            }

            $isNewTask = true;
        }

        if ($isNewTask) {
            $task->fk_project = (int) $this->fk_project;
            $task->label = $this->buildTaskLabel();
            $task->description = $this->buildTaskDescription();
            $task->date_start = dol_now();
            $this->applyActivityStatusToTask($task);

            $taskId = $task->create($user, 1);
            if ($taskId <= 0) {
                $this->error = $task->error ?: $task->errorsToString();

                return -1;
            }

            $this->fk_task = (int) $taskId;
            $this->persistTaskLinkOnActivity();

            $linkResult = $this->syncTaskExtrafieldLink((int) $taskId);
            if ($linkResult < 0) {
                return -1;
            }

            return 1;
        }

        $modified = false;

        if (!empty($this->fk_project) && (int) $task->fk_project !== (int) $this->fk_project) {
            $task->fk_project = (int) $this->fk_project;
            $modified = true;
        }

        $taskLabel = $this->buildTaskLabel();
        if ($taskLabel !== '' && $task->label !== $taskLabel) {
            $task->label = $taskLabel;
            $modified = true;
        }

        $taskDescription = $this->buildTaskDescription();
        if ($taskDescription !== '' && $task->description !== $taskDescription) {
            $task->description = $taskDescription;
            $modified = true;
        }

        if (empty($task->date_start)) {
            $task->date_start = dol_now();
            $modified = true;
        }

        if ($this->applyActivityStatusToTask($task)) {
            $modified = true;
        }

        if ($modified) {
            $taskUpdate = $task->update($user, 1);
            if ($taskUpdate <= 0) {
                $this->error = $task->error ?: $task->errorsToString();

                return -1;
            }
        }

        $linkResult = $this->syncTaskExtrafieldLink((int) $task->id);
        if ($linkResult < 0) {
            return -1;
        }

        return 1;
    }

    /**
     * Apply current activity status to task status/progress.
     *
     * @param Task $task
     * @return bool
     */
    protected function applyActivityStatusToTask($task)
    {
        $oldStatus = isset($task->status) ? (int) $task->status : ((isset($task->fk_statut) ? (int) $task->fk_statut : self::TASK_STATUS_TODO));
        $oldProgress = isset($task->progress) ? (int) $task->progress : 0;

        $newStatus = self::TASK_STATUS_TODO;
        $newProgress = 0;

        if ($this->isCanceled()) {
            $newStatus = self::TASK_STATUS_CANCELED;
            $newProgress = 0;
        } elseif ($this->isCompleted()) {
            $newStatus = self::TASK_STATUS_CLOSED;
            $newProgress = 100;
        } elseif ($this->isInProgress()) {
            $newStatus = self::TASK_STATUS_ONGOING;
            $newProgress = max(1, min(99, $oldProgress));
            if ($newProgress <= 0 || $newProgress >= 100) {
                $newProgress = 50;
            }
        }

        $task->status = $newStatus;
        $task->fk_statut = $newStatus;
        $task->progress = $newProgress;

        return $oldStatus !== $newStatus || $oldProgress !== $newProgress;
    }

    /**
     * Persist fk_task directly on activity row when task is created.
     *
     * @return int
     */
    protected function persistTaskLinkOnActivity()
    {
        if (empty($this->id)) {
            return 0;
        }

        $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element
            . ' SET fk_task = ' . ((int) $this->fk_task)
            . ' WHERE rowid = ' . ((int) $this->id);

        if (!$this->db->query($sql)) {
            $this->error = $this->db->lasterror();

            return -1;
        }

        return 1;
    }

    /**
     * Delete linked project task when deleting activity.
     *
     * @param User $user
     * @return int
     */
    protected function deleteLinkedTask(User $user)
    {
        if (empty($this->fk_task) || !isModEnabled('project')) {
            return 0;
        }

        dol_include_once('/projet/class/task.class.php');
        if (!class_exists('Task')) {
            return 0;
        }

        $task = new Task($this->db);
        if ($task->fetch((int) $this->fk_task) <= 0) {
            return 0;
        }

        // Avoid recursive delete through TASK_DELETE trigger when delete starts from activity.
        $task->context['skip_activity_delete'] = 1;
        $result = $task->delete($user, 1);
        if ($result <= 0) {
            $this->error = $task->error ?: $task->errorsToString();

            return -1;
        }

        return 1;
    }

    /**
     * Detect and cache available task extrafield link columns.
     *
     * @return string[]
     */
    protected function getTaskLinkColumns()
    {
        $cacheKey = spl_object_hash($this->db);
        if (isset(self::$taskLinkColumnsCache[$cacheKey])) {
            return self::$taskLinkColumnsCache[$cacheKey];
        }

        $columns = array();
        foreach (array(self::TASK_LINK_FIELD, self::TASK_LINK_FIELD_LEGACY) as $column) {
            if ($this->hasTaskExtrafieldColumn($column)) {
                $columns[] = $column;
            }
        }

        if (empty($columns) && $this->createTaskExtrafieldColumn(self::TASK_LINK_FIELD)) {
            if ($this->hasTaskExtrafieldColumn(self::TASK_LINK_FIELD)) {
                $columns[] = self::TASK_LINK_FIELD;
            }
        }

        self::$taskLinkColumnsCache[$cacheKey] = $columns;

        return $columns;
    }

    /**
     * Check if task extrafield column exists.
     *
     * @param string $columnName
     * @return bool
     */
    protected function hasTaskExtrafieldColumn($columnName)
    {
        $sql = 'SHOW COLUMNS FROM ' . MAIN_DB_PREFIX . "projet_task_extrafields LIKE '" . $this->db->escape($columnName) . "'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return false;
        }

        return ($this->db->fetch_object($resql) ? true : false);
    }

    /**
     * Try to create task extrafield column when missing.
     *
     * @param string $columnName
     * @return bool
     */
    protected function createTaskExtrafieldColumn($columnName)
    {
        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

        $extrafields = new ExtraFields($this->db);
        $params = array(
            'options' => array(
                'FvActivity:safra/class/FvActivity.class.php:1' => null,
            ),
        );

        $result = $extrafields->addExtraField(
            $columnName,
            'SafraActivity',
            'link',
            150,
            '',
            'projet_task',
            0,
            0,
            '',
            $params,
            1,
            '',
            'isModEnabled("safra")'
        );

        if ($result < 0 && !in_array($extrafields->error, array('ErrorFieldAlreadyExists', 'DB_ERROR_RECORD_ALREADY_EXISTS'), true)) {
            return false;
        }

        return true;
    }

    /**
     * Upsert task extrafield link to this activity.
     *
     * @param int $taskId
     * @return int
     */
    protected function syncTaskExtrafieldLink($taskId)
    {
        if (empty($taskId) || empty($this->id)) {
            return 0;
        }

        $columns = $this->getTaskLinkColumns();
        if (empty($columns)) {
            return 0;
        }

        $table = MAIN_DB_PREFIX . 'projet_task_extrafields';
        $taskId = (int) $taskId;
        $activityId = (int) $this->id;

        $sqlCheck = 'SELECT fk_object FROM ' . $table . ' WHERE fk_object = ' . $taskId;
        $resql = $this->db->query($sqlCheck);
        if (!$resql) {
            return 0;
        }

        $exists = $this->db->fetch_object($resql);

        if ($exists) {
            $set = array();
            foreach ($columns as $column) {
                $set[] = $column . ' = ' . $activityId;
            }

            $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE fk_object = ' . $taskId;
        } else {
            $fields = array('fk_object');
            $values = array($taskId);

            foreach ($columns as $column) {
                $fields[] = $column;
                $values[] = $activityId;
            }

            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
        }

        if (!$this->db->query($sql)) {
            $this->error = $this->db->lasterror();

            return -1;
        }

        return 1;
    }

    /**
     * Build task label based on activity.
     *
     * @return string
     */
    protected function buildTaskLabel()
    {
        $label = trim((string) $this->label);
        if ($label === '') {
            $label = trim((string) $this->ref);
        }

        if ($label === '') {
            $label = 'Atividade Safra';
        }

        return $label;
    }

    /**
     * Build task description from activity data.
     *
     * @return string
     */
    protected function buildTaskDescription()
    {
        $description = array();
        $description[] = 'Atividade Safra: ' . ($this->ref ?: $this->id);
        $description[] = 'Tipo: ' . self::getTypeLabel($this->type);

        if (!empty($this->note_public)) {
            $description[] = 'Obs.: ' . dol_string_nohtmltag((string) $this->note_public);
        }

        return implode("\n", $description);
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
     * Return URL link to activity card (with optional picto).
     *
     * @param int    $withpicto
     * @param string $option
     * @param int    $notooltip
     * @param string $morecss
     * @param int    $save_lastsearch_value
     * @return string
     */
    public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
    {
        global $langs;

        $label = trim((string) ($this->ref ?: $this->label ?: $this->id));
        if ($label === '') {
            $label = $langs->trans('SafraActivity');
        }

        $url = dol_buildpath('/safra/activity/activity_card.php', 1) . '?id=' . ((int) $this->id);
        if ($option !== 'nolink') {
            $addSaveLastSearch = ($save_lastsearch_value == 1 ? 1 : 0);
            if ($save_lastsearch_value == -1 && isset($_SERVER['PHP_SELF']) && preg_match('/list\.php/', $_SERVER['PHP_SELF'])) {
                $addSaveLastSearch = 1;
            }
            if ($addSaveLastSearch) {
                $url .= '&save_lastsearch_values=1';
            }
        }

        $content = '';
        if ((int) $withpicto > 0) {
            $content .= img_object($langs->trans('SafraActivity'), ($this->picto ?: 'generic'), (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, 0);
        }
        if ((int) $withpicto != 2) {
            $content .= dol_escape_htmltag($label);
        }

        $attrs = '';
        if ($morecss !== '') {
            $attrs .= ' class="' . dol_escape_htmltag($morecss) . '"';
        }
        if ((int) $notooltip === 0) {
            $attrs .= ' title="' . dol_escape_htmltag($label) . '"';
        }

        if ($option === 'nolink' || empty($url)) {
            return '<span' . $attrs . '>' . $content . '</span>';
        }

        return '<a href="' . $url . '"' . $attrs . '>' . $content . '</a>';
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
     * Check if activity is in progress.
     *
     * @return bool
     */
    public function isInProgress()
    {
        return ((int) $this->status) === self::STATUS_IN_PROGRESS;
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
        if (empty($this->id)) {
            return false;
        }

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
     * Register stock movements from activity lines.
     *
     * @param User|null $user
     * @param bool      $force
     * @param bool      $useTransaction
     * @return int
     */
    public function createStockMovements(User $user = null, $force = false, $useTransaction = true)
    {
        dol_include_once('/safra/class/ActivityStockService.class.php');

        $service = new ActivityStockService($this->db);
        $stockUser = $user ?: ($GLOBALS['user'] ?? null);

        if (!$stockUser instanceof User) {
            $this->error = 'MissingUser';

            return -1;
        }

        $result = $service->createConsumptionMovements($this, $stockUser, $force, $useTransaction);
        if ($result < 0) {
            $this->error = $service->error;
        }

        return $result;
    }

    /**
     * Revert stock movements for this activity.
     *
     * @param User|null $user
     * @param bool      $useTransaction
     * @return int
     */
    public function revertStockMovements(User $user = null, $useTransaction = true)
    {
        dol_include_once('/safra/class/ActivityStockService.class.php');

        $service = new ActivityStockService($this->db);
        $stockUser = $user ?: ($GLOBALS['user'] ?? null);

        if (!$stockUser instanceof User) {
            $this->error = 'MissingUser';

            return -1;
        }

        $result = $service->revertConsumptionMovements($this, $stockUser, $useTransaction);
        if ($result < 0) {
            $this->error = $service->error;
        }

        return $result;
    }

    /**
     * Append messages to private notes.
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
            $message = trim((string) $message);
            if ($message === '') {
                continue;
            }

            $this->note_private = trim((string) $this->note_private . "\n" . '[' . $timestamp . '] ' . $message);
        }
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

        $activityId = 0;

        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'safra_activity WHERE fk_task = ' . $taskId . ' LIMIT 1';
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            if ($obj) {
                $activityId = (int) $obj->rowid;
            }
        }

        if ($activityId <= 0) {
            $activityId = self::findActivityIdByTaskExtrafield($db, $taskId);
        }

        if ($activityId <= 0) {
            return null;
        }

        $activity = new self($db);
        if ($activity->fetch($activityId) > 0) {
            return $activity;
        }

        return null;
    }

    /**
     * Find activity id by task extrafield columns.
     *
     * @param DoliDB $db
     * @param int    $taskId
     * @return int
     */
    protected static function findActivityIdByTaskExtrafield($db, $taskId)
    {
        $taskId = (int) $taskId;
        foreach (array(self::TASK_LINK_FIELD, self::TASK_LINK_FIELD_LEGACY) as $column) {
            $sql = 'SELECT a.rowid'
                . ' FROM ' . MAIN_DB_PREFIX . 'safra_activity as a'
                . ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task_extrafields as tef ON tef.' . $column . ' = a.rowid'
                . ' WHERE tef.fk_object = ' . $taskId
                . ' LIMIT 1';

            $resql = $db->query($sql);
            if (!$resql) {
                continue;
            }

            $obj = $db->fetch_object($resql);
            if ($obj) {
                return (int) $obj->rowid;
            }
        }

        return 0;
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

        $sql = 'SELECT ' . $this->db->escape($field) . ' as target_id FROM ' . MAIN_DB_PREFIX . $this->db->escape($table)
            . ' WHERE fk_activity = ' . ((int) $this->id)
            . ' ORDER BY rowid';

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
            $sqlInsert = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->db->escape($table)
                . ' (entity, fk_activity, ' . $this->db->escape($field) . ', date_creation) VALUES ('
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

    /**
     * Normalize activity status.
     *
     * @param int|string $status
     * @return int
     */
    public static function normalizeStatus($status)
    {
        $status = (int) $status;

        if ($status === self::STATUS_CANCELED || $status === self::TASK_STATUS_CANCELED) {
            return self::STATUS_CANCELED;
        }
        if ($status === self::STATUS_COMPLETED || $status === self::TASK_STATUS_CLOSED) {
            return self::STATUS_COMPLETED;
        }
        if ($status === self::STATUS_IN_PROGRESS || $status === self::TASK_STATUS_ONGOING) {
            return self::STATUS_IN_PROGRESS;
        }

        return self::STATUS_DRAFT;
    }

    /**
     * Map task status/progress to activity status.
     *
     * @param int $taskStatus
     * @param int $taskProgress
     * @return int
     */
    public static function mapTaskToActivityStatus($taskStatus, $taskProgress)
    {
        $taskStatus = (int) $taskStatus;
        $taskProgress = (int) $taskProgress;

        if ($taskStatus === self::TASK_STATUS_CANCELED) {
            return self::STATUS_CANCELED;
        }
        if ($taskStatus === self::TASK_STATUS_CLOSED || $taskProgress >= 100) {
            return self::STATUS_COMPLETED;
        }
        if ($taskStatus === self::TASK_STATUS_ONGOING || $taskProgress > 0) {
            return self::STATUS_IN_PROGRESS;
        }

        return self::STATUS_DRAFT;
    }

    /**
     * Normalize activity type.
     *
     * @param string|null $type
     * @return string
     */
    public static function normalizeType($type)
    {
        $type = trim((string) $type);
        if ($type === '') {
            return self::TYPE_OTHER;
        }

        $type = strtolower($type);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $type);
        if ($ascii !== false) {
            $type = $ascii;
        }

        $type = str_replace(array('-', '/', '\\'), '_', $type);
        $type = preg_replace('/[^a-z0-9_]+/', '_', $type);
        $type = trim((string) $type, '_');

        $aliases = self::getTypeAliases();
        if (isset($aliases[$type])) {
            return $aliases[$type];
        }

        $validTypes = self::getTypeCodes();
        if (in_array($type, $validTypes, true)) {
            return $type;
        }

        return self::TYPE_OTHER;
    }

    /**
     * Return type select options with translations.
     *
     * @param Translate|null $translator
     * @return array<string,string>
     */
    public static function getTypeOptions($translator = null)
    {
        if (!is_object($translator)) {
            global $langs;
            $translator = $langs;
        }

        $translationMap = self::getTypeTranslationKeys();
        $options = array();

        foreach (self::getTypeCodes() as $code) {
            $translationKey = isset($translationMap[$code]) ? $translationMap[$code] : '';
            $label = $translationKey;
            if (is_object($translator) && $translationKey !== '') {
                $label = $translator->trans($translationKey);
            }

            if (!is_string($label) || $label === '' || $label === $translationKey) {
                $label = ucwords(str_replace('_', ' ', $code));
            }

            $options[$code] = $label;
        }

        return $options;
    }

    /**
     * Return type label.
     *
     * @param string $type
     * @param Translate|null $translator
     * @return string
     */
    public static function getTypeLabel($type, $translator = null)
    {
        $type = self::normalizeType($type);
        $options = self::getTypeOptions($translator);

        return isset($options[$type]) ? $options[$type] : $type;
    }

    /**
     * Return available status options.
     *
     * @param Translate|null $translator
     * @return array<int,string>
     */
    public static function getStatusOptions($translator = null)
    {
        if (!is_object($translator)) {
            global $langs;
            $translator = $langs;
        }

        $labels = array(
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_IN_PROGRESS => 'SafraActivityStatusInProgress',
            self::STATUS_COMPLETED => 'SafraActivityStatusCompleted',
            self::STATUS_CANCELED => 'SafraActivityStatusCanceled',
        );

        $result = array();
        foreach ($labels as $status => $translationKey) {
            if (is_object($translator)) {
                $result[$status] = $translator->trans($translationKey);
            } else {
                $result[$status] = $translationKey;
            }
        }

        return $result;
    }

    /**
     * Return human-readable status label.
     *
     * @param int            $status
     * @param Translate|null $translator
     * @return string
     */
    public static function getStatusLabel($status, $translator = null)
    {
        $status = self::normalizeStatus($status);
        $options = self::getStatusOptions($translator);

        return isset($options[$status]) ? $options[$status] : (string) $status;
    }

    /**
     * Return canonical type codes.
     *
     * @return string[]
     */
    protected static function getTypeCodes()
    {
        return array(
            self::TYPE_SOIL_PREPARATION,
            self::TYPE_SEED_PREPARATION,
            self::TYPE_PLANTING,
            self::TYPE_FERTILIZATION,
            self::TYPE_INPUT_APPLICATION,
            self::TYPE_HARVEST,
            self::TYPE_MONITORING,
            self::TYPE_OTHER,
        );
    }

    /**
     * Return aliases to canonical type codes.
     *
     * @return array<string,string>
     */
    protected static function getTypeAliases()
    {
        return array(
            'preparo_solo' => self::TYPE_SOIL_PREPARATION,
            'preparo_do_solo' => self::TYPE_SOIL_PREPARATION,
            'soil_preparation' => self::TYPE_SOIL_PREPARATION,
            'preparo_semente' => self::TYPE_SEED_PREPARATION,
            'preparo_de_semente' => self::TYPE_SEED_PREPARATION,
            'preparo_de_sementes' => self::TYPE_SEED_PREPARATION,
            'tratamento_semente' => self::TYPE_SEED_PREPARATION,
            'tratamento_de_semente' => self::TYPE_SEED_PREPARATION,
            'seed_preparation' => self::TYPE_SEED_PREPARATION,
            'plantio' => self::TYPE_PLANTING,
            'planting' => self::TYPE_PLANTING,
            'fertilizacao' => self::TYPE_FERTILIZATION,
            'fertilization' => self::TYPE_FERTILIZATION,
            'aplicacao' => self::TYPE_INPUT_APPLICATION,
            'atividade_com_insumos' => self::TYPE_INPUT_APPLICATION,
            'input_application' => self::TYPE_INPUT_APPLICATION,
            'colheita' => self::TYPE_HARVEST,
            'harvest' => self::TYPE_HARVEST,
            'monitoramento' => self::TYPE_MONITORING,
            'monitoring' => self::TYPE_MONITORING,
            'outro' => self::TYPE_OTHER,
            'other' => self::TYPE_OTHER,
        );
    }

    /**
     * Return type translation keys.
     *
     * @return array<string,string>
     */
    protected static function getTypeTranslationKeys()
    {
        return array(
            self::TYPE_SOIL_PREPARATION => 'SafraOperationPreparoSolo',
            self::TYPE_SEED_PREPARATION => 'SafraOperationTratamentoSemente',
            self::TYPE_PLANTING => 'SafraOperationPlantio',
            self::TYPE_FERTILIZATION => 'SafraOperationFertilizacao',
            self::TYPE_INPUT_APPLICATION => 'SafraOperationAplicacao',
            self::TYPE_HARVEST => 'SafraOperationColheita',
            self::TYPE_MONITORING => 'SafraOperationMonitoramento',
            self::TYPE_OTHER => 'SafraOperationOutro',
        );
    }
}

