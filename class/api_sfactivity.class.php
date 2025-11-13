
<?php
/*
 * Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * API layer exposing the SAFRA activity domain.
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once __DIR__.'/SfActivity.class.php';
require_once __DIR__.'/SfActivityLine.class.php';

/**
 * Safra activity REST endpoints.
 *
 * @class DolibarrApiAccess {@requires user,external}
 */
class Sfactivity extends DolibarrApi
{
    /**
     * Modern to legacy aliases kept for backwards compatibility.
     *
     * @var array<string,string>
     */
    private static $aliasMap = array(
        'activity_type' => 'operation_type',
        'date_activity' => 'date_application',
        'mixture_note' => 'calda_observacao',
    );

    /**
     * @var Translate
     */
    protected $langs;

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $db, $langs;

        $this->db = $db;
        $this->langs = $langs;
        $this->langs->loadLangs(array('safra@safra'));
    }

    /**
     * List activities.
     *
     * @url     GET /sfactivities
     * @url     GET /aplicacoes
     *
     * @param   string  $sortfield      Sort field
     * @param   string  $sortorder      Sort order
     * @param   int     $limit          Page size
     * @param   int     $page           Page number (zero based)
     * @param   int     $status         Optional status filter
     * @param   int     $fk_project     Optional project filter
     * @param   int     $fk_soc         Optional third party filter
     * @param   string  $activity_type  Optional activity type (new naming)
     * @param   string  $operation_type Optional activity type (legacy naming)
     * @param   string  $sqlfilters     Additional SQL filters (expert usage)
     * @param   int     $include_lines  Include lines in the response if truthy
     * @param   string  $properties     Optional projection of fields to keep
     *
     * @return  array
     *
     * @throws RestException
     */
    public function index(
        $sortfield = 't.rowid',
        $sortorder = 'ASC',
        $limit = 100,
        $page = 0,
        $status = null,
        $fk_project = null,
        $fk_soc = null,
        $activity_type = null,
        $operation_type = null,
        $sqlfilters = '',
        $include_lines = 0,
        $properties = ''
    ) {
        $this->assertPermission('read');

        $limit = max(0, (int) $limit);
        $page = max(0, (int) $page);
        $offset = $limit > 0 ? $page * $limit : 0;

        $filters = array();
        if ($status !== null && $status !== '') {
            $filters['t.status'] = (int) $status;
        }
        if ($fk_project !== null && $fk_project !== '') {
            $filters['t.fk_project'] = (int) $fk_project;
        }
        if ($fk_soc !== null && $fk_soc !== '') {
            $filters['t.fk_soc'] = (int) $fk_soc;
        }
        $typeFilter = $activity_type !== null && $activity_type !== '' ? $activity_type : $operation_type;
        if ($typeFilter !== null && $typeFilter !== '') {
            $filters['t.operation_type'] = SfActivity::normalizeActivityType($typeFilter);
        }
        if (!empty($sqlfilters)) {
            $filters['customsql'] = $sqlfilters;
        }

        $includeLines = $this->shouldIncludeLines($include_lines);

        $placeholder = new SfActivity($this->db);
        $records = $placeholder->fetchAll($sortorder, $sortfield, $limit, $offset, $filters);
        if (!is_array($records)) {
            throw new RestException(500, $this->buildErrorMessage($placeholder));
        }

        $response = array();
        foreach ($records as $record) {
            $activity = $this->loadActivity((int) $record->id, $includeLines);
            $response[] = $this->formatActivityForResponse($activity, $includeLines, $properties);
        }

        return $response;
    }

    /**
     * Fetch an activity.
     *
     * @url     GET /sfactivities/{id}
     * @url     GET /aplicacoes/{id}
     *
     * @param   int     $id             Activity identifier
     * @param   int     $include_lines  Include lines flag
     * @param   string  $properties     Optional projection of fields to keep
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function get($id, $include_lines = 0, $properties = '')
    {
        $this->assertPermission('read');
        $includeLines = $this->shouldIncludeLines($include_lines);
        $activity = $this->loadActivity($id, $includeLines);

        return $this->formatActivityForResponse($activity, $includeLines, $properties);
    }

    /**
     * Create an activity.
     *
     * @url     POST /sfactivities
     * @url     POST /aplicacoes
     *
     * @param   array   $request_data   Request payload
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function post($request_data = null)
    {
        $this->assertPermission('write');
        $payload = $this->validatePayload($request_data);

        list($data, $lines, $includeLines, $caller) = $this->extractRequestComponents($payload);

        $activity = new SfActivity($this->db);
        $activity->context['fromapi'] = 'sfactivity';
        if ($caller !== null) {
            $activity->context['caller'] = $caller;
        }

        $this->applyRequestData($activity, $data, true);

        if ($activity->create(DolibarrApiAccess::$user) <= 0) {
            throw new RestException(500, $this->buildErrorMessage($activity));
        }

        if ($lines !== null) {
            $this->persistLines($activity, $lines);
        }

        $includeLines = $this->shouldIncludeLines($includeLines ?? ($lines !== null));

        return $this->formatActivityForResponse($this->loadActivity($activity->id, $includeLines), $includeLines);
    }

    /**
     * Update an activity.
     *
     * @url     PUT /sfactivities/{id}
     * @url     PUT /aplicacoes/{id}
     *
     * @param   int     $id             Activity identifier
     * @param   array   $request_data   Update payload
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function put($id, $request_data = null)
    {
        $this->assertPermission('write');
        $payload = $this->validatePayload($request_data);

        $activity = $this->loadActivity($id, true);
        $activity->context['fromapi'] = 'sfactivity';

        list($data, $lines, $includeLines, $caller) = $this->extractRequestComponents($payload);
        if ($caller !== null) {
            $activity->context['caller'] = $caller;
        }

        $this->applyRequestData($activity, $data, false);

        if ($activity->update(DolibarrApiAccess::$user) < 0) {
            throw new RestException(500, $this->buildErrorMessage($activity));
        }

        if ($lines !== null) {
            $this->persistLines($activity, $lines);
        }

        $includeLines = $this->shouldIncludeLines($includeLines ?? ($lines !== null));

        return $this->formatActivityForResponse($this->loadActivity($activity->id, $includeLines), $includeLines);
    }

    /**
     * Delete an activity.
     *
     * @url     DELETE /sfactivities/{id}
     * @url     DELETE /aplicacoes/{id}
     *
     * @param   int $id Activity identifier
     *
     * @return  array
     *
     * @throws RestException
     */
    public function delete($id)
    {
        $this->assertPermission('delete');
        $activity = $this->loadActivity($id, false);
        $activity->context['fromapi'] = 'sfactivity';

        if ($activity->delete(DolibarrApiAccess::$user) < 0) {
            throw new RestException(500, $this->buildErrorMessage($activity));
        }

        return array('success' => true);
    }

    /**
     * Validate an activity.
     *
     * @url     POST /sfactivities/{id}/validate
     * @url     POST /aplicacoes/{id}/validate
     *
     * @param   int $id Activity identifier
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function postValidate($id)
    {
        return $this->runTransition($id, 'validate', function (SfActivity $activity) {
            return $activity->validate(DolibarrApiAccess::$user);
        });
    }

    /**
     * Start an activity (mark as in progress).
     *
     * @url     POST /sfactivities/{id}/start
     * @url     POST /aplicacoes/{id}/start
     *
     * @param   int $id Activity identifier
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function postStart($id)
    {
        return $this->runTransition($id, 'start', function (SfActivity $activity) {
            return $activity->markAsInProgress(DolibarrApiAccess::$user);
        });
    }

    /**
     * Close an activity (mark as completed).
     *
     * @url     POST /sfactivities/{id}/close
     * @url     POST /aplicacoes/{id}/close
     *
     * @param   int $id Activity identifier
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function postClose($id)
    {
        return $this->runTransition($id, 'close', function (SfActivity $activity) {
            return $activity->markAsCompleted(DolibarrApiAccess::$user);
        });
    }

    /**
     * Cancel an activity.
     *
     * @url     POST /sfactivities/{id}/cancel
     * @url     POST /aplicacoes/{id}/cancel
     *
     * @param   int $id Activity identifier
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function postCancel($id)
    {
        return $this->runTransition($id, 'close', function (SfActivity $activity) {
            return $activity->cancel(DolibarrApiAccess::$user);
        });
    }

    /**
     * Reopen a cancelled activity back to validated status.
     *
     * @url     POST /sfactivities/{id}/reopen
     * @url     POST /aplicacoes/{id}/reopen
     *
     * @param   int $id Activity identifier
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function postReopen($id)
    {
        return $this->runTransition($id, 'validate', function (SfActivity $activity) {
            return $activity->reopen(DolibarrApiAccess::$user);
        });
    }

    /**
     * Set an activity back to draft.
     *
     * @url     POST /sfactivities/{id}/draft
     * @url     POST /aplicacoes/{id}/draft
     *
     * @param   int $id Activity identifier
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    public function postDraft($id)
    {
        return $this->runTransition($id, 'validate', function (SfActivity $activity) {
            return $activity->setDraft(DolibarrApiAccess::$user);
        });
    }

    /**
     * Run a status transition ensuring permissions and response formatting.
     *
     * @param   int      $id          Activity identifier
     * @param   string   $permission  Permission keyword
     * @param   callable $callback    Transition callback
     *
     * @return  stdClass
     *
     * @throws RestException
     */
    protected function runTransition($id, $permission, callable $callback)
    {
        $this->assertPermission($permission);
        $includeLines = $this->shouldIncludeLines();
        $activity = $this->loadActivity($id, $includeLines);
        $activity->context['fromapi'] = 'sfactivity';

        $result = call_user_func($callback, $activity);
        if ($result < 0) {
            throw new RestException(409, $this->buildErrorMessage($activity));
        }

        return $this->formatActivityForResponse($this->loadActivity($activity->id, $includeLines), $includeLines);
    }

    /**
     * Ensure the current user owns the given permission.
     *
     * @param   string $action Permission keyword
     *
     * @return  void
     *
     * @throws RestException
     */
    protected function assertPermission($action)
    {
        if ($this->hasPermission($action)) {
            return;
        }

        throw new RestException(403, $this->langs->trans('ErrorSafraActivityNoRights'));
    }

    /**
     * Check if the user can perform the given action.
     *
     * @param   string $action
     *
     * @return  bool
     */
    protected function hasPermission($action)
    {
        $rights = isset(DolibarrApiAccess::$user->rights->safra) ? DolibarrApiAccess::$user->rights->safra : null;
        if (!$rights) {
            return false;
        }

        switch ($action) {
            case 'read':
                return !empty($rights->aplicacao->read);
            case 'write':
                return !empty($rights->aplicacao->write);
            case 'delete':
                return !empty($rights->aplicacao->delete);
            case 'validate':
                if (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && !empty($rights->safra_advance->validate)) {
                    return true;
                }

                return !empty($rights->aplicacao->write);
            case 'start':
                if (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && !empty($rights->safra_advance->start)) {
                    return true;
                }

                return !empty($rights->aplicacao->write);
            case 'close':
                if (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && !empty($rights->safra_advance->close)) {
                    return true;
                }

                return !empty($rights->aplicacao->write);
        }

        return false;
    }

    /**
     * Validate and normalise the payload structure.
     *
     * @param   mixed $payload
     *
     * @return  array
     *
     * @throws RestException
     */
    protected function validatePayload($payload)
    {
        if ($payload === null) {
            return array();
        }

        if (!is_array($payload)) {
            throw new RestException(400, $this->langs->trans('ErrorSafraActivityInvalidPayload'));
        }

        return $payload;
    }

    /**
     * Extract the known request components.
     *
     * @param   array $payload
     *
     * @return  array{array,array|null,bool|null,string|null}
     *
     * @throws RestException
     */
    protected function extractRequestComponents(array $payload)
    {
        $data = $payload;
        $lines = null;
        $includeLines = null;
        $caller = null;

        if (array_key_exists('lines', $data)) {
            $lines = $data['lines'];
            unset($data['lines']);

            if ($lines !== null && !is_array($lines)) {
                throw new RestException(400, $this->langs->trans('ErrorSafraActivityInvalidLines'));
            }
        }

        if (array_key_exists('include_lines', $data)) {
            $includeLines = (bool) $data['include_lines'];
            unset($data['include_lines']);
        }

        if (array_key_exists('caller', $data)) {
            $caller = sanitizeVal($data['caller'], 'aZ09');
            unset($data['caller']);
        }

        return array($data, $lines, $includeLines, $caller);
    }

    /**
     * Apply request payload to the activity.
     *
     * @param   SfActivity $activity
     * @param   array      $data
     * @param   bool       $isCreate
     *
     * @return  void
     *
     * @throws RestException
     */
    protected function applyRequestData(SfActivity $activity, array $data, $isCreate)
    {
        $writable = $this->getWritableFields($activity);

        foreach ($data as $field => $value) {
            if ($field === 'status' || $field === 'rowid' || $field === 'id') {
                continue;
            }

            if ($field === 'array_options' && is_array($value)) {
                $activity->array_options = array();
                foreach ($value as $key => $optionValue) {
                    $activity->array_options[$key] = $optionValue;
                }
                continue;
            }

            if ($field === 'activity_type' || $field === 'operation_type') {
                $normalized = SfActivity::normalizeActivityType($value);
                $activity->activity_type = $normalized;
                $activity->operation_type = $normalized;
                continue;
            }

            if ($field === 'date_activity' || $field === 'date_application') {
                $timestamp = $this->parseDateValue($value, $field);
                $activity->date_activity = $timestamp;
                $activity->date_application = $timestamp;
                continue;
            }

            if ($field === 'mixture_note' || $field === 'calda_observacao') {
                $text = is_scalar($value) ? (string) $value : '';
                $activity->mixture_note = $text;
                $activity->calda_observacao = $text;
                continue;
            }

            if (!in_array($field, $writable, true)) {
                throw new RestException(400, sprintf($this->langs->trans('ErrorSafraActivityUnknownField'), $field));
            }

            $activity->$field = $this->_checkValForAPI($field, $value, $activity);
        }

        if ($isCreate && empty($activity->operation_type)) {
            $activity->operation_type = SfActivity::OPERATION_APLICACAO;
        }
    }

    /**
     * Persist lines payload.
     *
     * @param   SfActivity   $activity
     * @param   array|null   $lines
     *
     * @return  void
     *
     * @throws RestException
     */
    protected function persistLines(SfActivity $activity, $lines)
    {
        if ($lines === null) {
            return;
        }

        $prepared = array();
        foreach ($lines as $line) {
            if ($line instanceof SfActivityLine) {
                $prepared[] = $line;
                continue;
            }

            if (is_object($line)) {
                $line = (array) $line;
            }

            if (is_array($line)) {
                $prepared[] = SfActivityLine::fromModernArray($this->db, $line);
                continue;
            }

            throw new RestException(400, $this->langs->trans('ErrorSafraActivityInvalidLines'));
        }

        $result = $activity->replaceLines($prepared, DolibarrApiAccess::$user);
        if ($result < 0) {
            throw new RestException(500, $this->buildErrorMessage($activity));
        }
    }

    /**
     * Load an activity and ensure it exists.
     *
     * @param   int   $id
     * @param   bool  $withLines
     *
     * @return  SfActivity
     *
     * @throws RestException
     */
    protected function loadActivity($id, $withLines)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new RestException(400, $this->langs->trans('ErrorSafraActivityInvalidIdentifier'));
        }

        $activity = new SfActivity($this->db);
        $result = $activity->fetch($id, '', 0, $withLines ? 0 : 1);
        if ($result <= 0) {
            throw new RestException(404, $this->langs->trans('ErrorSafraActivityNotFound', $id));
        }

        if (!$withLines) {
            unset($activity->lines);
        }

        return $activity;
    }

    /**
     * Prepare activity for output.
     *
     * @param   SfActivity $activity
     * @param   bool       $includeLines
     * @param   string     $properties
     *
     * @return  stdClass
     */
    protected function formatActivityForResponse(SfActivity $activity, $includeLines, $properties = '')
    {
        $export = clone $activity;
        if (!$includeLines) {
            unset($export->lines);
        }

        $clean = $this->_cleanObjectDatas($export);

        if ($includeLines && !empty($activity->lines) && is_array($activity->lines)) {
            $clean->lines = array();
            foreach ($activity->lines as $line) {
                $clean->lines[] = $this->formatLineForResponse($line);
            }
        }

        $this->applyAliasFields($clean);

        if (!empty($properties)) {
            $clean = $this->_filterObjectProperties($clean, $properties);
        }

        return $clean;
    }

    /**
     * Prepare line for output.
     *
     * @param   mixed $line
     *
     * @return  stdClass
     */
    protected function formatLineForResponse($line)
    {
        if ($line instanceof SfActivityLine) {
            $lineExport = clone $line;
        } elseif (is_object($line)) {
            $lineExport = clone $line;
        } else {
            $lineExport = (object) $line;
        }

        $clean = $this->_cleanObjectDatas($lineExport);

        if (!isset($clean->fk_activity) && isset($clean->fk_aplicacao)) {
            $clean->fk_activity = $clean->fk_aplicacao;
        }
        if (!isset($clean->fk_formulated_product) && isset($clean->fk_produto_formulado)) {
            $clean->fk_formulated_product = $clean->fk_produto_formulado;
        }
        if (!isset($clean->fk_technical_product) && isset($clean->fk_produtotecnico)) {
            $clean->fk_technical_product = $clean->fk_produtotecnico;
        }
        if (!isset($clean->fk_warehouse) && isset($clean->fk_entrepot)) {
            $clean->fk_warehouse = $clean->fk_entrepot;
        }

        return $clean;
    }

    /**
     * Add alias fields for backwards compatibility.
     *
     * @param   stdClass $object
     *
     * @return  void
     */
    protected function applyAliasFields($object)
    {
        foreach (self::$aliasMap as $modern => $legacy) {
            if (isset($object->$modern)) {
                $object->$legacy = $object->$modern;
                continue;
            }

            if (isset($object->$legacy)) {
                $object->$modern = $object->$legacy;
            }
        }

        if (isset($object->date_activity)) {
            $object->date_activity = (int) $object->date_activity;
        }
        if (isset($object->date_application)) {
            $object->date_application = (int) $object->date_application;
        } elseif (isset($object->date_activity)) {
            $object->date_application = $object->date_activity;
        }
    }

    /**
     * Resolve writable fields using the Dolibarr definition.
     *
     * @param   SfActivity $activity
     *
     * @return  array
     */
    protected function getWritableFields(SfActivity $activity)
    {
        $forbidden = array('rowid', 'id', 'status', 'date_creation', 'tms', 'fk_user_creat', 'fk_user_modif', 'import_key', 'model_pdf', 'last_main_doc');
        $fields = array();

        if (!is_array($activity->fields)) {
            return $fields;
        }

        foreach ($activity->fields as $field => $definition) {
            if (in_array($field, $forbidden, true)) {
                continue;
            }

            $fields[] = $field;
        }

        foreach (self::$aliasMap as $modern => $legacy) {
            if (!in_array($modern, $fields, true)) {
                $fields[] = $modern;
            }
            if (!in_array($legacy, $fields, true)) {
                $fields[] = $legacy;
            }
        }

        $fields[] = 'array_options';

        return $fields;
    }

    /**
     * Parse incoming date values.
     *
     * @param   mixed  $value
     * @param   string $field
     *
     * @return  int
     *
     * @throws RestException
     */
    protected function parseDateValue($value, $field)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $timestamp = dol_stringtotime($value);
        if ($timestamp === '' || $timestamp === false) {
            throw new RestException(400, $this->langs->trans('ErrorSafraActivityInvalidDate', $field));
        }

        return (int) $timestamp;
    }

    /**
     * Normalise include lines value.
     *
     * @param   mixed $value
     *
     * @return  bool
     */
    protected function shouldIncludeLines($value = null)
    {
        if ($value === null) {
            if (isset($_REQUEST['include_lines'])) {
                return (bool) $_REQUEST['include_lines'];
            }
            return false;
        }

        return (bool) $value;
    }

    /**
     * Aggregate Dolibarr error messages.
     *
     * @param   CommonObject $object
     *
     * @return  string
     */
    protected function buildErrorMessage($object)
    {
        if (!empty($object->errors) && is_array($object->errors)) {
            return implode("\n", array_filter($object->errors));
        }

        if (!empty($object->error)) {
            return $object->error;
        }

        return 'Unknown error';
    }
}
