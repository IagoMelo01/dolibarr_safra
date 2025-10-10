<?php
/*
 * Assistant for creating crop applications.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
        $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && !empty($_SERVER['SCRIPT_FILENAME'])) {
        $tmp = $_SERVER['SCRIPT_FILENAME'];
        $tmp2 = realpath(__FILE__);
        $i = strlen($tmp) - 1;
        $j = strlen($tmp2) - 1;
        while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
                $i--;
                $j--;
        }
        if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
                $res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
        }
        if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
                $res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
        }
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';

dol_include_once('/safra/class/aplicacao.class.php');

$langs->loadLangs(array('safra@safra', 'projects', 'products', 'stocks'));

$action = GETPOST('action', 'aZ09');

$form = new Form($db);
$formProject = new FormProjets($db);

$errors = array();
$messages = array();

/**
 * Check if a table exists in current database.
 */
function safraTableExists(DoliDB $db, $tablename)
{
        $info = $db->DDLDescTable(MAIN_DB_PREFIX.$tablename, $tablename);
        return is_array($info);
}

/**
 * Fetch a list of reference=>label pairs.
 */
function safraFetchPairs(DoliDB $db, $tablename, $labelFields, $where = '')
{
        $records = array();
        $table = MAIN_DB_PREFIX.$tablename;

        $fields = array('rowid');
        foreach ($labelFields as $alias => $field) {
                $fields[] = $field.' AS '.$alias;
        }

        $sql = 'SELECT '.implode(', ', $fields).' FROM '.$table;
        if ($where !== '') {
                $sql .= ' WHERE '.$where;
        }
        $sql .= ' ORDER BY '.$labelFields['ref'].' ASC';
        $resql = $db->query($sql);
        if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                        $label = $obj->ref;
                        if (!empty($obj->label)) {
                                $label .= ' - '.$obj->label;
                        }
                        $records[(int) $obj->rowid] = $label;
                }
                $db->free($resql);
        }

        return $records;
}

function safraFetchProducts(DoliDB $db)
{
        $records = array();
        $sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX."product";
        $sql .= ' WHERE entity IN ('.getEntity('product').')';
        $sql .= ' ORDER BY ref ASC LIMIT 500';
        $resql = $db->query($sql);
        if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                        $label = $obj->ref;
                        if (!empty($obj->label)) {
                                $label .= ' - '.$obj->label;
                        }
                        $records[(int) $obj->rowid] = $label;
                }
                $db->free($resql);
        }

        return $records;
}

function safraFetchWarehouseProducts(DoliDB $db)
{
        $records = array();

        if (!safraTableExists($db, 'product_stock')) {
                return $records;
        }

        $sql = 'SELECT ps.fk_entrepot, p.rowid, p.ref, p.label'
                .' FROM '.MAIN_DB_PREFIX.'product_stock AS ps'
                .' INNER JOIN '.MAIN_DB_PREFIX."product AS p ON p.rowid = ps.fk_product";
        $sql .= ' WHERE p.entity IN ('.getEntity('product').')';
        $sql .= ' AND ps.reel > 0';
        $sql .= ' ORDER BY ps.fk_entrepot ASC, p.ref ASC';

        $resql = $db->query($sql);
        if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                        $warehouseId = (int) $obj->fk_entrepot;
                        $productId = (int) $obj->rowid;
                        if ($warehouseId <= 0 || $productId <= 0) {
                                continue;
                        }

                        if (!isset($records[$warehouseId])) {
                                $records[$warehouseId] = array();
                        }

                        $label = $obj->ref;
                        if (!empty($obj->label)) {
                                $label .= ' - '.$obj->label;
                        }

                        $records[$warehouseId][$productId] = $label;
                }
                $db->free($resql);
        }

        return $records;
}

function safraFetchTalhoes(DoliDB $db)
{
        $talhoes = array();

        if (!safraTableExists($db, 'safra_talhao')) {
                return $talhoes;
        }

        $sql = 'SELECT t.rowid, t.ref, t.label, t.area, t.municipio, m.label AS municipio_label'
                .' FROM '.MAIN_DB_PREFIX.'safra_talhao AS t'
                .' LEFT JOIN '.MAIN_DB_PREFIX.'safra_municipio AS m ON m.rowid = t.municipio'
                .' ORDER BY t.ref ASC';

        $resql = $db->query($sql);
        if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                        $talhaoId = (int) $obj->rowid;
                        if ($talhaoId <= 0) {
                                continue;
                        }

                        $labelParts = array();
                        if (!empty($obj->ref)) {
                                $labelParts[] = $obj->ref;
                        }
                        if (!empty($obj->label)) {
                                $labelParts[] = $obj->label;
                        }

                        $talhoes[$talhaoId] = array(
                                'id' => $talhaoId,
                                'label' => implode(' - ', $labelParts),
                                'area' => (float) $obj->area,
                                'municipio' => $obj->municipio_label,
                                'url' => dol_buildpath('/safra/talhao_card.php', 1).'?id='.$talhaoId,
                        );
                }
                $db->free($resql);
        }

        return $talhoes;
}

