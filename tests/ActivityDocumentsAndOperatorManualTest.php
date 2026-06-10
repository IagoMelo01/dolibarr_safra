<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);
foreach (array(
    '/activity/activity_documents.php',
    '/lib/safra_activity.lib.php',
    '/manual/operator_manual.php',
) as $relativePath) {
    $assert(is_file($root . $relativePath), 'Missing activity document or operator manual surface: ' . $relativePath);
}

$activityLib = file_get_contents($root . '/lib/safra_activity.lib.php');
$assert($activityLib !== false && strpos($activityLib, "'documents'") !== false, 'Activity documents tab missing');
$assert(strpos($activityLib, "safra_activity/") !== false, 'Activity document storage path missing');
$assert(strpos($activityLib, "defined('DOL_DATA_ROOT')") !== false, 'Activity document storage fallback missing');

$documentsPage = file_get_contents($root . '/activity/activity_documents.php');
$assert($documentsPage !== false && strpos($documentsPage, 'actions_linkedfiles.inc.php') !== false, 'Dolibarr linked file actions missing');
$assert(strpos($documentsPage, 'document_actions_post_headers.tpl.php') !== false, 'Dolibarr document list template missing');
$assert(strpos($documentsPage, "'image/*'") !== false && strpos($documentsPage, 'SafraActivityTakePhoto') !== false, 'Field photo capture shortcut missing');
$assert(strpos($documentsPage, '$permissiontoadd = $user->rights->safra->SafraActivity->write') !== false, 'Attachment write permission missing');

$manual = file_get_contents($root . '/manual/operator_manual.php');
$assert($manual !== false && strpos($manual, 'Fluxo da plataforma') !== false, 'Operator manual workflow missing');
$assert(strpos($manual, 'Anexos, fotos e ocorrências') !== false, 'Operator manual attachment guidance missing');
$assert(strpos($manual, '/safra/activity/activity_kanban.php') !== false, 'Operator manual agenda shortcut missing');
$assert(strpos($manual, '/safra/report/input_consumption.php') !== false, 'Operator manual consumption shortcut missing');

$module = file_get_contents($root . '/core/modules/modSafra.class.php');
$assert($module !== false && strpos($module, '/custom/safra/manual/operator_manual.php') !== false, 'Operator manual menu missing');

return true;
