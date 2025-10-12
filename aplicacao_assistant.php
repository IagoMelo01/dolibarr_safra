<?php
// Assistant for creating crop applications - minimal stable version
$res=0; if(!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res=@include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
if(!$res && file_exists('../main.inc.php')) $res=@include '../main.inc.php';
if(!$res && file_exists('../../main.inc.php')) $res=@include '../../main.inc.php';
if(!$res) die('Include of main fails');
if(!isModEnabled('safra')) accessforbidden();
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/safra/class/aplicacao.class.php');
$langs->loadLangs(array('safra@safra','projects','products','stocks'));
$form=new Form($db); $formProject=new FormProjets($db);
$action=GETPOST('action','aZ09');
$editId=GETPOST('id','int');
function safraTableExists(DoliDB $db,$t){$i=$db->DDLDescTable(MAIN_DB_PREFIX.$t,$t);return is_array($i);} 
function safraColumnExists(DoliDB $db,$t,$c){ $sql="SHOW COLUMNS FROM ".$db->prefix().$t." LIKE '".$db->escape($c)."'"; $r=$db->query($sql); if($r){ $ok=($db->num_rows($r)>0); $db->free($r); return $ok;} return false; }
function safraPairs(DoliDB $db,$t,$f,$w=''){ $o=array(); $fs=array('rowid'); foreach($f as $a=>$c)$fs[]=$c.' AS '.$a; $sql='SELECT '.implode(', ',$fs).' FROM '.MAIN_DB_PREFIX.$t; if($w)$sql.=' WHERE '.$w; $sql.=' ORDER BY '.$f['ref'].' ASC'; $r=$db->query($sql); if($r){ while($x=$db->fetch_object($r)){ $l=$x->ref; if(!empty($x->label))$l.=' - '.$x->label; $o[(int)$x->rowid]=$l;} $db->free($r);} return $o; }
function safraSmartPairs(DoliDB $db,$table){
    $map=array();
    // detect columns
    $candsRef=array('ref','placa','code','codigo','num','matricula');
    $candsLabel=array('label','descricao','description','nome','name');
    $refCol=''; foreach($candsRef as $c){ $res=$db->query("SHOW COLUMNS FROM ".$db->prefix().$table." LIKE '".$db->escape($c)."'"); if($res){ if($db->num_rows($res)>0){ $refCol=$c; $db->free($res); break; } $db->free($res);} }
    $labCol=''; foreach($candsLabel as $c){ $res=$db->query("SHOW COLUMNS FROM ".$db->prefix().$table." LIKE '".$db->escape($c)."'"); if($res){ if($db->num_rows($res)>0){ $labCol=$c; $db->free($res); break; } $db->free($res);} }
    $fields='rowid'; if($refCol) $fields.=', '.$refCol.' AS ref'; else $fields.=', NULL AS ref'; if($labCol) $fields.=', '.$labCol.' AS label'; else $fields.=', NULL AS label';
    $sql='SELECT '.$fields.' FROM '.$db->prefix().$table.' ORDER BY rowid ASC';
    $res=$db->query($sql); if($res){ while($o=$db->fetch_object($res)){ $id=(int)$o->rowid; $parts=array('#'.$id); if(!empty($o->ref)) $parts[]=$o->ref; if(!empty($o->label)) $parts[]=$o->label; $map[$id]=trim(implode(' - ',$parts)); } $db->free($res);} 
    return $map;
}
// data
$products=array(); $defaultWarehouses=array(); $r=$db->query('SELECT rowid, ref, label, fk_default_warehouse FROM '.MAIN_DB_PREFIX."product".' WHERE entity IN ('.getEntity('product').') ORDER BY ref ASC LIMIT 500'); if($r){ while($o=$db->fetch_object($r)){ $l=$o->ref; if($o->label)$l.=' - '.$o->label; $products[(int)$o->rowid]=$l; if(!empty($o->fk_default_warehouse)) $defaultWarehouses[(int)$o->rowid]=(int)$o->fk_default_warehouse;} $db->free($r);} 
$warehouses=array();
$warehouseEntityFilter = trim(getEntity('stock'));
if ($warehouseEntityFilter === '') {
    $warehouseEntityFilter = (string) ((int) $conf->entity);
}
$warehouseColumns=array('description'=>safraColumnExists($db,'entrepot','description'));
$warehouseColumns['label']=safraColumnExists($db,'entrepot','label');
$warehouseColumns['lieu']=safraColumnExists($db,'entrepot','lieu');
$warehouseSelectFields=array('rowid','ref');
foreach(array('description','label','lieu') as $col){
    if(!empty($warehouseColumns[$col])) $warehouseSelectFields[]=$col;
}
$warehousesSql='SELECT '.implode(', ',$warehouseSelectFields).' FROM '.MAIN_DB_PREFIX."entrepot".' WHERE entity IN ('.$warehouseEntityFilter.') ORDER BY ref ASC';
$warehousesError=null;
$warehouseColumnsUsed=$warehouseSelectFields;
$rw=$db->query($warehousesSql);
if($rw){
    while($ow=$db->fetch_object($rw)){
        $parts=array();
        $ref=trim((string) $ow->ref);
        $candidates=array();
        if(!empty($warehouseColumns['lieu'])) $candidates[] = trim((string) $ow->lieu);
        if(!empty($warehouseColumns['label'])) $candidates[] = trim((string) $ow->label);
        if(!empty($warehouseColumns['description'])) $candidates[] = trim((string) $ow->description);
        if($ref!=='') $parts[]=$ref;
        foreach($candidates as $cand){
            if($cand!=='' && $cand!==$ref){
                $parts[]=$cand;
                break;
            }
        }
        if(empty($parts)) $parts[]='#'.(int)$ow->rowid;
        $warehouses[(int)$ow->rowid]=implode(' - ', $parts);
    }
    $db->free($rw);
} else {
    $warehousesError = $db->lasterror();
}
$vehicles=safraSmartPairs($db,'frota_veiculo');
$implements=safraSmartPairs($db,'frota_implemento');
$persons=array(); $r=$db->query('SELECT rowid, firstname, lastname, login FROM '.MAIN_DB_PREFIX."user WHERE statut=1 ORDER BY lastname"); if($r){ while($o=$db->fetch_object($r)){ $l=trim($o->firstname.' '.$o->lastname); if($l==='')$l=$o->login; $persons[(int)$o->rowid]=$l;} $db->free($r);} 
// save (create or update)
if($action==='save'){
    $tok = GETPOST('token','alphanohtml');
    if(empty($tok) || !isset($_SESSION['newtoken']) || $tok !== $_SESSION['newtoken']) accessforbidden('Bad token');
    $ref=trim(GETPOST('ref','alphanohtml')); $fkProject=GETPOST('fk_project','int'); $talhao=GETPOST('talhao_id','int'); $area=(double)str_replace(',','.',GETPOST('qty','alpha')); $ds=trim(GETPOST('date_application','alpha')); $dt=$ds?dol_stringtotime($ds.' 00:00:00'):null; $desc=GETPOST('description','restricthtml'); $calda=GETPOST('calda_observacao','restricthtml');
    $errs=array(); if($ref==='')$errs[]=$langs->trans('ErrorFieldRequired',$langs->trans('Ref')); if($fkProject<=0)$errs[]=$langs->trans('ErrorFieldRequired',$langs->trans('Project')); if($talhao<=0)$errs[]=$langs->trans('ErrorFieldRequired',$langs->trans('SafraAplicacaoTalhao')); if(!empty($errs)){ setEventMessages('', $errs,'errors'); } else {
        $app=new Aplicacao($db);
        $isEdit = GETPOST('id','int')>0 && $app->fetch(GETPOST('id','int'))>0;
        $app->ref=$ref; $app->fk_project=$fkProject; $app->qty=$area; $app->date_application=$dt?:null; $app->description=$desc; $app->calda_observacao=$calda; if(!$isEdit){ $app->status=Aplicacao::STATUS_DRAFT; }
        $rc = $isEdit ? $app->update($user) : $app->create($user);
        if($rc>0){
            $ary=array(); $postedLines=(array)GETPOST('lines_flat','array'); if(empty($postedLines) && !empty($_POST['lines_flat']) && is_array($_POST['lines_flat'])) { $postedLines = $_POST['lines_flat']; }
            foreach($postedLines as $ln){
                $pid=(int)($ln['fk_product']??0);
                $dose=(double)str_replace(',','.',$ln['dose']??'0');
                $a=(double)str_replace(',','.',$ln['area_ha']??'0');
                $u=$ln['dose_unit']??'';
                $t=(double)str_replace(',','.',$ln['total_qty']??'0');
                $wh=(int)($ln['fk_entrepot']??0);
                if($t==0 && $a>0 && $dose>=0){ $t=$a*$dose; }
                if($pid>0){
                    $ary[]=array(
                        'fk_product'=>$pid,
                        'fk_entrepot'=>$wh,
                        'dose'=>$dose,
                        'dose_unit'=>$u,
                        'area_ha'=>$a,
                        'total_qty'=>$t,
                        'label'=>($products[$pid]??'')
                    );
                }
            }
            if(!empty($ary)){
                $rr=$app->replaceLines($ary);
                if($rr<=0){ dol_syslog('safra/aplicacao_assistant replaceLines failed: '.var_export($app->error,true), LOG_ERR); setEventMessages($app->error?:'replaceLines failed',null,'errors'); }
            } else {
                dol_syslog('safra/aplicacao_assistant no lines to save. Posted='.var_export($postedLines,true), LOG_WARNING);
                setEventMessages($langs->trans('ErrorFieldRequired',$langs->trans('Product')), null, 'warnings');
            }
            $res=array(); foreach((array)GETPOST('vehicles','array') as $id) if(isset($vehicles[$id])) $res[]=array('type'=>'vehicle','fk_target'=>(int)$id,'label'=>$vehicles[$id]); foreach((array)GETPOST('implements','array') as $id) if(isset($implements[$id])) $res[]=array('type'=>'implement','fk_target'=>(int)$id,'label'=>$implements[$id]); foreach((array)GETPOST('persons','array') as $id) if(isset($persons[$id])) $res[]=array('type'=>'person','fk_target'=>(int)$id,'label'=>$persons[$id]); if(!empty($res)) $app->replaceResources($res);
            setEventMessages($langs->trans('RecordSaved'),null,'mesgs'); header('Location: '.dol_buildpath('/safra/aplicacao_card.php',1).'?id='.$app->id); exit;
        } else {
            setEventMessages($app->error?:'Create failed',null,'errors');
        }
    }
}
// selects and json
$prefill = null; if($editId>0){ $tmp=new Aplicacao($db); if($tmp->fetch($editId)>0){ $prefill=$tmp; } }
$projectSelectHtml=$formProject->select_projects_list(-1, ($prefill? (int)$prefill->fk_project : GETPOST('fk_project','int')), 'fk_project', 24, 0, 1, 0, 0, 0, 0, '', 1, 0, 'fk_project', 'minwidth300 js-select2');
$productsJson=json_encode((object)$products, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$warehousesJson=json_encode((object)$warehouses, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$defaultWarehousesJson=json_encode((object)$defaultWarehouses, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$vehiclesJson=json_encode((object)$vehicles, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$implementsJson=json_encode((object)$implements, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$personsJson=json_encode((object)$persons, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$ajaxTalhaoUrl=dol_buildpath('/safra/ajax/project_talhao.php',1);
// Optional debug: add &debug=1 in URL to show internal data
$__debug=(int)GETPOST('debug','int');
if($__debug){
    print '<details class="safra-card" open><summary>Debug - Safra Aplicação</summary>';
    print '<pre>'.dol_escape_htmltag(var_export(array(
                'products_count' => count($products),
        'products_first' => array_slice($products,0,10,true),
        'warehouses_count' => count($warehouses),
        'warehouses_first' => array_slice($warehouses,0,10,true),
        'warehouses_sql' => $warehousesSql,
        'warehouses_error' => $warehousesError,
        'warehouses_fields' => $warehouseSelectFields,
        'vehicles_count' => count($vehicles),
        'vehicles_first' => array_slice($vehicles,0,10,true),
        'implements_count' => count($implements),
        'implements_first' => array_slice($implements,0,10,true),
        'persons_count' => count($persons),
        'persons_first' => array_slice($persons,0,10,true),
    ), true)).'</pre>';
    print '</details>';
}

// Build a local map Project -> Talhao to avoid AJAX dependency
$projectTalhaoMap=array();
$coltal='';
$rescol=$db->query("SHOW COLUMNS FROM ".$db->prefix()."projet_extrafields LIKE 'fk_talhao'");
if($rescol && $db->num_rows($rescol)>0){ $coltal='fk_talhao'; $db->free($rescol);} else {
  $rescol2=$db->query("SHOW COLUMNS FROM ".$db->prefix()."projet_extrafields LIKE 'options_fk_talhao'");
  if($rescol2 && $db->num_rows($rescol2)>0){ $coltal='options_fk_talhao'; $db->free($rescol2);} }
if($coltal){
  $sql='SELECT p.rowid as pid, ef.'.$coltal.' as talhao_id, t.ref, t.label, t.area, m.label as municipio_label'
      .' FROM '.$db->prefix().'projet as p'
      .' LEFT JOIN '.$db->prefix().'projet_extrafields as ef ON ef.fk_object=p.rowid'
      .' LEFT JOIN '.$db->prefix().'safra_talhao as t ON t.rowid = ef.'.$coltal
      .' LEFT JOIN '.$db->prefix().'safra_municipio as m ON m.rowid = t.municipio'
      .' WHERE p.entity IN ('.getEntity('project').')';
  $rs=$db->query($sql);
  if($rs){
    while($o=$db->fetch_object($rs)){
      $pid=(int)$o->pid; $tid=(int)$o->talhao_id; if($pid<=0) continue;
      if($tid>0){
        $projectTalhaoMap[$pid]=array(
          'talhao_id'=>$tid,
          'talhao'=>array(
            'id'=>$tid,
            'label'=>trim(($o->ref? $o->ref.' - ':'').($o->label?:'')),
            'area'=>(float)$o->area,
            'municipio'=>$o->municipio_label,
            'url'=>dol_buildpath('/safra/talhao_card.php',1).'?id='.$tid,
          )
        );
      }
    }
    $db->free($rs);
  }
}
$projectTalhaoJson=json_encode((object)$projectTalhaoMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
llxHeader('', $langs->trans('Aplicacao').' - '.$langs->trans('New'));
?>
<style>.safra-shell{max-width:1100px;margin:0 auto;padding:18px 16px 40px}.safra-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 18px rgba(15,23,42,.08);padding:18px;margin:0 0 16px}.safra-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))}.safra-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}.safra-field label{font-size:12px;text-transform:uppercase;color:#64748b}.safra-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}.select2-container{width:100%!important}.safra-lines{display:flex;flex-direction:column;gap:12px}.safra-lines-toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.safra-lines-legend{display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));font-size:11px;text-transform:uppercase;color:#64748b;margin-bottom:8px}.safra-line-row{display:flex;flex-wrap:wrap;gap:12px;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}.safra-line-field{display:flex;flex-direction:column;gap:6px;flex:1 1 calc(50% - 12px)}.safra-line-field.full{flex-basis:100%}.safra-line-field.half{flex:1 1 calc(50% - 12px)}.safra-line-field.third{flex:1 1 calc(33.333% - 12px)}.safra-line-field.quarter{flex:1 1 calc(25% - 12px)}.safra-line-field.auto{flex:0 0 auto;align-self:flex-end}.safra-line-field label{font-size:11px;text-transform:uppercase;color:#475569}.safra-line-remove{display:flex;align-items:flex-end}.safra-line-remove button{padding:6px 10px;line-height:1}.safra-line-row input[type=number]{width:100%}</style></style>
<div class="safra-shell">
<form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<input type="hidden" name="action" value="save">
<?php if($prefill){ echo '<input type="hidden" name="id" value="'.(int)$prefill->id.'">'; } ?>
<input type="hidden" id="ajax-project-talhao-url" value="<?php echo dol_escape_htmltag($ajaxTalhaoUrl); ?>">
<input type="hidden" name="talhao_id" id="talhao_id" value="<?php echo (int)GETPOST('talhao_id','int'); ?>">
<section class="safra-card"><h2><?php echo $langs->trans('Project'); ?></h2><div class="safra-field"><label for="fk_project"><?php echo $langs->trans('Project'); ?></label><?php echo $projectSelectHtml; ?></div><div class="safra-field"><label><?php echo $langs->trans('SafraAplicacaoTalhao'); ?></label><div id="talhao-info" class="opacitymedium"><?php echo $langs->trans('SafraAplicacaoTalhaoNotLinked'); ?></div></div></section>
<section class="safra-card"><h2><?php echo $langs->trans('SafraAplicacaoTaskProducts'); ?></h2><div class="safra-field"><label for="ref"><?php echo $langs->trans('Ref'); ?> *</label><input type="text" name="ref" id="ref" required value="<?php echo $prefill? dol_escape_htmltag($prefill->ref):''; ?>"></div><div class="safra-field"><label for="qty"><?php echo $langs->trans('SafraAplicacaoAreaHa'); ?></label><input type="number" step="0.0001" name="qty" id="qty" value="<?php echo $prefill? price2num($prefill->qty,'4'): '0'; ?>"></div><div class="safra-field"><label for="date_application"><?php echo $langs->trans('SafraAplicacaoDate'); ?></label><input type="date" name="date_application" id="date_application" value="<?php echo ($prefill && !empty($prefill->date_application)) ? dol_print_date($prefill->date_application,'dayrfc') : '' ; ?>"></div><div class="safra-field"><label for="description"><?php echo $langs->trans('Description'); ?></label><textarea name="description" id="description" rows="3"><?php echo $prefill? dol_escape_htmltag($prefill->description):''; ?></textarea></div></section>
<section class="safra-card">
<div class="safra-lines-toolbar"><h3 style="margin:0;"><?php echo $langs->trans('Products'); ?></h3><div style="display:flex;gap:8px;align-items:center;"><button type="button" id="btn-calda" class="button"><?php echo $langs->trans('SafraAplicacaoCaldaCalculation') ?: 'Cálculo de calda'; ?></button><button type="button" id="add-line" class="button"><?php echo $langs->trans('Add'); ?></button></div></div>
<div class="safra-lines-legend"><span><?php echo $langs->trans('Product'); ?></span><span><?php echo $langs->trans('SafraAplicacaoAreaHa'); ?></span><span><?php echo $langs->trans('Dose'); ?></span><span><?php echo $langs->trans('Unit'); ?></span><span><?php echo $langs->trans('Total'); ?></span><span><?php echo $langs->trans('Warehouse'); ?></span></div>
<div id="lines-body" class="safra-lines"></div>
</section>
<section class="safra-card"><h3><?php echo $langs->trans('SafraAplicacaoResources'); ?></h3><div class="safra-grid"><div class="safra-field"><label><?php echo $langs->trans('SafraAplicacaoResourceVehicle'); ?></label><select name="vehicles[]" multiple class="js-select2"><?php foreach($vehicles as $id=>$lab) echo '<option value="'.$id.'">'.dol_escape_htmltag($lab).'</option>'; ?></select></div><div class="safra-field"><label><?php echo $langs->trans('SafraAplicacaoResourceImplement'); ?></label><select name="implements[]" multiple class="js-select2"><?php foreach($implements as $id=>$lab) echo '<option value="'.$id.'">'.dol_escape_htmltag($lab).'</option>'; ?></select></div><div class="safra-field"><label><?php echo $langs->trans('SafraAplicacaoResourcePerson'); ?></label><select name="persons[]" multiple class="js-select2"><?php foreach($persons as $id=>$lab) echo '<option value="'.$id.'">'.dol_escape_htmltag($lab).'</option>'; ?></select></div></div></section>
<section class="safra-card"><h3><?php echo $langs->trans('SafraAplicacaoCaldaObservation'); ?></h3><div class="safra-field"><label for="calda_observacao"><?php echo $langs->trans('Notes'); ?></label><textarea name="calda_observacao" id="calda_observacao" rows="4"><?php echo $prefill? dol_escape_htmltag($prefill->calda_observacao):''; ?></textarea></div></section>
<div class="safra-actions"><a class="button" href="<?php echo dol_buildpath('/safra/aplicacao_list.php',1); ?>"><?php echo $langs->trans('Cancel'); ?></a><button type="submit" class="button button-save"><?php echo $langs->trans('Create'); ?></button></div>
</form></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  const s2=window.jQuery&&window.jQuery.fn&&window.jQuery.fn.select2;
  function init(n){ try{ if(s2 && n && !window.jQuery(n).hasClass('select2-hidden-accessible')) window.jQuery(n).select2({width:'100%'});}catch(e){}
  }
  document.querySelectorAll('.js-select2').forEach(init);

  const tal=document.getElementById('talhao-info');
  const hid=document.getElementById('talhao_id');
  const qty=document.getElementById('qty');
  const ajax=document.getElementById('ajax-project-talhao-url').value;
  const mapTalhao=<?php echo $projectTalhaoJson?:'{}'; ?>;

  function findProjectSelect(){
    return document.getElementById('fk_project')
      || document.querySelector('select[name="fk_project"]')
      || document.querySelector('select[name="projectid"]')
      || document.querySelector('select[name="search_projectid"]')
      || document.querySelector('section.safra-card select');
  }

  function parseProjectId(val){
    if(!val) return 0;
    const m=String(val).match(/(\d+)/);
    return m?parseInt(m[1],10)||0:0;
  }

  let talhaoArea = 0;
  function setTal(d){
    if(d&&d.talhao&&d.talhao_id){
      hid.value=String(d.talhao_id);
      const t=d.talhao,p=[];
      p.push(t.url?('<a href="'+t.url+'" target="_blank" rel="noopener">'+(t.label||'')+'</a>'):(t.label||''));
      if(t.area) p.push('<?php echo dol_escape_js($langs->trans('SafraAplicacaoTalhaoAreaFormat','%s')); ?>'.replace('%s',Number(t.area).toFixed(2)));
      if(t.municipio) p.push('<?php echo dol_escape_js($langs->trans('SafraAplicacaoTalhaoMunicipioFormat','%s')); ?>'.replace('%s',t.municipio));
      tal.innerHTML=p.join('<br>');
      talhaoArea = Number(t.area||0) || 0;
      if(qty && (!qty.value || Number(qty.value)===0)) qty.value=talhaoArea.toFixed(4);
      try { document.querySelectorAll('input').forEach(function(inp){ if(!inp.name) return; if(inp.name.indexOf('[area_ha]')>-1){ const v=parseFloat(inp.value||'0')||0; if(v===0 && talhaoArea>0){ inp.value = talhaoArea.toFixed(4); inp.dispatchEvent(new Event('input')); } } }); } catch(e) {}
    } else {
      hid.value='';
      tal.textContent='<?php echo dol_escape_js($langs->trans('SafraAplicacaoTalhaoNotLinked')); ?>';
    }
  }

  function loadTalhaoForCurrent(){
    const sel=findProjectSelect();
    if(!sel){ setTal(null); return; }
    const id=parseProjectId(sel.value);
    if(!id){ setTal(null); return; }
    // Prefer local map to avoid AJAX issues
    if(mapTalhao && mapTalhao[id]){
      setTal(mapTalhao[id]);
      return;
    }
    // Fallback to AJAX if not found locally
    fetch(ajax+'?id='+id,{credentials:'same-origin'})
      .then(r=>r.ok?r.json():Promise.reject(new Error('http '+r.status)))
      .then(function(data){ if(!data||data.success===false){ console.warn('talhao fetch error', data); } setTal(data); })
      .catch(function(err){ console.warn('talhao fetch failed', err); setTal(null); });
  }

  const sel=findProjectSelect();
  if(sel){
    sel.addEventListener('change',loadTalhaoForCurrent);
    // Some select2 setups trigger events only via jQuery
    if(s2 && window.jQuery){
      window.jQuery(sel).on('select2:select',loadTalhaoForCurrent);
      window.jQuery(sel).on('change.select2',loadTalhaoForCurrent);
    }
    // Prefill on load
    loadTalhaoForCurrent();
  } else {
    setTal(null);
  }

  // Lines / products grid
  const body=document.getElementById('lines-body');
  const add=document.getElementById('add-line');
  const allProducts=<?php echo $productsJson?:'{}'; ?>;
  const warehouses=<?php echo $warehousesJson?:'{}'; ?>;
  const defaultWarehouses=<?php echo $defaultWarehousesJson?:'{}'; ?>;
  const DEBUG = <?php echo (int) GETPOST('debug','int'); ?>;
  const searchPlaceholder = <?php echo json_encode($langs->trans('Search') ?: 'Digite para filtrar'); ?>;
  function fillProducts(sel){ sel.innerHTML='';
    const ph=document.createElement('option'); ph.value=''; ph.textContent='\u00A0'; sel.appendChild(ph);
    Object.keys(allProducts||{}).forEach(function(k){ const o=document.createElement('option'); o.value=k; o.textContent=allProducts[k]; sel.appendChild(o); });
  }
  function fillWarehouses(sel){
    sel.innerHTML='';
    const ph=document.createElement('option'); ph.value=''; ph.textContent='-'; sel.appendChild(ph);
    Object.keys(warehouses||{}).forEach(function(k){ const o=document.createElement('option'); o.value=k; o.textContent=warehouses[k]; sel.appendChild(o); });
  }
  function selectFirstNonEmpty(sel){
    const found = Array.from(sel.options||[]).find(o=>o.value!=='' && o.value!=null);
    if(found){ sel.value = found.value; }
  }
  function ensureOptionAndSelect(sel, val, text){

    const v=String(val);

    let opt = sel.querySelector('option[value="'+v+'"]');

    if(!opt){ opt=document.createElement('option'); opt.value=v; opt.textContent=text || v; sel.appendChild(opt); }

    sel.value=v;

}

function enhanceSelectWithSearch(selectEl, placeholderText, dropdownContext){
    if(!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) return;
    const $sel = window.jQuery(selectEl);
    if($sel.data('select2')) return;
    const opts = {
        width: 'resolve',
        allowClear: true
    };
    if(placeholderText){
        opts.placeholder = placeholderText;
    }
    if(dropdownContext){
        opts.dropdownParent = window.jQuery(dropdownContext);
    }
    $sel.select2(opts);
}

function createLineField(labelText, element, sizeClass){

    const wrap=document.createElement('div');

    wrap.className='safra-line-field '+(sizeClass||'');

    const lbl=document.createElement('label');

    lbl.textContent=labelText;

    wrap.appendChild(lbl);

    wrap.appendChild(element);

    return wrap;

}

function ensureWarehouseOption(selectEl, warehouseId){
    if(!warehouseId) return;
    const key=String(warehouseId);
    if(selectEl.querySelector('option[value="'+key+'"]')) return;
    const label = warehouses && warehouses[key] ? warehouses[key] : '#'+key;
    const opt=document.createElement('option');
    opt.value=key; opt.textContent=label;
    selectEl.appendChild(opt);
}

  let idx=0;
  function addLine(pref){
    const row=document.createElement('div');
    row.className='safra-line-row';

    const pUI=document.createElement('select');
    pUI.id='line_'+idx+'_fk_product_ui';
    pUI.className='flat minwidth300 minwidth100';
    fillProducts(pUI);

    const pHidden=document.createElement('input');
    pHidden.type='hidden';
    pHidden.name='lines_flat['+idx+'][fk_product]';
    pHidden.value='';

    if(pref && pref.fk_product){
      const label = allProducts && allProducts[String(pref.fk_product)] ? allProducts[String(pref.fk_product)] : ('#'+pref.fk_product);
      ensureOptionAndSelect(pUI, pref.fk_product, label);
    } else {
      selectFirstNonEmpty(pUI);
    }
    pHidden.value = pUI.value || '';

    const warehouseSelect=document.createElement('select');
    warehouseSelect.className='flat minwidth200';
    fillWarehouses(warehouseSelect);

    const warehouseHidden=document.createElement('input');
    warehouseHidden.type='hidden';
    warehouseHidden.name='lines_flat['+idx+'][fk_entrepot]';
    warehouseHidden.value=(pref && pref.fk_entrepot ? String(pref.fk_entrepot) : '0');

    const ia=document.createElement('input'); ia.type='number'; ia.step='0.0001'; ia.name='lines_flat['+idx+'][area_ha]'; ia.value=(pref&&pref.area_ha!==undefined? String(pref.area_ha) : (talhaoArea>0? talhaoArea.toFixed(4) : (document.getElementById('qty').value||'0')));
    const id=document.createElement('input'); id.type='number'; id.step='0.0001'; id.name='lines_flat['+idx+'][dose]'; id.value=(pref&&pref.dose!==undefined? String(pref.dose):'0');
    const iu=document.createElement('select'); ['L/ha','kg/ha'].forEach(u=>{ const o=document.createElement('option'); o.value=u; o.text=u; iu.appendChild(o);}); iu.name='lines_flat['+idx+'][dose_unit]'; if(pref&&pref.dose_unit){ iu.value=pref.dose_unit; }
    const it=document.createElement('input'); it.type='number'; it.step='0.0001'; it.name='lines_flat['+idx+'][total_qty]'; it.readOnly=true; if(pref&&pref.total_qty!==undefined){ it.value=String(pref.total_qty); }

    const productField=createLineField(<?php echo json_encode($langs->trans('Product')); ?>, pUI, 'full');
    productField.appendChild(pHidden);

    const warehouseField=createLineField(<?php echo json_encode($langs->trans('Warehouse')); ?>, warehouseSelect, 'half');
    warehouseField.appendChild(warehouseHidden);

    const areaField=createLineField(<?php echo json_encode($langs->trans('SafraAplicacaoAreaHa')); ?>, ia, 'half');
    const doseField=createLineField(<?php echo json_encode($langs->trans('Dose')); ?>, id, 'half');
    const unitField=createLineField(<?php echo json_encode($langs->trans('Unit')); ?>, iu, 'quarter');
    const totalField=createLineField(<?php echo json_encode($langs->trans('Total')); ?>, it, 'quarter');

    const removeField=document.createElement('div');
    removeField.className='safra-line-field auto safra-line-remove';
    const rm=document.createElement('button'); rm.type='button'; rm.className='button'; rm.textContent='-'; rm.addEventListener('click',()=>{
      if(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2){
        const $p = window.jQuery(pUI); if($p.data('select2')) $p.select2('destroy');
        const $w = window.jQuery(warehouseSelect); if($w.data('select2')) $w.select2('destroy');
      }
      row.remove();
    });
    removeField.appendChild(rm);

    row.appendChild(productField);
    row.appendChild(warehouseField);
    row.appendChild(areaField);
    row.appendChild(doseField);
    row.appendChild(unitField);
    row.appendChild(totalField);
    row.appendChild(removeField);

    function rec(){ it.value=((parseFloat(ia.value)||0)*(parseFloat(id.value)||0)).toFixed(4); }
    ia.addEventListener('input',rec); id.addEventListener('input',rec); rec();

    function resetWarehouseSelection(){
      warehouseSelect.dataset.user = '';
      if(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery(warehouseSelect).data('select2')){
        window.jQuery(warehouseSelect).val(null).trigger('change');
      } else {
        warehouseSelect.value = '';
      }
      warehouseHidden.value = '0';
    }

    function applyDefaultWarehouse(productId){
      const def = defaultWarehouses && defaultWarehouses[String(productId)];
      if(!def){
        resetWarehouseSelection();
        return;
      }
      if(warehouseSelect.dataset.user === '1') return;
      ensureWarehouseOption(warehouseSelect, def);
      warehouseSelect.dataset.user = '';
      if(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && window.jQuery(warehouseSelect).data('select2')){
        window.jQuery(warehouseSelect).val(String(def)).trigger('change');
      } else {
        warehouseSelect.value = String(def);
      }
      warehouseHidden.value = String(def);
    }

    pUI.addEventListener('change',()=>{
      pHidden.value = pUI.value || '';
      warehouseSelect.dataset.user = '';
      applyDefaultWarehouse(pHidden.value);
      rec();
    });

    warehouseSelect.addEventListener('change',()=>{
      warehouseHidden.value = warehouseSelect.value || '0';
      warehouseSelect.dataset.user = warehouseSelect.value && warehouseSelect.value !== '0' ? '1' : '';
    });

    let initialWarehouse = null;
    if(pref && pref.fk_entrepot){
      initialWarehouse = String(pref.fk_entrepot);
      warehouseSelect.dataset.user = '1';
    } else if (pHidden.value && defaultWarehouses && defaultWarehouses[pHidden.value]){
      initialWarehouse = String(defaultWarehouses[pHidden.value]);
      warehouseSelect.dataset.user = '';
    }

    if(initialWarehouse){
      ensureWarehouseOption(warehouseSelect, initialWarehouse);
      if(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2){
        window.jQuery(warehouseSelect).val(initialWarehouse).trigger('change');
      } else {
        warehouseSelect.value = initialWarehouse;
      }
      warehouseHidden.value = initialWarehouse;
    } else {
      applyDefaultWarehouse(pHidden.value);
      warehouseHidden.value = warehouseSelect.value || '0';
    }

    body.appendChild(row);
    enhanceSelectWithSearch(pUI, searchPlaceholder, row);
    if(DEBUG){ try { console.log('DEBUG product options', pUI.options.length); } catch(e){} }
    idx++;
  }

  add.addEventListener('click', function(){ addLine(); });

  const prefillLines = <?php
    if($prefill && !empty($prefill->lines)){
      $arr=array();
      foreach($prefill->lines as $ln){
        $arr[]=array(
          'fk_product'=>(int)$ln->fk_product,
          'area_ha'=>price2num($ln->area_ha,'4'),
          'dose'=>price2num($ln->dose,'4'),
          'dose_unit'=>$ln->dose_unit,
          'total_qty'=>price2num($ln->total_qty,'4'),
          'fk_entrepot'=>(int)$ln->fk_entrepot
        );
      }
      echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } else {
      echo '[]';
    }
  ?>;
  if(prefillLines.length){ prefillLines.forEach(l=>addLine(l)); } else { addLine(); }
  // Prefill resources selections
  <?php
    if($prefill && !empty($prefill->resources)){
      $vids=array(); $iids=array(); $pids=array();
      foreach($prefill->resources as $type=>$items){ foreach($items as $it){ if($type==='vehicle') $vids[]=(int)$it['fk_target']; elseif($type==='implement') $iids[]=(int)$it['fk_target']; elseif($type==='person') $pids[]=(int)$it['fk_target']; } }
      echo 'try{ var vs='.json_encode($vids).', is='.json_encode($iids).', ps='.json_encode($pids).';';
      echo 'var vsel=document.querySelector("select[name=\\"vehicles[]\\"]"); if(vsel){ if(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2){ window.jQuery(vsel).val(vs.map(String)).trigger("change"); } else { vs.forEach(function(id){ var opt=[...vsel.options].find(o=>o.value==String(id)); if(opt) opt.selected=true; }); vsel.dispatchEvent(new Event("change")); } }';
      echo 'var isel=document.querySelector("select[name=\\"implements[]\\"]"); if(isel){ if(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2){ window.jQuery(isel).val(is.map(String)).trigger("change"); } else { is.forEach(function(id){ var opt=[...isel.options].find(o=>o.value==String(id)); if(opt) opt.selected=true; }); isel.dispatchEvent(new Event("change")); } }';
      echo 'var psel=document.querySelector("select[name=\\"persons[]\\"]"); if(psel){ if(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2){ window.jQuery(psel).val(ps.map(String)).trigger("change"); } else { ps.forEach(function(id){ var opt=[...psel.options].find(o=>o.value==String(id)); if(opt) opt.selected=true; }); psel.dispatchEvent(new Event("change")); } }';
      echo '}catch(e){}';
    }
  ?>

  // --- Calda assistant (mix calculation) ---
  const styles = document.createElement('style');
  styles.textContent = `.calda-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;z-index:1000}.calda-modal{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(520px,92vw);background:#fff;border-radius:10px;box-shadow:0 18px 40px rgba(2,6,23,.25);padding:18px 16px;display:none;z-index:1001}.calda-modal h3{margin:0 0 12px}.calda-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.calda-row{display:flex;flex-direction:column;gap:6px}.calda-footer{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}.calda-list{margin-top:10px;border-top:1px solid #eee;padding-top:10px}.calda-list li{display:flex;justify-content:space-between;padding:4px 0}`;
  document.head.appendChild(styles);

  const backdrop = document.createElement('div'); backdrop.className='calda-backdrop';
  const modal = document.createElement('div'); modal.className='calda-modal';
  modal.innerHTML = `
    <h3>`+ (<?php echo json_encode($langs->trans('SafraAplicacaoCaldaCalculation') ?: 'Cálculo de calda'); ?>) +`</h3>
    <div class="calda-grid">
      <div class="calda-row">
        <label>`+ (<?php echo json_encode($langs->trans('ApplicationRate') ?: 'Taxa de aplicação (L/ha)'); ?>) +`</label>
        <input type="number" id="calda-rate" step="0.01" value="0">
      </div>
      <div class="calda-row">
        <label>`+ (<?php echo json_encode($langs->trans('TankCapacity') ?: 'Capacidade do tanque (L)'); ?>) +`</label>
        <input type="number" id="calda-tank" step="0.01" value="0">
      </div>
    </div>
    <div style="margin-top:10px;">
      <strong>`+ (<?php echo json_encode($langs->trans('AreaPerTank') ?: 'Área aplicada por tanque'); ?>) +`:</strong>
      <span id="calda-area">0,00</span> ha
    </div>
    <div class="calda-list">
      <strong>`+ (<?php echo json_encode($langs->trans('QuantityPerTank') ?: 'Quantidade por tanque'); ?>) +`:</strong>
      <ul id="calda-items" style="list-style:none;margin:8px 0 0;padding:0"></ul>
    </div>
    <div class="calda-footer">
      <button type="button" class="button" id="calda-cancel">`+ (<?php echo json_encode($langs->trans('Cancel') ?: 'Cancelar'); ?>) +`</button>
      <button type="button" class="button button-save" id="calda-save">`+ (<?php echo json_encode($langs->trans('SaveAsNote') ?: 'Salvar como observação'); ?>) +`</button>
    </div>`;
  document.body.appendChild(backdrop); document.body.appendChild(modal);

  function openCalda(){ backdrop.style.display='block'; modal.style.display='block'; computeCalda(); }
  function closeCalda(){ backdrop.style.display='none'; modal.style.display='none'; }
  function getRows(){ return Array.from(document.getElementById('lines-body').querySelectorAll('.safra-line-row')); }
  function textOfSelected(sel){ const o=sel && sel.options && sel.selectedIndex>=0 ? sel.options[sel.selectedIndex] : null; return o? o.text.trim(): ''; }

  function computeCalda(){
    const rate = parseFloat(document.getElementById('calda-rate').value||'0')||0; // L/ha
    const tank = parseFloat(document.getElementById('calda-tank').value||'0')||0; // L
    const areaPerTank = rate>0 ? (tank / rate) : 0;
    document.getElementById('calda-area').textContent = areaPerTank.toFixed(2).replace('.',',');
    const list = document.getElementById('calda-items'); list.innerHTML='';
    getRows().forEach(function(row){
      const pUI = row.querySelector('select[id$="_fk_product_ui"]');
      const dose = parseFloat((row.querySelector('input[name*="[dose]"]')||{}).value||'0')||0;
      const unitSel = row.querySelector('select[name*="[dose_unit]"]');
      const unit = unitSel ? unitSel.value : '';
      const label = textOfSelected(pUI);
      if(!label || !(dose>0) || !(areaPerTank>0)) return;
      const qtyTank = dose * areaPerTank; // dose unit already per ha
      const li = document.createElement('li');
      li.innerHTML = '<span>'+label+'</span><span>'+qtyTank.toFixed(2).replace('.', ',')+' '+unit.replace('/ha','')+'</span>';
      list.appendChild(li);
    });
  }

  document.getElementById('btn-calda').addEventListener('click', openCalda);
  backdrop.addEventListener('click', closeCalda);
  document.getElementById('calda-cancel').addEventListener('click', closeCalda);
  document.getElementById('calda-rate').addEventListener('input', computeCalda);
  document.getElementById('calda-tank').addEventListener('input', computeCalda);
  document.getElementById('calda-save').addEventListener('click', function(){
    const rate = parseFloat(document.getElementById('calda-rate').value||'0')||0;
    const tank = parseFloat(document.getElementById('calda-tank').value||'0')||0;
    const area = document.getElementById('calda-area').textContent;
    let text = 'Cálculo de calda:\n';
    text += '- Taxa: '+rate.toFixed(2)+' L/ha\n';
    text += '- Tanque: '+tank.toFixed(2)+' L\n';
    text += '- Área por tanque: '+area+' ha\n';
    text += '- Insumos por tanque:\n';
    document.querySelectorAll('#calda-items li').forEach(function(li){
      const spans = li.querySelectorAll('span');
      const label = spans[0] ? spans[0].textContent.trim() : '';
      const qtyText = spans[1] ? spans[1].textContent.trim() : '';
      if(label && qtyText){ text += '  * '+label+': '+qtyText+'\n'; }
    });
    const obs = document.getElementById('calda_observacao');
    obs.value = (obs.value ? (obs.value+"\n\n") : '') + text;
    closeCalda();
  });
});
</script>
<?php llxFooter(); $db->close(); ?>
