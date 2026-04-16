<?php
/*
 * Safra module upgrade orchestrator.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}

$mainInc = __DIR__ . '/../../main.inc.php';
if (!file_exists($mainInc)) {
    $mainInc = __DIR__ . '/../main.inc.php';
}
require_once $mainInc;

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

if (!$user->admin) {
    accessforbidden();
}

$langs->loadLangs(array('admin', 'safra@safra'));

$action = GETPOST('action', 'aZ09');
$token = GETPOST('token', 'alphanohtml');
$messages = array();
$errors = array();

$targetVersion = '1.2.0';
$currentVersion = '1.0.0';
if (!empty($conf->global->SAFRA_VERSION)) {
    $currentVersion = $conf->global->SAFRA_VERSION;
} else {
    $currentVersionConst = dolibarr_get_const($db, 'SAFRA_VERSION', $conf->entity);
    if (is_array($currentVersionConst) && isset($currentVersionConst['value'])) {
        $currentVersion = $currentVersionConst['value'];
    }
}

$upgradeSteps = array(
    array(
        'title' => 'Safra Activity canonical schema',
        'description' => 'Ensures canonical safra_activity* tables exist before migration.',
        'sql' => array(
            '/safra/sql/migrations/20260409_migrate_aplicacao_to_activity.sql',
        ),
        'checks' => array(
            'Confirm `SELECT COUNT(*) FROM llx_safra_activity` returns records after migration.',
            'Confirm `SELECT COUNT(*) FROM llx_safra_activity_line` returns records after migration.',
            'Confirm no query relies on `llx_safra_aplicacao*` anymore.',
        ),
        'rollback' => array(
            'Restore the database backup taken before this upgrade.',
            'Revert SAFRA_VERSION to its previous value if needed.',
        ),
    ),
);

if ($action === 'run') {
    if (function_exists('checkToken') && !checkToken($token)) {
        accessforbidden('Invalid security token.');
    }

    foreach ($upgradeSteps as $step) {
        foreach ($step['sql'] as $relativePath) {
            $execution = safraExecuteSqlFile($db, $relativePath);
            if ($execution['status'] === 'error') {
                $errors[] = $execution['message'];
                break 2;
            }
            $messages[] = $execution['message'];
        }
    }

    if (empty($errors)) {
        $migrationOk = safraMigrateLegacyAplicacao($db, $messages, $errors);
        if (!$migrationOk) {
            $errors[] = 'Legacy migration failed. No destructive cleanup was applied.';
        }
    }

    if (empty($errors)) {
        $res = dolibarr_set_const($db, 'SAFRA_VERSION', $targetVersion, 'chaine', 0, '', $conf->entity);
        if ($res <= 0) {
            $errors[] = 'Unable to persist SAFRA_VERSION constant: ' . $db->lasterror();
        } else {
            $messages[] = 'Safra module metadata updated to version ' . $targetVersion . '.';
        }
    }
}

llxHeader('', 'Safra upgrade');

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre('Safra upgrade assistant', $linkback, 'safra@safra');

if (!empty($messages)) {
    foreach ($messages as $msg) {
        print dol_htmloutput_mesg($msg);
    }
}
if (!empty($errors)) {
    foreach ($errors as $err) {
        print dol_htmloutput_mesg($err, '', 'error');
    }
}

print '<div class="fiche">';
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<h3>Upgrade overview</h3>';
print '<p>This assistant migrates legacy `safra_aplicacao*` data into canonical `safra_activity*` tables and removes the legacy model.</p>';
print '<p><strong>Current version:</strong> ' . dol_escape_htmltag($currentVersion) . '<br />';
print '<strong>Target version:</strong> ' . dol_escape_htmltag($targetVersion) . '</p>';

print '<h3>Pre-deployment checklist</h3>';
print '<ol>';
print '<li>Place Dolibarr in maintenance mode and notify users about a brief downtime.</li>';
print '<li>Take a full database backup (schema and data).</li>';
print '<li>Pause background jobs during migration.</li>';
print '</ol>';

foreach ($upgradeSteps as $index => $step) {
    print '<h3>' . dol_escape_htmltag(($index + 1) . '. ' . $step['title']) . '</h3>';
    print '<p>' . dol_escape_htmltag($step['description']) . '</p>';
    print '<p><strong>SQL scripts</strong></p>';
    print '<ul>';
    foreach ($step['sql'] as $sql) {
        print '<li><code>' . dol_escape_htmltag($sql) . '</code></li>';
    }
    print '</ul>';
    if (!empty($step['checks'])) {
        print '<p><strong>Post-upgrade validation</strong></p>';
        print '<ul>';
        foreach ($step['checks'] as $check) {
            print '<li>' . dol_escape_htmltag($check) . '</li>';
        }
        print '</ul>';
    }
}

print '<h3>Post-deployment actions</h3>';
print '<ol>';
print '<li>Flush Dolibarr caches and restart queue workers or cron jobs.</li>';
print '<li>Run smoke tests in UI: list, card, save, start, complete, cancel, delete.</li>';
print '<li>Run API smoke tests: <code>GET /api/index.php/sfactivities?limit=5</code>.</li>';
print '</ol>';

print '</div>';
print '<div class="fichehalfright">';
print '<h3>Execute upgrade</h3>';
print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="action" value="run" />';
if (function_exists('newToken')) {
    print '<input type="hidden" name="token" value="' . newToken() . '" />';
}
print '<div class="center">';
print '<input class="button" type="submit" value="Run migration" />';
print '</div>';
print '</form>';
print '</div>';
print '</div>';
print '</div>';

llxFooter();
$db->close();

/**
 * Execute SQL file content.
 *
 * @param DoliDB $db
 * @param string $relativePath
 * @return array{status:string,message:string}
 */
