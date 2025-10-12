﻿<?php
/* Return talhão linked to a project (via extrafields) as JSON */

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
    echo json_encode(array('success' => false, 'error' => 'bootstrap_failed'));
    exit;
}

if (!isModEnabled('safra')) {
    accessforbidden();
}

header('Content-Type: application/json; charset=utf-8');

$projectId = (int) GETPOST('id', 'int');
if ($projectId <= 0) {
    echo json_encode(array('success' => false, 'error' => 'bad_id'));
    exit;
}

// Detect column name in extrafields
function safraAjaxColumnExists(DoliDB $db, $tablename, $columnname)
{
    $sql = "SHOW COLUMNS FROM ".$db->prefix().$tablename." LIKE '".$db->escape($columnname)."'";
    $res = $db->query($sql);
    if ($res) {
        $ok = ($db->num_rows($res) > 0);
        $db->free($res);
        return $ok;
    }
    return false;
}

$col = '';
if (safraAjaxColumnExists($db, 'projet_extrafields', 'fk_talhao')) {
    $col = 'fk_talhao';
} elseif (safraAjaxColumnExists($db, 'projet_extrafields', 'options_fk_talhao')) {
    $col = 'options_fk_talhao';
}

$talhaoId = 0;
if ($col !== '') {
    $sql = 'SELECT '.$col.' as talhao_id FROM '.$db->prefix().'projet_extrafields WHERE fk_object='.(int) $projectId.' LIMIT 1';
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj) {
            $talhaoId = (int) $obj->talhao_id;
        }
        $db->free($resql);
    }
}

$talhao = null;
if ($talhaoId > 0) {
    $sqlTalhao = 'SELECT t.rowid, t.ref, t.label, t.area, t.municipio, m.label as municipio_label'
        .' FROM '.$db->prefix().'safra_talhao as t'
        .' LEFT JOIN '.$db->prefix().'safra_municipio as m ON m.rowid=t.municipio'
        .' WHERE t.rowid='.(int) $talhaoId.' LIMIT 1';
    $rst = $db->query($sqlTalhao);
    if ($rst) {
        $o = $db->fetch_object($rst);
        if ($o) {
            $talhao = array(
                'id' => (int) $o->rowid,
                'label' => trim(($o->ref ? $o->ref.' - ' : '').($o->label ? $o->label : '')),
                'area' => (float) $o->area,
                'municipio' => $o->municipio_label,
                'url' => dol_buildpath('/safra/talhao_card.php', 1).'?id='.(int) $o->rowid,
            );
        }
        $db->free($rst);
    }
}

echo json_encode(array('success' => true, 'talhao_id' => $talhaoId, 'talhao' => $talhao));
exit;
