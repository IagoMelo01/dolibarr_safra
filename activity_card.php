<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *  \file       activity_card.php
 *  \ingroup    safra
 *  \brief      Modern activity card with tabs, transitions and alerts.
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
dol_include_once('/safra/class/SfActivity.class.php');

global $langs, $db, $conf, $user;

$langs->loadLangs(array('safra@safra', 'companies', 'projects', 'product', 'stocks'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

$permissiontoread = $user->rights['safra']['aplicacao']['read'] ?? 1;
$permissiontoadd = $user->rights['safra']['aplicacao']['write'] ?? 1;
if (!$permissiontoread) {
    accessforbidden();
}

$object = new SfActivity($db);
if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result <= 0) {
        setEventMessages($object->error ?: $langs->trans('ErrorSafraActivityNotFound', $id ?: $ref), $object->errors, 'errors');
    }
}

if ($action === 'create' || $action === 'edit') {
    $target = dol_buildpath('/safra/aplicacao_assistant.php', 1).'?action='.(($action === 'create') ? 'create' : 'edit');
    if ($object->id > 0) {
        $target .= '&id='.$object->id;
    }
    header('Location: '.$target);
    exit;
}

if (!empty($object->id)) {
    $object->fetchLines();
    $object->info($object->id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!GETPOST('token', 'alphanohtml') || !checkToken(GETPOST('token'))) {
        accessforbidden('Invalid token');
    }
}