function safraFetchProjectTalhaoMap(DoliDB $db)
{
        $map = array();

        if (!isModEnabled('project') || !safraTableExists($db, 'projet_extrafields')) {
                return $map;
        }

        $sql = 'SELECT fk_object AS project_id, options_fk_talhao AS talhao_id'
                .' FROM '.MAIN_DB_PREFIX.'projet_extrafields'
                .' WHERE options_fk_talhao IS NOT NULL AND options_fk_talhao <> ""';

        $resql = $db->query($sql);
        if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                        $projectId = (int) $obj->project_id;
                        $talhaoId = (int) $obj->talhao_id;
                        if ($projectId > 0 && $talhaoId > 0) {
                                $map[$projectId] = $talhaoId;
                        }
                }
                $db->free($resql);
        }

        return $map;
}

$products = safraFetchProducts($db);
$warehouseProductOptions = safraFetchWarehouseProducts($db);
$talhaoList = safraFetchTalhoes($db);
$projectTalhaoMap = safraFetchProjectTalhaoMap($db);
$vehicleOptions = safraTableExists($db, 'frota_veiculo') ? safraFetchPairs($db, 'frota_veiculo', array('ref' => 'placa', 'label' => 'descricao')) : array();
$implementOptions = safraTableExists($db, 'frota_implemento') ? safraFetchPairs($db, 'frota_implemento', array('ref' => 'ref', 'label' => 'descricao')) : array();
$warehouseOptions = array();
if (safraTableExists($db, 'entrepot')) {
        $sqlWarehouses = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX."entrepot";
        $sqlWarehouses .= ' WHERE entity IN ('.getEntity('stock').')';
        $sqlWarehouses .= ' ORDER BY ref ASC';
        $resWarehouses = $db->query($sqlWarehouses);
        if ($resWarehouses) {
                while ($obj = $db->fetch_object($resWarehouses)) {
                        $label = $obj->ref;
                        if (!empty($obj->label)) {
                                $label .= ' - '.$obj->label;
                        }
                        $warehouseOptions[(int) $obj->rowid] = $label;
                }
                $db->free($resWarehouses);
        }
}

$resourcePersons = array();
$sqlUsers = 'SELECT rowid, firstname, lastname, login FROM '.MAIN_DB_PREFIX."user WHERE statut = 1 ORDER BY lastname";
$resUsers = $db->query($sqlUsers);
if ($resUsers) {
        while ($obj = $db->fetch_object($resUsers)) {
                $label = trim($obj->firstname.' '.$obj->lastname);
                if ($label === '') {
                        $label = $obj->login;
                }
                $resourcePersons[(int) $obj->rowid] = $label;
        }
        $db->free($resUsers);
}

$postedVehicles = GETPOST('vehicles', 'array');
if (!is_array($postedVehicles)) {
        $postedVehicles = array();
}
$postedImplements = GETPOST('implements', 'array');
if (!is_array($postedImplements)) {
        $postedImplements = array();
}
$postedPersons = GETPOST('persons', 'array');
if (!is_array($postedPersons)) {
        $postedPersons = array();
}

$postedLines = GETPOST('lines', 'array');
if (!is_array($postedLines)) {
        $postedLines = array();
}

function safraCleanIdList($values)
{
        $clean = array();
        foreach ($values as $value) {
                $id = (int) $value;
                if ($id > 0) {
                        $clean[$id] = $id;
                }
        }

        return array_values($clean);
}

$vehicleSelected = safraCleanIdList($postedVehicles);
$implementSelected = safraCleanIdList($postedImplements);
$personSelected = safraCleanIdList($postedPersons);

$selectedTalhaoId = (int) GETPOST('talhao_id', 'int');
if ($selectedTalhaoId <= 0) {
        $selectedTalhaoId = 0;
}

$postedProjectId = GETPOST('fk_project', 'int');
if ($selectedTalhaoId === 0 && $postedProjectId > 0 && isset($projectTalhaoMap[$postedProjectId]) && isset($talhaoList[$projectTalhaoMap[$postedProjectId]])) {
        $selectedTalhaoId = (int) $projectTalhaoMap[$postedProjectId];
}

if ($selectedTalhaoId > 0 && !isset($talhaoList[$selectedTalhaoId])) {
        $selectedTalhaoId = 0;
}

$talhaoDetails = $selectedTalhaoId > 0 ? $talhaoList[$selectedTalhaoId] : null;

