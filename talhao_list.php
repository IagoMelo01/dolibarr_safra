<?php
/* Copyright (C) 2007-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       talhao_list.php
 *		\ingroup    safra
 *		\brief      List page for talhao
 */


// General defined Options
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');					// Force use of CSRF protection with tokens even for GET
//if (! defined('MAIN_AUTHENTICATION_MODE')) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined('MAIN_LANG_DEFAULT'))        define('MAIN_LANG_DEFAULT', 'auto');					// Force LANG (language) to a particular value
//if (! defined('MAIN_SECURITY_FORCECSP'))   define('MAIN_SECURITY_FORCECSP', 'none');				// Disable all Content Security Policies
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');					// Disable browser notification
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');						// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOLOGIN'))                  define('NOLOGIN', '1');						// Do not use login - if this page is public (can be called outside logged session). This includes the NOIPCHECK too.
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  		// Do not load ajax.lib.php library
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');					// Do not create database handler $db
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');					// Do not load html.form.class.php
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');					// Do not load and show top and left menu
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');					// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');					// Do not load object $langs
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');					// Do not load object $user
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');			// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');			// Do not check injection attack on POST parameters
//if (! defined('NOSESSION'))                define('NOSESSION', '1');						// On CLI mode, no need to use web sessions
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');					// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');					// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)


// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

// load module libraries
require_once __DIR__.'/class/talhao.class.php';

// for other modules
//dol_include_once('/othermodule/class/otherobject.class.php');

// Load translation files required by the page
$langs->loadLangs(array("safra@safra", "other"));

// Get parameters
$action     = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view'; // The action 'create'/'add', 'edit'/'update', 'view', ...
$originalAction = $action;
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : str_replace('_', '', basename(dirname(__FILE__)).basename(__FILE__, '.php')); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss  = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$mode       = GETPOST('mode', 'aZ'); // The output mode ('list', 'kanban', 'hierarchy', 'calendar', ...)

$displayImportForm = ($action === 'show_import');
if ($displayImportForm) {
        $action = 'list';
}

$exportRequested = in_array($originalAction, array('export_kml', 'export_kmz'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$originalLimitValue = $limit;
if ($exportRequested) {
        $limit = 0;
}
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
        // If $page is not defined, or '' or -1 or if we click on clear filters
        $page = 0;
}
if ($exportRequested) {
        $page = 0;
}
$offset = $limit * $page;
$offset = $exportRequested ? 0 : $offset;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize technical objects
$object = new Talhao($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->safra->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array($contextpage)); 	// Note that conf->hooks_modules contains array of activated contexes

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
//$extrafields->fetch_name_optionals_label($object->table_element_line);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order (if not yet defined by previous GETPOST)
if (!$sortfield) {
	reset($object->fields);					// Reset is required to avoid key() to return null.
	$sortfield = "t.".key($object->fields); // Set here default search field. By default 1st field in definition.
}
if (!$sortorder) {
	$sortorder = "ASC";
}

// Initialize array of search criterias
$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
	if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
		$search[$key.'_dtstart'] = dol_mktime(0, 0, 0, GETPOST('search_'.$key.'_dtstartmonth', 'int'), GETPOST('search_'.$key.'_dtstartday', 'int'), GETPOST('search_'.$key.'_dtstartyear', 'int'));
		$search[$key.'_dtend'] = dol_mktime(23, 59, 59, GETPOST('search_'.$key.'_dtendmonth', 'int'), GETPOST('search_'.$key.'_dtendday', 'int'), GETPOST('search_'.$key.'_dtendyear', 'int'));
	}
}

$fieldstosearchall = array();
// List of fields to search into when doing a "search in all"
// foreach ($object->fields as $key => $val) {
// 	if (!empty($val['searchall'])) {
// 		$fieldstosearchall['t.'.$key] = $val['label'];
// 	}
// }
// $parameters = array('fieldstosearchall'=>$fieldstosearchall);
// $reshook = $hookmanager->executeHooks('completeFieldsToSearchAll', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
// if ($reshook > 0) {
// 	$fieldstosearchall = empty($hookmanager->resArray['fieldstosearchall']) ? array() : $hookmanager->resArray['fieldstosearchall'];
// } elseif ($reshook == 0) {
// 	$fieldstosearchall = array_merge($fieldstosearchall, empty($hookmanager->resArray['fieldstosearchall']) ? array() : $hookmanager->resArray['fieldstosearchall']);
// }

