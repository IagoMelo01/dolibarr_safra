<?php
/*
 * REST API for Safra Activities.
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT . '/api/class/api.class.php';
dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/safra/class/FvActivityLine.class.php');

/**
 * API class for safra activities.
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user}
 */
class Sfactivities extends DolibarrApi
{
    /** @var FvActivity */
    public $activity;

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $db;

        $this->db = $db;
        $this->activity = new FvActivity($this->db);
    }

    /**
     * List activities.
     *
     * @param string $sortfield
     * @param string $sortorder
     * @param int    $limit
     * @param int    $page
     * @param string $sqlfilters
     * @param int    $include_lines
     * @return array
     */
    public function index($sortfield = 't.rowid', $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $include_lines = 0)
    {
        $this->assertReadRight();

        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = 100;
        }
        $limit = min(1000, $limit);

        $page = max(0, (int) $page);
        $offset = $limit * $page;

        $sql = 'SELECT t.rowid';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'safra_activity as t';
        $sql .= ' WHERE t.entity IN (' . getEntity('safra_activity') . ')';

        if (!empty($sqlfilters)) {
            $errorMessage = '';
            $sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errorMessage, 1);
            if ($errorMessage) {
                throw new RestException(400, $errorMessage);
            }
        }

        $sql .= $this->db->order($sortfield, $sortorder);
        $sql .= $this->db->plimit($limit, $offset);

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RestException(503, $this->db->lasterror());
        }

        $result = array();
        while ($obj = $this->db->fetch_object($resql)) {
            $activity = new FvActivity($this->db);
            if ($activity->fetch((int) $obj->rowid) > 0) {
                $result[] = $this->formatActivity($activity, (int) $include_lines > 0);
            }
        }

        return $result;
    }

    /**
     * Get activity by id.
     *
     * @param int $id
     * @param int $include_lines
     * @return array
     */
    public function get($id, $include_lines = 0)
    {
        $this->assertReadRight();

        $activity = $this->fetchActivityOr404($id);

        return $this->formatActivity($activity, (int) $include_lines > 0);
    }

    /**
     * Create activity.
     *
     * @param array|null $request_data
     * @return array
     */
    public function post($request_data = null)
    {
        $this->assertWriteRight();

        if (!is_array($request_data)) {
            throw new RestException(400, 'No activity data provided.');
        }

        $this->db->begin();

        $activity = new FvActivity($this->db);
        $this->hydrateActivity($activity, $request_data, true);

        if (empty($activity->label)) {
            $this->db->rollback();
            throw new RestException(400, 'Missing required field: label');
        }

        $res = $activity->create(DolibarrApiAccess::$user);
        if ($res <= 0) {
            $this->db->rollback();
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        $applyRes = $this->applyRelationsAndLines($activity, $request_data);
        if ($applyRes < 0) {
            $this->db->rollback();
            throw new RestException(500, $activity->error ?: 'Failed to save activity relations/lines.');
        }

        $this->db->commit();

        $activity->fetch($activity->id);

        return $this->formatActivity($activity, true);
    }

    /**
     * Update activity.
     *
     * @param int        $id
     * @param array|null $request_data
     * @return array
     */
    public function put($id, $request_data = null)
    {
        $this->assertWriteRight();

        if (!is_array($request_data)) {
            throw new RestException(400, 'No activity data provided.');
        }

        $activity = $this->fetchActivityOr404($id);

        $this->db->begin();

        $this->hydrateActivity($activity, $request_data, false);

        $res = $activity->update(DolibarrApiAccess::$user);
        if ($res <= 0) {
            $this->db->rollback();
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        $applyRes = $this->applyRelationsAndLines($activity, $request_data);
        if ($applyRes < 0) {
            $this->db->rollback();
            throw new RestException(500, $activity->error ?: 'Failed to save activity relations/lines.');
        }

        $this->db->commit();

        $activity->fetch($activity->id);

        return $this->formatActivity($activity, true);
    }

    /**
     * Delete activity.
     *
     * @param int $id
     * @return array
     */
    public function delete($id)
    {
        $this->assertDeleteRight();

        $activity = $this->fetchActivityOr404($id);

        $res = $activity->delete(DolibarrApiAccess::$user);
        if ($res <= 0) {
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        return array('success' => true, 'id' => (int) $id);
    }

    /**
     * Start activity.
     *
     * @param int $id
     * @return array
     *
     * @url POST {id}/start
     */
    public function postStart($id)
    {
        $this->assertWriteRight();

        $activity = $this->fetchActivityOr404($id);
        $res = $activity->start(DolibarrApiAccess::$user);
        if ($res <= 0) {
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        $activity->fetch($activity->id);

        return $this->formatActivity($activity, true);
    }

    /**
     * Complete activity.
     *
     * @param int $id
     * @return array
     *
     * @url POST {id}/complete
     */
    public function postComplete($id)
    {
        $this->assertWriteRight();

        $activity = $this->fetchActivityOr404($id);
        $res = $activity->complete(DolibarrApiAccess::$user);
        if ($res <= 0) {
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        $activity->fetch($activity->id);

        return $this->formatActivity($activity, true);
    }

    /**
     * Cancel activity.
     *
     * @param int $id
     * @return array
     *
     * @url POST {id}/cancel
     */
    public function postCancel($id)
    {
        $this->assertWriteRight();

        $activity = $this->fetchActivityOr404($id);
        $res = $activity->cancel(DolibarrApiAccess::$user);
        if ($res <= 0) {
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        $activity->fetch($activity->id);

        return $this->formatActivity($activity, true);
    }

    /**
     * Fill object fields from request payload.
     *
     * @param FvActivity $activity
     * @param array      $requestData
     * @param bool       $isCreate
     * @return void
     */
    private function hydrateActivity(FvActivity $activity, array $requestData, $isCreate)
    {
        if ($isCreate && empty($activity->ref)) {
            $activity->ref = '(PROV)';
        }

        if (array_key_exists('ref', $requestData)) {
            $activity->ref = trim((string) $requestData['ref']);
        }
        if (array_key_exists('label', $requestData)) {
            $activity->label = trim((string) $requestData['label']);
        }
        if (array_key_exists('type', $requestData)) {
            $activity->type = FvActivity::normalizeType($requestData['type']);
        }
        if (array_key_exists('status', $requestData)) {
            $activity->status = FvActivity::normalizeStatus($requestData['status']);
        }

        if (array_key_exists('fk_project', $requestData)) {
            $activity->fk_project = $this->asNullableInt($requestData['fk_project']);
        }
        if (array_key_exists('fk_task', $requestData)) {
            $activity->fk_task = $this->asNullableInt($requestData['fk_task']);
        }
        if (array_key_exists('fk_thirdparty', $requestData) || array_key_exists('fk_soc', $requestData)) {
            $thirdparty = array_key_exists('fk_thirdparty', $requestData) ? $requestData['fk_thirdparty'] : $requestData['fk_soc'];
            $activity->fk_thirdparty = $this->asNullableInt($thirdparty);
        }
        if (array_key_exists('fk_fieldplot', $requestData) || array_key_exists('fk_talhao', $requestData)) {
            $fieldplot = array_key_exists('fk_fieldplot', $requestData) ? $requestData['fk_fieldplot'] : $requestData['fk_talhao'];
            $activity->fk_fieldplot = $this->asNullableInt($fieldplot);
        }
        if (array_key_exists('area_total', $requestData)) {
            $activity->area_total = price2num($requestData['area_total'], 'MT');
        }
        if (array_key_exists('note_public', $requestData)) {
            $activity->note_public = $requestData['note_public'];
        }
        if (array_key_exists('note_private', $requestData)) {
            $activity->note_private = $requestData['note_private'];
        }

        $activity->fk_user_modif = (int) DolibarrApiAccess::$user->id;
    }

    /**
     * Save optional relation arrays and line payload.
     *
     * @param FvActivity $activity
     * @param array      $requestData
     * @return int
     */
    private function applyRelationsAndLines(FvActivity $activity, array $requestData)
    {
        if (array_key_exists('machine_ids', $requestData) && is_array($requestData['machine_ids'])) {
            if ($activity->setMachines($requestData['machine_ids']) < 0) {
                return -1;
            }
        }

        if (array_key_exists('implement_ids', $requestData) && is_array($requestData['implement_ids'])) {
            if ($activity->setImplements($requestData['implement_ids']) < 0) {
                return -1;
            }
        }

        if (array_key_exists('user_ids', $requestData) && is_array($requestData['user_ids'])) {
            if ($activity->setUsers($requestData['user_ids']) < 0) {
                return -1;
            }
        }

        if (array_key_exists('lines', $requestData) && is_array($requestData['lines'])) {
            if (FvActivityLine::deleteForActivity($this->db, $activity->id) < 0) {
                $activity->error = $this->db->lasterror();
                return -1;
            }

            foreach ($requestData['lines'] as $lineData) {
                if (!is_array($lineData)) {
                    continue;
                }

                $fkProduct = $this->asNullableInt(isset($lineData['fk_product']) ? $lineData['fk_product'] : null);
                if ($fkProduct === null || $fkProduct <= 0) {
                    continue;
                }

                $line = new FvActivityLine($this->db);
                $line->fk_activity = (int) $activity->id;
                $line->fk_product = $fkProduct;
                $line->area_applied = price2num(isset($lineData['area_applied']) ? $lineData['area_applied'] : 0, 'MT');
                $line->dose = price2num(isset($lineData['dose']) ? $lineData['dose'] : 0, 'MT');
                $line->dose_unit = isset($lineData['dose_unit']) ? (string) $lineData['dose_unit'] : '';
                $line->total = price2num(isset($lineData['total']) ? $lineData['total'] : ($line->area_applied * $line->dose), 'MT');
                $line->movement_type = isset($lineData['movement_type']) ? (string) $lineData['movement_type'] : 'consume';
                if (!in_array($line->movement_type, array('consume', 'return', 'transfer'), true)) {
                    $line->movement_type = 'consume';
                }
                $line->fk_warehouse = $this->asNullableInt(isset($lineData['fk_warehouse']) ? $lineData['fk_warehouse'] : null);
                $line->fk_user_creat = (int) DolibarrApiAccess::$user->id;
                $line->fk_user_modif = (int) DolibarrApiAccess::$user->id;

                if ($line->create(DolibarrApiAccess::$user) < 0) {
                    $activity->error = $line->error ?: $line->errorsToString();
                    return -1;
                }
            }
        }

        return 1;
    }

    /**
     * Build API payload from activity object.
     *
     * @param FvActivity $activity
     * @param bool       $includeLines
     * @return array
     */
    private function formatActivity(FvActivity $activity, $includeLines = false)
    {
        $data = array(
            'id' => (int) $activity->id,
            'rowid' => (int) $activity->id,
            'ref' => (string) $activity->ref,
            'label' => (string) $activity->label,
            'fk_project' => $this->asNullableInt($activity->fk_project),
            'fk_task' => $this->asNullableInt($activity->fk_task),
            'fk_thirdparty' => $this->asNullableInt($activity->fk_thirdparty),
            'fk_fieldplot' => $this->asNullableInt($activity->fk_fieldplot),
            'area_total' => (float) $activity->area_total,
            'type' => (string) FvActivity::normalizeType($activity->type),
            'status' => (int) FvActivity::normalizeStatus($activity->status),
            'status_label' => FvActivity::getStatusLabel($activity->status),
            'note_public' => (string) $activity->note_public,
            'note_private' => (string) $activity->note_private,
            'date_creation' => $activity->date_creation,
            'tms' => $activity->tms,
            'fk_user_creat' => $this->asNullableInt($activity->fk_user_creat),
            'fk_user_modif' => $this->asNullableInt($activity->fk_user_modif),
            'machine_ids' => $activity->fetchMachines(),
            'implement_ids' => $activity->fetchImplements(),
            'user_ids' => $activity->fetchUsers(),
        );

        if ($includeLines) {
            $activity->fetchLines();
            $lines = array();
            foreach ($activity->lines as $line) {
                $lines[] = array(
                    'id' => (int) $line->id,
                    'rowid' => (int) $line->id,
                    'fk_activity' => (int) $line->fk_activity,
                    'fk_product' => $this->asNullableInt($line->fk_product),
                    'area_applied' => (float) $line->area_applied,
                    'dose' => (float) $line->dose,
                    'dose_unit' => (string) $line->dose_unit,
                    'total' => (float) $line->total,
                    'movement_type' => (string) $line->movement_type,
                    'fk_warehouse' => $this->asNullableInt($line->fk_warehouse),
                    'date_creation' => $line->date_creation,
                    'tms' => $line->tms,
                );
            }
            $data['lines'] = $lines;
        }

        return $data;
    }

    /**
     * @param int|string $id
     * @return FvActivity
     */
    private function fetchActivityOr404($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new RestException(400, 'Invalid id.');
        }

        $activity = new FvActivity($this->db);
        if ($activity->fetch($id) <= 0) {
            throw new RestException(404, 'Activity not found.');
        }

        return $activity;
    }

    private function asNullableInt($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $value = (int) $value;
        if ($value <= 0) {
            return null;
        }

        return $value;
    }

    private function assertReadRight()
    {
        if (empty(DolibarrApiAccess::$user) || !DolibarrApiAccess::$user->hasRight('safra', 'SafraActivity', 'read')) {
            throw new RestException(403, 'Read permission denied.');
        }
    }

    private function assertWriteRight()
    {
        if (empty(DolibarrApiAccess::$user) || !DolibarrApiAccess::$user->hasRight('safra', 'SafraActivity', 'write')) {
            throw new RestException(403, 'Write permission denied.');
        }
    }

    private function assertDeleteRight()
    {
        if (empty(DolibarrApiAccess::$user) || !DolibarrApiAccess::$user->hasRight('safra', 'SafraActivity', 'delete')) {
            throw new RestException(403, 'Delete permission denied.');
        }
    }
}