function safraExecuteSqlFile($db, $relativePath)
{
    $absolutePath = dol_buildpath($relativePath, 0);
    if (!is_readable($absolutePath)) {
        return array('status' => 'error', 'message' => 'SQL file not found: ' . $relativePath);
    }

    $sqlContent = file_get_contents($absolutePath);
    if ($sqlContent === false) {
        return array('status' => 'error', 'message' => 'Unable to read SQL file: ' . $relativePath);
    }

    $sqlContent = str_replace('__MAIN_DB_PREFIX__', MAIN_DB_PREFIX, $sqlContent);
    $statements = safraSplitSqlStatements($sqlContent);

    $executed = 0;
    foreach ($statements as $statement) {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            continue;
        }
        $res = $db->query($trimmed);
        if (!$res) {
            return array(
                'status' => 'error',
                'message' => 'SQL error while executing `' . $trimmed . '` : ' . $db->lasterror(),
            );
        }
        $executed++;
    }

    return array('status' => 'success', 'message' => $executed . ' statements executed from ' . basename($relativePath));
}

/**
 * Migrate legacy safra_aplicacao data into canonical activity tables.
 *
 * @param DoliDB $db
 * @param array  $messages
 * @param array  $errors
 * @return bool
 */
function safraMigrateLegacyAplicacao($db, &$messages, &$errors)
{
    $legacyHeader = MAIN_DB_PREFIX . 'safra_aplicacao';
    $legacyLine = MAIN_DB_PREFIX . 'safra_aplicacao_line';

    if (!safraTableExists($db, $legacyHeader)) {
        $messages[] = 'No legacy table `' . $legacyHeader . '` found. Skipping legacy data migration.';
        return true;
    }

    $headerColumns = safraGetTableColumns($db, $legacyHeader);
    $lineColumns = safraTableExists($db, $legacyLine) ? safraGetTableColumns($db, $legacyLine) : array();

    $db->begin();

    $entityCol = safraPickColumn($headerColumns, array('entity'));
    $refCol = safraPickColumn($headerColumns, array('ref'));
    $labelCol = safraPickColumn($headerColumns, array('label'));
    $projectCol = safraPickColumn($headerColumns, array('fk_project'));
    $taskCol = safraPickColumn($headerColumns, array('fk_task'));
    $thirdpartyCol = safraPickColumn($headerColumns, array('fk_thirdparty', 'fk_soc'));
    $fieldplotCol = safraPickColumn($headerColumns, array('fk_fieldplot', 'fk_talhao'));
    $areaCol = safraPickColumn($headerColumns, array('area_total', 'area_ha', 'qty'));
    $typeCol = safraPickColumn($headerColumns, array('type', 'activity_type', 'operation_type'));
    $statusCol = safraPickColumn($headerColumns, array('status', 'fk_statut'));
    $notePublicCol = safraPickColumn($headerColumns, array('note_public'));
    $notePrivateCol = safraPickColumn($headerColumns, array('note_private', 'description'));
    $dateCreationCol = safraPickColumn($headerColumns, array('date_creation', 'datec'));
    $userCreatCol = safraPickColumn($headerColumns, array('fk_user_creat', 'fk_user_author'));
    $userModifCol = safraPickColumn($headerColumns, array('fk_user_modif'));

    $sqlHeader = 'SELECT rowid'
        . ($entityCol ? ', ' . $entityCol . ' AS legacy_entity' : '')
        . ($refCol ? ', ' . $refCol . ' AS legacy_ref' : '')
        . ($labelCol ? ', ' . $labelCol . ' AS legacy_label' : '')
        . ($projectCol ? ', ' . $projectCol . ' AS legacy_fk_project' : '')
        . ($taskCol ? ', ' . $taskCol . ' AS legacy_fk_task' : '')
        . ($thirdpartyCol ? ', ' . $thirdpartyCol . ' AS legacy_fk_thirdparty' : '')
        . ($fieldplotCol ? ', ' . $fieldplotCol . ' AS legacy_fk_fieldplot' : '')
        . ($areaCol ? ', ' . $areaCol . ' AS legacy_area_total' : '')
        . ($typeCol ? ', ' . $typeCol . ' AS legacy_type' : '')
        . ($statusCol ? ', ' . $statusCol . ' AS legacy_status' : '')
        . ($notePublicCol ? ', ' . $notePublicCol . ' AS legacy_note_public' : '')
        . ($notePrivateCol ? ', ' . $notePrivateCol . ' AS legacy_note_private' : '')
        . ($dateCreationCol ? ', ' . $dateCreationCol . ' AS legacy_date_creation' : '')
        . ($userCreatCol ? ', ' . $userCreatCol . ' AS legacy_fk_user_creat' : '')
        . ($userModifCol ? ', ' . $userModifCol . ' AS legacy_fk_user_modif' : '')
        . ' FROM ' . $legacyHeader;

    $resHeader = $db->query($sqlHeader);
    if (!$resHeader) {
        $db->rollback();
        $errors[] = 'Unable to read legacy activity headers: ' . $db->lasterror();
        return false;
    }

    $migratedHeaders = 0;
    while ($row = $db->fetch_object($resHeader)) {
        $activityId = (int) $row->rowid;
        $entity = isset($row->legacy_entity) ? (int) $row->legacy_entity : (int) $GLOBALS['conf']->entity;
        if ($entity <= 0) {
            $entity = (int) $GLOBALS['conf']->entity;
        }

        $ref = isset($row->legacy_ref) ? trim((string) $row->legacy_ref) : '';
        if ($ref === '') {
            $ref = 'ACT-' . $activityId;
        }

        $label = isset($row->legacy_label) ? trim((string) $row->legacy_label) : '';
        if ($label === '') {
            $label = $ref;
        }

        $type = safraNormalizeLegacyType(isset($row->legacy_type) ? $row->legacy_type : null);
        $status = safraNormalizeLegacyStatus(isset($row->legacy_status) ? $row->legacy_status : null);
        $dateCreation = !empty($row->legacy_date_creation) ? $row->legacy_date_creation : dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');

        $sqlInsert = 'INSERT INTO ' . MAIN_DB_PREFIX . 'safra_activity ('
            . 'rowid, entity, ref, label, fk_project, fk_task, fk_thirdparty, fk_fieldplot, area_total, type, status, note_public, note_private, date_creation, fk_user_creat, fk_user_modif'
            . ') VALUES ('
            . $activityId . ', '
            . $entity . ', '
            . safraSqlString($db, $ref) . ', '
            . safraSqlString($db, $label) . ', '
            . safraSqlNullableInt(isset($row->legacy_fk_project) ? $row->legacy_fk_project : null) . ', '
            . safraSqlNullableInt(isset($row->legacy_fk_task) ? $row->legacy_fk_task : null) . ', '
            . safraSqlNullableInt(isset($row->legacy_fk_thirdparty) ? $row->legacy_fk_thirdparty : null) . ', '
            . safraSqlNullableInt(isset($row->legacy_fk_fieldplot) ? $row->legacy_fk_fieldplot : null) . ', '
            . safraSqlNumeric(isset($row->legacy_area_total) ? $row->legacy_area_total : 0) . ', '
            . safraSqlString($db, $type) . ', '
            . ((int) $status) . ', '
            . safraSqlNullableString($db, isset($row->legacy_note_public) ? $row->legacy_note_public : null) . ', '
            . safraSqlNullableString($db, isset($row->legacy_note_private) ? $row->legacy_note_private : null) . ', '
            . safraSqlString($db, $dateCreation) . ', '
            . ((int) (isset($row->legacy_fk_user_creat) && (int) $row->legacy_fk_user_creat > 0 ? $row->legacy_fk_user_creat : 1)) . ', '
            . safraSqlNullableInt(isset($row->legacy_fk_user_modif) ? $row->legacy_fk_user_modif : null)
            . ') ON DUPLICATE KEY UPDATE '
            . 'entity = VALUES(entity), ref = VALUES(ref), label = VALUES(label), fk_project = VALUES(fk_project), fk_task = VALUES(fk_task), '
            . 'fk_thirdparty = VALUES(fk_thirdparty), fk_fieldplot = VALUES(fk_fieldplot), area_total = VALUES(area_total), '
            . 'type = VALUES(type), status = VALUES(status), note_public = VALUES(note_public), note_private = VALUES(note_private), '
            . 'fk_user_modif = VALUES(fk_user_modif)';

        if (!$db->query($sqlInsert)) {
            $db->rollback();
            $errors[] = 'Failed to migrate legacy activity row #' . $activityId . ': ' . $db->lasterror();
            return false;
        }

        $migratedHeaders++;
    }

    $migratedLines = 0;
    $lineMigrationExecuted = false;
    if (!empty($lineColumns)) {
        $lineEntityCol = safraPickColumn($lineColumns, array('entity'));
        $lineActivityCol = safraPickColumn($lineColumns, array('fk_activity', 'fk_aplicacao'));
        $lineProductCol = safraPickColumn($lineColumns, array('fk_product'));
        $lineAreaCol = safraPickColumn($lineColumns, array('area_applied', 'area_ha'));
        $lineDoseCol = safraPickColumn($lineColumns, array('dose'));
        $lineDoseUnitCol = safraPickColumn($lineColumns, array('dose_unit'));
        $lineTotalCol = safraPickColumn($lineColumns, array('total', 'total_qty', 'qty'));
        $lineMovementTypeCol = safraPickColumn($lineColumns, array('movement_type'));
        $lineWarehouseCol = safraPickColumn($lineColumns, array('fk_warehouse', 'fk_entrepot'));
        $lineDateCreationCol = safraPickColumn($lineColumns, array('date_creation', 'datec'));
        $lineUserCreatCol = safraPickColumn($lineColumns, array('fk_user_creat', 'fk_user_author'));
        $lineUserModifCol = safraPickColumn($lineColumns, array('fk_user_modif'));

        if ($lineActivityCol) {
            $lineMigrationExecuted = true;
            $sqlLine = 'SELECT rowid'
                . ($lineEntityCol ? ', ' . $lineEntityCol . ' AS legacy_entity' : '')
                . ', ' . $lineActivityCol . ' AS legacy_fk_activity'
                . ($lineProductCol ? ', ' . $lineProductCol . ' AS legacy_fk_product' : '')
                . ($lineAreaCol ? ', ' . $lineAreaCol . ' AS legacy_area_applied' : '')
                . ($lineDoseCol ? ', ' . $lineDoseCol . ' AS legacy_dose' : '')
                . ($lineDoseUnitCol ? ', ' . $lineDoseUnitCol . ' AS legacy_dose_unit' : '')
                . ($lineTotalCol ? ', ' . $lineTotalCol . ' AS legacy_total' : '')
                . ($lineMovementTypeCol ? ', ' . $lineMovementTypeCol . ' AS legacy_movement_type' : '')
                . ($lineWarehouseCol ? ', ' . $lineWarehouseCol . ' AS legacy_fk_warehouse' : '')
                . ($lineDateCreationCol ? ', ' . $lineDateCreationCol . ' AS legacy_date_creation' : '')
                . ($lineUserCreatCol ? ', ' . $lineUserCreatCol . ' AS legacy_fk_user_creat' : '')
                . ($lineUserModifCol ? ', ' . $lineUserModifCol . ' AS legacy_fk_user_modif' : '')
                . ' FROM ' . $legacyLine;

            $resLine = $db->query($sqlLine);
            if (!$resLine) {
                $db->rollback();
                $errors[] = 'Unable to read legacy activity lines: ' . $db->lasterror();
                return false;
            }

            while ($line = $db->fetch_object($resLine)) {
                $lineId = (int) $line->rowid;
                $movementType = isset($line->legacy_movement_type) ? trim((string) $line->legacy_movement_type) : '';
                if ($movementType === '' || !in_array($movementType, array('consume', 'return', 'transfer'), true)) {
                    $movementType = 'consume';
                }

                $lineDateCreation = !empty($line->legacy_date_creation) ? $line->legacy_date_creation : dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S');

                $sqlInsertLine = 'INSERT INTO ' . MAIN_DB_PREFIX . 'safra_activity_line ('
                    . 'rowid, entity, fk_activity, fk_product, area_applied, dose, dose_unit, total, movement_type, fk_warehouse, date_creation, fk_user_creat, fk_user_modif'
                    . ') VALUES ('
                    . $lineId . ', '
                    . ((int) (isset($line->legacy_entity) ? $line->legacy_entity : $GLOBALS['conf']->entity)) . ', '
                    . ((int) $line->legacy_fk_activity) . ', '
                    . safraSqlNullableInt(isset($line->legacy_fk_product) ? $line->legacy_fk_product : null) . ', '
                    . safraSqlNumeric(isset($line->legacy_area_applied) ? $line->legacy_area_applied : 0) . ', '
                    . safraSqlNumeric(isset($line->legacy_dose) ? $line->legacy_dose : 0) . ', '
                    . safraSqlNullableString($db, isset($line->legacy_dose_unit) ? $line->legacy_dose_unit : null) . ', '
                    . safraSqlNumeric(isset($line->legacy_total) ? $line->legacy_total : 0) . ', '
                    . safraSqlString($db, $movementType) . ', '
                    . safraSqlNullableInt(isset($line->legacy_fk_warehouse) ? $line->legacy_fk_warehouse : null) . ', '
                    . safraSqlString($db, $lineDateCreation) . ', '
                    . safraSqlNullableInt(isset($line->legacy_fk_user_creat) ? $line->legacy_fk_user_creat : null) . ', '
                    . safraSqlNullableInt(isset($line->legacy_fk_user_modif) ? $line->legacy_fk_user_modif : null)
                    . ') ON DUPLICATE KEY UPDATE '
                    . 'entity = VALUES(entity), fk_activity = VALUES(fk_activity), fk_product = VALUES(fk_product), area_applied = VALUES(area_applied), '
                    . 'dose = VALUES(dose), dose_unit = VALUES(dose_unit), total = VALUES(total), movement_type = VALUES(movement_type), '
                    . 'fk_warehouse = VALUES(fk_warehouse), fk_user_modif = VALUES(fk_user_modif)';

                if (!$db->query($sqlInsertLine)) {
                    $db->rollback();
                    $errors[] = 'Failed to migrate legacy activity line #' . $lineId . ': ' . $db->lasterror();
                    return false;
                }

                $migratedLines++;
            }
        }
    }

    // Integrity validation before destructive cleanup.
    $sqlMissingHeaders = 'SELECT COUNT(*) as nb'
        . ' FROM ' . $legacyHeader . ' as legacy_h'
        . ' LEFT JOIN ' . MAIN_DB_PREFIX . 'safra_activity as act ON act.rowid = legacy_h.rowid'
        . ' WHERE act.rowid IS NULL';
    $resMissingHeaders = $db->query($sqlMissingHeaders);
    if (!$resMissingHeaders) {
        $db->rollback();
        $errors[] = 'Unable to validate migrated activity headers: ' . $db->lasterror();
        return false;
    }
    $objMissingHeaders = $db->fetch_object($resMissingHeaders);
    $missingHeaders = $objMissingHeaders ? (int) $objMissingHeaders->nb : 0;
    if ($missingHeaders > 0) {
        $db->rollback();
        $errors[] = 'Integrity validation failed: ' . $missingHeaders . ' legacy activity headers were not migrated.';
        return false;
    }

    if ($lineMigrationExecuted) {
        $sqlMissingLines = 'SELECT COUNT(*) as nb'
            . ' FROM ' . $legacyLine . ' as legacy_l'
            . ' LEFT JOIN ' . MAIN_DB_PREFIX . 'safra_activity_line as act_l ON act_l.rowid = legacy_l.rowid'
            . ' WHERE act_l.rowid IS NULL';
        $resMissingLines = $db->query($sqlMissingLines);
        if (!$resMissingLines) {
            $db->rollback();
            $errors[] = 'Unable to validate migrated activity lines: ' . $db->lasterror();
            return false;
        }
        $objMissingLines = $db->fetch_object($resMissingLines);
        $missingLines = $objMissingLines ? (int) $objMissingLines->nb : 0;
        if ($missingLines > 0) {
            $db->rollback();
            $errors[] = 'Integrity validation failed: ' . $missingLines . ' legacy activity lines were not migrated.';
            return false;
        }
    }

    // Remove legacy schema and extrafield columns now that migration is complete.
    $legacyCleanup = array(
        'DROP VIEW IF EXISTS ' . MAIN_DB_PREFIX . 'safra_aplicacao',
        'DROP VIEW IF EXISTS ' . MAIN_DB_PREFIX . 'safra_aplicacao_line',
        'DROP TABLE IF EXISTS ' . MAIN_DB_PREFIX . 'safra_aplicacao_line',
        'DROP TABLE IF EXISTS ' . MAIN_DB_PREFIX . 'safra_aplicacao_resource',
        'DROP TABLE IF EXISTS ' . MAIN_DB_PREFIX . 'safra_aplicacao',
    );

    foreach ($legacyCleanup as $sqlDrop) {
        if (!$db->query($sqlDrop)) {
            $db->rollback();
            $errors[] = 'Legacy cleanup failed: ' . $db->lasterror();
            return false;
        }
    }

    if (safraTableExists($db, MAIN_DB_PREFIX . 'projet_task_extrafields')) {
        foreach (array('fk_aplicacao', 'options_fk_aplicacao') as $legacyColumn) {
            if (safraTableHasColumn($db, MAIN_DB_PREFIX . 'projet_task_extrafields', $legacyColumn)) {
                $sqlDropColumn = 'ALTER TABLE ' . MAIN_DB_PREFIX . 'projet_task_extrafields DROP COLUMN ' . $legacyColumn;
                if (!$db->query($sqlDropColumn)) {
                    $db->rollback();
                    $errors[] = 'Failed to drop legacy extrafield column `' . $legacyColumn . '`: ' . $db->lasterror();
                    return false;
                }
            }
        }
    }

    $db->commit();

    $messages[] = 'Legacy migration completed: ' . $migratedHeaders . ' activities and ' . $migratedLines . ' lines migrated.';
    $messages[] = 'Legacy schema `safra_aplicacao*` removed successfully.';

    return true;
}