// Definition of array of fields for columns
$arrayfields = array();
foreach ($object->fields as $key => $val) {
	// If $val['visible']==0, then we never show the field
	if (!empty($val['visible'])) {
		$visible = (int) dol_eval($val['visible'], 1);
		$arrayfields['t.'.$key] = array(
			'label'=>$val['label'],
			'checked'=>(($visible < 0) ? 0 : 1),
			'enabled'=>(abs($visible) != 3 && dol_eval($val['enabled'], 1)),
			'position'=>$val['position'],
			'help'=> isset($val['help']) ? $val['help'] : ''
		);
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

$object->fields = dol_sort_array($object->fields, 'position');
//$arrayfields['anotherfield'] = array('type'=>'integer', 'label'=>'AnotherField', 'checked'=>1, 'enabled'=>1, 'position'=>90, 'csslist'=>'right');
$arrayfields = dol_sort_array($arrayfields, 'position');

// There is several ways to check permission.
// Set $enablepermissioncheck to 1 to enable a minimum low level of checks
$enablepermissioncheck = 0;
if ($enablepermissioncheck) {
	$permissiontoread = $user->hasRight('safra', 'talhao', 'read');
	$permissiontoadd = $user->hasRight('safra', 'talhao', 'write');
	$permissiontodelete = $user->hasRight('safra', 'talhao', 'delete');
} else {
	$permissiontoread = 1;
	$permissiontoadd = 1;
	$permissiontodelete = 1;
}

// Security check (enable the most restrictive one)
if ($user->socid > 0) {
	accessforbidden();
}
//if ($user->socid > 0) accessforbidden();
//$socid = 0; if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->module, 0, $object->table_element, $object->element, 'fk_soc', 'rowid', $isdraft);
if (!isModEnabled("safra")) {
	accessforbidden('Module safra not enabled');
}
if (!$permissiontoread) {
	accessforbidden();
}


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
        if ($originalAction === 'import_kml' && $permissiontoadd) {
                $importErrors = array();
                $importMunicipio = GETPOSTINT('import_municipio');
                $importStatus = GETPOSTINT('import_status');
                if ($importStatus === null) {
                        $importStatus = Talhao::STATUS_VALIDATED;
                }

                if (empty($importMunicipio)) {
                        $importErrors[] = $langs->trans('TalhaoImportErrorMunicipioRequired');
                }

                $fileInfo = isset($_FILES['import_file']) ? $_FILES['import_file'] : null;
                if (empty($fileInfo) || empty($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
                        $importErrors[] = $langs->trans('TalhaoImportErrorFileRequired');
                } elseif (!empty($fileInfo['error'])) {
                        $importErrors[] = $langs->trans('TalhaoImportErrorUpload', $fileInfo['error']);
                }

                if (empty($importErrors)) {
                        dol_include_once('/safra/lib/talhao_geo.lib.php');
                        $kmlContent = safra_talhao_read_kml_content($fileInfo['tmp_name']);
                        if ($kmlContent === false) {
                                $importErrors[] = $langs->trans('TalhaoImportErrorReadFile');
                        }
                }

                $features = array();
                if (empty($importErrors)) {
                        $parseErrors = array();
                        $features = safra_talhao_parse_kml($kmlContent, $parseErrors);
                        if (!empty($parseErrors)) {
                                foreach ($parseErrors as $parseError) {
                                        $importErrors[] = $parseError;
                                }
                        }
                        if (empty($features)) {
                                $importErrors[] = $langs->trans('TalhaoImportErrorNoFeatures');
                        }
                }

                if (!empty($importErrors)) {
                        setEventMessages('', $importErrors, 'errors');
                        $displayImportForm = true;
                        $action = 'list';
                } else {
                        $created = 0;
                        $skipped = 0;
                        $warnings = array();
                        $talhaoChecker = new Talhao($db);

                        $refSanitizer = function ($text) {
                                $text = (string) $text;
                                $text = preg_replace('/[^\pL\pN]+/u', '-', $text);
                                $text = trim($text, "-_ ");
                                if ($text === '') {
                                        $text = 'TALHAO';
                                }
                                $text = strtoupper($text);
                                if (dol_strlen($text) > 64) {
                                        $text = dol_substr($text, 0, 64);
                                }

                                return $text;
                        };

                        foreach ($features as $index => $feature) {
                                $polygons = isset($feature['polygons']) ? $feature['polygons'] : array();
                                if (empty($polygons)) {
                                        $skipped++;
                                        continue;
                                }

                                $name = isset($feature['name']) && $feature['name'] ? $feature['name'] : $langs->trans('TalhaoImportDefaultName', $index + 1);
                                $description = isset($feature['description']) ? $feature['description'] : '';
                                $properties = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : array();

                                $desiredRef = '';
                                foreach (array('ref', 'codigo', 'code', 'name') as $propertyKey) {
                                        if (!empty($properties[$propertyKey])) {
                                                $desiredRef = $properties[$propertyKey];
                                                break;
                                        }
                                }
                                if ($desiredRef === '') {
                                        $desiredRef = $name;
                                }

                                $baseRef = $refSanitizer($desiredRef);
                                $candidateRef = $baseRef;
                                $suffix = 1;
                                while ($talhaoChecker->fetch(0, $candidateRef) > 0) {
                                        $suffix++;
                                        $candidateRef = $baseRef.'-'.$suffix;
                                        if (dol_strlen($candidateRef) > 124) {
                                                $candidateRef = dol_substr($baseRef, 0, 124 - dol_strlen((string) $suffix)).'-'.$suffix;
                                        }
                                        if ($suffix > 9999) {
                                                break;
                                        }
                                }

                                if ($suffix > 9999) {
                                        $warnings[] = $langs->trans('TalhaoImportErrorRef', $baseRef);
                                        $skipped++;
                                        continue;
                                }

                                $effectiveMunicipio = $importMunicipio;
                                if (!empty($properties['municipio_id']) && is_numeric($properties['municipio_id'])) {
                                        $effectiveMunicipio = (int) $properties['municipio_id'];
                                }

                                $areaHa = safra_talhao_polygons_area_ha($polygons);
                                $bbox = safra_talhao_polygons_bbox($polygons);
                                $center = $bbox ? safra_talhao_center_from_bbox($bbox) : null;

                                $talhao = new Talhao($db);
                                $talhao->ref = $candidateRef;
                                $talhao->label = dol_trunc($name, 255);
                                $talhao->description = $description;
                                $talhao->municipio = $effectiveMunicipio;
                                $talhao->status = $importStatus;
                                $talhao->area = round($areaHa, 4);
                                $talhao->geo_json = safra_talhao_polygons_to_geojson($polygons, array('ref' => $candidateRef, 'name' => $name));
                                $talhao->wkt = safra_talhao_polygons_to_wkt($polygons);
                                $talhao->bbox = $bbox ? safra_talhao_format_bbox($bbox) : '';
                                $talhao->center = $center ? safra_talhao_format_center($center) : '';

                                $resultCreate = $talhao->create($user);
                                if ($resultCreate > 0) {
                                        $created++;
                                } else {
                                        $warnings[] = $langs->trans('TalhaoImportErrorCreate', $candidateRef, $talhao->error);
                                }
                        }

                        if ($created > 0) {
                                setEventMessages($langs->trans('TalhaoImportSuccess', $created, count($features)), null, 'mesgs');
                        }
                        if (!empty($warnings)) {
                                setEventMessages('', $warnings, 'warnings');
                        }

                        header('Location: '.$_SERVER['PHP_SELF']);
                        exit;
                }
        }

        // Selection of new fields
        include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
			if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
				$search[$key.'_dtstart'] = '';
				$search[$key.'_dtend'] = '';
			}
		}
		$toselect = array();
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'Talhao';
	$objectlabel = 'Talhao';
	$uploaddir = $conf->safra->dir_output;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

	// You can add more action here
	// if ($action == 'xxx' && $permissiontoxxx) ...
}



