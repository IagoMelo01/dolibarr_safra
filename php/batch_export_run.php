<?php
require_once __DIR__ . '/../../../main.inc.php';

$tokenPost = GETPOST('token', 'alphanohtml');
if (empty($tokenPost) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $tokenPost)) {
    accessforbidden('Bad token');
}

$rowid = (int) GETPOST('id', 'int');
$sql = 'SELECT * FROM llx_fv_batch_export WHERE rowid = ' . $rowid;
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    $export = $db->fetch_object($resql);
    setEventMessages($langs->trans('BatchExportReady', $export->zip_path), null, 'mesgs');
}

header('Location: ../batch_export.php');
