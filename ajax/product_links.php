<?php
/*
 * Copyright (C) 2024-2025
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

dol_include_once('/safra/class/safra_product_link.class.php');

if (empty($user->rights->produit->lire)) {
    accessforbidden();
}

$type = GETPOST('type', 'aZ09');
$term = trim(GETPOST('term', 'restricthtml'));
if ($term === '') {
    $term = trim(GETPOST('q', 'restricthtml'));
}
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit');

if ($page < 1) {
    $page = 1;
}
if ($limit <= 0) {
    $limit = 25;
} elseif ($limit > 100) {
    $limit = 100;
}

$offset = ($page - 1) * $limit;

$results = array();
$hasMore = false;

$sql = '';
$permissionOk = false;

if ($type === SafraProductLink::TYPE_FORMULADO) {
    $permissionOk = !empty($user->rights->safra->produtoformulado->read);
    if ($permissionOk) {
        $sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'safra_produto_formulado WHERE status = 1';
    }
} elseif ($type === SafraProductLink::TYPE_TECNICO) {
    $permissionOk = !empty($user->rights->safra->produtostecnicos->read);
    if ($permissionOk) {
        $sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'safra_produtostecnicos WHERE status = 1';
    }
} elseif ($type === SafraProductLink::TYPE_CULTIVAR) {
    $permissionOk = !empty($user->rights->safra->cultivar->read);
    if ($permissionOk) {
        $sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX."safra_cultivar WHERE status = 1 AND "
            . getEntity('safra_cultivar', 1);
    }
}

if (!$permissionOk || $sql === '') {
    accessforbidden();
}

if ($term !== '') {
    $escaped = $db->escape($db->escapeforlike($term));
    $sql .= " AND (ref LIKE '%".$escaped."%' OR label LIKE '%".$escaped."%')";
}

$sql .= ' ORDER BY ref ASC';
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
    $count = 0;
    while ($obj = $db->fetch_object($resql)) {
        $count++;
        if ($count > $limit) {
            $hasMore = true;
            break;
        }
        $text = dol_escape_htmltag($obj->ref);
        if (!empty($obj->label)) {
            $text .= ' - '.dol_escape_htmltag($obj->label);
        }
        $results[] = array(
            'id' => (int) $obj->rowid,
            'text' => $text,
        );
    }
    $db->free($resql);
}

top_httphead('application/json');

echo json_encode(array(
    'results' => $results,
    'pagination' => array('more' => $hasMore),
));

$db->close();
