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
function safraFetchPairs(DoliDB $db, $tablename, $labelFields)
{
        $records = array();
        $table = MAIN_DB_PREFIX.$tablename;

        $fields = array('rowid');
        foreach ($labelFields as $alias => $field) {
                $fields[] = $field.' AS '.$alias;
        }

        $sql = 'SELECT '.implode(', ', $fields).' FROM '.$table.' ORDER BY '.$labelFields['ref'].' ASC';
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
        $sql .= ' ORDER BY ref ASC LIMIT 200';
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

$products = safraFetchProducts($db);
$produtosTecnicos = safraTableExists($db, 'safra_produtostecnicos') ? safraFetchPairs($db, 'safra_produtostecnicos', array('ref' => 'ref', 'label' => 'label')) : array();
$produtosFormulados = safraTableExists($db, 'safra_produto_formulado') ? safraFetchPairs($db, 'safra_produto_formulado', array('ref' => 'ref', 'label' => 'label')) : array();
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

if ($action === 'save') {
        if (!GETPOST('token') || !dol_verify_token(GETPOST('token'))) {
                accessforbidden('Bad token');
        }

        $ref = trim(GETPOST('ref', 'alpha'));
        $label = trim(GETPOST('label', 'alphanohtml'));
        $fkProject = GETPOST('fk_project', 'int');
        $areaHa = (double) GETPOST('qty', 'alphanohtml');
        $applicationDate = dol_mktime(0, 0, 0, GETPOST('date_applicationmonth', 'int'), GETPOST('date_applicationday', 'int'), GETPOST('date_applicationyear', 'int'));
        $description = GETPOST('description', 'restricthtml');
        $caldaObservacao = trim(GETPOST('calda_observacao', 'restricthtml'));

        if ($ref === '') {
                $errors[] = $langs->trans('ErrorFieldRequired', $langs->trans('Ref'));
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
                                'fk_produto_formulado' => empty($line['fk_produto_formulado']) ? 0 : (int) $line['fk_produto_formulado'],
                                'fk_produtotecnico' => empty($line['fk_produtotecnico']) ? 0 : (int) $line['fk_produtotecnico'],
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

                        $vehicleSelected = GETPOST('vehicles', 'array:int');
                        if (is_array($vehicleSelected)) {
                                foreach ($vehicleSelected as $id) {
                                        $id = (int) $id;
                                        if ($id > 0 && isset($vehicleOptions[$id])) {
                                                $resourcePayload[] = array(
                                                        'type' => 'vehicle',
                                                        'fk_target' => $id,
                                                        'label' => $vehicleOptions[$id],
                                                );
                                        }
                                }
                        }

                        $implementSelected = GETPOST('implements', 'array:int');
                        if (is_array($implementSelected)) {
                                foreach ($implementSelected as $id) {
                                        $id = (int) $id;
                                        if ($id > 0 && isset($implementOptions[$id])) {
                                                $resourcePayload[] = array(
                                                        'type' => 'implement',
                                                        'fk_target' => $id,
                                                        'label' => $implementOptions[$id],
                                                );
                                        }
                                }
                        }

                        $personSelected = GETPOST('persons', 'array:int');
                        if (is_array($personSelected)) {
                                foreach ($personSelected as $id) {
                                        $id = (int) $id;
                                        if ($id > 0 && isset($resourcePersons[$id])) {
                                                $resourcePayload[] = array(
                                                        'type' => 'person',
                                                        'fk_target' => $id,
                                                        'label' => $resourcePersons[$id],
                                                );
                                        }
                                }
                        }

                        if (!empty($resourcePayload)) {
                                $application->replaceResources($resourcePayload);
                        }

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

print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr><td class="fieldrequired">'.$langs->trans('Ref').'</td><td><input type="text" name="ref" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('ref', 'alphanohtml')).'" required></td></tr>';
print '<tr><td>'.$langs->trans('Label').'</td><td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('label', 'alphanohtml')).'"></td></tr>';
print '<tr><td>'.$langs->trans('Project').'</td><td>'.$formProject->select_projects(-1, GETPOST('fk_project', 'int'), 'fk_project', 0, 0, 1, 1, '', 0, '', '', 1, 0, 0, '', 1).'</td></tr>';
print '<tr><td>'.$langs->trans('SafraAplicacaoAreaHa').'</td><td><input type="number" step="0.0001" min="0" name="qty" id="application-area" value="'.dol_escape_htmltag(GETPOST('qty', 'alphanohtml') ?: '0').'"></td></tr>';
print '<tr><td>'.$langs->trans('SafraAplicacaoDate').'</td><td>'.$form->select_date(GETPOST('date_application', 'alpha'), 'date_application', 0, 0, 0, 'aplicacao-assistant-form').'</td></tr>';
print '<tr><td>'.$langs->trans('Description').'</td><td><textarea name="description" class="centpercent" rows="4">'.dol_escape_htmltag(GETPOST('description', 'restricthtml')).'</textarea></td></tr>';
print '</table>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="titre">'.$langs->trans('SafraAplicacaoTaskProducts').'</div>';
print '<table class="noborder centpercent" id="product-lines-table">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Product').'</th>';
print '<th>'.$langs->trans('SafraAplicacaoTechnicalProduct').'</th>';
print '<th>'.$langs->trans('SafraAplicacaoFormulatedProduct').'</th>';
print '<th>'.$langs->trans('SafraAplicacaoAreaHa').'</th>';
print '<th>'.$langs->trans('Dose').'</th>';
print '<th>'.$langs->trans('Unit').'</th>';
print '<th>'.$langs->trans('Total').'</th>';
print '<th>'.$langs->trans('Warehouse').'</th>';
print '<th>'.$langs->trans('Notes').'</th>';
print '<th>'.$langs->trans('Action').'</th>';
print '</tr>';
print '</table>';
print '<div class="right"><button type="button" class="button" id="add-product-line">'.$langs->trans('Add').'</button></div>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="titre">'.$langs->trans('SafraAplicacaoResourceVehicle').'</div>';
print '<select name="vehicles[]" multiple class="minwidth300" data-placeholder="'.$langs->trans('Select').'">';
foreach ($vehicleOptions as $id => $labelOption) {
        print '<option value="'.$id.'">'.dol_escape_htmltag($labelOption).'</option>';
}
print '</select>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="titre">'.$langs->trans('SafraAplicacaoResourceImplement').'</div>';
print '<select name="implements[]" multiple class="minwidth300" data-placeholder="'.$langs->trans('Select').'">';
foreach ($implementOptions as $id => $labelOption) {
        print '<option value="'.$id.'">'.dol_escape_htmltag($labelOption).'</option>';
}
print '</select>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="titre">'.$langs->trans('SafraAplicacaoResourcePerson').'</div>';
print '<select name="persons[]" multiple class="minwidth300" data-placeholder="'.$langs->trans('Select').'">';
foreach ($resourcePersons as $id => $labelOption) {
        print '<option value="'.$id.'">'.dol_escape_htmltag($labelOption).'</option>';
}
print '</select>';
print '</div>';

print '<div class="center marginbottom">';
print '<button type="button" class="button" id="open-calda-modal">'.$langs->trans('SafraAplicacaoCaldaObservation').'</button> ';
print '<button type="submit" class="button button-save">'.$langs->trans('Create').'</button>';
print '</div>';

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
$tecnicoOptionsJson = json_encode((object) $produtosTecnicos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$formuladoOptionsJson = json_encode((object) $produtosFormulados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$warehouseOptionsJson = json_encode((object) $warehouseOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

print '<script>';
print 'document.addEventListener("DOMContentLoaded", function () {';
print 'const productOptions = '.($productOptionsJson ?: '{}').';';
print 'const tecnicoOptions = '.($tecnicoOptionsJson ?: '{}').';';
print 'const formuladoOptions = '.($formuladoOptionsJson ?: '{}').';';
print 'const warehouseOptions = '.($warehouseOptionsJson ?: '{}').';';
print 'let lineIndex = 0;';
print 'function createSelect(name, options) {';
print '    const select = document.createElement("select");';
print '    select.name = name;';
print '    select.className = "minwidth200";';
print '    const emptyOption = document.createElement("option");';
print '    emptyOption.value = "";';
print '    emptyOption.textContent = "";';
print '    select.appendChild(emptyOption);';
print '    Object.entries(options).forEach(function ([key, value]) {';
print '        const option = document.createElement("option");';
print '        option.value = key;';
print '        option.textContent = value;';
print '        select.appendChild(option);';
print '    });';
print '    return select;';
print '}';
print 'function addProductLine() {';
print '    const table = document.getElementById("product-lines-table");';
print '    if (!table) { return; }';
print '    const tr = document.createElement("tr");';
print '    tr.className = "oddeven";';
print '    const idx = lineIndex++;';
print '    const tdProduct = document.createElement("td");';
print '    tdProduct.appendChild(createSelect(`lines[${idx}][fk_product]`, productOptions));';
print '    tr.appendChild(tdProduct);';
print '    const tdTecnico = document.createElement("td");';
print '    tdTecnico.appendChild(createSelect(`lines[${idx}][fk_produtotecnico]`, tecnicoOptions));';
print '    tr.appendChild(tdTecnico);';
print '    const tdFormulado = document.createElement("td");';
print '    tdFormulado.appendChild(createSelect(`lines[${idx}][fk_produto_formulado]`, formuladoOptions));';
print '    tr.appendChild(tdFormulado);';
print '    const tdArea = document.createElement("td");';
print '    const areaInput = document.createElement("input");';
print '    areaInput.type = "number";';
print '    areaInput.name = `lines[${idx}][area_ha]`;';
print '    areaInput.value = document.getElementById("application-area").value || "0";';
print '    areaInput.step = "0.0001";';
print '    tdArea.appendChild(areaInput);';
print '    tr.appendChild(tdArea);';
print '    const tdDose = document.createElement("td");';
print '    const doseInput = document.createElement("input");';
print '    doseInput.type = "number";';
print '    doseInput.name = `lines[${idx}][dose]`;';
print '    doseInput.step = "0.0001";';
print '    doseInput.value = "0";';
print '    tdDose.appendChild(doseInput);';
print '    tr.appendChild(tdDose);';
print '    const tdUnit = document.createElement("td");';
print '    const unitSelect = document.createElement("select");';
print '    unitSelect.name = `lines[${idx}][dose_unit]`;';
print '    ["L/ha", "kg/ha"].forEach(function (unit) {';
print '        const opt = document.createElement("option");';
print '        opt.value = unit;';
print '        opt.textContent = unit;';
print '        unitSelect.appendChild(opt);';
print '    });';
print '    tdUnit.appendChild(unitSelect);';
print '    tr.appendChild(tdUnit);';
print '    const tdTotal = document.createElement("td");';
print '    const totalInput = document.createElement("input");';
print '    totalInput.type = "number";';
print '    totalInput.name = `lines[${idx}][total_qty]`;';
print '    totalInput.readOnly = true;';
print '    totalInput.step = "0.0001";';
print '    totalInput.value = "0";';
print '    tdTotal.appendChild(totalInput);';
print '    tr.appendChild(tdTotal);';
print '    const tdWarehouse = document.createElement("td");';
print '    tdWarehouse.appendChild(createSelect(`lines[${idx}][fk_entrepot]`, warehouseOptions));';
print '    tr.appendChild(tdWarehouse);';
print '    const tdNote = document.createElement("td");';
print '    const noteInput = document.createElement("textarea");';
print '    noteInput.name = `lines[${idx}][note]`;';
print '    noteInput.rows = 1;';
print '    tdNote.appendChild(noteInput);';
print '    tr.appendChild(tdNote);';
print '    const tdAction = document.createElement("td");';
print '    const removeBtn = document.createElement("button");';
print '    removeBtn.type = "button";';
print '    removeBtn.className = "button button-cancel";';
print '    removeBtn.textContent = "-";';
print '    removeBtn.addEventListener("click", function () { tr.remove(); });';
print '    tdAction.appendChild(removeBtn);';
print '    tr.appendChild(tdAction);';
print '    table.appendChild(tr);';
print '    function recompute() {';
print '        const area = parseFloat(areaInput.value) || 0;';
print '        const dose = parseFloat(doseInput.value) || 0;';
print '        totalInput.value = (area * dose).toFixed(4);';
print '    }';
print '    areaInput.addEventListener("input", recompute);';
print '    doseInput.addEventListener("input", recompute);';
print '}';
print 'const addLineButton = document.getElementById("add-product-line");';
print 'if (addLineButton) {';
print '    addLineButton.addEventListener("click", addProductLine);';
print '    addProductLine();';
print '}';
print 'const openCaldaModal = document.getElementById("open-calda-modal");';
print 'if (openCaldaModal) {';
print '    openCaldaModal.addEventListener("click", function () {';
print '        document.getElementById("calda-modal").style.display = "flex";';
print '        const areaField = document.getElementById("application-area");';
print '        document.getElementById("modal-area").value = areaField ? (areaField.value || "0") : "0";';
print '    });';
print '}';
print 'const modalCancel = document.getElementById("modal-cancel");';
print 'if (modalCancel) {';
print '    modalCancel.addEventListener("click", function () {';
print '        document.getElementById("calda-modal").style.display = "none";';
print '    });';
print '}';
print 'const modalSave = document.getElementById("modal-save");';
print 'if (modalSave) {';
print '    modalSave.addEventListener("click", function () {';
print '        const area = parseFloat(document.getElementById("modal-area").value) || 0;';
print '        const dose = parseFloat(document.getElementById("modal-dose").value) || 0;';
print '        const unit = document.getElementById("modal-unit").value || "";';
print '        const note = document.getElementById("modal-note").value || "";';
print '        const total = area * dose;';
print '        document.getElementById("modal-total").value = total.toFixed(4);';
print '        document.getElementById("calda-observacao").value = area.toFixed(2) + " " + unit + " -> " + total.toFixed(2) + "\n" + note;';
print '        document.getElementById("calda-modal").style.display = "none";';
print '    });';
print '}';
print '});';
print '</script>';

llxFooter();
$db->close();