$prefillLines = array();
foreach ($postedLines as $line) {
        if (!is_array($line)) {
                continue;
        }

        $prefillLines[] = array(
                'fk_entrepot' => isset($line['fk_entrepot']) ? (int) $line['fk_entrepot'] : 0,
                'fk_product' => isset($line['fk_product']) ? (int) $line['fk_product'] : 0,
                'area_ha' => isset($line['area_ha']) ? (string) $line['area_ha'] : '',
                'dose' => isset($line['dose']) ? (string) $line['dose'] : '',
                'dose_unit' => isset($line['dose_unit']) ? (string) $line['dose_unit'] : '',
                'total_qty' => isset($line['total_qty']) ? (string) $line['total_qty'] : '',
                'note' => isset($line['note']) ? (string) $line['note'] : '',
        );
}

if ($action === 'save') {
        if (!GETPOST('token') || !dol_verify_token(GETPOST('token'))) {
                accessforbidden('Bad token');
        }

        $ref = trim(GETPOST('ref', 'alpha'));
        $label = trim(GETPOST('label', 'alphanohtml'));
        $fkProject = GETPOST('fk_project', 'int');
        $areaInput = GETPOST('qty', 'alphanohtml');
        $areaHa = $areaInput !== '' ? (double) str_replace(',', '.', $areaInput) : 0;
        if ($areaHa <= 0 && $talhaoDetails && !empty($talhaoDetails['area'])) {
                $areaHa = (double) $talhaoDetails['area'];
        }
        $applicationDate = dol_mktime(0, 0, 0, GETPOST('date_applicationmonth', 'int'), GETPOST('date_applicationday', 'int'), GETPOST('date_applicationyear', 'int'));
        $description = GETPOST('description', 'restricthtml');
        $caldaObservacao = trim(GETPOST('calda_observacao', 'restricthtml'));

        if ($ref === '') {
                $errors[] = $langs->trans('ErrorFieldRequired', $langs->trans('Ref'));
        }

        if ($selectedTalhaoId <= 0) {
                $errors[] = $langs->trans('SafraAplicacaoErrorTalhaoRequired');
        }

        $lineData = GETPOST('lines', 'array');
        $lineArray = array();
        if (is_array($lineData)) {
                foreach ($lineData as $line) {
                        $fkProduct = empty($line['fk_product']) ? 0 : (int) $line['fk_product'];
                        $dose = isset($line['dose']) ? (double) str_replace(',', '.', $line['dose']) : 0;
                        $doseUnit = isset($line['dose_unit']) ? $line['dose_unit'] : '';
                        $lineArea = isset($line['area_ha']) ? (double) str_replace(',', '.', $line['area_ha']) : $areaHa;
                        $totalQty = isset($line['total_qty']) ? (double) str_replace(',', '.', $line['total_qty']) : ($dose * $lineArea);

                        if ($fkProduct <= 0 || $dose <= 0 || $totalQty <= 0) {
                                continue;
                        }

                        $lineEntry = array(
                                'fk_product' => $fkProduct,
                                'fk_entrepot' => empty($line['fk_entrepot']) ? 0 : (int) $line['fk_entrepot'],
                                'label' => isset($line['label']) ? trim($line['label']) : '',
                                'dose' => $dose,
                                'dose_unit' => $doseUnit,
                                'area_ha' => $lineArea,
                                'total_qty' => $totalQty,
                                'note' => isset($line['note']) ? trim($line['note']) : '',
                        );

                        if ($lineEntry['label'] === '' && isset($products[$fkProduct])) {
                                $lineEntry['label'] = $products[$fkProduct];
                        }

                        $lineArray[] = $lineEntry;
                }
        }

        if (empty($lineArray)) {
                $errors[] = $langs->trans('ErrorFieldRequired', $langs->trans('Products'));
        }

        if (empty($errors)) {
                $application = new Aplicacao($db);
                $application->ref = $ref;
                $application->label = $label;
                $application->fk_project = $fkProject > 0 ? $fkProject : null;
                $application->qty = $areaHa;
                $application->date_application = $applicationDate ?: null;
                $application->description = $description;
                $application->calda_observacao = $caldaObservacao;
                $application->status = Aplicacao::STATUS_DRAFT;

                $res = $application->create($user);
                if ($res > 0) {
                        $application->replaceLines($lineArray);

                        $resourcePayload = array();

                        foreach ($vehicleSelected as $id) {
                                if (isset($vehicleOptions[$id])) {
                                        $resourcePayload[] = array(
                                                'type' => 'vehicle',
                                                'fk_target' => $id,
                                                'label' => $vehicleOptions[$id],
                                        );
                                }
                        }

                        foreach ($implementSelected as $id) {
                                if (isset($implementOptions[$id])) {
                                        $resourcePayload[] = array(
                                                'type' => 'implement',
                                                'fk_target' => $id,
                                                'label' => $implementOptions[$id],
                                        );
                                }
                        }

                        foreach ($personSelected as $id) {
                                if (isset($resourcePersons[$id])) {
                                        $resourcePayload[] = array(
                                                'type' => 'person',
                                                'fk_target' => $id,
                                                'label' => $resourcePersons[$id],
                                        );
                                }
                        }

                        $application->replaceResources($resourcePayload);

                        // Create project task if project selected
                        if (!empty($application->fk_project) && isModEnabled('project')) {
                                require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
                                $task = new Task($db);
                                $task->fk_project = $application->fk_project;
                                $task->label = $application->label ?: $application->ref;
                                $task->description = $application->buildTaskDescription();
                                $task->date_start = $applicationDate ?: dol_now();
                                $task->date_end = $applicationDate ?: dol_now();
                                $task->progress = 0;
                                if ($task->create($user) > 0) {
                                        $application->fk_task = $task->id;
                                        $application->updateCommon('', array('fk_task' => $task->id));
                                }
                        }

                        setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                        header('Location: '.dol_buildpath('/safra/aplicacao_card.php', 1).'?id='.$application->id);
                        exit;
                } else {
                        $errors[] = $application->error ?: $application->errors;
                }
        }
}