/*
 * View
 */

$form = new Form($db);

$now = dol_now();

$title = $langs->trans("Talhaos");
//$help_url = "EN:Module_Talhao|FR:Module_Talhao_FR|ES:Módulo_Talhao";
$help_url = '';
$morejs = array();
$morecss = array();


// Build and execute select
// --------------------------------------------------------------------
$sql = 'SELECT ';
$sql .= $object->getFieldList('t');
// Add fields from extrafields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? ", ef.".$key." as options_".$key : '');
	}
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
$sql = preg_replace('/,\s*$/', '', $sql);

$sqlfields = $sql; // $sql fields to remove for count total

$sql .= " FROM ".MAIN_DB_PREFIX.$object->table_element." as t";
//$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."anothertable as rc ON rc.parent = t.rowid";
if (isset($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (t.rowid = ef.fk_object)";
}
// Add table from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
if ($object->ismultientitymanaged == 1) {
	$sql .= " WHERE t.entity IN (".getEntity($object->element, (GETPOST('search_current_entity', 'int') ? 0 : 1)).")";
} else {
	$sql .= " WHERE 1 = 1";
}
foreach ($search as $key => $val) {
	if (array_key_exists($key, $object->fields)) {
		if ($key == 'status' && $search[$key] == -1) {
			continue;
		}
		$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
		if ((strpos($object->fields[$key]['type'], 'integer:') === 0) || (strpos($object->fields[$key]['type'], 'sellist:') === 0) || !empty($object->fields[$key]['arrayofkeyval'])) {
			if ($search[$key] == '-1' || ($search[$key] === '0' && (empty($object->fields[$key]['arrayofkeyval']) || !array_key_exists('0', $object->fields[$key]['arrayofkeyval'])))) {
				$search[$key] = '';
			}
			$mode_search = 2;
		}
		if ($search[$key] != '') {
			$sql .= natural_search("t.".$db->escape($key), $search[$key], (($key == 'status') ? 2 : $mode_search));
		}
	} else {
		if (preg_match('/(_dtstart|_dtend)$/', $key) && $search[$key] != '') {
			$columnName = preg_replace('/(_dtstart|_dtend)$/', '', $key);
			if (preg_match('/^(date|timestamp|datetime)/', $object->fields[$columnName]['type'])) {
				if (preg_match('/_dtstart$/', $key)) {
					$sql .= " AND t.".$db->escape($columnName)." >= '".$db->idate($search[$key])."'";
				}
				if (preg_match('/_dtend$/', $key)) {
					$sql .= " AND t.".$db->escape($columnName)." <= '".$db->idate($search[$key])."'";
				}
			}
		}
	}
}
if ($search_all) {
	$sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}
//$sql.= dolSqlDateFilter("t.field", $search_xxxday, $search_xxxmonth, $search_xxxyear);
// Add where from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

/* If a group by is required
$sql .= " GROUP BY ";
foreach($object->fields as $key => $val) {
	$sql .= "t.".$db->escape($key).", ";
}
// Add fields from extrafields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? "ef.".$key.', ' : '');
	}
}
// Add groupby from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListGroupBy', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
$sql = preg_replace('/,\s*$/', '', $sql);
*/

// Add HAVING from hooks
/*
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListHaving', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$sql .= empty($hookmanager->resPrint) ? "" : " HAVING 1=1 ".$hookmanager->resPrint;
*/

$sqlForExport = $sql;

if ($exportRequested) {
        dol_include_once('/safra/lib/talhao_geo.lib.php');

        $exportSql = $sqlForExport.$db->order($sortfield, $sortorder);
        $resExport = $db->query($exportSql);
        if ($resExport) {
                $rowsExport = array();
                while ($objExport = $db->fetch_object($resExport)) {
                        $rowsExport[] = $objExport;
                }
                $db->free($resExport);

                if (empty($rowsExport)) {
                        setEventMessages($langs->trans('TalhaoExportEmpty'), null, 'warnings');
                        $exportRequested = false;
                        $originalAction = 'list';
                        $action = 'list';
                        $limit = $originalLimitValue;
                        $page = 0;
                        $offset = 0;
                } else {
                        $municipioLabels = array();
                        $municipioIds = array();
                        foreach ($rowsExport as $rowExport) {
                                if (!empty($rowExport->municipio)) {
                                        $municipioIds[(int) $rowExport->municipio] = true;
                                }
                        }
                        if (!empty($municipioIds)) {
                                $municipioIdList = array_map('intval', array_keys($municipioIds));
                                $sqlMunicipio = 'SELECT rowid, ref FROM '.MAIN_DB_PREFIX."safra_municipio WHERE rowid IN (".implode(',', $municipioIdList).')';
                                $resMunicipio = $db->query($sqlMunicipio);
                                if ($resMunicipio) {
                                        while ($objMunicipio = $db->fetch_object($resMunicipio)) {
											$municipioLabels[$objMunicipio->rowid] = $objMunicipio->ref;
											
                                        }
                                        $db->free($resMunicipio);
                                }
                        }

                        $features = array();
                        foreach ($rowsExport as $rowExport) {
                                $geojsonRaw = isset($rowExport->geo_json) ? (string) $rowExport->geo_json : '';
                                $polygons = safra_talhao_extract_polygons_from_geojson($geojsonRaw);
                                if (empty($polygons) && !empty($rowExport->wkt)) {
                                        $polygons = safra_talhao_extract_polygons_from_wkt($rowExport->wkt);
                                }
                                if (empty($polygons)) {
                                        continue;
                                }

                                $properties = array(
                                        'ref' => $rowExport->ref,
                                        'area_ha' => (float) $rowExport->area,
                                );

                                if (!empty($rowExport->municipio)) {
                                        $properties['municipio_id'] = (int) $rowExport->municipio;
                                        if (!empty($municipioLabels[$rowExport->municipio])) {
                                                $properties['municipio'] = $municipioLabels[$rowExport->municipio];
                                        }
                                }

                                $features[] = array(
                                        'name' => ($rowExport->label ? $rowExport->label : $rowExport->ref),
                                        'description' => strip_tags((string) $rowExport->description),
                                        'polygons' => $polygons,
                                        'properties' => $properties,
                                );
                        }

                        if (empty($features)) {
                                setEventMessages($langs->trans('TalhaoExportEmptyGeometry'), null, 'warnings');
                                $exportRequested = false;
                                $originalAction = 'list';
                                $action = 'list';
                                $limit = $originalLimitValue;
                                $page = 0;
                                $offset = 0;
                        } else {
                                $nowExport = dol_now();
                                $filenameBase = 'talhoes_'.date('Ymd_His', $nowExport);
                                $kmlContent = safra_talhao_build_kml($features);

                                if ($originalAction === 'export_kmz') {
                                        $kmzContent = safra_talhao_wrap_kml_to_kmz($kmlContent);
                                        if ($kmzContent === false) {
                                                setEventMessages($langs->trans('TalhaoExportKmzUnavailable'), null, 'errors');
                                                $exportRequested = false;
                                                $originalAction = 'list';
                                                $action = 'list';
                                                $limit = $originalLimitValue;
                                                $page = 0;
                                                $offset = 0;
                                        } else {
                                                header('Content-Type: application/vnd.google-earth.kmz');
                                                header('Content-Disposition: attachment; filename="'.$filenameBase.'.kmz"');
                                                header('Content-Length: '.dol_strlen($kmzContent));
                                                echo $kmzContent;
                                                exit;
                                        }
                                } else {
                                        header('Content-Type: application/vnd.google-earth.kml+xml');
                                        header('Content-Disposition: attachment; filename="'.$filenameBase.'.kml"');
                                        header('Content-Length: '.dol_strlen($kmlContent));
                                        echo $kmlContent;
                                        exit;
                                }
                        }
                }
        } else {
                dol_print_error($db);
                exit;
        }
}

// Count total nb of records
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	/* The fast and low memory method to get and count full list converts the sql into a sql count */
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$sqlforcount = preg_replace('/GROUP BY .*$/', '', $sqlforcount);

	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount = $db->fetch_object($resql);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
	} else {
		dol_print_error($db);
	}

	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller than the paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
	$db->free($resql);
}

