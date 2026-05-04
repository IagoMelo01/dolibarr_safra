<?php
/*
 * REST API for Safra agricultural activities.
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT . '/api/class/api.class.php';
dol_include_once('/safra/class/FvActivity.class.php');
dol_include_once('/safra/class/FvActivityLine.class.php');

/**
 * @access protected
 * @class DolibarrApiAccess {@requires user}
 */
class Sfactivities extends DolibarrApi
{
    /** @var FvActivity */
    public $activity;

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

        $limit = max(1, min(1000, (int) $limit));
        $page = max(0, (int) $page);
        $offset = $limit * $page;

        $sql = 'SELECT t.rowid FROM ' . MAIN_DB_PREFIX . 'safra_activity as t';
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

        return $this->formatActivity($this->fetchActivityOr404($id), (int) $include_lines > 0);
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
        if (trim((string) $activity->label) === '') {
            $this->db->rollback();
            throw new RestException(400, 'Missing required field: label');
        }

        if ($activity->create(DolibarrApiAccess::$user) <= 0) {
            $this->db->rollback();
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        if ($this->applyRelationsAndLines($activity, $request_data) < 0) {
            $this->db->rollback();
            throw new RestException(500, $activity->error ?: 'Failed to save activity relations.');
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
        if ($activity->update(DolibarrApiAccess::$user) <= 0) {
            $this->db->rollback();
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        if ($this->applyRelationsAndLines($activity, $request_data) < 0) {
            $this->db->rollback();
            throw new RestException(500, $activity->error ?: 'Failed to save activity relations.');
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
        if ($activity->delete(DolibarrApiAccess::$user) <= 0) {
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        return array('success' => true, 'id' => (int) $id);
    }

    /**
     * @url POST {id}/start
     */
    public function postStart($id)
    {
        $this->assertWriteRight();

        $activity = $this->fetchActivityOr404($id);
        if ($activity->start(DolibarrApiAccess::$user) <= 0) {
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        $activity->fetch($activity->id);

        return $this->formatActivity($activity, true);
    }

    /**
     * @url POST {id}/complete
     */
    public function postComplete($id)
    {
        $this->assertWriteRight();

        $activity = $this->fetchActivityOr404($id);
        if ($activity->complete(DolibarrApiAccess::$user) <= 0) {
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        $activity->fetch($activity->id);

        return $this->formatActivity($activity, true);
    }

    /**
     * @url POST {id}/cancel
     */
    public function postCancel($id)
    {
        $this->assertWriteRight();

        $activity = $this->fetchActivityOr404($id);
        if ($activity->cancel(DolibarrApiAccess::$user) <= 0) {
            throw new RestException(500, $activity->error ?: $activity->errorsToString());
        }

        $activity->fetch($activity->id);

        return $this->formatActivity($activity, true);
    }

    /**
     * Fill object fields from request payload.
     *
     * @param FvActivity $activity
     * @param array      $data
     * @param bool       $isCreate
     * @return void
     */
    private function hydrateActivity(FvActivity $activity, array $data, $isCreate)
    {
        foreach (array('ref', 'label', 'season', 'crop_name', 'cultivar_name', 'weather', 'note_public', 'note_private') as $field) {
            if (array_key_exists($field, $data)) {
                $activity->{$field} = is_scalar($data[$field]) ? (string) $data[$field] : '';
            }
        }

        if (array_key_exists('type', $data)) {
            $activity->type = FvActivity::normalizeType($data['type']);
        }
        if (array_key_exists('status', $data)) {
            $activity->status = FvActivity::normalizeStatus($data['status']);
        } elseif ($isCreate && empty($activity->status)) {
            $activity->status = FvActivity::STATUS_DRAFT;
        }
        if (array_key_exists('priority', $data)) {
            $activity->priority = FvActivity::normalizePriority($data['priority']);
        }

        foreach (array('fk_project', 'fk_task', 'fk_thirdparty', 'fk_soc', 'fk_fieldplot', 'fk_talhao') as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $target = $field;
            if ($field === 'fk_soc') {
                $target = 'fk_thirdparty';
            } elseif ($field === 'fk_talhao') {
                $target = 'fk_fieldplot';
            }
            $activity->{$target} = $this->asNullableInt($data[$field]);
        }

        foreach (array('progress', 'area_planned', 'area_done', 'area_total') as $field) {
            if (array_key_exists($field, $data)) {
                $activity->{$field} = price2num($data[$field], 'MT');
            }
        }

        foreach (array('date_planned_start', 'date_planned_end', 'date_start', 'date_end') as $field) {
            if (array_key_exists($field, $data)) {
                $activity->{$field} = $this->asTimestamp($data[$field]);
            }
        }

        $activity->fk_user_modif = (int) DolibarrApiAccess::$user->id;
    }

    /**
     * Save relation arrays and input lines.
     *
     * @param FvActivity $activity
     * @param array      $data
     * @return int
     */
    private function applyRelationsAndLines(FvActivity $activity, array $data)
    {
        if (array_key_exists('lines', $data)) {
            if (!is_array($data['lines'])) {
                $activity->error = 'Invalid lines payload.';
                return -1;
            }
            if (FvActivityLine::deleteForActivity($this->db, $activity->id) < 0) {
                $activity->error = $this->db->lasterror();
                return -1;
            }

            foreach ($data['lines'] as $position => $lineData) {
                if (!is_array($lineData)) {
                    continue;
                }
                $productId = $this->asNullableInt(isset($lineData['fk_product']) ? $lineData['fk_product'] : null);
                if (empty($productId)) {
                    continue;
                }

                $line = new FvActivityLine($this->db);
                $line->fk_activity = (int) $activity->id;
                $line->position = (int) $position + 1;
                $line->fk_product = $productId;
                $line->fk_warehouse = $this->asNullableInt(isset($lineData['fk_warehouse']) ? $lineData['fk_warehouse'] : null);
                $line->movement_type = isset($lineData['movement_type']) ? $lineData['movement_type'] : FvActivityLine::MOVEMENT_CONSUME;
                foreach (array('area_planned', 'area_done', 'dose_planned', 'dose_done', 'qty_planned', 'qty_done', 'unit_cost') as $field) {
                    if (array_key_exists($field, $lineData)) {
                        $line->{$field} = price2num($lineData[$field], 'MT');
                    }
                }
                if (array_key_exists('area_applied', $lineData)) {
                    $line->area_applied = price2num($lineData['area_applied'], 'MT');
                }
                if (array_key_exists('dose', $lineData)) {
                    $line->dose = price2num($lineData['dose'], 'MT');
                }
                if (array_key_exists('total', $lineData)) {
                    $line->total = price2num($lineData['total'], 'MT');
                }
                $line->dose_unit = isset($lineData['dose_unit']) ? (string) $lineData['dose_unit'] : '';
                $line->note = isset($lineData['note']) ? (string) $lineData['note'] : '';

                if ($line->create(DolibarrApiAccess::$user) < 0) {
                    $activity->error = $line->error ?: $line->errorsToString();
                    return -1;
                }
            }
        }

        if (array_key_exists('user_links', $data) && is_array($data['user_links'])) {
            if ($activity->setUsers($data['user_links']) < 0) {
                return -1;
            }
        } elseif (array_key_exists('user_ids', $data) && is_array($data['user_ids'])) {
            if ($activity->setUsers($data['user_ids']) < 0) {
                return -1;
            }
        }

        if (array_key_exists('vehicle_links', $data) && is_array($data['vehicle_links'])) {
            if ($activity->setVehicles($data['vehicle_links']) < 0) {
                return -1;
            }
        } elseif (array_key_exists('vehicle_ids', $data) && is_array($data['vehicle_ids'])) {
            if ($activity->setVehicles($data['vehicle_ids']) < 0) {
                return -1;
            }
        } elseif (array_key_exists('machine_ids', $data) && is_array($data['machine_ids'])) {
            if ($activity->setVehicles($data['machine_ids']) < 0) {
                return -1;
            }
        }

        if (array_key_exists('implement_links', $data) && is_array($data['implement_links'])) {
            if ($activity->setImplements($data['implement_links']) < 0) {
                return -1;
            }
        } elseif (array_key_exists('implement_ids', $data) && is_array($data['implement_ids'])) {
            if ($activity->setImplements($data['implement_ids']) < 0) {
                return -1;
            }
        }

        return 1;
    }

    /**
     * Format activity payload.
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
            'type' => FvActivity::normalizeType($activity->type),
            'status' => FvActivity::normalizeStatus($activity->status),
            'status_label' => FvActivity::getStatusLabel($activity->status),
            'priority' => FvActivity::normalizePriority($activity->priority),
            'progress' => (float) $activity->progress,
            'season' => (string) $activity->season,
            'crop_name' => (string) $activity->crop_name,
            'cultivar_name' => (string) $activity->cultivar_name,
            'fk_project' => $this->asNullableInt($activity->fk_project),
            'fk_task' => $this->asNullableInt($activity->fk_task),
            'fk_thirdparty' => $this->asNullableInt($activity->fk_thirdparty),
            'fk_fieldplot' => $this->asNullableInt($activity->fk_fieldplot),
            'area_planned' => (float) $activity->area_planned,
            'area_done' => (float) $activity->area_done,
            'area_total' => (float) $activity->area_total,
            'date_planned_start' => $activity->date_planned_start,
            'date_planned_end' => $activity->date_planned_end,
            'date_start' => $activity->date_start,
            'date_end' => $activity->date_end,
            'weather' => (string) $activity->weather,
            'note_public' => (string) $activity->note_public,
            'note_private' => (string) $activity->note_private,
            'user_ids' => $activity->fetchUsers(),
            'vehicle_ids' => $activity->fetchVehicles(),
            'machine_ids' => $activity->fetchVehicles(),
            'implement_ids' => $activity->fetchImplements(),
            'user_links' => $activity->fetchUserLinks(),
            'vehicle_links' => $activity->fetchVehicleLinks(),
            'implement_links' => $activity->fetchImplementLinks(),
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
                    'fk_warehouse' => $this->asNullableInt($line->fk_warehouse),
                    'movement_type' => FvActivityLine::normalizeMovementType($line->movement_type),
                    'area_planned' => (float) $line->area_planned,
                    'area_done' => (float) $line->area_done,
                    'dose_planned' => (float) $line->dose_planned,
                    'dose_done' => (float) $line->dose_done,
                    'dose_unit' => (string) $line->dose_unit,
                    'qty_planned' => (float) $line->qty_planned,
                    'qty_done' => (float) $line->qty_done,
                    'total' => (float) $line->total,
                    'unit_cost' => (float) $line->unit_cost,
                    'note' => (string) $line->note,
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

        return $value > 0 ? $value : null;
    }

    private function asTimestamp($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ? $timestamp : null;
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