$title = $langs->trans('Aplicacao').' - '.$langs->trans('Create');
llxHeader('', $title);

print load_fiche_titre($langs->trans('AplicacaoCreateAssistant'), '', 'fa-fill-drip');

print '<style>
#calda-modal {position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);display:none;align-items:center;justify-content:center;z-index:1000;}
#calda-modal .modal-dialog {background:#fff;padding:20px;max-width:420px;width:90%;box-shadow:0 2px 10px rgba(0,0,0,0.3);}
#calda-modal .modal-header {margin-bottom:10px;}
#calda-modal .modal-body label {display:block;margin-top:10px;}
#calda-modal .modal-body input,#calda-modal .modal-body textarea {width:100%;}
#calda-modal .modal-footer {margin-top:15px;text-align:right;}
</style>';

if (!empty($errors)) {
        setEventMessages('', $errors, 'errors');
}
if (!empty($messages)) {
        setEventMessages('', $messages, 'mesgs');
}

print '<form method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" id="aplicacao-assistant-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="calda_observacao" id="calda-observacao" value="'.dol_escape_htmltag(GETPOST('calda_observacao', 'alphanohtml')).'">';


$areaFieldValue = GETPOST('qty', 'alphanohtml');
if ($areaFieldValue === '' && $talhaoDetails && !empty($talhaoDetails['area'])) {
        $areaFieldValue = sprintf('%.4f', (float) $talhaoDetails['area']);
}
if ($areaFieldValue === '') {
        $areaFieldValue = '0';
}

$talhaoInfoHtml = dol_escape_htmltag($langs->trans('SafraAplicacaoTalhaoNotLinked'));
if ($talhaoDetails) {
        $infoParts = array();
        $talhaoLabel = dol_escape_htmltag($talhaoDetails['label']);
        if (!empty($talhaoDetails['url'])) {
                $talhaoUrl = dol_escape_htmltag($talhaoDetails['url']);
                $talhaoLabel = '<a href="'.$talhaoUrl.'" target="_blank" rel="noopener">'.$talhaoLabel.'</a>';
        }
        $infoParts[] = $talhaoLabel;
        if (!empty($talhaoDetails['area'])) {
                $infoParts[] = dol_escape_htmltag($langs->trans('SafraAplicacaoTalhaoAreaFormat', sprintf('%.2f', (float) $talhaoDetails['area'])));
        }
        if (!empty($talhaoDetails['municipio'])) {
                $infoParts[] = dol_escape_htmltag($langs->trans('SafraAplicacaoTalhaoMunicipioFormat', $talhaoDetails['municipio']));
        }
        $talhaoInfoHtml = implode('<br>', $infoParts);
}