// Complete request and execute it with limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);


// Direct jump if only one record found
if ($num == 1 && getDolGlobalInt('MAIN_SEARCH_DIRECT_OPEN_IF_ONLY_ONE') && $search_all && !$page) {
	$obj = $db->fetch_object($resql);
	$id = $obj->rowid;
	header("Location: ".dol_buildpath('/safra/talhao_card.php', 1).'?id='.((int) $id));
	exit;
}


// Output page
// --------------------------------------------------------------------

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss, '', 'mod-safra page-list bodyforlist');	// Can use also classforhorizontalscrolloftabs instead of bodyforlist for no horizontal scroll

// Example : Adding jquery code
// print '<script type="text/javascript">
// jQuery(document).ready(function() {
// 	function init_myfunc()
// 	{
// 		jQuery("#myid").removeAttr(\'disabled\');
// 		jQuery("#myid").attr(\'disabled\',\'disabled\');
// 	}
// 	init_myfunc();
// 	jQuery("#mybutton").click(function() {
// 		init_myfunc();
// 	});
// });
// </script>';

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($mode)) {
	$param .= '&mode='.urlencode($mode);
}
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if ($optioncss != '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
foreach ($search as $key => $val) {
	if (is_array($search[$key])) {
		foreach ($search[$key] as $skey) {
			if ($skey != '') {
				$param .= '&search_'.$key.'[]='.urlencode($skey);
			}
		}
	} elseif (preg_match('/(_dtstart|_dtend)$/', $key) && !empty($val)) {
		$param .= '&search_'.$key.'month='.((int) GETPOST('search_'.$key.'month', 'int'));
		$param .= '&search_'.$key.'day='.((int) GETPOST('search_'.$key.'day', 'int'));
		$param .= '&search_'.$key.'year='.((int) GETPOST('search_'.$key.'year', 'int'));
	} elseif ($search[$key] != '') {
		$param .= '&search_'.$key.'='.urlencode($search[$key]);
	}
}
// Add $param from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';
// Add $param from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$param .= $hookmanager->resPrint;

// List of mass actions available
$arrayofmassactions = array(
	//'validate'=>img_picto('', 'check', 'class="pictofixedwidth"').$langs->trans("Validate"),
	//'generate_doc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("ReGeneratePDF"),
	//'builddoc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("PDFMerge"),
	//'presend'=>img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans("SendByMail"),
);
if (!empty($permissiontodelete)) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
}
if (GETPOST('nomassaction', 'int') || in_array($massaction, array('presend', 'predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

//div para o mapa leaflet
print '<div class="container"><div class="item" id="mapList"></div><div class="item">';

if ($displayImportForm) {
        $importMunicipioValue = GETPOSTINT('import_municipio');
        $importStatusValue = GETPOSTINT('import_status');
        if ($importStatusValue === null) {
                $importStatusValue = Talhao::STATUS_VALIDATED;
        }

        $municipioChoices = array();
        $sqlMunicipios = 'SELECT rowid, ref FROM '.MAIN_DB_PREFIX."safra_municipio ORDER BY label";
        $resMunicipios = $db->query($sqlMunicipios);
        if ($resMunicipios) {
                while ($objMunicipio = $db->fetch_object($resMunicipios)) {
                        $municipioChoices[$objMunicipio->rowid] = $objMunicipio->ref;
                }
                $db->free($resMunicipios);
        }

        $statusChoices = isset($object->fields['status']['arrayofkeyval']) ? $object->fields['status']['arrayofkeyval'] : array();

        print '<div class="talhao-import-panel">';
        print '<div class="talhao-import-panel__header">';
        print '<h3>'.$langs->trans('TalhaoImportTitle').'</h3>';
        print '<p>'.$langs->trans('TalhaoImportHelp').'</p>';
        print '</div>';
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" enctype="multipart/form-data" class="talhao-import-panel__form">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="import_kml">';
        print '<div class="talhao-import-panel__row">';
        print '<label class="talhao-import-panel__label" for="import_file">'.$langs->trans('TalhaoImportFile').'</label>';
        print '<input class="talhao-import-panel__input" type="file" name="import_file" id="import_file" accept=".kml,.kmz" required>';
        print '</div>';
        print '<div class="talhao-import-panel__row">';
        print '<label class="talhao-import-panel__label" for="import_municipio">'.$langs->trans('TalhaoImportMunicipio').'</label>';
        print $form->selectarray('import_municipio', $municipioChoices, $importMunicipioValue, 1, 0, 0, '', 0, 0, 0, '', '', 'talhao-import-panel__select');
        print '</div>';
        if (!empty($statusChoices)) {
                print '<div class="talhao-import-panel__row">';
                print '<label class="talhao-import-panel__label" for="import_status">'.$langs->trans('TalhaoImportStatus').'</label>';
                print $form->selectarray('import_status', $statusChoices, $importStatusValue, 0, 0, 0, '', 0, 0, 0, '', '', 'talhao-import-panel__select');
                print '</div>';
        }
        print '<div class="talhao-import-panel__actions">';
        print '<button type="submit" class="butAction">'.$langs->trans('TalhaoImportSubmit').'</button>';
        print '&nbsp;<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Cancel').'</a>';
        print '</div>';
        print '</form>';
        print '</div>';
}

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="page_y" value="">';
print '<input type="hidden" name="mode" value="'.$mode.'">';


$newcardbutton = '';
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewList'), '', 'fa fa-bars imgforviewmode', $_SERVER["PHP_SELF"].'?mode=common'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', ((empty($mode) || $mode == 'common') ? 2 : 1), array('morecss'=>'reposition'));
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewKanban'), '', 'fa fa-th-list imgforviewmode', $_SERVER["PHP_SELF"].'?mode=kanban'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', ($mode == 'kanban' ? 2 : 1), array('morecss'=>'reposition'));
$newcardbutton .= dolGetButtonTitleSeparator();
$newcardbutton .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/safra/talhao_card.php', 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF']), '', $permissiontoadd);
if ($permissiontoadd) {
        $newcardbutton .= dolGetButtonTitle($langs->trans('TalhaoImportButton'), '', 'fa fa-upload', $_SERVER["PHP_SELF"].'?action=show_import'.$param, '', 1);
}
if ($permissiontoread) {
        $newcardbutton .= dolGetButtonTitle($langs->trans('TalhaoExportKml'), '', 'fa fa-download', $_SERVER["PHP_SELF"].'?action=export_kml'.$param, '', 1);
        if (class_exists('ZipArchive')) {
                $newcardbutton .= dolGetButtonTitle($langs->trans('TalhaoExportKmz'), '', 'fa fa-download', $_SERVER["PHP_SELF"].'?action=export_kmz'.$param, '', 1);
        }
}

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'object_'.$object->picto, 0, $newcardbutton, '', $limit, 0, 0, 1);