if (!empty($object->id) && !empty($permissiontoadd)) {
    if ($action === 'validate') {
        $result = $object->validate($user);
        if ($result > 0) {
            setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
        exit;
    }
    if ($action === 'setdraft') {
        $result = $object->setDraft($user);
        if ($result >= 0) {
            setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
        exit;
    }
    if ($action === 'start') {
        $result = $object->markAsInProgress($user);
        if ($result > 0) {
            setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
        exit;
    }
    if ($action === 'complete') {
        $result = $object->markAsCompleted($user);
        if ($result > 0) {
            setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
        exit;
    }
    if ($action === 'cancel') {
        $result = $object->cancel($user);
        if ($result > 0) {
            setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
        exit;
    }
    if ($action === 'reopen') {
        $result = $object->reopen($user);
        if ($result > 0) {
            setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
        exit;
    }
}

$form = new Form($db);
$formfile = new FormFile($db);
$formcompany = new FormCompany($db);
$formproject = new FormProjets($db);

$title = $langs->trans('SafraActivityCardTitle', $object->ref ?: '');
$help_url = '';
$morejs = array();
$morecss = array();

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss, '', 'mod-safra page-card');

$css = "<style>
.safra-activity-header{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;margin-bottom:16px;}
.safra-activity-meta{flex:1 1 260px;}
.safra-activity-status{display:flex;flex-direction:column;gap:8px;min-width:180px;}
.safra-activity-status .status-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:12px;font-weight:600;color:#fff;}
.status-pill.status-".SfActivity::STATUS_DRAFT."{background:#6c757d;}
.status-pill.status-".SfActivity::STATUS_VALIDATED."{background:#0d6efd;}
.status-pill.status-".SfActivity::STATUS_IN_PROGRESS."{background:#198754;}
.status-pill.status-".SfActivity::STATUS_COMPLETED."{background:#6f42c1;}
.status-pill.status-".SfActivity::STATUS_CANCELED."{background:#dc3545;}
.safra-activity-tabs{margin-top:20px;}
.safra-activity-tab-buttons{display:flex;gap:8px;border-bottom:1px solid #ddd;flex-wrap:wrap;}
.safra-activity-tab-buttons button{border:none;background:none;padding:10px 16px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;}
.safra-activity-tab-buttons button.active{border-color:#0d6efd;color:#0d6efd;}
.safra-activity-tab{display:none;padding:18px 0;}
.safra-activity-tab.active{display:block;}
.safra-activity-summary-table{width:100%;border-collapse:collapse;}
.safra-activity-summary-table td{padding:6px 12px;border-bottom:1px solid #e9ecef;vertical-align:top;}
.safra-activity-lines-table{width:100%;border-collapse:collapse;margin-bottom:18px;}
.safra-activity-lines-table th,.safra-activity-lines-table td{padding:8px 10px;border-bottom:1px solid #e9ecef;}
.safra-activity-lines-table th{background:#f8f9fa;text-align:left;}
.safra-activity-alert{padding:10px 14px;border-radius:6px;margin-bottom:10px;}
.safra-activity-alert.info{background:#e7f1ff;color:#084298;}
.safra-activity-alert.warning{background:#fff3cd;color:#664d03;}
.safra-activity-alert.danger{background:#f8d7da;color:#842029;}
.safra-activity-timeline{list-style:none;padding:0;margin:0;}
.safra-activity-timeline li{border-left:3px solid #0d6efd;padding-left:12px;margin-left:6px;margin-bottom:12px;position:relative;}
.safra-activity-timeline li::before{content:\'\';position:absolute;left:-9px;top:0;width:10px;height:10px;border-radius:50%;background:#0d6efd;}
.safra-activity-timeline time{font-size:0.9em;color:#6c757d;display:block;margin-bottom:4px;}
.safra-activity-actions{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0;}
.safra-activity-actions form{display:inline;}
.safra-activity-actions button{border:none;border-radius:4px;padding:8px 14px;font-weight:600;cursor:pointer;}
.safra-activity-actions button.primary{background:#0d6efd;color:#fff;}
.safra-activity-actions button.success{background:#198754;color:#fff;}
.safra-activity-actions button.warning{background:#ffc107;color:#000;}
.safra-activity-actions button.danger{background:#dc3545;color:#fff;}
.safra-activity-actions button.secondary{background:#adb5bd;color:#212529;}
.safra-activity-stock-summary{margin-top:12px;}
.safra-activity-stock-summary table{width:100%;border-collapse:collapse;}
.safra-activity-stock-summary th,.safra-activity-stock-summary td{padding:8px 10px;border-bottom:1px solid #e9ecef;}
</style>";
print $css;

if (!empty($object->id)) {
    $statusHtml = '<span class="status-pill status-'.$object->status.'">'.img_picto('', 'status'.$object->status).$object->LibStatut($object->status, 1).'</span>';

    $alerts = array();
    if (empty($object->lines)) {
        $alerts[] = array('type' => 'warning', 'message' => $langs->trans('SafraActivityNoLines'));
    }
    $missingWarehouse = false;
    $stockSummary = $object->buildStockSummary($user, $missingWarehouse);
    if ($missingWarehouse) {
        $alerts[] = array('type' => 'warning', 'message' => $langs->trans('SafraActivityAlertMissingWarehouse'));
    }
    if ($object->status == SfActivity::STATUS_CANCELED) {
        $alerts[] = array('type' => 'info', 'message' => $langs->trans('SafraActivityAlertCanceled'));
    }

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<div class="safra-activity-header">';
    print '<div class="safra-activity-meta">';
    $headerTitle = $object->ref ?: $langs->trans('SafraActivityListTitle');
    print load_fiche_titre(dol_escape_htmltag($headerTitle), '', 'fa fa-seedling');
    print '<table class="safra-activity-summary-table">';
    $project = null;
    if (!empty($object->fk_project)) {
        $project = new Project($db);
        $project->fetch($object->fk_project);
    }
    $thirdparty = null;
    if (!empty($object->fk_soc)) {
        $thirdparty = new Societe($db);
        $thirdparty->fetch($object->fk_soc);
    }
    print '<tr><td>'.$langs->trans('Label').'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
    print '<tr><td>'.$langs->trans('Project').'</td><td>'.($project ? $project->getNomUrl(1) : '').'</td></tr>';
    print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.($thirdparty ? $thirdparty->getNomUrl(1) : '').'</td></tr>';
    $typeLabel = SfActivity::getActivityTypeLabel($object->activity_type, $langs);
    print '<tr><td>'.$langs->trans('SafraActivityType').'</td><td>'.dol_escape_htmltag($typeLabel).'</td></tr>';
    print '<tr><td>'.$langs->trans('Date').'</td><td>'.($object->date_activity ? dol_print_date($object->date_activity, 'day') : '').'</td></tr>';
    print '<tr><td>'.$langs->trans('SafraAplicacaoAreaHa').'</td><td>'.dol_print_decimal($object->qty, 2).' ha</td></tr>';
    print '<tr><td>'.$langs->trans('Description').'</td><td>'.dol_escape_htmltag($object->description).'</td></tr>';
    if (!empty($object->mixture_note)) {
        print '<tr><td>'.$langs->trans('SafraAplicacaoCaldaObservation').'</td><td>'.dol_escape_htmltag($object->mixture_note).'</td></tr>';
    }
    print '</table>';
    print '</div>';
    print '<div class="safra-activity-status">';
    print $statusHtml;
    if (!empty($object->date_creation)) {
        print '<div>'.$langs->trans('DateCreation').': '.dol_print_date($object->date_creation, 'dayhour').'</div>';
    }
    if (!empty($object->date_modification)) {
        print '<div>'.$langs->trans('DateModification').': '.dol_print_date($object->date_modification, 'dayhour').'</div>';
    }
    print '</div>';
    print '</div>';

    foreach ($alerts as $alert) {
        print '<div class="safra-activity-alert '.$alert['type'].'">'.$alert['message'].'</div>';
    }

    print '<div class="safra-activity-actions">';
    $tokenField = '<input type="hidden" name="token" value="'.newToken().'">';
    if ($object->status == SfActivity::STATUS_DRAFT) {
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
        print $tokenField;
        print '<input type="hidden" name="action" value="validate">';
        print '<button type="submit" class="primary">'.$langs->trans('Validate').'</button>';
        print '</form>';
    }
    if ($object->status == SfActivity::STATUS_VALIDATED) {
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
        print $tokenField;
        print '<input type="hidden" name="action" value="start">';
        print '<button type="submit" class="success">'.$langs->trans('SafraActivityStart').'</button>';
        print '</form>';
    }
    if (in_array($object->status, array(SfActivity::STATUS_VALIDATED, SfActivity::STATUS_IN_PROGRESS), true)) {
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
        print $tokenField;
        print '<input type="hidden" name="action" value="complete">';
        print '<button type="submit" class="success">'.$langs->trans('SafraActivityComplete').'</button>';
        print '</form>';
    }
    if (in_array($object->status, array(SfActivity::STATUS_VALIDATED, SfActivity::STATUS_IN_PROGRESS), true)) {
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
        print $tokenField;
        print '<input type="hidden" name="action" value="cancel">';
        print '<button type="submit" class="danger">'.$langs->trans('Cancel').'</button>';
        print '</form>';
    }
    if (in_array($object->status, array(SfActivity::STATUS_COMPLETED, SfActivity::STATUS_CANCELED), true)) {
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
        print $tokenField;
        print '<input type="hidden" name="action" value="reopen">';
        print '<button type="submit" class="secondary">'.$langs->trans('ReOpen').'</button>';
        print '</form>';
    }
    print '</div>';

    print '<div class="safra-activity-tabs">';
    print '<div class="safra-activity-tab-buttons">';
    print '<button type="button" class="active" data-target="tab-general">'.$langs->trans('SafraActivityGeneralTab').'</button>';
    print '<button type="button" data-target="tab-lines">'.$langs->trans('SafraActivityLinesTab').'</button>';
    print '<button type="button" data-target="tab-timeline">'.$langs->trans('SafraActivityTimelineTab').'</button>';
    print '</div>';

    print '<div id="tab-general" class="safra-activity-tab active">';
    if (!empty($object->note_public) || !empty($object->note_private)) {
        print '<h3>'.$langs->trans('Notes').'</h3>';
        if (!empty($object->note_public)) {
            print '<div><strong>'.$langs->trans('NotePublic').':</strong> '.nl2br(dol_escape_htmltag($object->note_public)).'</div>';
        }
        if (!empty($object->note_private)) {
            print '<div><strong>'.$langs->trans('NotePrivate').':</strong> '.nl2br(dol_escape_htmltag($object->note_private)).'</div>';
        }
    } else {
        print '<p class="opacitymedium">'.$langs->trans('SafraActivityNoNotes').'</p>';
    }
    print '</div>';

    print '<div id="tab-lines" class="safra-activity-tab">';
    if (!empty($object->lines)) {
        $productCache = array();
        $warehouseCache = array();
        print '<table class="safra-activity-lines-table">';
        print '<tr>';
        print '<th>'.$langs->trans('Product').'</th>';
        print '<th>'.$langs->trans('Label').'</th>';
        print '<th>'.$langs->trans('Dose').'</th>';
        print '<th>'.$langs->trans('SafraActivityAreaHa').'</th>';
        print '<th>'.$langs->trans('Total').'</th>';
        print '<th>'.$langs->trans('SafraLineMovement').'</th>';
        print '<th>'.$langs->trans('Warehouse').'</th>';
        print '</tr>';
        foreach ($object->lines as $line) {
            print '<tr>';
            $productLabel = '';
            if (!empty($line->fk_product)) {
                if (!isset($productCache[$line->fk_product])) {
                    $prod = new Product($db);
                    if ($prod->fetch($line->fk_product) > 0) {
                        $productCache[$line->fk_product] = $prod->getNomUrl(1);
                    } else {
                        $productCache[$line->fk_product] = '#'.$line->fk_product;
                    }
                }
                $productLabel = $productCache[$line->fk_product];
            }
            print '<td>'.$productLabel.'</td>';
            print '<td>'.dol_escape_htmltag($line->label).'</td>';
            $doseLabel = '';
            if (!empty($line->dose)) {
                $doseLabel = price2num($line->dose, 4).' '.dol_escape_htmltag($line->dose_unit);
            }
            print '<td>'.$doseLabel.'</td>';
            print '<td>'.dol_print_decimal($line->area_ha, 2).'</td>';
            print '<td>'.dol_print_decimal($line->total_qty, 2).'</td>';
            $movementLabel = isset($line->movement) && (int) $line->movement === 0 ? 'SafraLineMovementReceive' : 'SafraLineMovementConsume';
            print '<td>'.$langs->trans($movementLabel).'</td>';
            $warehouseLabel = '';
            if (!empty($line->fk_entrepot)) {
                if (!isset($warehouseCache[$line->fk_entrepot])) {
                    $wh = new Entrepot($db);
                    if ($wh->fetch($line->fk_entrepot) > 0) {
                        $warehouseCache[$line->fk_entrepot] = $wh->getNomUrl(1);
                    } else {
                        $warehouseCache[$line->fk_entrepot] = '#'.$line->fk_entrepot;
                    }
                }
                $warehouseLabel = $warehouseCache[$line->fk_entrepot];
            }
            print '<td>'.$warehouseLabel.'</td>';
            print '</tr>';
        }
        print '</table>';
    } else {
        print '<p class="opacitymedium">'.$langs->trans('None').'</p>';
    }

    print '<div class="safra-activity-stock-summary">';
    print '<h3>'.$langs->trans('SafraActivityStockSummary').'</h3>';
    if (!empty($stockSummary)) {
        print '<table>';
        print '<tr><th>'.$langs->trans('Product').'</th><th>'.$langs->trans('Warehouse').'</th><th>'.$langs->trans('SafraLineMovement').'</th><th class="right">'.$langs->trans('Quantity').'</th></tr>';
        foreach ($stockSummary as $entry) {
            $productLabel = '';
            $productId = empty($entry['fk_product']) ? 0 : (int) $entry['fk_product'];
            if ($productId > 0) {
                if (!isset($productCache[$productId])) {
                    $prod = new Product($db);
                    if ($prod->fetch($productId) > 0) {
                        $productCache[$productId] = $prod->getNomUrl(1);
                    } else {
                        $productCache[$productId] = '#'.$productId;
                    }
                }
                $productLabel = $productCache[$productId];
            }
            $warehouseLabel = '';
            $warehouseId = empty($entry['fk_entrepot']) ? 0 : (int) $entry['fk_entrepot'];
            if ($warehouseId > 0) {
                if (!isset($warehouseCache[$warehouseId])) {
                    $wh = new Entrepot($db);
                    if ($wh->fetch($warehouseId) > 0) {
                        $warehouseCache[$warehouseId] = $wh->getNomUrl(1);
                    } else {
                        $warehouseCache[$warehouseId] = '#'.$warehouseId;
                    }
                }
                $warehouseLabel = $warehouseCache[$warehouseId];
            }
            $movementLabel = !empty($entry['movement']) ? 'SafraLineMovementConsume' : 'SafraLineMovementReceive';
            print '<tr>';
            print '<td>'.$productLabel.'</td>';
            print '<td>'.$warehouseLabel.'</td>';
            print '<td>'.$langs->trans($movementLabel).'</td>';
            print '<td class="right">'.dol_print_decimal($entry['qty'], 2).'</td>';
            print '</tr>';
        }
        print '</table>';
    } else {
        print '<p class="opacitymedium">'.$langs->trans('None').'</p>';
    }
    print '</div>';
    print '</div>';

    print '<div id="tab-timeline" class="safra-activity-tab">';
    $timeline = array();
    if (!empty($object->date_creation)) {
        $timeline[] = array(
            'label' => $langs->trans('RecordCreated'),
            'date' => $object->date_creation,
            'user' => '',
        );
    }
    if (!empty($object->date_modification)) {
        $timeline[] = array(
            'label' => $langs->trans('RecordModified'),
            'date' => $object->date_modification,
            'user' => '',
        );
    }
    $sqlTimeline = 'SELECT a.id, a.label, a.datep as date_event, a.code, a.note, u.rowid as user_id, u.firstname, u.lastname, u.login'
        .' FROM '.MAIN_DB_PREFIX."actioncomm AS a"
        .' LEFT JOIN '.MAIN_DB_PREFIX."user AS u ON u.rowid = a.fk_user_author"
        .' WHERE a.elementtype IN (\'safra_aplicacao\', \'safra_activity\') AND a.fk_element = '.((int) $object->id)
        .' ORDER BY a.datep DESC'
        .' LIMIT 25';
    $resqlTimeline = $db->query($sqlTimeline);
    if ($resqlTimeline) {
        while ($row = $db->fetch_object($resqlTimeline)) {
            $timeline[] = array(
                'label' => $row->label,
                'date' => $db->jdate($row->date_event),
                'user' => dolGetFirstLastname($row->firstname, $row->lastname, $row->login),
                'note' => $row->note,
            );
        }
        $db->free($resqlTimeline);
    }
    if (!empty($timeline)) {
        print '<ul class="safra-activity-timeline">';
        foreach ($timeline as $event) {
            print '<li>';
            if (!empty($event['date'])) {
                print '<time>'.dol_print_date($event['date'], 'dayhour').'</time>';
            }
            print '<div><strong>'.dol_escape_htmltag($event['label']).'</strong>';
            if (!empty($event['user'])) {
                print ' &middot; '.dol_escape_htmltag($event['user']);
            }
            print '</div>';
            if (!empty($event['note'])) {
                print '<div class="opacitymedium">'.nl2br(dol_escape_htmltag($event['note'])).'</div>';
            }
            print '</li>';
        }
        print '</ul>';
    } else {
        print '<p class="opacitymedium">'.$langs->trans('SafraActivityTimelineEmpty').'</p>';
    }
    print '</div>';

    print '</div>'; // tabs
    print '</div>'; // fichehalfleft
    print '<div class="fichehalfright">';
    print '</div>';
    print '</div>'; // fichecenter

    print '<script>
    document.querySelectorAll(".safra-activity-tab-buttons button").forEach(function(btn){
        btn.addEventListener("click", function(){
            document.querySelectorAll(".safra-activity-tab-buttons button").forEach(function(b){b.classList.remove("active");});
            document.querySelectorAll(".safra-activity-tab").forEach(function(tab){tab.classList.remove("active");});
            btn.classList.add("active");
            var target = document.getElementById(btn.getAttribute("data-target"));
            if(target){target.classList.add("active");}
        });
    });
    </script>';
} else {
    print '<div class="center opacitymedium">'.$langs->trans('ErrorSafraActivityInvalidIdentifier').'</div>';
}

llxFooter();
$db->close();