$talhaoDependentAttr = $selectedTalhaoId > 0 ? '' : ' disabled';
print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr><td class="fieldrequired">'.$langs->trans('Ref').'</td><td><input type="text" name="ref" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('ref', 'alphanohtml')).'" required></td></tr>';
print '<tr><td>'.$langs->trans('Label').'</td><td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('label', 'alphanohtml')).'"></td></tr>';
print '<tr><td>'.$langs->trans('Project').'</td><td>'.$formProject->select_projects(-1, GETPOST('fk_project', 'int'), 'fk_project', 0, 0, 1, 1, '', 0, '', '', 1, 0, 0, '', 1).'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('SafraAplicacaoTalhao').'</td><td><select name="talhao_id" id="talhao-select" class="minwidth300">';
print '<option value="">'.$langs->trans('SafraAplicacaoSelectTalhao').'</option>';
foreach ($talhaoList as $talhaoId => $talhao) {
        $selectedAttr = ((int) $talhaoId === (int) $selectedTalhaoId) ? ' selected' : '';
        print '<option value="'.$talhaoId.'"'.$selectedAttr.'>'.dol_escape_htmltag($talhao['label']).'</option>';
}
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('SafraAplicacaoTalhaoInfo').'</td><td><div id="talhao-info" class="opacitymedium">'.$talhaoInfoHtml.'</div></td></tr>';
print '<tr><td>'.$langs->trans('SafraAplicacaoAreaHa').'</td><td><input type="number" step="0.0001" min="0" name="qty" id="application-area" class="requires-talhao" value="'.dol_escape_htmltag($areaFieldValue).'"'.$talhaoDependentAttr.'></td></tr>';
print '<tr><td>'.$langs->trans('SafraAplicacaoDate').'</td><td>'.$form->select_date(GETPOST('date_application', 'alpha'), 'date_application', 0, 0, 0, 'aplicacao-assistant-form').'</td></tr>';
print '<tr><td>'.$langs->trans('Description').'</td><td><textarea name="description" class="centpercent" rows="4">'.dol_escape_htmltag(GETPOST('description', 'restricthtml')).'</textarea></td></tr>';
print '</table>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="titre">'.$langs->trans('SafraAplicacaoTaskProducts').'</div>';
print '<table class="noborder centpercent" id="product-lines-table">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Warehouse').'</th>';
print '<th>'.$langs->trans('Product').'</th>';
print '<th>'.$langs->trans('SafraAplicacaoAreaHa').'</th>';
print '<th>'.$langs->trans('Dose').'</th>';
print '<th>'.$langs->trans('Unit').'</th>';
print '<th>'.$langs->trans('Total').'</th>';
print '<th>'.$langs->trans('Notes').'</th>';
print '<th>'.$langs->trans('Action').'</th>';
print '</tr>';
print '</table>';
print '<div class="right"><button type="button" class="button requires-talhao" id="add-product-line"'.$talhaoDependentAttr.'>'.$langs->trans('Add').'</button></div>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="titre">'.$langs->trans('SafraAplicacaoResources').'</div>';
print '<table class="border centpercent">';
print '<tr><td>'.$langs->trans('SafraAplicacaoResourceVehicle').'</td><td><select name="vehicles[]" multiple class="minwidth300 resource-select requires-talhao" data-placeholder="'.$langs->trans('Select').'"'.$talhaoDependentAttr.'>';
foreach ($vehicleOptions as $id => $labelOption) {
        $selectedAttr = in_array($id, $vehicleSelected, true) ? ' selected' : '';
        print '<option value="'.$id.'"'.$selectedAttr.'>'.dol_escape_htmltag($labelOption).'</option>';
}
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('SafraAplicacaoResourceImplement').'</td><td><select name="implements[]" multiple class="minwidth300 resource-select requires-talhao" data-placeholder="'.$langs->trans('Select').'"'.$talhaoDependentAttr.'>';
foreach ($implementOptions as $id => $labelOption) {
        $selectedAttr = in_array($id, $implementSelected, true) ? ' selected' : '';
        print '<option value="'.$id.'"'.$selectedAttr.'>'.dol_escape_htmltag($labelOption).'</option>';
}
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('SafraAplicacaoResourcePerson').'</td><td><select name="persons[]" multiple class="minwidth300 resource-select requires-talhao" data-placeholder="'.$langs->trans('Select').'"'.$talhaoDependentAttr.'>';
foreach ($resourcePersons as $id => $labelOption) {
        $selectedAttr = in_array($id, $personSelected, true) ? ' selected' : '';
        print '<option value="'.$id.'"'.$selectedAttr.'>'.dol_escape_htmltag($labelOption).'</option>';
}
print '</select></td></tr>';
print '</table>';
print '</div>';

print '<div class="center marginbottom">';
print '<button type="button" class="button requires-talhao" id="open-calda-modal"'.$talhaoDependentAttr.'>'.$langs->trans('SafraAplicacaoCaldaObservation').'</button> ';
print '<button type="submit" class="button button-save">'.$langs->trans('Create').'</button>';
print '</form>';

print '<div id="calda-modal" class="modal" style="display:none;">';
print '<div class="modal-dialog">';
print '<div class="modal-header"><h3>'.$langs->trans('SafraAplicacaoCaldaObservation').'</h3></div>';
print '<div class="modal-body">';
print '<label>'.$langs->trans('SafraAplicacaoAreaHa').'</label><input type="number" step="0.0001" id="modal-area" class="minwidth100" value="0"><br>';
print '<label>'.$langs->trans('Dose').'</label><input type="number" step="0.0001" id="modal-dose" class="minwidth100" value="0"><br>';
print '<label>'.$langs->trans('Unit').'</label><input type="text" id="modal-unit" class="minwidth100" value="L/ha"><br>';
print '<label>'.$langs->trans('Total').'</label><input type="text" id="modal-total" class="minwidth100" readonly><br>';
print '<label>'.$langs->trans('Notes').'</label><textarea id="modal-note" rows="4" class="centpercent"></textarea>';
print '</div>';
print '<div class="modal-footer">';
print '<button type="button" class="button" id="modal-save">'.$langs->trans('Save').'</button> ';
print '<button type="button" class="button" id="modal-cancel">'.$langs->trans('Cancel').'</button>';
print '</div>';
print '</div>';
print '</div>';