// Add code for pre mass action (confirmation or email presend form)
$topicmail = "SendTalhaoRef";
$modelmail = "talhao";
$objecttmp = new Talhao($db);
$trackid = 'xxxx'.$object->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if ($search_all) {
	$setupstring = '';
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
		$setupstring .= $key."=".$val.";";
	}
	print '<!-- Search done like if MYOBJECT_QUICKSEARCH_ON_FIELDS = '.$setupstring.' -->'."\n";
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $search_all).join(', ', $fieldstosearchall).'</div>'."\n";
}

$moreforfilter = '';
/*$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.= $langs->trans('MyFilter') . ': <input type="text" name="search_myfield" value="'.dol_escape_htmltag($search_myfield).'">';
$moreforfilter.= '</div>';*/

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	$moreforfilter .= $hookmanager->resPrint;
} else {
	$moreforfilter = $hookmanager->resPrint;
}

if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	print '</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$selectedfields = ($mode != 'kanban' ? $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN', '')) : ''); // This also change content of $arrayfields
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="talhao-list-lead" role="note">';
print '        <span class="talhao-list-lead__icon"><i class="fa fa-map-marker"></i></span>';
print '        <div class="talhao-list-lead__content">';
print '                <strong>Organize seus talhões com mais clareza.</strong>';
print '                <p>Combine a busca geral com os filtros de cada coluna para localizar rapidamente qualquer área. Clique no código do talhão para abrir sua ficha completa.</p>';
print '        </div>';
print '</div>';

