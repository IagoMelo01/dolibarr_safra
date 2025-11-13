<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       activity_kanban.php
 *  \ingroup    safra
 *  \brief      Optional kanban overview for activities grouped by status.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

if (!isModEnabled('safra')) {
    accessforbidden();
}

dol_include_once('/safra/class/SfActivity.class.php');
dol_include_once('/safra/lib/safra_rights.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

global $langs, $db, $conf, $user;

$langs->loadLangs(array('safra@safra', 'companies', 'projects'));

$permissiontoread = getSafraRightValue($user, 'read');
if (!$permissiontoread) {
    accessforbidden();
}

$limit = GETPOSTINT('limit');
if (empty($limit) || $limit < 0) {
    $limit = 200;
}

$statuses = array(
    SfActivity::STATUS_DRAFT => $langs->trans('Draft'),
    SfActivity::STATUS_VALIDATED => $langs->trans('SafraActivityStatusValidated'),
    SfActivity::STATUS_IN_PROGRESS => $langs->trans('SafraActivityStatusInProgress'),
    SfActivity::STATUS_COMPLETED => $langs->trans('SafraActivityStatusCompleted'),
    SfActivity::STATUS_CANCELED => $langs->trans('SafraActivityStatusCanceled'),
);

$boards = array();
foreach (array_keys($statuses) as $statusKey) {
    $boards[$statusKey] = array();
}

$sql = 'SELECT t.rowid, t.ref, t.label, t.status, t.date_activity, t.activity_type, t.fk_project, t.fk_soc,'
    .' p.ref as project_ref, p.title as project_title,'
    .' s.nom as thirdparty_name'
    .' FROM '.MAIN_DB_PREFIX.'safra_activity AS t'
    .' LEFT JOIN '.MAIN_DB_PREFIX.'projet AS p ON p.rowid = t.fk_project'
    .' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = t.fk_soc'
    .' WHERE t.entity IN ('.getEntity('safra_activity').')'
    .' ORDER BY t.status ASC, t.date_activity DESC';
if ($limit > 0) {
    $sql .= $db->plimit($limit, 0);
}

$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $status = (int) $obj->status;
        if (!isset($boards[$status])) {
            $boards[$status] = array();
        }
        $boards[$status][] = $obj;
    }
    $db->free($resql);
}

$title = $langs->trans('SafraActivityKanbanTitle');
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, array(), array(), '', 'mod-safra activity-kanban');

print '<style>
.safra-kanban-board{display:flex;gap:18px;align-items:flex-start;overflow-x:auto;padding:10px 0;}
.safra-kanban-column{flex:1 1 220px;min-width:220px;background:#f8f9fa;border-radius:8px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
.safra-kanban-column h3{margin-top:0;font-size:1em;display:flex;justify-content:space-between;align-items:center;}
.safra-kanban-card{background:#fff;border-radius:6px;padding:10px;margin-bottom:10px;border-left:4px solid #0d6efd;box-shadow:0 1px 2px rgba(0,0,0,0.05);} 
.safra-kanban-card.draft{border-color:#6c757d;}
.safra-kanban-card.validated{border-color:#0d6efd;}
.safra-kanban-card.inprogress{border-color:#198754;}
.safra-kanban-card.completed{border-color:#6f42c1;}
.safra-kanban-card.canceled{border-color:#dc3545;opacity:0.8;}
.safra-kanban-card .ref{font-weight:600;margin-bottom:4px;display:flex;justify-content:space-between;align-items:center;}
.safra-kanban-card .meta{font-size:0.9em;color:#6c757d;}
.safra-kanban-card .project{margin-top:4px;font-size:0.85em;}
</style>';

print '<div class="tabsAction">';
print '<a class="butAction" href="'.dol_buildpath('/safra/activity_list.php', 1).'">'.$langs->trans('ViewList').'</a>';
print '</div>';

print load_fiche_titre($title, '', 'fa fa-columns');
print '<div class="safra-kanban-board">';

foreach ($statuses as $statusKey => $statusLabel) {
    $cards = isset($boards[$statusKey]) ? $boards[$statusKey] : array();
    $count = count($cards);
    $statusClass = 'draft';
    if ($statusKey == SfActivity::STATUS_VALIDATED) {
        $statusClass = 'validated';
    } elseif ($statusKey == SfActivity::STATUS_IN_PROGRESS) {
        $statusClass = 'inprogress';
    } elseif ($statusKey == SfActivity::STATUS_COMPLETED) {
        $statusClass = 'completed';
    } elseif ($statusKey == SfActivity::STATUS_CANCELED) {
        $statusClass = 'canceled';
    }
    print '<div class="safra-kanban-column">';
    print '<h3>'.dol_escape_htmltag($statusLabel).' <span class="badge">'.$count.'</span></h3>';
    if ($count === 0) {
        print '<div class="opacitymedium">'.$langs->trans('SafraActivityKanbanEmpty').'</div>';
    } else {
        foreach ($cards as $card) {
            $dateLabel = $card->date_activity ? dol_print_date($db->jdate($card->date_activity), 'day') : '';
            $url = dol_buildpath('/safra/activity_card.php', 1).'?id='.(int) $card->rowid;
            print '<div class="safra-kanban-card '.$statusClass.'">';
            print '<div class="ref"><a href="'.$url.'">'.dol_escape_htmltag($card->ref).'</a>'; 
            if ($dateLabel) {
                print '<span class="meta">'.$dateLabel.'</span>';
            }
            print '</div>';
            if (!empty($card->label)) {
                print '<div class="meta">'.dol_escape_htmltag($card->label).'</div>';
            }
            if (!empty($card->project_ref)) {
                print '<div class="project">'.$langs->trans('Project').': '.dol_escape_htmltag($card->project_ref.($card->project_title ? ' - '.$card->project_title : '')).'</div>';
            }
            if (!empty($card->thirdparty_name)) {
                print '<div class="project">'.$langs->trans('ThirdParty').': '.dol_escape_htmltag($card->thirdparty_name).'</div>';
            }
            print '</div>';
        }
    }
    print '</div>';
}

print '</div>';

llxFooter();
$db->close();