$productOptionsJson = json_encode((object) $products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$warehouseOptionsJson = json_encode((object) $warehouseOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$warehouseProductJson = json_encode((object) $warehouseProductOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$talhaoDataJson = json_encode((object) $talhaoList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$projectTalhaoJson = json_encode((object) $projectTalhaoMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$prefillLinesJson = json_encode($prefillLines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$talhaoDefaultJson = json_encode($langs->trans('SafraAplicacaoTalhaoNotLinked'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$talhaoAreaLabelJson = json_encode($langs->trans('SafraAplicacaoTalhaoAreaFormat', '%s'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$talhaoMunicipioLabelJson = json_encode($langs->trans('SafraAplicacaoTalhaoMunicipioFormat', '%s'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$noProductsLabelJson = json_encode($langs->trans('SafraAplicacaoNoWarehouseProducts'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$newProductLabelJson = json_encode($langs->trans('SafraAplicacaoAddNewProduct'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$selectLabelJson = json_encode($langs->trans('Select'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$addProductUrlJson = json_encode(dol_buildpath('/product/card.php', 1).'?action=create', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$initialTalhaoJson = json_encode($selectedTalhaoId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

print '<script>';
print 'document.addEventListener("DOMContentLoaded", function () {';
print 'const productOptions = '.($productOptionsJson ?: '{}').';';
print 'const warehouseOptions = '.($warehouseOptionsJson ?: '{}').';';
print 'const warehouseProductMap = '.($warehouseProductJson ?: '{}').';';
print 'const talhaoData = '.($talhaoDataJson ?: '{}').';';
print 'const projectTalhao = '.($projectTalhaoJson ?: '{}').';';
print 'const prefillLines = '.($prefillLinesJson ?: '[]').';';
print 'const talhaoDefaultInfo = '.($talhaoDefaultJson ?: '""').';';
print 'const talhaoAreaLabel = '.($talhaoAreaLabelJson ?: '""').';';
print 'const talhaoMunicipioLabel = '.($talhaoMunicipioLabelJson ?: '""').';';
print 'const noProductsLabel = '.($noProductsLabelJson ?: '""').';';
print 'const newProductLabel = '.($newProductLabelJson ?: '""').';';
print 'const selectLabel = '.($selectLabelJson ?: '""').';';
print 'const addProductUrl = '.($addProductUrlJson ?: '""').';';
print 'const initialTalhaoId = '.($initialTalhaoJson ?: '0').';';
print 'let talhaoManual = false;';
print 'let talhaoActive = false;';
print 'let lineIndex = 0;';
print 'const prefillData = Array.isArray(prefillLines) ? prefillLines : [];';
print <<<'SAFRA_JS'
const talhaoSelect = document.getElementById("talhao-select");
const talhaoInfo = document.getElementById("talhao-info");
const projectSelect = document.getElementById("fk_project");
const areaField = document.getElementById("application-area");
const addLineButton = document.getElementById("add-product-line");
const linesTable = document.getElementById("product-lines-table");
const talhaoDependentNodes = document.querySelectorAll(".requires-talhao");
function escapeHtml(value) {
    if (value === undefined || value === null) { return ""; }
    const div = document.createElement("div");
    div.textContent = value;
    return div.innerHTML;
}
function escapeAttribute(value) {
    if (value === undefined || value === null) { return ""; }
    return String(value).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}
function setRowEnabled(row, enabled) {
    row.querySelectorAll("input, select, textarea, button").forEach(function (el) {
        el.disabled = !enabled;
    });
}
function setTalhaoActive(enabled) {
    talhaoActive = !!enabled;
    talhaoDependentNodes.forEach(function (node) {
        if (talhaoActive) {
            node.removeAttribute("disabled");
        } else {
            node.setAttribute("disabled", "disabled");
        }
    });
    if (linesTable) {
        linesTable.querySelectorAll("tr.oddeven").forEach(function (row) {
            setRowEnabled(row, talhaoActive);
        });
    }
    if (talhaoActive && linesTable && !linesTable.querySelector("tr.oddeven") && prefillData.length === 0) {
        addProductLine();
    }
}
function fillSelect(select, options, includeEmpty, selectedValue) {
    select.innerHTML = "";
    if (includeEmpty) {
        const emptyOption = document.createElement("option");
        emptyOption.value = "";
        emptyOption.textContent = selectLabel || "";
        select.appendChild(emptyOption);
    }
    let hasOption = false;
    if (options) {
        Object.entries(options).forEach(function ([key, label]) {
            const opt = document.createElement("option");
            opt.value = String(key);
            opt.textContent = label;
            if (selectedValue && String(selectedValue) === String(key)) {
                opt.selected = true;
            }
            select.appendChild(opt);
            hasOption = true;
        });
    }
    if (selectedValue && !select.value && productOptions[selectedValue]) {
        const opt = document.createElement("option");
        opt.value = String(selectedValue);
        opt.textContent = productOptions[selectedValue];
        opt.selected = true;
        select.appendChild(opt);
        hasOption = true;
    }
    if (!hasOption) {
        const infoOption = document.createElement("option");
        infoOption.value = "";
        infoOption.textContent = noProductsLabel || "";
        infoOption.disabled = true;
        infoOption.selected = true;
        select.appendChild(infoOption);
    }
}
function refreshProducts(productSelect, warehouseId, selectedValue) {
    const key = warehouseId ? String(warehouseId) : "";
    const options = key && warehouseProductMap[key] ? warehouseProductMap[key] : productOptions;
    fillSelect(productSelect, options, true, selectedValue);
}
function syncLineAreas() {
    if (!talhaoActive || !areaField || !linesTable) { return; }
    const value = areaField.value || "";
    linesTable.querySelectorAll(".line-area").forEach(function (input) {
        if (input.dataset.userEdited !== "1") {
            input.value = value;
            input.dispatchEvent(new Event("input"));
        }
    });
}
function addProductLine(prefill) {
    if (!linesTable) { return; }
    const tr = document.createElement("tr");
    tr.className = "oddeven";
    const idx = lineIndex++;
    const warehouseCell = document.createElement("td");
    const warehouseSelect = document.createElement("select");
    warehouseSelect.name = "lines[" + idx + "][fk_entrepot]";
    warehouseSelect.className = "minwidth200 line-field";
    fillSelect(warehouseSelect, warehouseOptions, true, prefill && prefill.fk_entrepot ? prefill.fk_entrepot : "");
    warehouseCell.appendChild(warehouseSelect);
    tr.appendChild(warehouseCell);
    const productCell = document.createElement("td");
    const productWrapper = document.createElement("div");
    productWrapper.style.display = "flex";
    productWrapper.style.gap = "4px";
    const productSelect = document.createElement("select");
    productSelect.name = "lines[" + idx + "][fk_product]";
    productSelect.className = "minwidth250 line-field";
    productWrapper.appendChild(productSelect);
    const addBtn = document.createElement("button");
    addBtn.type = "button";
    addBtn.className = "button button-small";
    addBtn.textContent = "+";
    addBtn.title = newProductLabel || "+";
    addBtn.addEventListener("click", function () {
        if (addProductUrl) {
            window.open(addProductUrl, "_blank");
        }
    });
    productWrapper.appendChild(addBtn);
    productCell.appendChild(productWrapper);
    tr.appendChild(productCell);
    refreshProducts(productSelect, warehouseSelect.value, prefill && prefill.fk_product ? prefill.fk_product : "");
    warehouseSelect.addEventListener("change", function () {
        refreshProducts(productSelect, warehouseSelect.value, productSelect.value);
    });
    const areaCell = document.createElement("td");
    const areaInput = document.createElement("input");
    areaInput.type = "number";
    areaInput.name = "lines[" + idx + "][area_ha]";
    areaInput.className = "line-area line-field";
    areaInput.step = "0.0001";
    const areaDefault = prefill && prefill.area_ha !== undefined && prefill.area_ha !== null && prefill.area_ha !== "" ? prefill.area_ha : (areaField ? areaField.value || "0" : "0");
    areaInput.value = areaDefault;
    if (areaField && areaField.value && String(areaDefault) === String(areaField.value)) {
        areaInput.dataset.userEdited = "0";
    } else if (prefill && prefill.area_ha) {
        areaInput.dataset.userEdited = "1";
    } else {
        areaInput.dataset.userEdited = "0";
    }
    areaInput.addEventListener("input", function () {
        areaInput.dataset.userEdited = "1";
        recompute();
    });
    areaCell.appendChild(areaInput);
    tr.appendChild(areaCell);
    const doseCell = document.createElement("td");
    const doseInput = document.createElement("input");
    doseInput.type = "number";
    doseInput.name = "lines[" + idx + "][dose]";
    doseInput.className = "line-field";
    doseInput.step = "0.0001";
    doseInput.value = prefill && prefill.dose ? prefill.dose : "0";
    doseInput.addEventListener("input", recompute);
    doseCell.appendChild(doseInput);
    tr.appendChild(doseCell);
    const unitCell = document.createElement("td");
    const unitSelect = document.createElement("select");
    unitSelect.name = "lines[" + idx + "][dose_unit]";
    unitSelect.className = "line-field";
    ["L/ha", "kg/ha"].forEach(function (unit) {
        const opt = document.createElement("option");
        opt.value = unit;
        opt.textContent = unit;
        unitSelect.appendChild(opt);
    });
    if (prefill && prefill.dose_unit) {
        unitSelect.value = prefill.dose_unit;
    }
    unitCell.appendChild(unitSelect);
    tr.appendChild(unitCell);
    const totalCell = document.createElement("td");
    const totalInput = document.createElement("input");
    totalInput.type = "number";
    totalInput.name = "lines[" + idx + "][total_qty]";
    totalInput.className = "line-field";
    totalInput.readOnly = true;
    totalInput.step = "0.0001";
    totalCell.appendChild(totalInput);
    tr.appendChild(totalCell);
    const noteCell = document.createElement("td");
    const noteInput = document.createElement("textarea");
    noteInput.name = "lines[" + idx + "][note]";
    noteInput.className = "line-field";
    noteInput.rows = 1;
    noteInput.value = prefill && prefill.note ? prefill.note : "";
    noteCell.appendChild(noteInput);
    tr.appendChild(noteCell);
    const actionCell = document.createElement("td");
    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "button button-cancel line-remove";
    removeBtn.textContent = "-";
    removeBtn.addEventListener("click", function () {
        tr.remove();
    });
    actionCell.appendChild(removeBtn);
    tr.appendChild(actionCell);
    linesTable.appendChild(tr);
    function recompute() {
        const areaVal = parseFloat(areaInput.value) || 0;
        const doseVal = parseFloat(doseInput.value) || 0;
        totalInput.value = (areaVal * doseVal).toFixed(4);
    }
    recompute();
    if (prefill && prefill.total_qty) {
        totalInput.value = prefill.total_qty;
    }
    setRowEnabled(tr, talhaoActive);
}
function updateTalhaoSelection(value) {
    const id = parseInt(value, 10) || 0;
    if (talhaoSelect) {
        talhaoSelect.value = id ? String(id) : "";
    }
    if (talhaoInfo) {
        if (id && talhaoData[id]) {
            const data = talhaoData[id];
            const parts = [];
            const label = escapeHtml(data.label || "");
            if (data.url) {
                parts.push('<a href="' + escapeAttribute(data.url) + '" target="_blank" rel="noopener">' + label + '</a>');
            } else {
                parts.push(label);
            }
            if (data.area) {
                const areaText = (talhaoAreaLabel || '').replace('%s', (Number(data.area) || 0).toFixed(2));
                parts.push(escapeHtml(areaText));
            }
            if (data.municipio) {
                const municipioText = (talhaoMunicipioLabel || '').replace('%s', data.municipio);
                parts.push(escapeHtml(municipioText));
            }
            talhaoInfo.innerHTML = parts.join('<br>');
        } else {
            talhaoInfo.innerHTML = talhaoDefaultInfo;
        }
    }
    setTalhaoActive(id > 0);
    if (areaField && id > 0 && talhaoData[id] && talhaoData[id].area) {
        const formatted = (Number(talhaoData[id].area) || 0).toFixed(4);
        if (!areaField.dataset.manual || areaField.dataset.manual !== "1") {
            areaField.value = formatted;
            areaField.dataset.autoValue = formatted;
            areaField.dataset.manual = "0";
            syncLineAreas();
        }
    }
}
if (addLineButton) {
    addLineButton.addEventListener("click", function () {
        if (!addLineButton.disabled) {
            addProductLine();
        }
    });
}
if (areaField) {
    areaField.dataset.autoValue = areaField.value || "";
    areaField.dataset.manual = areaField.value && Number(areaField.value) !== 0 ? "1" : "0";
    areaField.addEventListener("input", function () {
        if (talhaoActive && areaField.dataset.autoValue && areaField.value === areaField.dataset.autoValue) {
            areaField.dataset.manual = "0";
        } else if (talhaoActive) {
            areaField.dataset.manual = "1";
        } else {
            areaField.dataset.manual = areaField.value && Number(areaField.value) !== 0 ? "1" : "0";
        }
        syncLineAreas();
    });
}
if (talhaoSelect) {
    talhaoSelect.addEventListener("change", function () {
        talhaoManual = true;
        updateTalhaoSelection(talhaoSelect.value);
    });
}
if (projectSelect) {
    projectSelect.addEventListener("change", function () {
        const projectId = parseInt(projectSelect.value, 10) || 0;
        if (!talhaoManual && projectId && projectTalhao[projectId]) {
            updateTalhaoSelection(projectTalhao[projectId]);
        }
    });
}
prefillData.forEach(function (line) {
    addProductLine(line);
});
updateTalhaoSelection(initialTalhaoId);
if ((!talhaoSelect || !talhaoSelect.value) && projectSelect) {
    const projectId = parseInt(projectSelect.value, 10) || 0;
    if (projectId && projectTalhao[projectId]) {
        updateTalhaoSelection(projectTalhao[projectId]);
    }
}
});
SAFRA_JS;
print '</script>';
llxFooter();
$db->close();
