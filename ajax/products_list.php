<?php
/* Return products for Select2, optionally filtered by warehouse */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('results' => array()));
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$q = trim(GETPOST('q', 'alphanohtml'));
$wh = (int) GETPOST('wh', 'int');
$page = max(1, (int) GETPOST('page', 'int'));
$pagesize = 50;
$offset = ($page - 1) * $pagesize;

$results = array();

if (!isModEnabled('product')) {
    echo json_encode(array('results' => $results));
    exit;
}

$sql = 'SELECT p.rowid, p.ref, p.label'
    .' FROM '.$db->prefix().'product as p';
if ($wh > 0 && $db->DDLDescTable($db->prefix().'product_stock', 'product_stock')) {
    $sql .= ' INNER JOIN '.$db->prefix().'product_stock as ps ON ps.fk_product = p.rowid AND ps.fk_entrepot = '.((int) $wh);
}
$sql .= ' WHERE p.entity IN ('.getEntity('product').')';
if ($q !== '') {
    $sq = $db->escape($q);
    $sql .= " AND (p.ref LIKE '%".$db->escape($db->escapeforlike($sq))."%' OR p.label LIKE '%".$db->escape($db->escapeforlike($sq))."%')";
}
$sql .= ' ORDER BY p.ref ASC';
$sql .= ' LIMIT '.((int) $pagesize).' OFFSET '.((int) $offset);

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $text = $obj->ref;
        if (!empty($obj->label)) $text .= ' - '.$obj->label;
        $results[] = array('id' => (int) $obj->rowid, 'text' => $text);
    }
    $db->free($resql);
}

echo json_encode(array('results' => $results, 'pagination' => array('more' => (count($results) === $pagesize))));
exit;

