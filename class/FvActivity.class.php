<?php
/*
 * Agricultural activity object for Safra.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once __DIR__ . '/FvActivityLine.class.php';

class FvActivity extends CommonObject
{
    const STATUS_DRAFT = 0;
    const STATUS_PLANNED = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_CANCELED = 9;

    const PRIORITY_LOW = 0;
    const PRIORITY_NORMAL = 1;
    const PRIORITY_HIGH = 2;
    const PRIORITY_URGENT = 3;

    const TYPE_SOIL_PREPARATION = 'preparo_solo';
    const TYPE_SEED_PREPARATION = 'preparo_semente';
    const TYPE_PLANTING = 'plantio';
    const TYPE_FERTILIZATION = 'fertilizacao';
    const TYPE_INPUT_APPLICATION = 'aplicacao';
    const TYPE_MONITORING = 'monitoramento';
    const TYPE_HARVEST = 'colheita';
    const TYPE_MAINTENANCE = 'manutencao';
    const TYPE_OTHER = 'outro';

    const TASK_STATUS_DRAFT = 0;
    const TASK_STATUS_TODO = 1;
    const TASK_STATUS_ONGOING = 2;
    const TASK_STATUS_CLOSED = 3;
    const TASK_STATUS_CANCELED = 9;

    const TASK_LINK_FIELD = 'fk_activity';

    /** @var array<string,string[]> */
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
    public $picto = 'fa-tractor';

    /** @var FvActivityLine[] */
    public $lines = array();

    /** @var array */
    public $context = array();

    /** @var array */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'visible' => -1, 'notnull' => 1, 'position' => 10),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'default' => 1, 'index' => 1, 'position' => 20),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'index' => 1, 'searchall' => 1, 'position' => 30),
        'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'searchall' => 1, 'position' => 40),
        'type' => array('type' => 'varchar(32)', 'label' => 'SafraActivityType', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => self::TYPE_OTHER, 'index' => 1, 'position' => 50),
        'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => self::STATUS_DRAFT, 'index' => 1, 'position' => 60),
        'priority' => array('type' => 'integer', 'label' => 'Priority', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => self::PRIORITY_NORMAL, 'index' => 1, 'position' => 70),
        'progress' => array('type' => 'double(6,2)', 'label' => 'SafraActivityProgress', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 80),
        'season' => array('type' => 'varchar(32)', 'label' => 'SafraActivitySeason', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 90),
        'crop_name' => array('type' => 'varchar(128)', 'label' => 'SafraActivityCrop', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 100),
        'cultivar_name' => array('type' => 'varchar(128)', 'label' => 'Cultivar', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 110),
        'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project', 'enabled' => "isModEnabled('project')", 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 120),
        'fk_task' => array('type' => 'integer:Task:projet/class/task.class.php:1', 'label' => 'Task', 'enabled' => "isModEnabled('project')", 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 130),
        'fk_thirdparty' => array('type' => 'integer:ThirdParty:societe/class/societe.class.php:1', 'label' => 'ThirdParty', 'enabled' => "isModEnabled('societe')", 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 140),
        'fk_fieldplot' => array('type' => 'integer:Talhao:custom/safra/class/talhao.class.php:1', 'label' => 'FieldPlot', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'index' => 1, 'position' => 150),
        'area_planned' => array('type' => 'double(24,8)', 'label' => 'SafraActivityAreaPlanned', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 160),
        'area_done' => array('type' => 'double(24,8)', 'label' => 'SafraActivityAreaDone', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0', 'position' => 170),
        'area_total' => array('type' => 'double(24,8)', 'label' => 'AreaTotal', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'default' => '0', 'position' => 180),
        'date_planned_start' => array('type' => 'datetime', 'label' => 'SafraActivityDatePlannedStart', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 190),
        'date_planned_end' => array('type' => 'datetime', 'label' => 'SafraActivityDatePlannedEnd', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 200),
        'date_start' => array('type' => 'datetime', 'label' => 'DateStart', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 210),
        'date_end' => array('type' => 'datetime', 'label' => 'DateEnd', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 220),
        'weather' => array('type' => 'varchar(255)', 'label' => 'SafraActivityWeather', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 230),
        'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => '1', 'visible' => 1, 'position' => 240),
        'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => '1', 'visible' => 0, 'position' => 250),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'position' => 260),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'position' => 270),
        'fk_user_creat' => array('type' => 'integer', 'label' => 'UserAuthor', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'position' => 280),
        'fk_user_modif' => array('type' => 'integer', 'label' => 'UserModif', 'enabled' => '1', 'visible' => -2, 'notnull' => 0, 'position' => 290),
    );

    public function __construct(DoliDB $db)
    {
        $this->db = $db;

        if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
            $this->fields['rowid']['visible'] = 0;
        }
        if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
            $this->fields['entity']['enabled'] = 0;
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
        $this->prepareForSave($user, true);

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
     * Fetch activity by id/ref.
     *
     * @param int         $id
     * @param string|null $ref
     * @return int
     */
    public function fetch($id, $ref = null)
    {
        $result = $this->fetchCommon($id, $ref);
        if ($result > 0) {
            $this->status = self::normalizeStatus($this->status);
            $this->type = self::normalizeType($this->type);
            $this->priority = self::normalizePriority($this->priority);
            $this->syncLegacyAreaAliases();
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
        $this->prepareForSave($user, false);

        $skipTaskSync = !empty($this->context['skip_task_sync']);
        $result = $this->updateCommon($user);

        if ($result > 0 && !$skipTaskSync) {
            $taskResult = $this->syncTask($user, true);
            if ($taskResult < 0) {
                return -1;
            }
        }

        return $result;
    }

    /**
     * Delete activity only when no stock movement was posted.
     *
     * @param User $user
     * @param bool $deleteLinkedTask
     * @return int
     */
    public function delete($user, $deleteLinkedTask = true)
    {
        if ($this->hasStockMovements()) {
            $this->error = 'ErrorSafraActivityDeleteWithStock';
            return -1;
        }

        if ($deleteLinkedTask && empty($this->context['skip_task_delete'])) {
            $deleteTask = $this->deleteLinkedTask($user);
            if ($deleteTask < 0) {
                return -1;
            }
        }

        $this->deleteAllRelations();
        FvActivityLine::deleteForActivity($this->db, (int) $this->id);

        $result = $this->deleteCommon($user);
        if ($result > 0) {
            $this->call_trigger('SAFRA_ACTIVITY_DELETE', $user);
        }

        return $result;
    }

    /**
     * Start execution.
     *
     * @param User $user
     * @return int
     */
    public function start(User $user)
    {
        if (empty($this->id)) {
            $this->error = 'ErrorSafraActivityInvalidIdentifier';
            return -1;
        }
        if ($this->isCompleted() || $this->isCanceled()) {
            $this->error = 'ErrorSafraActivityInvalidTransitionState';
            return -1;
        }

        $this->status = self::STATUS_IN_PROGRESS;
        $this->progress = max(1, min(99, (float) $this->progress));
        if (empty($this->date_start)) {
            $this->date_start = dol_now();
        }

        $result = $this->update($user);
        if ($result > 0) {
            $this->call_trigger('SAFRA_ACTIVITY_START', $user);
            return 1;
        }

        return -1;
    }

    /**
     * Complete activity and post stock movements from done quantities.
     *
     * @param User $user
     * @return int
     */
    public function complete(User $user)
    {
        if (empty($this->id)) {
            $this->error = 'ErrorSafraActivityInvalidIdentifier';
            return -1;
        }
        if ($this->isCanceled()) {
            $this->error = 'ErrorSafraActivityInvalidTransitionState';
            return -1;
        }

        $stockValidation = $this->validateStockLinesForCompletion();
        if ($stockValidation < 0) {
            return -1;
        }

        $useLocalTransaction = empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        $this->status = self::STATUS_COMPLETED;
        $this->progress = 100;
        if (empty($this->date_start)) {
            $this->date_start = dol_now();
        }
        if (empty($this->date_end)) {
            $this->date_end = dol_now();
        }
        if ($this->area_done <= 0 && $this->area_planned > 0) {
            $this->area_done = $this->area_planned;
        }

        $updateResult = $this->update($user);
        if ($updateResult <= 0) {
            if ($useLocalTransaction) {
                $this->db->rollback();
            }
            return -1;
        }

        $stockResult = $this->createStockMovements($user, false, false);
        if ($stockResult < 0) {
            if ($useLocalTransaction) {
                $this->db->rollback();
            }
            return -1;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        $this->call_trigger('SAFRA_ACTIVITY_DONE', $user);
        $this->call_trigger('SAFRA_ACTIVITY_CLOSE', $user);

        return 1;
    }

    /**
     * Cancel the activity and reverse stock movements when needed.
     *
     * @param User $user
     * @return int
     */
    public function cancel(User $user)
    {
        global $langs;

        if (empty($this->id)) {
            $this->error = 'ErrorSafraActivityInvalidIdentifier';
            return -1;
        }
        if ($this->isCanceled()) {
            $this->error = 'SafraActivityAlertCanceled';
            return -1;
        }

        $useLocalTransaction = empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        if ($this->hasStockMovements()) {
            $revertResult = $this->revertStockMovements($user, false);
            if ($revertResult < 0) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                return -1;
            }
            $this->appendPrivateNotes(array(is_object($langs) ? $langs->trans('SafraActivityStockRevertedLog', $this->ref ?: $this->id) : 'Stock reversed'));
        }

        $this->status = self::STATUS_CANCELED;
        $this->progress = 0;
        $this->appendPrivateNotes(array(is_object($langs) ? $langs->trans('SafraActivityCanceledLog', $this->ref ?: $this->id) : 'Activity canceled'));

        $result = $this->update($user);
        if ($result <= 0) {
            if ($useLocalTransaction) {
                $this->db->rollback();
            }
            return -1;
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        $this->call_trigger('SAFRA_ACTIVITY_CANCEL', $user);

        return 1;
    }

    /**
     * Reopen a canceled or completed activity without changing stock.
     *
     * @param User $user
     * @return int
     */
    public function reopen(User $user)
    {
        if (empty($this->id)) {
            $this->error = 'ErrorSafraActivityInvalidIdentifier';
            return -1;
        }

        $this->status = self::STATUS_PLANNED;
        $this->progress = 0;
        $this->date_end = null;

        return $this->update($user) > 0 ? 1 : -1;
    }

    /**
     * Load input lines.
     *
     * @return int
     */
    public function fetchLines()
    {
        $this->lines = array();

        if (empty($this->id)) {
            return 0;
        }

        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . $this->table_element_line
            . ' WHERE fk_activity = ' . ((int) $this->id)
            . ' ORDER BY position ASC, rowid ASC';
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $line = new FvActivityLine($this->db);
            if ($line->fetch((int) $obj->rowid) > 0) {
                $this->lines[] = $line;
            }
        }

        return count($this->lines);
    }

    /**
     * Fetch user rows linked to this activity.
     *
     * @return array
     */
    public function fetchUserLinks()
    {
        return $this->fetchRelationRows('safra_activity_user', 'fk_user', array('role', 'planned_hours', 'done_hours', 'note'));
    }

    /**
     * Fetch linked user identifiers.
     *
     * @return int[]
     */
    public function fetchUsers()
    {
        return $this->extractRelationIds($this->fetchUserLinks(), 'fk_user');
    }

    /**
     * Replace linked users. Accepts ids or rows with fk_user, role and hours.
     *
     * @param array $rows
     * @return int
     */
    public function setUsers(array $rows)
    {
        return $this->replaceRelationRows('safra_activity_user', 'fk_user', $rows, array(
            'role' => 'varchar',
            'planned_hours' => 'number',
            'done_hours' => 'number',
            'note' => 'text',
        ));
    }

    /**
     * Fetch vehicle rows linked to this activity.
     *
     * @return array
     */
    public function fetchVehicleLinks()
    {
        return $this->fetchRelationRows('safra_activity_vehicle', 'fk_vehicle', array('vehicle_class', 'planned_hours', 'done_hours', 'note'));
    }

    /**
     * Fetch linked vehicle identifiers.
     *
     * @return int[]
     */
    public function fetchVehicles()
    {
        return $this->extractRelationIds($this->fetchVehicleLinks(), 'fk_vehicle');
    }

    /**
     * Replace linked vehicles. Accepts ids or rows with fk_vehicle and hours.
     *
     * @param array $rows
     * @return int
     */
    public function setVehicles(array $rows)
    {
        $normalized = $this->addDefaultClass($rows, 'vehicle_class', 'Veiculo');

        return $this->replaceRelationRows('safra_activity_vehicle', 'fk_vehicle', $normalized, array(
            'vehicle_class' => 'varchar',
            'planned_hours' => 'number',
            'done_hours' => 'number',
            'note' => 'text',
        ));
    }

    /**
     * Backward compatible aliases for older machine naming.
     *
     * @return int[]
     */
    public function fetchMachines()
    {
        return $this->fetchVehicles();
    }

    /**
     * @param array $rows
     * @return int
     */
    public function setMachines(array $rows)
    {
        return $this->setVehicles($rows);
    }

    /**
     * Fetch implement rows linked to this activity.
     *
     * @return array
     */
    public function fetchImplementLinks()
    {
        return $this->fetchRelationRows('safra_activity_implement', 'fk_implement', array('implement_class', 'planned_hours', 'done_hours', 'note'));
    }

    /**
     * Fetch linked implement identifiers.
     *
     * @return int[]
     */
    public function fetchImplements()
    {
        return $this->extractRelationIds($this->fetchImplementLinks(), 'fk_implement');
    }

    /**
     * Replace linked implements. Accepts ids or rows with fk_implement and hours.
     *
     * @param array $rows
     * @return int
     */
    public function setImplements(array $rows)
    {
        $normalized = $this->addDefaultClass($rows, 'implement_class', 'Implemento');

        return $this->replaceRelationRows('safra_activity_implement', 'fk_implement', $normalized, array(
            'implement_class' => 'varchar',
            'planned_hours' => 'number',
            'done_hours' => 'number',
            'note' => 'text',
        ));
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

        $sql = 'SELECT COUNT(rowid) as nb FROM ' . MAIN_DB_PREFIX . "stock_mouvement"
            . ' WHERE fk_origin = ' . ((int) $this->id)
            . " AND origintype = 'safra_activity'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            return false;
        }

        $obj = $this->db->fetch_object($resql);

        return $obj && ((int) $obj->nb > 0);
    }

    /**
     * Register stock movements from activity input lines.
     *
     * @param User|null $user
     * @param bool      $force
     * @param bool      $useTransaction
     * @return int
     */
    public function createStockMovements($user = null, $force = false, $useTransaction = true)
    {
        dol_include_once('/safra/class/ActivityStockService.class.php');

        $stockUser = $user ?: ($GLOBALS['user'] ?? null);
        if (!$stockUser instanceof User) {
            $this->error = 'MissingUser';
            return -1;
        }

        $service = new ActivityStockService($this->db);
        $result = $service->createConsumptionMovements($this, $stockUser, $force, $useTransaction);
        if ($result < 0) {
            $this->error = $service->error;
        }

        return $result;
    }

    /**
     * Reverse stock movements for this activity.
     *
     * @param User|null $user
     * @param bool      $useTransaction
     * @return int
     */
    public function revertStockMovements($user = null, $useTransaction = true)
    {
        dol_include_once('/safra/class/ActivityStockService.class.php');

        $stockUser = $user ?: ($GLOBALS['user'] ?? null);
        if (!$stockUser instanceof User) {
            $this->error = 'MissingUser';
            return -1;
        }

        $service = new ActivityStockService($this->db);
        $result = $service->revertConsumptionMovements($this, $stockUser, $useTransaction);
        if ($result < 0) {
            $this->error = $service->error;
        }

        return $result;
    }

    /**
     * Sync status from linked project task.
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

        $mapped = self::mapTaskToActivityStatus((int) $task->status, (int) $task->progress);
        if ((int) $this->status === (int) $mapped) {
            return 0;
        }

        $this->status = $mapped;
        $this->progress = isset($task->progress) ? min(100, max(0, (float) $task->progress)) : $this->progress;

        if (!$persist || !($user instanceof User)) {
            return 1;
        }

        $this->context['skip_task_sync'] = 1;
        $result = $this->updateCommon($user);
        unset($this->context['skip_task_sync']);

        return ($result > 0) ? 1 : -1;
    }

    /**
     * Fetch activity by project task id.
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
     * Return link to card.
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
        $content = '';
        if ((int) $withpicto > 0) {
            $content .= img_object($langs->trans('SafraActivity'), ($this->picto ?: 'generic'), (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, 0);
        }
        if ((int) $withpicto != 2) {
            $content .= dol_escape_htmltag($label);
        }

        $attrs = $morecss !== '' ? ' class="' . dol_escape_htmltag($morecss) . '"' : '';
        if ((int) $notooltip === 0) {
            $attrs .= ' title="' . dol_escape_htmltag($label) . '"';
        }

        if ($option === 'nolink') {
            return '<span' . $attrs . '>' . $content . '</span>';
        }

        return '<a href="' . $url . '"' . $attrs . '>' . $content . '</a>';
    }

    public function isDraft()
    {
        return ((int) $this->status) === self::STATUS_DRAFT;
    }

    public function isPlanned()
    {
        return ((int) $this->status) === self::STATUS_PLANNED;
    }

    public function isInProgress()
    {
        return ((int) $this->status) === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted()
    {
        return ((int) $this->status) === self::STATUS_COMPLETED;
    }

    public function isCanceled()
    {
        return ((int) $this->status) === self::STATUS_CANCELED;
    }

    /**
     * Prepare values for create/update.
     *
     * @param User|null $user
     * @param bool      $isCreate
     * @return void
     */
    protected function prepareForSave($user = null, $isCreate = false)
    {
        global $conf;

        $this->entity = $this->entity ?: $conf->entity;
        $this->type = self::normalizeType($this->type);
        $this->status = self::normalizeStatus($this->status);
        $this->priority = self::normalizePriority($this->priority);
        $this->progress = min(100, max(0, self::asNumber($this->progress)));

        $this->area_planned = self::asNumber($this->area_planned);
        $this->area_done = self::asNumber($this->area_done);
        $this->area_total = self::asNumber($this->area_total);
        $this->syncLegacyAreaAliases();

        if ($this->isCompleted()) {
            $this->progress = 100;
        } elseif ($this->isCanceled()) {
            $this->progress = 0;
        } elseif ($this->isInProgress() && $this->progress <= 0) {
            $this->progress = 10;
        }

        $this->label = trim((string) $this->label);
        if ($this->label === '') {
            $this->label = self::getTypeLabel($this->type) . ' ' . dol_print_date(dol_now(), 'day');
        }

        $this->ref = trim((string) $this->ref);
        if ($this->ref === '' || $this->ref === '(PROV)') {
            $this->ref = $this->generateRef();
        }

        if ($user instanceof User) {
            if ($isCreate && empty($this->fk_user_creat)) {
                $this->fk_user_creat = (int) $user->id;
            }
            $this->fk_user_modif = (int) $user->id;
        }
    }

    /**
     * Keep legacy area_total as planned area alias.
     *
     * @return void
     */
    protected function syncLegacyAreaAliases()
    {
        if (empty($this->area_planned) && !empty($this->area_total)) {
            $this->area_planned = $this->area_total;
        }
        $this->area_total = $this->area_planned;
    }

    /**
     * Validate stock-ready lines before completion.
     *
     * @return int
     */
    protected function validateStockLinesForCompletion()
    {
        if (empty($this->lines)) {
            $this->fetchLines();
        }

        foreach ($this->lines as $line) {
            $quantity = $this->getLineMovementQuantity($line);
            if ($quantity <= 0 || empty($line->fk_product)) {
                continue;
            }
            if (empty($line->fk_warehouse)) {
                $this->error = 'ErrorSafraActivityMissingWarehouse';
                return -1;
            }
        }

        return 1;
    }

    /**
     * Compute quantity used for stock movement.
     *
     * @param mixed $line
     * @return float
     */
    protected function getLineMovementQuantity($line)
    {
        foreach (array('qty_done', 'total', 'qty_planned') as $field) {
            if (isset($line->{$field}) && self::asNumber($line->{$field}) > 0) {
                return self::asNumber($line->{$field});
            }
        }

        return 0;
    }

    /**
     * Sync linked Dolibarr project task.
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
        } elseif ($createIfMissing && !empty($this->fk_project)) {
            $isNewTask = true;
        }

        if (!$isNewTask && empty($task->id)) {
            return 0;
        }

        $task->fk_project = (int) $this->fk_project;
        $task->label = $this->buildTaskLabel();
        $task->description = $this->buildTaskDescription();
        $task->date_start = !empty($this->date_planned_start) ? $this->date_planned_start : (!empty($this->date_start) ? $this->date_start : dol_now());
        if (!empty($this->date_planned_end)) {
            $task->date_end = $this->date_planned_end;
        } elseif (!empty($this->date_end)) {
            $task->date_end = $this->date_end;
        }
        $this->applyActivityStatusToTask($task);

        if ($isNewTask) {
            $taskId = $task->create($user, 1);
            if ($taskId <= 0) {
                $this->error = $task->error ?: $task->errorsToString();
                return -1;
            }
            $this->fk_task = (int) $taskId;
            $this->persistTaskLinkOnActivity();
        } else {
            $taskUpdate = $task->update($user, 1);
            if ($taskUpdate <= 0) {
                $this->error = $task->error ?: $task->errorsToString();
                return -1;
            }
        }

        return $this->syncTaskExtrafieldLink((int) $this->fk_task);
    }

    /**
     * Apply activity status to task status/progress.
     *
     * @param Task $task
     * @return bool
     */
    protected function applyActivityStatusToTask($task)
    {
        if ($this->isCanceled()) {
            $task->status = self::TASK_STATUS_CANCELED;
            $task->fk_statut = self::TASK_STATUS_CANCELED;
            $task->progress = 0;
        } elseif ($this->isCompleted()) {
            $task->status = self::TASK_STATUS_CLOSED;
            $task->fk_statut = self::TASK_STATUS_CLOSED;
            $task->progress = 100;
        } elseif ($this->isInProgress()) {
            $task->status = self::TASK_STATUS_ONGOING;
            $task->fk_statut = self::TASK_STATUS_ONGOING;
            $task->progress = max(1, min(99, (int) $this->progress));
        } else {
            $task->status = self::TASK_STATUS_TODO;
            $task->fk_statut = self::TASK_STATUS_TODO;
            $task->progress = max(0, min(99, (int) $this->progress));
        }

        return true;
    }

    /**
     * Persist fk_task directly after task creation.
     *
     * @return int
     */
    protected function persistTaskLinkOnActivity()
    {
        if (empty($this->id) || empty($this->fk_task)) {
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
     * Delete linked project task.
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

        $task->context['skip_activity_delete'] = 1;
        $result = $task->delete($user, 1);
        if ($result <= 0) {
            $this->error = $task->error ?: $task->errorsToString();
            return -1;
        }

        return 1;
    }

    /**
     * Upsert task extrafield link.
     *
     * @param int $taskId
     * @return int
     */
    protected function syncTaskExtrafieldLink($taskId)
    {
        $columns = $this->getTaskLinkColumns();
        if (empty($columns) || empty($taskId) || empty($this->id)) {
            return 0;
        }

        $sqlCheck = 'SELECT fk_object FROM ' . MAIN_DB_PREFIX . 'projet_task_extrafields WHERE fk_object = ' . ((int) $taskId);
        $resql = $this->db->query($sqlCheck);
        if (!$resql) {
            return 0;
        }

        $exists = $this->db->fetch_object($resql);
        if ($exists) {
            $set = array();
            foreach ($columns as $column) {
                $set[] = $column . ' = ' . ((int) $this->id);
            }
            $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'projet_task_extrafields SET ' . implode(', ', $set) . ' WHERE fk_object = ' . ((int) $taskId);
        } else {
            $fields = array('fk_object');
            $values = array((int) $taskId);
            foreach ($columns as $column) {
                $fields[] = $column;
                $values[] = (int) $this->id;
            }
            $sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'projet_task_extrafields (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
        }

        if (!$this->db->query($sql)) {
            $this->error = $this->db->lasterror();
            return -1;
        }

        return 1;
    }

    /**
     * Detect task extrafield columns.
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
        $sql = 'SHOW COLUMNS FROM ' . MAIN_DB_PREFIX . "projet_task_extrafields LIKE '" . $this->db->escape(self::TASK_LINK_FIELD) . "'";
        $resql = $this->db->query($sql);
        if ($resql && $this->db->fetch_object($resql)) {
            $columns[] = self::TASK_LINK_FIELD;
        }

        self::$taskLinkColumnsCache[$cacheKey] = $columns;

        return $columns;
    }

    /**
     * Find activity through task extrafield relation.
     *
     * @param DoliDB $db
     * @param int    $taskId
     * @return int
     */
    protected static function findActivityIdByTaskExtrafield($db, $taskId)
    {
        $sql = 'SELECT a.rowid'
            . ' FROM ' . MAIN_DB_PREFIX . 'safra_activity as a'
            . ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task_extrafields as te ON te.' . self::TASK_LINK_FIELD . ' = a.rowid'
            . ' WHERE te.fk_object = ' . ((int) $taskId)
            . ' LIMIT 1';
        $resql = $db->query($sql);
        if (!$resql) {
            return 0;
        }

        $obj = $db->fetch_object($resql);

        return $obj ? (int) $obj->rowid : 0;
    }

    /**
     * Build linked task label.
     *
     * @return string
     */
    protected function buildTaskLabel()
    {
        return trim((string) ($this->ref ? $this->ref . ' - ' . $this->label : $this->label));
    }

    /**
     * Build linked task description.
     *
     * @return string
     */
    protected function buildTaskDescription()
    {
        $description = array();
        $description[] = 'Safra activity: ' . ($this->ref ?: $this->id);
        $description[] = 'Type: ' . self::getTypeLabel($this->type);
        if (!empty($this->season)) {
            $description[] = 'Season: ' . $this->season;
        }
        if (!empty($this->crop_name)) {
            $description[] = 'Crop: ' . $this->crop_name;
        }
        if (!empty($this->note_public) && function_exists('dol_string_nohtmltag')) {
            $description[] = 'Note: ' . dol_string_nohtmltag((string) $this->note_public);
        }

        return implode("\n", $description);
    }

    /**
     * Fetch relation rows.
     *
     * @param string   $table
     * @param string   $targetField
     * @param string[] $extraFields
     * @return array
     */
    protected function fetchRelationRows($table, $targetField, array $extraFields)
    {
        $rows = array();
        if (empty($this->id)) {
            return $rows;
        }

        $select = array('rowid', $targetField);
        foreach ($extraFields as $field) {
            $select[] = $field;
        }

        $sql = 'SELECT ' . implode(', ', $select)
            . ' FROM ' . MAIN_DB_PREFIX . $table
            . ' WHERE fk_activity = ' . ((int) $this->id)
            . ' ORDER BY rowid ASC';
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return $rows;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $rows[] = $obj;
        }

        return $rows;
    }

    /**
     * Replace relation rows.
     *
     * @param string $table
     * @param string $targetField
     * @param array  $rows
     * @param array  $extraFields
     * @return int
     */
    protected function replaceRelationRows($table, $targetField, array $rows, array $extraFields)
    {
        global $conf;

        if (empty($this->id)) {
            $this->error = 'MissingActivityIdentifier';
            return -1;
        }

        $normalized = $this->normalizeRelationRows($rows, $targetField, $extraFields);

        $useLocalTransaction = empty($this->db->transaction_opened);
        if ($useLocalTransaction) {
            $this->db->begin();
        }

        $sqlDelete = 'DELETE FROM ' . MAIN_DB_PREFIX . $table . ' WHERE fk_activity = ' . ((int) $this->id);
        if (!$this->db->query($sqlDelete)) {
            if ($useLocalTransaction) {
                $this->db->rollback();
            }
            $this->error = $this->db->lasterror();
            return -1;
        }

        foreach ($normalized as $row) {
            $columns = array('entity', 'fk_activity', $targetField, 'date_creation');
            $values = array((int) $conf->entity, (int) $this->id, (int) $row[$targetField], "'" . $this->db->idate(dol_now()) . "'");

            foreach ($extraFields as $field => $type) {
                $columns[] = $field;
                if ($type === 'number') {
                    $values[] = self::asNumber(isset($row[$field]) ? $row[$field] : 0);
                } else {
                    $values[] = $this->sqlNullableString(isset($row[$field]) ? $row[$field] : '');
                }
            }

            $sqlInsert = 'INSERT INTO ' . MAIN_DB_PREFIX . $table
                . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
            if (!$this->db->query($sqlInsert)) {
                if ($useLocalTransaction) {
                    $this->db->rollback();
                }
                $this->error = $this->db->lasterror();
                return -1;
            }
        }

        if ($useLocalTransaction) {
            $this->db->commit();
        }

        return count($normalized);
    }

    /**
     * Normalize relation input rows.
     *
     * @param array  $rows
     * @param string $targetField
     * @param array  $extraFields
     * @return array
     */
    protected function normalizeRelationRows(array $rows, $targetField, array $extraFields)
    {
        $clean = array();
        foreach ($rows as $row) {
            if (is_scalar($row)) {
                $id = (int) $row;
                $rowData = array($targetField => $id);
            } elseif (is_array($row)) {
                $id = 0;
                foreach (array($targetField, 'id', 'rowid', 'fk_machine') as $idField) {
                    if (!empty($row[$idField])) {
                        $id = (int) $row[$idField];
                        break;
                    }
                }
                $rowData = $row;
                $rowData[$targetField] = $id;
            } else {
                continue;
            }

            if ((int) $rowData[$targetField] <= 0) {
                continue;
            }

            foreach ($extraFields as $field => $type) {
                if (!array_key_exists($field, $rowData)) {
                    $rowData[$field] = ($type === 'number') ? 0 : '';
                }
            }

            $clean[(int) $rowData[$targetField]] = $rowData;
        }

        return array_values($clean);
    }

    /**
     * Add default relation class.
     *
     * @param array  $rows
     * @param string $field
     * @param string $default
     * @return array
     */
    protected function addDefaultClass(array $rows, $field, $default)
    {
        foreach ($rows as $key => $row) {
            if (is_array($row)) {
                if (empty($row[$field])) {
                    $row[$field] = $default;
                }
                $rows[$key] = $row;
            } else {
                $rows[$key] = array('id' => $row, $field => $default);
            }
        }

        return $rows;
    }

    /**
     * Extract ids from relation rows.
     *
     * @param array  $rows
     * @param string $field
     * @return int[]
     */
    protected function extractRelationIds(array $rows, $field)
    {
        $ids = array();
        foreach ($rows as $row) {
            if (isset($row->{$field})) {
                $ids[] = (int) $row->{$field};
            }
        }

        return $ids;
    }

    /**
     * Delete relation rows.
     *
     * @return void
     */
    protected function deleteAllRelations()
    {
        foreach (array('safra_activity_user', 'safra_activity_vehicle', 'safra_activity_implement') as $table) {
            $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . $table . ' WHERE fk_activity = ' . ((int) $this->id);
            $this->db->query($sql);
        }
    }

    /**
     * Append private notes.
     *
     * @param string[] $messages
     * @return void
     */
    protected function appendPrivateNotes(array $messages)
    {
        $timestamp = function_exists('dol_print_date') ? dol_print_date(dol_now(), 'dayhourlog') : date('Y-m-d H:i:s');
        foreach ($messages as $message) {
            $message = trim((string) $message);
            if ($message === '') {
                continue;
            }
            $this->note_private = trim((string) $this->note_private . "\n" . '[' . $timestamp . '] ' . $message);
        }
    }

    /**
     * Generate activity reference.
     *
     * @return string
     */
    protected function generateRef()
    {
        return 'ACT-' . date('Ymd-His', dol_now()) . '-' . mt_rand(100, 999);
    }

    /**
     * SQL nullable string.
     *
     * @param mixed $value
     * @return string
     */
    protected function sqlNullableString($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'NULL';
        }

        return "'" . $this->db->escape($value) . "'";
    }

    /**
     * Normalize activity status.
     *
     * @param int|string|null $status
     * @return int
     */
    public static function normalizeStatus($status)
    {
        $status = is_string($status) ? strtolower(trim($status)) : $status;
        if ($status === 'draft' || $status === 'rascunho') {
            return self::STATUS_DRAFT;
        }
        if ($status === 'planned' || $status === 'planejada' || $status === 'validated') {
            return self::STATUS_PLANNED;
        }
        if ($status === 'in_progress' || $status === 'running' || $status === 'em_execucao') {
            return self::STATUS_IN_PROGRESS;
        }
        if ($status === 'completed' || $status === 'done' || $status === 'closed' || $status === 'concluida') {
            return self::STATUS_COMPLETED;
        }
        if ($status === 'canceled' || $status === 'cancelled' || $status === 'cancelada') {
            return self::STATUS_CANCELED;
        }

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
        if ($status === self::STATUS_PLANNED || $status === self::TASK_STATUS_TODO) {
            return self::STATUS_PLANNED;
        }

        return self::STATUS_DRAFT;
    }

    /**
     * Map Dolibarr task status/progress to activity status.
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
        if ($taskStatus === self::TASK_STATUS_TODO) {
            return self::STATUS_PLANNED;
        }

        return self::STATUS_DRAFT;
    }

    /**
     * Normalize operation type.
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
        if (in_array($type, self::getTypeCodes(), true)) {
            return $type;
        }

        return self::TYPE_OTHER;
    }

    /**
     * Normalize priority.
     *
     * @param int|string|null $priority
     * @return int
     */
    public static function normalizePriority($priority)
    {
        $priority = is_string($priority) ? strtolower(trim($priority)) : $priority;
        if ($priority === 'low' || $priority === 'baixa') {
            return self::PRIORITY_LOW;
        }
        if ($priority === 'high' || $priority === 'alta') {
            return self::PRIORITY_HIGH;
        }
        if ($priority === 'urgent' || $priority === 'urgente') {
            return self::PRIORITY_URGENT;
        }

        $priority = (int) $priority;
        if ($priority < self::PRIORITY_LOW || $priority > self::PRIORITY_URGENT) {
            return self::PRIORITY_NORMAL;
        }

        return $priority;
    }

    /**
     * Return type options.
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

        $labels = array(
            self::TYPE_SOIL_PREPARATION => 'SafraOperationPreparoSolo',
            self::TYPE_SEED_PREPARATION => 'SafraOperationTratamentoSemente',
            self::TYPE_PLANTING => 'SafraOperationPlantio',
            self::TYPE_FERTILIZATION => 'SafraOperationFertilizacao',
            self::TYPE_INPUT_APPLICATION => 'SafraOperationAplicacao',
            self::TYPE_MONITORING => 'SafraOperationMonitoramento',
            self::TYPE_HARVEST => 'SafraOperationColheita',
            self::TYPE_MAINTENANCE => 'SafraOperationManutencao',
            self::TYPE_OTHER => 'SafraOperationOutro',
        );

        $options = array();
        foreach (self::getTypeCodes() as $code) {
            $key = isset($labels[$code]) ? $labels[$code] : $code;
            $label = is_object($translator) ? $translator->trans($key) : $key;
            if ($label === $key) {
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
     * Return status options.
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
            self::STATUS_PLANNED => 'SafraActivityStatusPlanned',
            self::STATUS_IN_PROGRESS => 'SafraActivityStatusInProgress',
            self::STATUS_COMPLETED => 'SafraActivityStatusCompleted',
            self::STATUS_CANCELED => 'SafraActivityStatusCanceled',
        );

        $result = array();
        foreach ($labels as $status => $key) {
            $result[$status] = is_object($translator) ? $translator->trans($key) : $key;
        }

        return $result;
    }

    /**
     * Return status label.
     *
     * @param int|string $status
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
     * Return priority options.
     *
     * @param Translate|null $translator
     * @return array<int,string>
     */
    public static function getPriorityOptions($translator = null)
    {
        if (!is_object($translator)) {
            global $langs;
            $translator = $langs;
        }

        $labels = array(
            self::PRIORITY_LOW => 'SafraPriorityLow',
            self::PRIORITY_NORMAL => 'SafraPriorityNormal',
            self::PRIORITY_HIGH => 'SafraPriorityHigh',
            self::PRIORITY_URGENT => 'SafraPriorityUrgent',
        );

        $result = array();
        foreach ($labels as $priority => $key) {
            $result[$priority] = is_object($translator) ? $translator->trans($key) : $key;
        }

        return $result;
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
            self::TYPE_MONITORING,
            self::TYPE_HARVEST,
            self::TYPE_MAINTENANCE,
            self::TYPE_OTHER,
        );
    }

    /**
     * Return aliases.
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
            'tratamento_semente' => self::TYPE_SEED_PREPARATION,
            'tratamento_de_semente' => self::TYPE_SEED_PREPARATION,
            'seed_preparation' => self::TYPE_SEED_PREPARATION,
            'plantio' => self::TYPE_PLANTING,
            'planting' => self::TYPE_PLANTING,
            'fertilizacao' => self::TYPE_FERTILIZATION,
            'fertilization' => self::TYPE_FERTILIZATION,
            'adubacao' => self::TYPE_FERTILIZATION,
            'aplicacao' => self::TYPE_INPUT_APPLICATION,
            'pulverizacao' => self::TYPE_INPUT_APPLICATION,
            'input_application' => self::TYPE_INPUT_APPLICATION,
            'monitoramento' => self::TYPE_MONITORING,
            'monitoring' => self::TYPE_MONITORING,
            'colheita' => self::TYPE_HARVEST,
            'harvest' => self::TYPE_HARVEST,
            'manutencao' => self::TYPE_MAINTENANCE,
            'maintenance' => self::TYPE_MAINTENANCE,
            'outro' => self::TYPE_OTHER,
            'other' => self::TYPE_OTHER,
        );
    }

    /**
     * Convert numeric input.
     *
     * @param mixed $value
     * @return float
     */
    protected static function asNumber($value)
    {
        if (function_exists('price2num')) {
            return (float) price2num($value, 'MT');
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }
}
