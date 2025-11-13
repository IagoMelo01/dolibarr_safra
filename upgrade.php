<?php
/*
 * Safra module upgrade orchestrator.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', 1);
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

$targetVersion = '1.1.0';
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
        'title' => 'Safra Activity data model migration',
        'description' => 'Creates the Activity tables, migrates legacy Aplicacao records, and sets up compatibility views.',
        'sql' => array(
            '/safra/sql/migrations/20241005_add_activity_tables.sql',
            '/safra/sql/migrations/20241007_migrate_application_to_activity.sql',
        ),
        'checks' => array(
            'Confirm `SELECT COUNT(*) FROM llx_safra_activity` matches the legacy Aplicacao volume.',
            'Ensure stock movements now reference `safra_activity` as `origintype`.',
            'Verify the legacy views `llx_safra_aplicacao` and `llx_safra_aplicacao_line` exist for backwards compatibility.',
        ),
        'rollback' => array(
            'Restore the database backup taken before executing this upgrade.',
            'Revert the `SAFRA_VERSION` constant to the previous value if it was updated.',
            'Drop the compatibility views and re-create the legacy tables only if you need to resume the previous schema.',
        ),
    ),
);

if ($action === 'run') {
    if (!empty($token) && function_exists('checkToken') && !checkToken($token)) {
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
print '<p>This assistant executes the SQL migrations required to rename Safra “Aplicação” into the neutral “Activity” terminology while keeping integrations online.</p>';
print '<p><strong>Current version:</strong> ' . dol_escape_htmltag($currentVersion) . '<br />';
print '<strong>Target version:</strong> ' . dol_escape_htmltag($targetVersion) . '</p>';

print '<h3>Pre-deployment checklist</h3>';
print '<ol>';
print '<li>Place Dolibarr in maintenance mode and notify integrators about a brief downtime.</li>';
print '<li>Take a full database backup (schema and data) plus a snapshot of the document directories.</li>';
print '<li>Ensure no background jobs are creating Aplicação records during the migration.</li>';
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
    if (!empty($step['rollback'])) {
        print '<p><strong>Rollback guidance</strong></p>';
        print '<ul>';
        foreach ($step['rollback'] as $rollback) {
            print '<li>' . dol_escape_htmltag($rollback) . '</li>';
        }
        print '</ul>';
    }
}

print '<h3>Post-deployment actions</h3>';
print '<ol>';
print '<li>Flush Dolibarr caches and restart queue workers or cron jobs.</li>';
print '<li>Run a smoke test through the REST API: <code>GET /api/index.php/sfactivities?limit=5</code>.</li>';
print '<li>Monitor the logs for trigger warnings (<code>SAFRA_ACTIVITY_*</code>) during the first hour after go-live.</li>';
print '</ol>';

print '<h3>Rollback procedure</h3>';
print '<ol>';
print '<li>Restore the database backup created before the upgrade.</li>';
print '<li>Re-deploy the previous Safra module package if files changed.</li>';
print '<li>Reopen access only after verifying Aplicação listings and API endpoints are operating normally.</li>';
print '</ol>';

print '</div>';
print '<div class="fichehalfright">';
print '<h3>Integrator communication</h3>';
print '<ul>';
print '<li>New REST resource preferred path: <code>/sfactivities</code> (legacy alias <code>/aplicacoes</code> retained).</li>';
print '<li>Workflow triggers now emit <code>SAFRA_ACTIVITY_CREATE</code>, <code>SAFRA_ACTIVITY_VALIDATE</code>, <code>SAFRA_ACTIVITY_START</code>, <code>SAFRA_ACTIVITY_DONE</code>, <code>SAFRA_ACTIVITY_CANCEL</code>, and <code>SAFRA_ACTIVITY_DELETE</code>.</li>';
print '<li>Payload fields `activity_type`, `date_activity`, and `mixture_note` mirror the old keys `operation_type`, `date_application`, and `calda_observacao`.</li>';
print '<li>Compatibility views (`llx_safra_aplicacao*`) remain available for one release to shield custom SQL integrations.</li>';
print '</ul>';

print '<h3>Execute upgrade</h3>';
print '<form method="POST" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="action" value="run" />';
if (function_exists('newToken')) {
    print '<input type="hidden" name="token" value="' . newToken() . '" />';
}
print '<div class="center">';
print '<input class="button" type="submit" value="Run migrations" />';
print '</div>';
print '</form>';

print '</div>';
print '</div>';
print '</div>';

llxFooter();
$db->close();

/**
 * Execute the SQL statements contained in a migration file.
 *
 * @param DoliDB  $db           Database handler
 * @param string  $relativePath Path relative to Dolibarr root
 *
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

    return array(
        'status' => 'success',
        'message' => $executed . ' statements executed from ' . basename($relativePath),
    );
}

/**
 * Split SQL content into executable statements while keeping quoted semicolons intact.
 *
 * @param string $sqlContent SQL file content
 *
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
            if ($char === '#' ) {
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