print '<div class="div-table-responsive talhao-list-container">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").' talhao-list-table">'."\n";

// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre_filter">';
// Action column
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print '<td class="liste_titre center maxwidthsearch">';
	$searchpicto = $form->showFilterButtons('left');
	print $searchpicto;
	print '</td>';
}
foreach ($object->fields as $key => $val) {
	$searchkey = empty($search[$key]) ? '' : $search[$key];
	$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
	} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('id', 'rowid', 'ref', 'status')) && $val['label'] != 'TechnicalID' && empty($val['arrayofkeyval'])) {
		$cssforfield .= ($cssforfield ? ' ' : '').'right';
	}
	if (!empty($arrayfields['t.'.$key]['checked'])) {
		print '<td class="liste_titre'.($cssforfield ? ' '.$cssforfield : '').($key == 'status' ? ' parentonrightofpage' : '').'">';
		if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
			print $form->selectarray('search_'.$key, $val['arrayofkeyval'], (isset($search[$key]) ? $search[$key] : ''), 1, 0, 0, '', 1, 0, 0, '', 'maxwidth100'.($key == 'status' ? ' search_status width100 onrightofpage' : ''), 1);
		} elseif ((strpos($val['type'], 'integer:') === 0) || (strpos($val['type'], 'sellist:') === 0)) {
			print $object->showInputField($val, $key, (isset($search[$key]) ? $search[$key] : ''), '', '', 'search_', $cssforfield.' maxwidth250', 1);
		} elseif (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
			print '<div class="nowrap">';
			print $form->selectDate($search[$key.'_dtstart'] ? $search[$key.'_dtstart'] : '', "search_".$key."_dtstart", 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
			print '</div>';
			print '<div class="nowrap">';
			print $form->selectDate($search[$key.'_dtend'] ? $search[$key.'_dtend'] : '', "search_".$key."_dtend", 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
			print '</div>';
		} elseif ($key == 'lang') {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
			$formadmin = new FormAdmin($db);
			print $formadmin->select_language($search[$key], 'search_lang', 0, null, 1, 0, 0, 'minwidth150 maxwidth200', 2);
		} else {
			print '<input type="text" class="flat maxwidth75" name="search_'.$key.'" value="'.dol_escape_htmltag(isset($search[$key]) ? $search[$key] : '').'">';
		}
		print '</td>';
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';

// Fields from hook
$parameters = array('arrayfields'=>$arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
/*if (!empty($arrayfields['anotherfield']['checked'])) {
	print '<td class="liste_titre"></td>';
}*/
// Action column
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print '<td class="liste_titre center maxwidthsearch">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';
}
print '</tr>'."\n";

$totalarray = array();
$totalarray['nbfield'] = 0;

// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
// Action 
if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
foreach ($object->fields as $key => $val) {
	$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
	} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('id', 'rowid', 'ref', 'status')) && $val['label'] != 'TechnicalID' && empty($val['arrayofkeyval'])) {
		$cssforfield .= ($cssforfield ? ' ' : '').'right';
	}
	$cssforfield = preg_replace('/small\s*/', '', $cssforfield);	// the 'small' css must not be used for the title label
	if (!empty($arrayfields['t.'.$key]['checked'])) {
		print getTitleFieldOfList($arrayfields['t.'.$key]['label'], 0, $_SERVER['PHP_SELF'], 't.'.$key, '', $param, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''), 0, (empty($val['helplist']) ? '' : $val['helplist']))."\n";
		$totalarray['nbfield']++;
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
// Hook fields
$parameters = array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder, 'totalarray'=>&$totalarray);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
/*if (!empty($arrayfields['anotherfield']['checked'])) {
	print '<th class="liste_titre right">'.$langs->trans("AnotherField").'</th>';
	$totalarray['nbfield']++;
}*/
// Action column
if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
print '</tr>'."\n";

