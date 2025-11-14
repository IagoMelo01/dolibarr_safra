<?php
require_once DOL_DOCUMENT_ROOT . '/core/lib/rest.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/dolibarrapi.class.php';
require_once __DIR__ . '/safraactivity.class.php';

class SafraActivitiesApi extends DolibarrApi
{
    public function __construct($db)
    {
        parent::__construct($db);
        global $langs;
        if (is_object($langs)) {
            $langs->load('safra@safra');
        }
    }

    /**
     * List activities.
     *
     * @url GET /sfactivities
     */
    public function getList($sortfield = 't.rowid', $sortorder = 'ASC', $limit = 0, $page = 0)
    {
        $activities = array();
        $sql = 'SELECT t.rowid FROM ' . MAIN_DB_PREFIX . 'safra_activity as t';
        $sql .= ' ORDER BY ' . $this->db->escape($sortfield) . ' ' . $this->db->escape($sortorder);
        if ($limit > 0) {
            $offset = max(0, (int) $page) * (int) $limit;
            $sql .= ' ' . $this->db->plimit((int) $limit, $offset);
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RestException(500, $this->db->lasterror());
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $activity = new SafraActivity($this->db);
            if ($activity->fetch($obj->rowid) > 0) {
                $activities[] = $this->formatForResponse($activity, false);
            }
        }
        $this->db->free($resql);

        return $activities;
    }

    /**
     * Retrieve activity by id.
     *
     * @url GET /sfactivities/{id}
     */
    public function get($id, $include_lines = 0)
    {
        $activity = $this->loadActivity($id, (bool) $include_lines);

        return $this->formatForResponse($activity, (bool) $include_lines);
    }

    /**
     * Create activity.
     *
     * @url POST /sfactivities
     */
    public function post($request_data)
    {
        global $user;

        if (!is_array($request_data)) {
            throw new RestException(400, 'ErrorSafraActivityInvalidPayload');
        }

        $activity = new SafraActivity($this->db);
        $this->populateActivity($activity, $request_data);

        if ($activity->create($user) <= 0) {
            throw new RestException(500, $activity->error);
        }

        if (!empty($request_data['lines']) && is_array($request_data['lines'])) {
            $activity->addInputLines($user, $request_data['lines'], true);
        }

        $activity->fetch($activity->id);
        $activity->fetchLines();

        return $this->formatForResponse($activity, true);
    }

    /**
     * Update activity.
     *
     * @url PUT /sfactivities/{id}
     */
    public function put($id, $request_data)
    {
        global $user;

        $activity = $this->loadActivity($id, false);
        $this->populateActivity($activity, $request_data);

        if ($activity->update($activity->id, $user) <= 0) {
            throw new RestException(500, $activity->error);
        }

        if (isset($request_data['lines']) && is_array($request_data['lines'])) {
            $activity->addInputLines($user, $request_data['lines'], true);
        }

        $activity->fetch($activity->id);
        $activity->fetchLines();

        return $this->formatForResponse($activity, true);
    }

    /**
     * Delete activity.
     *
     * @url DELETE /sfactivities/{id}
     */
    public function delete($id)
    {
        global $user;

        $activity = $this->loadActivity($id, false);
        if ($activity->delete($user) <= 0) {
            throw new RestException(500, $activity->error);
        }

        return array('success' => true);
    }

    /**
     * Change activity status.
     *
     * @url POST /sfactivities/{id}/status
     */
    public function postStatus($id, $request_data)
    {
        global $user;

        if (!is_array($request_data) || empty($request_data['action'])) {
            throw new RestException(400, 'ErrorSafraActivityInvalidPayload');
        }

        $activity = $this->loadActivity($id, true);
        $action = strtolower($request_data['action']);
        $result = 0;

        switch ($action) {
            case 'validate':
                $result = $activity->validate($user);
                break;
            case 'start':
                $result = $activity->start($user);
                break;
            case 'complete':
                $result = $activity->complete($user);
                break;
            case 'cancel':
                $result = $activity->cancel($user);
                break;
            case 'reopen':
                $result = $activity->reopen($user);
                break;
            default:
                throw new RestException(400, 'ErrorSafraActivityUnknownField');
        }

        if ($result < 0) {
            throw new RestException(500, $activity->error);
        }

        $activity->fetch($activity->id);
        $activity->fetchLines();

        return $this->formatForResponse($activity, true);
    }

    protected function loadActivity($id, $includeLines = false)
    {
        $activity = new SafraActivity($this->db);
        if ($activity->fetch((int) $id) <= 0) {
            throw new RestException(404, 'ErrorSafraActivityNotFound');
        }
        if ($includeLines) {
            $activity->fetchLines();
        }

        return $activity;
    }

    protected function populateActivity(SafraActivity $activity, array $data)
    {
        if (isset($data['ref'])) {
            $activity->ref = trim((string) $data['ref']);
        }
        if (isset($data['label'])) {
            $activity->label = trim((string) $data['label']);
        }
        if (isset($data['fk_project'])) {
            $activity->fk_project = (int) $data['fk_project'];
        }
        if (isset($data['fk_talhao'])) {
            $activity->fk_talhao = (int) $data['fk_talhao'];
        }
        if (isset($data['activity_type'])) {
            $activity->activity_type = trim((string) $data['activity_type']);
        }
        if (isset($data['date_planned_start'])) {
            $activity->date_planned_start = $this->parseDate($data['date_planned_start']);
        }
        if (isset($data['date_planned_end'])) {
            $activity->date_planned_end = $this->parseDate($data['date_planned_end']);
        }
        if (isset($data['date_real_start'])) {
            $activity->date_real_start = $this->parseDate($data['date_real_start']);
        }
        if (isset($data['date_real_end'])) {
            $activity->date_real_end = $this->parseDate($data['date_real_end']);
        }
        if (isset($data['note_public'])) {
            $activity->note_public = (string) $data['note_public'];
        }
        if (isset($data['note_private'])) {
            $activity->note_private = (string) $data['note_private'];
        }
    }

    protected function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RestException(400, 'ErrorSafraActivityInvalidDate');
        }

        return $timestamp;
    }

    protected function formatForResponse(SafraActivity $activity, $includeLines)
    {
        $response = array(
            'id' => (int) $activity->id,
            'ref' => $activity->ref,
            'label' => $activity->label,
            'activity_type' => $activity->activity_type,
            'fk_project' => (int) $activity->fk_project,
            'fk_talhao' => (int) $activity->fk_talhao,
            'date_planned_start' => $activity->date_planned_start,
            'date_planned_end' => $activity->date_planned_end,
            'date_real_start' => $activity->date_real_start,
            'date_real_end' => $activity->date_real_end,
            'status' => (int) $activity->status,
            'planned_cost' => (float) $activity->planned_cost,
            'actual_cost' => (float) $activity->actual_cost,
            'note_public' => $activity->note_public,
            'note_private' => $activity->note_private,
        );

        if ($includeLines && is_array($activity->lines)) {
            $response['lines'] = array();
            foreach ($activity->lines as $line) {
                $response['lines'][] = array(
                    'id' => isset($line->id) ? (int) $line->id : null,
                    'fk_product' => (int) $line->fk_product,
                    'label' => $line->label,
                    'qty' => (float) $line->qty,
                    'fk_warehouse' => (int) $line->fk_warehouse,
                    'movement_type' => $line->movement_type,
                );
            }
        }

        return $response;
    }
}