/**
 * Split SQL content into executable statements.
 *
 * @param string $sqlContent
 * @return string[]
 */
function safraSplitSqlStatements($sqlContent)
{
    $statements = array();
    $buffer = '';
    $length = strlen($sqlContent);
    $inSingle = false;
    $inDouble = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sqlContent[$i];
        $next = ($i + 1 < $length) ? $sqlContent[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble) {
            if ($char === '-' && $next === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($char === '#') {
                $inLineComment = true;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($char === "'" && !$inDouble) {
            if ($inSingle && $next === "'") {
                $buffer .= "''";
                $i++;
                continue;
            }
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle) {
            if ($inDouble && $next === '"') {
                $buffer .= '""';
                $i++;
                continue;
            }
            $inDouble = !$inDouble;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $statements[] = $buffer;
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}

function safraTableExists($db, $tableName)
{
    $sql = "SHOW TABLES LIKE '" . $db->escape($tableName) . "'";
    $res = $db->query($sql);
    return $res && ((int) $db->num_rows($res) > 0);
}

function safraTableHasColumn($db, $tableName, $column)
{
    $sql = 'SHOW COLUMNS FROM ' . $tableName . " LIKE '" . $db->escape($column) . "'";
    $res = $db->query($sql);
    return $res && ((int) $db->num_rows($res) > 0);
}

function safraGetTableColumns($db, $tableName)
{
    $columns = array();
    $sql = 'SHOW COLUMNS FROM ' . $tableName;
    $res = $db->query($sql);
    if (!$res) {
        return $columns;
    }

    while ($obj = $db->fetch_object($res)) {
        if (!empty($obj->Field)) {
            $columns[] = (string) $obj->Field;
        }
    }

    return $columns;
}

function safraPickColumn(array $columns, array $candidates)
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function safraSqlString($db, $value)
{
    return "'" . $db->escape((string) $value) . "'";
}

function safraSqlNullableString($db, $value)
{
    if ($value === null || $value === '') {
        return 'NULL';
    }

    return safraSqlString($db, $value);
}

function safraSqlNullableInt($value)
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return 'NULL';
    }

    return (string) ((int) $value);
}

function safraSqlNumeric($value)
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '0';
    }

    return (string) (0 + $value);
}

function safraNormalizeLegacyType($value)
{
    $raw = strtolower(trim((string) $value));
    if ($raw === '') {
        return 'outro';
    }

    $map = array(
        'application' => 'aplicacao',
        'aplicacao' => 'aplicacao',
        'fertilization' => 'fertilizacao',
        'fertilizacao' => 'fertilizacao',
        'plantio' => 'plantio',
        'planting' => 'plantio',
        'harvest' => 'colheita',
        'colheita' => 'colheita',
        'monitoring' => 'monitoramento',
        'monitoramento' => 'monitoramento',
        'preparo_solo' => 'preparo_solo',
        'soil_preparation' => 'preparo_solo',
        'preparo_semente' => 'preparo_semente',
        'seed_preparation' => 'preparo_semente',
    );

    return isset($map[$raw]) ? $map[$raw] : 'outro';
}

function safraNormalizeLegacyStatus($value)
{
    $status = (int) $value;

    // Legacy map to canonical status: 0 draft, 3 in progress, 1 completed, 2 canceled.
    if ($status === 9) {
        return 2;
    }
    if ($status === 3) {
        return 1;
    }
    if ($status === 2 || $status === 1) {
        return 3;
    }

    return 0;
}