// Detect if we need a fetch on each output line
$needToFetchEachLine = 0;
if (isset($extrafields->attributes[$object->table_element]['computed']) && is_array($extrafields->attributes[$object->table_element]['computed']) && count($extrafields->attributes[$object->table_element]['computed']) > 0) {
	foreach ($extrafields->attributes[$object->table_element]['computed'] as $key => $val) {
		if (!is_null($val) && preg_match('/\$object/', $val)) {
			$needToFetchEachLine++; // There is at least one compute field that use $object
		}
	}
}


// Loop on record
// --------------------------------------------------------------------
$i = 0;
$savnbfield = $totalarray['nbfield'];
$totalarray = array();
$totalarray['nbfield'] = 0;
$imaxinloop = ($limit ? min($num, $limit) : $num);
// json dos poligono
$json_pol = [];
while ($i < $imaxinloop) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	// Store properties in $object
	$object->setVarsFromFetchObj($obj);

	/*
	$object->thirdparty = null;
	if ($obj->fk_soc > 0) {
		if (!empty($conf->cache['thirdparty'][$obj->fk_soc])) {
			$companyobj = $conf->cache['thirdparty'][$obj->fk_soc];
		} else {
			$companyobj = new Societe($db);
			$companyobj->fetch($obj->fk_soc);
			$conf->cache['thirdparty'][$obj->fk_soc] = $companyobj;
		}

		$object->thirdparty = $companyobj;
	}*/

	if ($mode == 'kanban') {
		if ($i == 0) {
			print '<tr class="trkanban"><td colspan="'.$savnbfield.'">';
			print '<div class="box-flex-container kanban">';
		}
		// Output Kanban
		$selected = -1;
		if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
			$selected = 0;
			if (in_array($object->id, $arrayofselected)) {
				$selected = 1;
			}
		}
		//print $object->getKanbanView('', array('thirdparty'=>$object->thirdparty, 'selected' => $selected));
		print $object->getKanbanView('', array('selected' => $selected));
		if ($i == ($imaxinloop - 1)) {
			print '</div>';
			print '</td></tr>';
		}
	} else {
		// Show line of result
		$j = 0;
		print '<tr data-rowid="'.$object->id.'" class="oddeven">';

		// Action column
		if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
				$selected = 0;
				if (in_array($object->id, $arrayofselected)) {
					$selected = 1;
				}
				print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		foreach ($object->fields as $key => $val) {
			$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
			if (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
				$cssforfield .= ($cssforfield ? ' ' : '').'center';
			} elseif ($key == 'status') {
				$cssforfield .= ($cssforfield ? ' ' : '').'center';
			}

			if (in_array($val['type'], array('timestamp'))) {
				$cssforfield .= ($cssforfield ? ' ' : '').'nowraponall';
			} elseif ($key == 'ref') {
				$cssforfield .= ($cssforfield ? ' ' : '').'nowraponall';
			}

			if (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('id', 'rowid', 'ref', 'status')) && empty($val['arrayofkeyval'])) {
				$cssforfield .= ($cssforfield ? ' ' : '').'right';
			}
			//if (in_array($key, array('fk_soc', 'fk_user', 'fk_warehouse'))) $cssforfield = 'tdoverflowmax100';

			if (!empty($arrayfields['t.'.$key]['checked'])) {
				print '<td'.($cssforfield ? ' class="'.$cssforfield.(preg_match('/tdoverflow/', $cssforfield) ? ' classfortooltip' : '').'"' : '');
				if (preg_match('/tdoverflow/', $cssforfield) && !in_array($val['type'], array('ip', 'url')) && !is_numeric($object->$key)) {
					print ' title="'.dol_escape_htmltag($object->$key).'"';
				}
				print '>';
                                if ($key == 'status') {
                                        print '<span class="talhao-status-chip">'.$object->getLibStatut(5).'</span>';
                                } elseif ($key == 'rowid') {
                                        print $object->showOutputField($val, $key, $object->id, '');
                                } elseif ($key == 'ref') {
                                        $refValue = $object->showOutputField($val, $key, $object->$key, '');
                                        print '<strong class="talhao-list-ref">'.$refValue.'</strong>';
                                } else {
                                        print $object->showOutputField($val, $key, $object->$key, '');
                                }
				print '</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
				if (!empty($val['isameasure']) && $val['isameasure'] == 1) {
					if (!$i) {
						$totalarray['pos'][$totalarray['nbfield']] = 't.'.$key;
					}
					if (!isset($totalarray['val'])) {
						$totalarray['val'] = array();
					}
					if (!isset($totalarray['val']['t.'.$key])) {
						$totalarray['val']['t.'.$key] = 0;
					}
					$totalarray['val']['t.'.$key] += $object->$key;
				}
			}
		}
		// Extra fields
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
		// Fields from hook
		$parameters = array('arrayfields'=>$arrayfields, 'object'=>$object, 'obj'=>$obj, 'i'=>$i, 'totalarray'=>&$totalarray);
		$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;

		/*if (!empty($arrayfields['anotherfield']['checked'])) {
			print '<td class="right">'.$obj->anotherfield.'</td>';
		}*/

		// Action column
		if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
				$selected = 0;
				if (in_array($object->id, $arrayofselected)) {
					$selected = 1;
				}
				print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		print '</tr>'."\n";
	}
	$json_pol[]=$object->geo_json;
	$i++;
}

// Show total line
include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';

// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}


$db->free($resql);

$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";

// Fim da area do mapa
print '</div></div>';

if (in_array('builddoc', array_keys($arrayofmassactions)) && ($nbtotalofrecords === '' || $nbtotalofrecords)) {
	$hidegeneratedfilelistifempty = 1;
	if ($massaction == 'builddoc' || $action == 'remove_file' || $show_files) {
		$hidegeneratedfilelistifempty = 0;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
	$formfile = new FormFile($db);

	// Show list of available documents
	$urlsource = $_SERVER['PHP_SELF'].'?sortfield='.$sortfield.'&sortorder='.$sortorder;
	$urlsource .= str_replace('&amp;', '&', $param);

	$filedir = $diroutputmassaction;
	$genallowed = $permissiontoread;
	$delallowed = $permissiontoadd;

	print $formfile->showdocuments('massfilesarea_'.$object->module, '', $filedir, $urlsource, 0, $delallowed, '', 1, 1, 0, 48, 1, $param, $title, '', '', '', null, $hidegeneratedfilelistifempty);
}

?>

<style>
.talhao-list-lead {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        background: #edf6ff;
        border: 1px solid #b8dcff;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        margin: 1rem 0 1.2rem;
        color: #0b3b66;
}

.talhao-list-lead__icon {
        font-size: 1.5rem;
        color: #0b6fa4;
        line-height: 1;
        margin-top: 0.15rem;
}

.talhao-list-lead__content p {
        margin: 0.35rem 0 0;
        font-size: 0.95rem;
        line-height: 1.5;
}

.talhao-list-container {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        padding: 0;
        overflow: hidden;
}

.talhao-list-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
}

.talhao-list-table tr.liste_titre {
        background: linear-gradient(90deg, #0b6fa4 0%, #1098c2 100%);
        color: #fff;
}

.talhao-list-table tr.liste_titre th {
        color: inherit;
        font-weight: 600;
        padding: 0.75rem 1rem;
        border-bottom: none;
}

.talhao-list-table tr.liste_titre_filter td {
        background: #f8fafc;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #dbe5f2;
}

.talhao-list-table tr.liste_titre_filter input.flat,
.talhao-list-table tr.liste_titre_filter select.flat,
.talhao-list-table tr.liste_titre_filter .maxwidth100,
.talhao-list-table tr.liste_titre_filter .maxwidth250 {
        width: 100%;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding: 0.35rem 0.5rem;
        font-size: 0.9rem;
}

.talhao-list-table tr.oddeven td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #edf2f9;
        transition: background 0.2s ease;
}

.talhao-list-table tr.oddeven:hover td {
        background: #f1f7ff;
}

.talhao-list-ref {
        color: #0b6fa4;
        font-weight: 600;
}

.talhao-status-chip {
        display: inline-flex;
        align-items: center;
}

.talhao-status-chip .badge {
        padding: 0.3rem 0.65rem;
        border-radius: 999px;
        font-size: 0.85rem;
}

@media (max-width: 992px) {
        .talhao-list-lead {
                flex-direction: column;
        }

        .talhao-list-table tr.liste_titre_filter td,
        .talhao-list-table tr.liste_titre th,
        .talhao-list-table tr.oddeven td {
                padding: 0.65rem;
        }
}
</style>

<script>
        let json_pol = <?php echo json_encode($json_pol); ?>
</script>

<?php
// include js
include_once "./js/talhao_list.js.php";

// End of page
llxFooter();
$db->close();
