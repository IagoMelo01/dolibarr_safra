<?php
/* Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019-2020  Frédéric France         <frederic.france@netlogic.fr>
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
 * 	\defgroup   safra     Module Safra
 *  \brief      Safra module descriptor.
 *
 *  \file       htdocs/safra/core/modules/modSafra.class.php
 *  \ingroup    safra
 *  \brief      Description and activation file for module Safra
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module Safra
 */
class modSafra extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 500010; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'safra';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "Farmevo";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '10';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleSafraName' not found (Safra is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleSafraDesc' not found (Safra is name of module).
		$this->description = "SafraDescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "SafraDescription";

		// Author
		$this->editor_name = 'Farmevo';
		$this->editor_url = 'farmevo.com.br';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
		$this->version = 'development';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where SAFRA is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'fa-leaf';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 1,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(
				'/safra/css/safra.css.php',
			),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(
				'/safra/js/safra.js.php',
			),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
                        'hooks' => array(
                                'data' => array(
                                        'projectcard',
                                        'productcard',
                                        'productdao',
                                ),
                                //   'entity' => '0',
                        ),
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/safra/temp","/safra/subdir");
		$this->dirs = array("/safra/temp", "/safra/json/ndvi", "/safra/json/ndmi", "/safra/json/evi", "/safra/json/savi");

		// Config pages. Put here list of php page, stored into safra/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@safra");

		// Dependencies
		// A condition to hide module
		$this->hidden = false;
		// List of module class names that must be enabled if this module is enabled. Example: array('always'=>array('modModuleToEnable1','modModuleToEnable2'), 'FR'=>array('modModuleToEnableFR')...)
		$this->depends = array('always'=>array('modProjet','modEventOrganization','modSociete','modHoliday','modExpenseReport','modRecruitment','modHRM','modPropale','modCommande','modExpedition','modContrat','modFicheinter','modTicket','modKnowledgeManagement','modPartnership','modFournisseur','modSupplierProposal','modReception','modIncoterm','modFacture','modTax','modSalaries','modLoan','modDon','modBanque','modPaymentByBankTransfer','modPrelevement','modMargin','modComptabilite','modProduct','modService','modStock','modProductBatch','modVariants','modBom','modMrp','modWorkstation','modAgenda','modResource','modMultiCurrency','modExternalRss','modBookmark','modBarcode','modWorkflow','modStripe','modPaypal','modPrinting','modReceiptPrinter','modCron','modSyslog'));
		// List of module class names to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->requiredby = array();
		// List of module class names this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array();

		// The language file dedicated to your module
		$this->langfiles = array("safra@safra");

		// Prerequisites
		$this->phpmin = array(7, 0); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(11, -3); // Minimum version of Dolibarr required by module
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		//$this->automatic_activation = array('FR'=>'SafraWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('SAFRA_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('SAFRA_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array();

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isModEnabled("safra")) {
			$conf->safra = new stdClass();
			$conf->safra->enabled = 0;
		}

		// Array to add new pages in new tabs
                $this->tabs = array();
                $this->tabs[] = array(
                        'data' => 'product:+safra_product_formulado:SafraProductFormuladoTab:safra@safra:$user->hasRight(\'safra\', \'produtoformulado\', \'read\'):/safra/product_safra_links.php?id=__ID__&type=formulado'
                );
                $this->tabs[] = array(
                        'data' => 'product:+safra_product_tecnico:SafraProductTecnicoTab:safra@safra:$user->hasRight(\'safra\', \'produtostecnicos\', \'read\'):/safra/product_safra_links.php?id=__ID__&type=tecnico'
                );
		// Example:
		// $this->tabs[] = array('data'=>'objecttype:+tabname1:Title1:mylangfile@safra:$user->hasRight('safra', 'read'):/safra/mynewtab1.php?id=__ID__');  					// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data'=>'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@safra:$user->hasRight('othermodule', 'read'):/safra/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data'=>'objecttype:-tabname:NU:conditiontoremove');                                                     										// To remove an existing tab identified by code tabname
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in sale order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view

		// Dictionaries
		/* Example:
		 $this->dictionaries=array(
		 'langs'=>'safra@safra',
		 // List of tables we want to see into dictonnary editor
		 'tabname'=>array("table1", "table2", "table3"),
		 // Label of tables
		 'tablib'=>array("Table1", "Table2", "Table3"),
		 // Request to select fields
		 'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),
		 // Sort order
		 'tabsqlsort'=>array("label ASC", "label ASC", "label ASC"),
		 // List of fields (result of select to show dictionary)
		 'tabfield'=>array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields to edit a record)
		 'tabfieldvalue'=>array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields for insert)
		 'tabfieldinsert'=>array("code,label", "code,label", "code,label"),
		 // Name of columns with primary key (try to always name it 'rowid')
		 'tabrowid'=>array("rowid", "rowid", "rowid"),
		 // Condition to show each dictionary
		 'tabcond'=>array(isModEnabled('safra'), isModEnabled('safra'), isModEnabled('safra')),
		 // Tooltip for every fields of dictionaries: DO NOT PUT AN EMPTY ARRAY
		 'tabhelp'=>array(array('code'=>$langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), array('code'=>$langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), ...),
		 );
		 */
		/* BEGIN MODULEBUILDER DICTIONARIES */
		$this->dictionaries = array();
		/* END MODULEBUILDER DICTIONARIES */

		// Boxes/Widgets
		// Add here list of php file(s) stored in safra/core/boxes that contains a class to show a widget.
		/* BEGIN MODULEBUILDER WIDGETS */
		$this->boxes = array(
			//  0 => array(
			//      'file' => 'safrawidget1.php@safra',
			//      'note' => 'Widget provided by Safra',
			//      'enabledbydefaulton' => 'Home',
			//  ),
			//  ...
		);
		/* END MODULEBUILDER WIDGETS */

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		/* BEGIN MODULEBUILDER CRON */
		$this->cronjobs = array(
			//  0 => array(
			//      'label' => 'MyJob label',
			//      'jobtype' => 'method',
			//      'class' => '/safra/class/cultura.class.php',
			//      'objectname' => 'Cultura',
			//      'method' => 'doScheduledJob',
			//      'parameters' => '',
			//      'comment' => 'Comment',
			//      'frequency' => 2,
			//      'unitfrequency' => 3600,
			//      'status' => 0,
			//      'test' => 'isModEnabled("safra")',
			//      'priority' => 50,
			//  ),
			 0 => array(
			     'label' => 'Request NDVI',
			     'jobtype' => 'method',
			     'class' => '/safra/class/ndvi.class.php',
			     'objectname' => 'ndvi',
			     'method' => 'requestNDVIData',
			     'parameters' => '',
			     'comment' => 'Busca o geojson ndvi do sentinel hub',
			     'frequency' => 1,
			     'unitfrequency' => 604800,
			     'status' => 0,
			     'test' => 'isModEnabled("safra")',
			     'priority' => 50,
			 ),
		);
		/* END MODULEBUILDER CRON */
		// Example: $this->cronjobs=array(
		//    0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>'isModEnabled("safra")', 'priority'=>50),
		//    1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>'isModEnabled("safra")', 'priority'=>50)
		// );

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Cultivar object of Safra';
		$this->rights[$r][4] = 'cultivar';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update Cultivar object of Safra';
		$this->rights[$r][4] = 'cultivar';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (0 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete Cultivar object of Safra';
		$this->rights[$r][4] = 'cultivar';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (1 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Cultura object of Safra';
		$this->rights[$r][4] = 'cultura';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (1 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update Cultura object of Safra';
		$this->rights[$r][4] = 'cultura';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (1 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete Cultura object of Safra';
		$this->rights[$r][4] = 'cultura';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (2 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Talhao object of Safra';
		$this->rights[$r][4] = 'talhao';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (2 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update Talhao object of Safra';
		$this->rights[$r][4] = 'talhao';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (2 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete Talhao object of Safra';
		$this->rights[$r][4] = 'talhao';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (3 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read AnaliseSolo object of Safra';
		$this->rights[$r][4] = 'analisesolo';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (3 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update AnaliseSolo object of Safra';
		$this->rights[$r][4] = 'analisesolo';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (3 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete AnaliseSolo object of Safra';
		$this->rights[$r][4] = 'analisesolo';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (4 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Colheita object of Safra';
		$this->rights[$r][4] = 'colheita';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (4 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update Colheita object of Safra';
		$this->rights[$r][4] = 'colheita';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (4 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete Colheita object of Safra';
		$this->rights[$r][4] = 'colheita';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (5 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Evento object of Safra';
		$this->rights[$r][4] = 'evento';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (5 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update Evento object of Safra';
		$this->rights[$r][4] = 'evento';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (5 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete Evento object of Safra';
		$this->rights[$r][4] = 'evento';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (6 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read RecomendacaoAdubo object of Safra';
		$this->rights[$r][4] = 'recomendacaoadubo';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (6 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update RecomendacaoAdubo object of Safra';
		$this->rights[$r][4] = 'recomendacaoadubo';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (6 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete RecomendacaoAdubo object of Safra';
		$this->rights[$r][4] = 'recomendacaoadubo';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (7 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Aplicacao object of Safra';
		$this->rights[$r][4] = 'aplicacao';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (7 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update Aplicacao object of Safra';
		$this->rights[$r][4] = 'aplicacao';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (7 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete Aplicacao object of Safra';
		$this->rights[$r][4] = 'aplicacao';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (8 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Municipio object of Safra';
		$this->rights[$r][4] = 'municipio';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (8 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update Municipio object of Safra';
		$this->rights[$r][4] = 'municipio';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (8 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete Municipio object of Safra';
		$this->rights[$r][4] = 'municipio';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (9 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read NDVI object of Safra';
		$this->rights[$r][4] = 'ndvi';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (9 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update NDVI object of Safra';
		$this->rights[$r][4] = 'ndvi';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (9 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete NDVI object of Safra';
		$this->rights[$r][4] = 'ndvi';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (10 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read NDMI object of Safra';
		$this->rights[$r][4] = 'ndmi';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (10 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update NDMI object of Safra';
		$this->rights[$r][4] = 'ndmi';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (10 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete NDMI object of Safra';
		$this->rights[$r][4] = 'ndmi';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (11 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read JanelaPlantio object of Safra';
		$this->rights[$r][4] = 'janelaplantio';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (11 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update JanelaPlantio object of Safra';
		$this->rights[$r][4] = 'janelaplantio';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (12 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Zoneamento object of Safra';
		$this->rights[$r][4] = 'zoneamento';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (13 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read EVI object of Safra';
		$this->rights[$r][4] = 'evi';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (13 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update EVI object of Safra';
		$this->rights[$r][4] = 'evi';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (13 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete EVI object of Safra';
		$this->rights[$r][4] = 'evi';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (14 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read SWIR object of Safra';
		$this->rights[$r][4] = 'swir';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (14 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update SWIR object of Safra';
		$this->rights[$r][4] = 'swir';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (14 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete SWIR object of Safra';
		$this->rights[$r][4] = 'swir';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (15 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read ProdutosTecnicos object of Safra';
		$this->rights[$r][4] = 'produtostecnicos';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (15 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update ProdutosTecnicos object of Safra';
		$this->rights[$r][4] = 'produtostecnicos';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (15 * 10) + 2 + 1);
		$this->rights[$r][1] = 'Delete ProdutosTecnicos object of Safra';
		$this->rights[$r][4] = 'produtostecnicos';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (16 * 10) + 0 + 1);
		$this->rights[$r][1] = 'Read Pragas object of Safra';
		$this->rights[$r][4] = 'pragas';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', (16 * 10) + 1 + 1);
		$this->rights[$r][1] = 'Create/Update Pragas object of Safra';
		$this->rights[$r][4] = 'pragas';
		$this->rights[$r][5] = 'write';
		$r++;
                $this->rights[$r][0] = $this->numero . sprintf('%02d', (16 * 10) + 2 + 1);
                $this->rights[$r][1] = 'Delete Pragas object of Safra';
                $this->rights[$r][4] = 'pragas';
                $this->rights[$r][5] = 'delete';
                $r++;
                $this->rights[$r][0] = $this->numero . sprintf('%02d', (17 * 10) + 0 + 1);
                $this->rights[$r][1] = 'Read Produto Formulado object of Safra';
                $this->rights[$r][4] = 'produtoformulado';
                $this->rights[$r][5] = 'read';
                $r++;
                $this->rights[$r][0] = $this->numero . sprintf('%02d', (17 * 10) + 1 + 1);
                $this->rights[$r][1] = 'Create/Update Produto Formulado object of Safra';
                $this->rights[$r][4] = 'produtoformulado';
                $this->rights[$r][5] = 'write';
                $r++;
                $this->rights[$r][0] = $this->numero . sprintf('%02d', (17 * 10) + 2 + 1);
                $this->rights[$r][1] = 'Delete Produto Formulado object of Safra';
                $this->rights[$r][4] = 'produtoformulado';
                $this->rights[$r][5] = 'delete';
                $r++;

		/* END MODULEBUILDER PERMISSIONS */

		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$this->menu[$r++] = array(
			'fk_menu'=>'', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'top', // This is a Top menu entry
			'titre'=>'ModuleSafraName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu'=>'safra',
			'leftmenu'=>'',
			'url'=>'/safra/safraindex.php',
			'langs'=>'safra@safra', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000 + $r,
			'enabled'=>'isModEnabled("safra")', // Define condition to show or hide menu entry. Use 'isModEnabled("safra")' if entry must be visible if module is enabled.
			'perms'=>'1', // Use 'perms'=>'$user->hasRight("safra", "cultura", "read")' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
		);
		/* END MODULEBUILDER TOPMENU */
		/* BEGIN MODULEBUILDER LEFTMENU PRODUTOSTECNICOS */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=safra',
			'type' => 'left',
			'titre' => 'ProdutosTecnicos',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'safra',
			'leftmenu' => 'produtostecnicos',
			'url' => '/safra/produtostecnicos_list.php',
			'langs' => 'safra@safra',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("safra")',
			'perms' => '$user->hasRight("safra", "produtostecnicos", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'ProdutosTecnicos'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=produtostecnicos',
			'type' => 'left',
			'titre' => 'List ProdutosTecnicos',
			'mainmenu' => 'safra',
			'leftmenu' => 'safra_produtostecnicos_list',
			'url' => '/safra/produtostecnicos_list.php',
			'langs' => 'safra@safra',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("safra")',
			'perms' => '$user->hasRight("safra", "produtostecnicos", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'ProdutosTecnicos'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=produtostecnicos',
			'type' => 'left',
			'titre' => 'New ProdutosTecnicos',
			'mainmenu' => 'safra',
			'leftmenu' => 'safra_produtostecnicos_new',
			'url' => '/safra/produtostecnicos_card.php?action=create',
			'langs' => 'safra@safra',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("safra")',
			'perms' => '$user->hasRight("safra", "produtostecnicos", "write")',
			'target' => '',
			'user' => 2,
			'object' => 'ProdutosTecnicos'
		);
		/* END MODULEBUILDER LEFTMENU PRODUTOSTECNICOS */
		/* BEGIN MODULEBUILDER LEFTMENU PRAGAS */
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=safra',
			'type' => 'left',
			'titre' => 'Pragas',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'safra',
			'leftmenu' => 'pragas',
			'url' => '/safra/pragas_list.php',
			'langs' => 'safra@safra',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("safra")',
			'perms' => '$user->hasRight("safra", "pragas", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'Pragas'
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=pragas',
			'type' => 'left',
			'titre' => 'List Pragas',
			'mainmenu' => 'safra',
			'leftmenu' => 'safra_pragas_list',
			'url' => '/safra/pragas_list.php',
			'langs' => 'safra@safra',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("safra")',
			'perms' => '$user->hasRight("safra", "pragas", "read")',
			'target' => '',
			'user' => 2,
			'object' => 'Pragas'
		);
                $this->menu[$r++] = array(
                        'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=pragas',
                        'type' => 'left',
                        'titre' => 'New Pragas',
                        'mainmenu' => 'safra',
                        'leftmenu' => 'safra_pragas_new',
                        'url' => '/safra/pragas_card.php?action=create',
                        'langs' => 'safra@safra',
                        'position' => 1000 + $r,
                        'enabled' => 'isModEnabled("safra")',
                        'perms' => '$user->hasRight("safra", "pragas", "write")',
                        'target' => '',
                        'user' => 2,
                        'object' => 'Pragas'
                );
                $this->menu[$r++] = array(
                        'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=pragas',
                        'type' => 'left',
                        'titre' => 'SafraMenuPragaProductSearch',
                        'mainmenu' => 'safra',
                        'leftmenu' => 'safra_pragas_produtos',
                        'url' => '/safra/pragas_produtos.php',
                        'langs' => 'safra@safra',
                        'position' => 1000 + $r,
                        'enabled' => 'isModEnabled("safra")',
                        'perms' => '$user->hasRight("safra", "pragas", "read") && $user->hasRight("safra", "produtoformulado", "read") && $user->hasRight("produit", "lire")',
                        'target' => '',
                        'user' => 2,
                        'object' => 'SafraPragaProductSearch'
                );
                /* END MODULEBUILDER LEFTMENU PRAGAS */
		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		/* LEFTMENU CULTURA */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'Cultura',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'cultura',
			 'url' => '/safra/cultura_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'cultura\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU CULTURA */
		/* LEFTMENU LIST CULTURA */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=cultura',
			 'type' => 'left',
			 'titre' => 'Lista Cultura',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_cultura_list',
			 'url' => '/safra/cultura_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'cultura\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST CULTURA */
		/* LEFTMENU NEW CULTURA */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=cultura',
			 'type' => 'left',
			 'titre' => 'Nova Cultura',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_cultura_new',
			 'url' => '/safra/cultura_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'cultura\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW CULTURA */
		/* LEFTMENU CULTIVAR */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'Cultivar',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'cultivar',
			 'url' => '/safra/cultivar_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'cultivar\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU CULTIVAR */
		/* LEFTMENU LIST CULTIVAR */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=cultivar',
			 'type' => 'left',
			 'titre' => 'Lista Cultivar',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_cultivar_list',
			 'url' => '/safra/cultivar_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'cultivar\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST CULTIVAR */
		/* LEFTMENU NEW CULTIVAR */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=cultivar',
			 'type' => 'left',
			 'titre' => 'Novo Cultivar',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_cultivar_new',
			 'url' => '/safra/cultivar_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'cultivar\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW CULTIVAR */
		/* LEFTMENU TALHAO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'Talhão',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'talhao',
			 'url' => '/safra/talhao_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'talhao\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU TALHAO */
		/* LEFTMENU LIST TALHAO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=talhao',
			 'type' => 'left',
			 'titre' => 'Lista Talhão',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_talhao_list',
			 'url' => '/safra/talhao_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'talhao\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST TALHAO */
		/* LEFTMENU NEW TALHAO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=talhao',
			 'type' => 'left',
			 'titre' => 'Novo Talhão',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_talhao_new',
			 'url' => '/safra/talhao_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'talhao\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW TALHAO */

                $this->menu[$r++]=array(
                         'fk_menu' => 'fk_mainmenu=safra',
                         'type' => 'left',
                         'titre' => 'Análises de Satélite',
                         'prefix' => img_picto('', 'fa-satellite', 'class="pictofixedwidth valignmiddle"'),
                         'mainmenu' => 'safra',
                         'leftmenu' => 'ndvi',
                         'url' => '/safra/ndvi_view.php',
                         'langs' => 'safra@safra',
                         'position' => 1000 + $r,
                         'enabled' => 'isModEnabled(\'safra\')',
                         'perms' => '$user->hasRight(\'safra\', \'ndvi\', \'read\')',
                         'target' => '',
                         'user' => 2,
                );

                $satelliteMenus = array(
                        array('code' => 'ndvi', 'title' => 'NDVI'),
                        array('code' => 'ndmi', 'title' => 'NDMI'),
                        array('code' => 'swir', 'title' => 'SWIR'),
                        array('code' => 'ndwi', 'title' => 'NDWI'),
                        array('code' => 'evi', 'title' => 'EVI'),
                );

                foreach ($satelliteMenus as $menuItem) {
                        $code = $menuItem['code'];
                        $title = $menuItem['title'];

                        $this->menu[$r++] = array(
                                'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=ndvi',
                                'type' => 'left',
                                'titre' => $title,
                                'mainmenu' => 'safra',
                                'leftmenu' => 'safra_'.$code,
                                'url' => '/safra/'.$code.'_view.php',
                                'langs' => 'safra@safra',
                                'position' => 1000 + $r,
                                'enabled' => 'isModEnabled(\'safra\')',
                                'perms' => '$user->hasRight(\'safra\', \''.$code.'\', \'read\')',
                                'target' => '',
                                'user' => 2,
                        );
                }

		/* LEFTMENU SWIR */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra',
		// 	 'type' => 'left',
		// 	 'titre' => 'SWIR',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'swir',
		// 	 'url' => '/safra/swir_list.php',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'swir\', \'read\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		// /* END LEFTMENU SWIR */
		// /* LEFTMENU LIST SWIR */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=swir',
		// 	 'type' => 'left',
		// 	 'titre' => 'Lista SWIR',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'safra_swir_list',
		// 	 'url' => '/safra/swir_list.php',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'swir\', \'read\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		// /* END LEFTMENU LIST SWIR */
		// /* LEFTMENU NEW SWIR */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=swir',
		// 	 'type' => 'left',
		// 	 'titre' => 'Novo SWIR',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'safra_swir_new',
		// 	 'url' => '/safra/swir_card.php?action=create',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'swir\', \'write\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		/* END LEFTMENU NEW SWIR */
		/* LEFTMENU NDWI */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra',
		// 	 'type' => 'left',
		// 	 'titre' => 'NDWI',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'ndwi',
		// 	 'url' => '/safra/ndwi_list.php',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'ndwi\', \'read\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		// /* END LEFTMENU NDWI */
		// /* LEFTMENU LIST NDWI */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=ndwi',
		// 	 'type' => 'left',
		// 	 'titre' => 'Lista NDWI',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'safra_ndwi_list',
		// 	 'url' => '/safra/ndwi_list.php',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'ndwi\', \'read\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		// /* END LEFTMENU LIST NDWI */
		// /* LEFTMENU NEW NDWI */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=ndwi',
		// 	 'type' => 'left',
		// 	 'titre' => 'Novo NDWI',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'safra_ndwi_new',
		// 	 'url' => '/safra/ndwi_card.php?action=create',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'ndwi\', \'write\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		/* END LEFTMENU NEW NDWI */
		/* LEFTMENU ANALISESOLO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'AnaliseSolo',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'analisesolo',
			 'url' => '/safra/analisesolo_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'analisesolo\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU ANALISESOLO */
		/* LEFTMENU LIST ANALISESOLO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=analisesolo',
			 'type' => 'left',
			 'titre' => 'Lista AnaliseSolo',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_analisesolo_list',
			 'url' => '/safra/analisesolo_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'analisesolo\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST ANALISESOLO */
		/* LEFTMENU NEW ANALISESOLO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=analisesolo',
			 'type' => 'left',
			 'titre' => 'Novo AnaliseSolo',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_analisesolo_new',
			 'url' => '/safra/analisesolo_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'analisesolo\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW ANALISESOLO */
		/* LEFTMENU JANELAPLANTIO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'JanelaPlantio',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'janelaplantio',
			 'url' => '/safra/janelaplantio_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'janelaplantio\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU JANELAPLANTIO */
		/* LEFTMENU LIST JANELAPLANTIO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=janelaplantio',
			 'type' => 'left',
			 'titre' => 'Lista JanelaPlantio',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_janelaplantio_list',
			 'url' => '/safra/janelaplantio_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'janelaplantio\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST JANELAPLANTIO */
		/* LEFTMENU NEW JANELAPLANTIO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=janelaplantio',
			 'type' => 'left',
			 'titre' => 'Novo JanelaPlantio',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_janelaplantio_new',
			 'url' => '/safra/janelaplantio_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'janelaplantio\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW JANELAPLANTIO */
		/* LEFTMENU ZONEAMENTO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'Zoneamento',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'zoneamento',
			 'url' => '/safra/zoneamento_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'zoneamento\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU ZONEAMENTO */
		/* LEFTMENU LIST ZONEAMENTO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=zoneamento',
			 'type' => 'left',
			 'titre' => 'Lista Zoneamento',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_zoneamento_list',
			 'url' => '/safra/zoneamento_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'zoneamento\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST ZONEAMENTO */
		/* LEFTMENU NEW ZONEAMENTO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=zoneamento',
			 'type' => 'left',
			 'titre' => 'Novo Zoneamento',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_zoneamento_new',
			 'url' => '/safra/zoneamento_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'zoneamento\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW ZONEAMENTO */
		/* LEFTMENU EXPECTATIVAPRODUTIVIDADE */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'ExpectativaProdutividade',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'expectativaprodutividade',
			 'url' => '/safra/expectativaprodutividade_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'expectativaprodutividade\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU EXPECTATIVAPRODUTIVIDADE */
		/* LEFTMENU LIST EXPECTATIVAPRODUTIVIDADE */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=expectativaprodutividade',
			 'type' => 'left',
			 'titre' => 'Lista ExpectativaProdutividade',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_expectativaprodutividade_list',
			 'url' => '/safra/expectativaprodutividade_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'expectativaprodutividade\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST EXPECTATIVAPRODUTIVIDADE */
		/* LEFTMENU NEW EXPECTATIVAPRODUTIVIDADE */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=expectativaprodutividade',
			 'type' => 'left',
			 'titre' => 'Novo ExpectativaProdutividade',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_expectativaprodutividade_new',
			 'url' => '/safra/expectativaprodutividade_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'expectativaprodutividade\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW EXPECTATIVAPRODUTIVIDADE */
		/* LEFTMENU COLHEITA */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'Colheita',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'colheita',
			 'url' => '/safra/colheita_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'colheita\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU COLHEITA */
		/* LEFTMENU LIST COLHEITA */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=colheita',
			 'type' => 'left',
			 'titre' => 'Lista Colheita',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_colheita_list',
			 'url' => '/safra/colheita_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'colheita\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST COLHEITA */
		/* LEFTMENU NEW COLHEITA */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=colheita',
			 'type' => 'left',
			 'titre' => 'Novo Colheita',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_colheita_new',
			 'url' => '/safra/colheita_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'colheita\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW COLHEITA */
		/* LEFTMENU EVENTO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'Evento',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'evento',
			 'url' => '/safra/evento_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'evento\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU EVENTO */
		/* LEFTMENU LIST EVENTO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=evento',
			 'type' => 'left',
			 'titre' => 'Lista Evento',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_evento_list',
			 'url' => '/safra/evento_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'evento\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST EVENTO */
		/* LEFTMENU NEW EVENTO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=evento',
			 'type' => 'left',
			 'titre' => 'Novo Evento',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_evento_new',
			 'url' => '/safra/evento_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'evento\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW EVENTO */
		/* LEFTMENU RECOMENDACAOADUBO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'RecomendacaoAdubo',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'recomendacaoadubo',
			 'url' => '/safra/recomendacaoadubo_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'recomendacaoadubo\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU RECOMENDACAOADUBO */
		/* LEFTMENU LIST RECOMENDACAOADUBO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=recomendacaoadubo',
			 'type' => 'left',
			 'titre' => 'Lista RecomendacaoAdubo',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_recomendacaoadubo_list',
			 'url' => '/safra/recomendacaoadubo_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'recomendacaoadubo\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST RECOMENDACAOADUBO */
		/* LEFTMENU NEW RECOMENDACAOADUBO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=recomendacaoadubo',
			 'type' => 'left',
			 'titre' => 'Novo RecomendacaoAdubo',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_recomendacaoadubo_new',
			 'url' => '/safra/recomendacaoadubo_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'recomendacaoadubo\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW RECOMENDACAOADUBO */
		/* LEFTMENU EVI */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra',
		// 	 'type' => 'left',
		// 	 'titre' => 'EVI',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'evi',
		// 	 'url' => '/safra/evi_list.php',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'evi\', \'read\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		// /* END LEFTMENU EVI */
		// /* LEFTMENU LIST EVI */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=evi',
		// 	 'type' => 'left',
		// 	 'titre' => 'Lista EVI',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'safra_evi_list',
		// 	 'url' => '/safra/evi_list.php',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'evi\', \'read\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		// /* END LEFTMENU LIST EVI */
		// /* LEFTMENU NEW EVI */
		// $this->menu[$r++]=array(
		// 	 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=evi',
		// 	 'type' => 'left',
		// 	 'titre' => 'Novo EVI',
		// 	 'mainmenu' => 'safra',
		// 	 'leftmenu' => 'safra_evi_new',
		// 	 'url' => '/safra/evi_card.php?action=create',
		// 	 'langs' => 'safra@safra',
		// 	 'position' => 1000 + $r,
		// 	 'enabled' => 'isModEnabled(\'safra\')',
		// 	 'perms' => '$user->hasRight(\'safra\', \'evi\', \'write\')',
		// 	 'target' => '',
		// 	 'user' => 2,
		// );
		/* END LEFTMENU NEW EVI */
		/* LEFTMENU APLICACAO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'Aplicacao',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'aplicacao',
			 'url' => '/safra/aplicacao_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'aplicacao\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU APLICACAO */
		/* LEFTMENU LIST APLICACAO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=aplicacao',
			 'type' => 'left',
			 'titre' => 'Lista Aplicacao',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_aplicacao_list',
			 'url' => '/safra/aplicacao_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'aplicacao\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST APLICACAO */
		/* LEFTMENU NEW APLICACAO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=aplicacao',
			 'type' => 'left',
			 'titre' => 'Novo Aplicacao',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_aplicacao_new',
			 'url' => '/safra/aplicacao_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'aplicacao\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW APLICACAO */
		/* LEFTMENU MUNICIPIO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra',
			 'type' => 'left',
			 'titre' => 'Municipio',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'municipio',
			 'url' => '/safra/municipio_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'municipio\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU MUNICIPIO */
		/* LEFTMENU LIST MUNICIPIO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=municipio',
			 'type' => 'left',
			 'titre' => 'Lista Municipio',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_municipio_list',
			 'url' => '/safra/municipio_list.php',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'municipio\', \'read\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU LIST MUNICIPIO */
		/* LEFTMENU NEW MUNICIPIO */
		$this->menu[$r++]=array(
			 'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=municipio',
			 'type' => 'left',
			 'titre' => 'Novo Municipio',
			 'mainmenu' => 'safra',
			 'leftmenu' => 'safra_municipio_new',
			 'url' => '/safra/municipio_card.php?action=create',
			 'langs' => 'safra@safra',
			 'position' => 1000 + $r,
			 'enabled' => 'isModEnabled(\'safra\')',
			 'perms' => '$user->hasRight(\'safra\', \'municipio\', \'write\')',
			 'target' => '',
			 'user' => 2,
		);
		/* END LEFTMENU NEW MUNICIPIO */


		/*LEFTMENU NDMI*/
		// $this->menu[$r++]=array(
		// 	'fk_menu'=>'fk_mainmenu=safra',
		// 	'type'=>'left',
		// 	'titre'=>'NDMI',
		// 	'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
		// 	'mainmenu'=>'safra',
		// 	'leftmenu'=>'ndmi',
		// 	'url'=>'/safra/ndmi_list.php',
		// 	'langs'=>'safra@safra',
		// 	'position'=>1000+$r,
		// 	'enabled'=>'isModEnabled("safra")',
		// 	'perms'=>'$user->hasRight("safra", "ndmi", "read")',
		// 	'target'=>'',
		// 	'user'=>2,
		// );
        // $this->menu[$r++]=array(
        //     'fk_menu'=>'fk_mainmenu=safra,fk_leftmenu=ndmi',
        //     'type'=>'left',
        //     'titre'=>'List NDMI',
        //     'mainmenu'=>'safra',
        //     'leftmenu'=>'safra_ndmi_list',
        //     'url'=>'/safra/ndmi_list.php',
        //     'langs'=>'safra@safra',
        //     'position'=>1000+$r,
        //     'enabled'=>'isModEnabled("safra")',
		// 	'perms'=>'$user->hasRight("safra", "ndmi", "read")',
        //     'target'=>'',
        //     'user'=>2,
        // );
        // $this->menu[$r++]=array(
        //     'fk_menu'=>'fk_mainmenu=safra,fk_leftmenu=ndmi',
        //     'type'=>'left',
        //     'titre'=>'New NDMI',
        //     'mainmenu'=>'safra',
        //     'leftmenu'=>'safra_ndmi_new',
        //     'url'=>'/safra/ndmi_card.php?action=create',
        //     'langs'=>'safra@safra',
        //     'position'=>1000+$r,
        //     'enabled'=>'isModEnabled("safra")',
		// 	'perms'=>'$user->hasRight("safra", "ndmi", "write")',
        //     'target'=>'',
        //     'user'=>2
        // );

		/*END LEFTMENU NDMI*/
		/* END MODULEBUILDER LEFTMENU MYOBJECT */
		// Exports profiles provided by this module
		$r = 1;
		/* BEGIN MODULEBUILDER EXPORT MYOBJECT */
		/*
		$langs->load("safra@safra");
		$this->export_code[$r]=$this->rights_class.'_'.$r;
		$this->export_label[$r]='CulturaLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_icon[$r]='cultura@safra';
		// Define $this->export_fields_array, $this->export_TypeFields_array and $this->export_entities_array
		$keyforclass = 'Cultura'; $keyforclassfile='/safra/class/cultura.class.php'; $keyforelement='cultura@safra';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		//$this->export_fields_array[$r]['t.fieldtoadd']='FieldToAdd'; $this->export_TypeFields_array[$r]['t.fieldtoadd']='Text';
		//unset($this->export_fields_array[$r]['t.fieldtoremove']);
		//$keyforclass = 'CulturaLine'; $keyforclassfile='/safra/class/cultura.class.php'; $keyforelement='culturaline@safra'; $keyforalias='tl';
		//include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		$keyforselect='cultura'; $keyforaliasextra='extra'; $keyforelement='cultura@safra';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$keyforselect='culturaline'; $keyforaliasextra='extraline'; $keyforelement='culturaline@safra';
		//include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$this->export_dependencies_array[$r] = array('culturaline'=>array('tl.rowid','tl.ref')); // To force to activate one or several fields if we select some fields that need same (like to select a unique key if we ask a field of a child to avoid the DISTINCT to discard them, or for computed field than need several other fields)
		//$this->export_special_array[$r] = array('t.field'=>'...');
		//$this->export_examplevalues_array[$r] = array('t.field'=>'Example');
		//$this->export_help_array[$r] = array('t.field'=>'FieldDescHelp');
		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM '.MAIN_DB_PREFIX.'cultura as t';
		//$this->export_sql_end[$r]  =' LEFT JOIN '.MAIN_DB_PREFIX.'cultura_line as tl ON tl.fk_cultura = t.rowid';
		$this->export_sql_end[$r] .=' WHERE 1 = 1';
		$this->export_sql_end[$r] .=' AND t.entity IN ('.getEntity('cultura').')';
               $r++; */
               /* END MODULEBUILDER EXPORT MYOBJECT */

               // --- Refreshed menu structure --------------------------------------------------
               $this->menu = array();
               $r = 0;

               // Top level + dashboard
               $this->menu[$r++] = array(
                       'fk_menu' => '',
                       'type' => 'top',
                       'titre' => 'SafraMenuTitle',
                       'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
                       'mainmenu' => 'safra',
                       'leftmenu' => '',
                       'url' => '/safra/safraindex.php',
                       'langs' => 'safra@safra',
                       'position' => 1000 + $r,
                       'enabled' => 'isModEnabled("safra")',
                       'perms' => '1',
                       'target' => '',
                       'user' => 2,
               );

               $this->menu[$r++] = array(
                       'fk_menu' => 'fk_mainmenu=safra',
                       'type' => 'left',
                       'titre' => 'SafraDashboard',
                       'mainmenu' => 'safra',
                       'leftmenu' => 'safra_dashboard',
                       'url' => '/safra/safraindex.php',
                       'langs' => 'safra@safra',
                       'position' => 1000 + $r,
                       'enabled' => 'isModEnabled("safra")',
                       'perms' => '1',
                       'target' => '',
                       'user' => 2,
               );

               // Cadastros
               $this->menu[$r++] = array(
                       'fk_menu' => 'fk_mainmenu=safra',
                       'type' => 'left',
                       'titre' => 'SafraMenuCadastros',
                       'prefix' => img_picto('', 'fa-database', 'class="pictofixedwidth valignmiddle"'),
                       'mainmenu' => 'safra',
                       'leftmenu' => 'safra_cadastros',
                       'url' => '/safra/talhao_list.php',
                       'langs' => 'safra@safra',
                       'position' => 1000 + $r,
                       'enabled' => 'isModEnabled("safra")',
                       'perms' => '1',
                       'target' => '',
                       'user' => 2,
               );

               $cadastros = array(
                       array('Talhoes', 'talhao', '/safra/talhao_list.php', '/safra/talhao_card.php?action=create', '$user->hasRight("safra", "talhao", "read")', '$user->hasRight("safra", "talhao", "write")'),
                       array('Culturas', 'cultura', '/safra/cultura_list.php', '/safra/cultura_card.php?action=create', '$user->hasRight("safra", "cultura", "read")', '$user->hasRight("safra", "cultura", "write")'),
                       array('Cultivares', 'cultivar', '/safra/cultivar_list.php', '/safra/cultivar_card.php?action=create', '$user->hasRight("safra", "cultivar", "read")', '$user->hasRight("safra", "cultivar", "write")'),
                       array('Pragas', 'pragas', '/safra/pragas_list.php', '/safra/pragas_card.php?action=create', '$user->hasRight("safra", "pragas", "read")', '$user->hasRight("safra", "pragas", "write")'),
                       array('ProdutosTecnicosShort', 'produtostecnicos', '/safra/produtostecnicos_list.php', '/safra/produtostecnicos_card.php?action=create', '$user->hasRight("safra", "produtostecnicos", "read")', '$user->hasRight("safra", "produtostecnicos", "write")'),
                       array('ProdutosFormulados', 'produto_formulado', '/safra/produto_formulado/list.php', '/safra/produto_formulado/card.php?action=create', '$user->hasRight("safra", "produtoformulado", "read")', '$user->hasRight("safra", "produtoformulado", "write")'),
                       array('Municipios', 'municipio', '/safra/municipio_list.php', '/safra/municipio_card.php?action=create', '$user->hasRight("safra", "municipio", "read")', '$user->hasRight("safra", "municipio", "write")'),
               );

               foreach ($cadastros as $item) {
                       list($labelKey, $code, $listUrl, $newUrl, $permRead, $permWrite) = $item;
                       $this->menu[$r++] = array(
                               'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_cadastros',
                               'type' => 'left',
                               'titre' => 'SafraMenu'.$labelKey,
                               'mainmenu' => 'safra',
                               'leftmenu' => 'safra_'.$code.'_list',
                               'url' => $listUrl,
                               'langs' => 'safra@safra',
                               'position' => 1000 + $r,
                               'enabled' => 'isModEnabled("safra")',
                               'perms' => $permRead,
                               'target' => '',
                               'user' => 2,
                       );

                       $this->menu[$r++] = array(
                               'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_'.$code.'_list',
                               'type' => 'left',
                               'titre' => 'SafraMenuNew'.$labelKey,
                               'mainmenu' => 'safra',
                               'leftmenu' => 'safra_'.$code.'_new',
                               'url' => $newUrl,
                               'langs' => 'safra@safra',
                               'position' => 1000 + $r,
                               'enabled' => 'isModEnabled("safra")',
                               'perms' => $permWrite,
                               'target' => '',
                               'user' => 2,
                       );

                       if ($code === 'pragas') {
                               $this->menu[$r++] = array(
                                       'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_pragas_list',
                                       'type' => 'left',
                                       'titre' => 'SafraMenuPragaProductSearch',
                                       'mainmenu' => 'safra',
                                       'leftmenu' => 'safra_pragas_produtos',
                                       'url' => '/safra/pragas_produtos.php',
                                       'langs' => 'safra@safra',
                                       'position' => 1000 + $r,
                                       'enabled' => 'isModEnabled("safra")',
                                       'perms' => '$user->hasRight("safra", "pragas", "read") && $user->hasRight("safra", "produtoformulado", "read") && $user->hasRight("produit", "lire")',
                                       'target' => '',
                                       'user' => 2,
                               );
                       }
               }

               // Planejamento
               $this->menu[$r++] = array(
                       'fk_menu' => 'fk_mainmenu=safra',
                       'type' => 'left',
                       'titre' => 'SafraMenuPlanejamento',
                       'prefix' => img_picto('', 'fa-route', 'class="pictofixedwidth valignmiddle"'),
                       'mainmenu' => 'safra',
                       'leftmenu' => 'safra_planejamento',
                       'url' => '/safra/janelaplantio_list.php',
                       'langs' => 'safra@safra',
                       'position' => 1000 + $r,
                       'enabled' => 'isModEnabled("safra")',
                       'perms' => '1',
                       'target' => '',
                       'user' => 2,
               );

               $planejamento = array(
                       array('JanelasPlantio', 'janelaplantio', '/safra/janelaplantio_list.php', '/safra/janelaplantio_card.php?action=create', '$user->hasRight("safra", "janelaplantio", "read")', '$user->hasRight("safra", "janelaplantio", "write")'),
                       array('Zoneamentos', 'zoneamento', '/safra/zoneamento_list.php', '/safra/zoneamento_card.php?action=create', '$user->hasRight("safra", "zoneamento", "read")', '$user->hasRight("safra", "zoneamento", "write")'),
                       array('Expectativas', 'expectativaprodutividade', '/safra/expectativaprodutividade_list.php', '/safra/expectativaprodutividade_card.php?action=create', '$user->hasRight("safra", "expectativaprodutividade", "read")', '$user->hasRight("safra", "expectativaprodutividade", "write")'),
                       array('Recomendacoes', 'recomendacaoadubo', '/safra/recomendacaoadubo_list.php', '/safra/recomendacaoadubo_card.php?action=create', '$user->hasRight("safra", "recomendacaoadubo", "read")', '$user->hasRight("safra", "recomendacaoadubo", "write")'),
               );

               foreach ($planejamento as $item) {
                       list($labelKey, $code, $listUrl, $newUrl, $permRead, $permWrite) = $item;
                       $this->menu[$r++] = array(
                               'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_planejamento',
                               'type' => 'left',
                               'titre' => 'SafraMenu'.$labelKey,
                               'mainmenu' => 'safra',
                               'leftmenu' => 'safra_'.$code.'_list',
                               'url' => $listUrl,
                               'langs' => 'safra@safra',
                               'position' => 1000 + $r,
                               'enabled' => 'isModEnabled("safra")',
                               'perms' => $permRead,
                               'target' => '',
                               'user' => 2,
                       );

                       $this->menu[$r++] = array(
                               'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_'.$code.'_list',
                               'type' => 'left',
                               'titre' => 'SafraMenuNew'.$labelKey,
                               'mainmenu' => 'safra',
                               'leftmenu' => 'safra_'.$code.'_new',
                               'url' => $newUrl,
                               'langs' => 'safra@safra',
                               'position' => 1000 + $r,
                               'enabled' => 'isModEnabled("safra")',
                               'perms' => $permWrite,
                               'target' => '',
                               'user' => 2,
                       );
               }

               // Operações
               $this->menu[$r++] = array(
                       'fk_menu' => 'fk_mainmenu=safra',
                       'type' => 'left',
                       'titre' => 'SafraMenuOperacoes',
                       'prefix' => img_picto('', 'fa-tractor', 'class="pictofixedwidth valignmiddle"'),
                       'mainmenu' => 'safra',
                       'leftmenu' => 'safra_operacoes',
                       'url' => '/safra/aplicacao_list.php',
                       'langs' => 'safra@safra',
                       'position' => 1000 + $r,
                       'enabled' => 'isModEnabled("safra")',
                       'perms' => '1',
                       'target' => '',
                       'user' => 2,
               );

               $operacoes = array(
                       array('Aplicacoes', 'aplicacao', '/safra/aplicacao_list.php', '/safra/aplicacao_card.php?action=create', '$user->hasRight("safra", "aplicacao", "read")', '$user->hasRight("safra", "aplicacao", "write")'),
                       array('Eventos', 'evento', '/safra/evento_list.php', '/safra/evento_card.php?action=create', '$user->hasRight("safra", "evento", "read")', '$user->hasRight("safra", "evento", "write")'),
                       array('Colheitas', 'colheita', '/safra/colheita_list.php', '/safra/colheita_card.php?action=create', '$user->hasRight("safra", "colheita", "read")', '$user->hasRight("safra", "colheita", "write")'),
               );

               foreach ($operacoes as $item) {
                       list($labelKey, $code, $listUrl, $newUrl, $permRead, $permWrite) = $item;
                       $this->menu[$r++] = array(
                               'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_operacoes',
                               'type' => 'left',
                               'titre' => 'SafraMenu'.$labelKey,
                               'mainmenu' => 'safra',
                               'leftmenu' => 'safra_'.$code.'_list',
                               'url' => $listUrl,
                               'langs' => 'safra@safra',
                               'position' => 1000 + $r,
                               'enabled' => 'isModEnabled("safra")',
                               'perms' => $permRead,
                               'target' => '',
                               'user' => 2,
                       );

                       $this->menu[$r++] = array(
                               'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_'.$code.'_list',
                               'type' => 'left',
                               'titre' => 'SafraMenuNew'.$labelKey,
                               'mainmenu' => 'safra',
                               'leftmenu' => 'safra_'.$code.'_new',
                               'url' => $newUrl,
                               'langs' => 'safra@safra',
                               'position' => 1000 + $r,
                               'enabled' => 'isModEnabled("safra")',
                               'perms' => $permWrite,
                               'target' => '',
                               'user' => 2,
                       );
               }

               // Monitoramento
               $this->menu[$r++] = array(
                       'fk_menu' => 'fk_mainmenu=safra',
                       'type' => 'left',
                       'titre' => 'SafraMenuMonitoramento',
                       'prefix' => img_picto('', 'fa-satellite', 'class="pictofixedwidth valignmiddle"'),
                       'mainmenu' => 'safra',
                       'leftmenu' => 'safra_monitoramento',
                       'url' => '/safra/ndvi_view.php',
                       'langs' => 'safra@safra',
                       'position' => 1000 + $r,
                       'enabled' => 'isModEnabled("safra")',
                       'perms' => '1',
                       'target' => '',
                       'user' => 2,
               );

               $monitoramento = array(
                       array('labelKey' => 'NDVI', 'code' => 'ndvi'),
                       array('labelKey' => 'NDMI', 'code' => 'ndmi'),
                       array('labelKey' => 'NDWI', 'code' => 'ndwi'),
                       array('labelKey' => 'SWIR', 'code' => 'swir'),
                       array('labelKey' => 'EVI', 'code' => 'evi'),
                       array(
                               'labelKey' => 'AnalisesSolo',
                               'code' => 'analisesolo',
                               'listUrl' => '/safra/analisesolo_list.php',
                               'newUrl' => '/safra/analisesolo_card.php?action=create',
                       ),
               );

               foreach ($monitoramento as $item) {
                       $labelKey = $item['labelKey'];
                       $code = $item['code'];
                       $listUrl = isset($item['listUrl']) ? $item['listUrl'] : '/safra/'.$code.'_view.php';
                       $newUrl = isset($item['newUrl']) ? $item['newUrl'] : '/safra/'.$code.'_card.php?action=create';
                       $this->menu[$r++] = array(
                               'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_monitoramento',
                               'type' => 'left',
                               'titre' => 'SafraMenu'.$labelKey,
                               'mainmenu' => 'safra',
                               'leftmenu' => 'safra_'.$code.'_list',
                               'url' => $listUrl,
                               'langs' => 'safra@safra',
                               'position' => 1000 + $r,
                               'enabled' => 'isModEnabled("safra")',
                               'perms' => '$user->hasRight("safra", "'.$code.'", "read")',
                               'target' => '',
                               'user' => 2,
                       );

                       $this->menu[$r++] = array(
                               'fk_menu' => 'fk_mainmenu=safra,fk_leftmenu=safra_'.$code.'_list',
                               'type' => 'left',
                               'titre' => 'SafraMenuNew'.$labelKey,
                               'mainmenu' => 'safra',
                               'leftmenu' => 'safra_'.$code.'_new',
                               'url' => $newUrl,
                               'langs' => 'safra@safra',
                               'position' => 1000 + $r,
                               'enabled' => 'isModEnabled("safra")',
                               'perms' => '$user->hasRight("safra", "'.$code.'", "write")',
                               'target' => '',
                               'user' => 2,
                       );
               }

               // Imports profiles provided by this module
               $r = 1;
		/* BEGIN MODULEBUILDER IMPORT MYOBJECT */
		/*
		$langs->load("safra@safra");
		$this->import_code[$r]=$this->rights_class.'_'.$r;
		$this->import_label[$r]='CulturaLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->import_icon[$r]='cultura@safra';
		$this->import_tables_array[$r] = array('t' => MAIN_DB_PREFIX.'safra_cultura', 'extra' => MAIN_DB_PREFIX.'safra_cultura_extrafields');
		$this->import_tables_creator_array[$r] = array('t' => 'fk_user_author'); // Fields to store import user id
		$import_sample = array();
		$keyforclass = 'Cultura'; $keyforclassfile='/safra/class/cultura.class.php'; $keyforelement='cultura@safra';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinimport.inc.php';
		$import_extrafield_sample = array();
		$keyforselect='cultura'; $keyforaliasextra='extra'; $keyforelement='cultura@safra';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinimport.inc.php';
		$this->import_fieldshidden_array[$r] = array('extra.fk_object' => 'lastrowid-'.MAIN_DB_PREFIX.'safra_cultura');
		$this->import_regex_array[$r] = array();
		$this->import_examplevalues_array[$r] = array_merge($import_sample, $import_extrafield_sample);
		$this->import_updatekeys_array[$r] = array('t.ref' => 'Ref');
		$this->import_convertvalue_array[$r] = array(
			't.ref' => array(
				'rule'=>'getrefifauto',
				'class'=>(!getDolGlobalString('SAFRA_MYOBJECT_ADDON') ? 'mod_cultura_standard' : getDolGlobalString('SAFRA_MYOBJECT_ADDON')),
				'path'=>"/core/modules/commande/".(!getDolGlobalString('SAFRA_MYOBJECT_ADDON') ? 'mod_cultura_standard' : getDolGlobalString('SAFRA_MYOBJECT_ADDON')).'.php'
				'classobject'=>'Cultura',
				'pathobject'=>'/safra/class/cultura.class.php',
			),
			't.fk_soc' => array('rule' => 'fetchidfromref', 'file' => '/societe/class/societe.class.php', 'class' => 'Societe', 'method' => 'fetch', 'element' => 'ThirdParty'),
			't.fk_user_valid' => array('rule' => 'fetchidfromref', 'file' => '/user/class/user.class.php', 'class' => 'User', 'method' => 'fetch', 'element' => 'user'),
			't.fk_mode_reglement' => array('rule' => 'fetchidfromcodeorlabel', 'file' => '/compta/paiement/class/cpaiement.class.php', 'class' => 'Cpaiement', 'method' => 'fetch', 'element' => 'cpayment'),
		);
		$this->import_run_sql_after_array[$r] = array();
		$r++; */
		/* END MODULEBUILDER IMPORT MYOBJECT */
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		//$result = $this->_load_tables('/install/mysql/', 'safra');
		$result = $this->_load_tables('/safra/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

		$extrafields = new ExtraFields($this->db);



		// $r1 = $extrafields->addExtraField('fk_talhao', 'Talhão', 'link', 10, 11, null);
		// $result1=$extrafields->addExtraField('fk_talhao', "Talhão", 'link', 10,  null, 'project', 0, 0, null, array('options' => array("Talhao:custom/safra/class/talhao.class.php"=>null)), 1, '', 1, '', '', '', '', '$conf->safra->enabled', 0, 1);
		// $result2=$extrafields->addExtraField('fk_cultura', "Cultura", 'link', 11,  null, 'project', 0, 0, null, array('options' => array("Cultura:custom/safra/class/cultura.class.php"=>null)), 1, '', 1, '', '', '', '', '$conf->safra->enabled', 0, 1);
		// $result3=$extrafields->addExtraField('fk_cultivar', "Cultivar", 'link', 12,  null, 'project', 0, 0, null, array('options' => array("Cultivar:custom/safra/class/cultivar.class.php"=>null)), 1, '', 1, '', '', '', '', '$conf->safra->enabled', 0, 1);


			// Adicionar extrafield para referenciar Cultura
			$extrafields->addExtraField(
				'fk_cultura',                            // Nome do campo
				'Cultura',                               // Rótulo do campo
				'link',                               // Tipo do campo (sellist para criar uma lista)
				101,                                       // Posição
				'',                                      // Tamanho
				'projet',                               // Módulo/elemento (project)
				0,                                       // Campo não único
				1,                                       // Não obrigatório
				'',                                      // Valor padrão
				array('options' => array("Cultura:safra/class/cultura.class.php:1"=>null)), // Parâmetros para fazer o link (Tabela:Campo a Exibir:Campo de Referência)
				1,                                        // Sempre editável
				'',
				'isModEnabled("safra")'

			);



			// Adicionar extrafield para referenciar Cultivar
			$extrafields->addExtraField(
				'fk_cultivar',                           // Nome do campo
				'Cultivar',                              // Rótulo do campo
				'link',                               // Tipo do campo (sellist)
				102,                                       // Posição
				'',                                      // Tamanho
				'projet',                               // Módulo/elemento (project)
				0,                                       // Campo não único
				0,                                       // Não obrigatório
				'',                                      // Valor padrão
				// array('options' => "llx_safra_cultivar:label:rowid"), // Parâmetros para fazer o link (Tabela:Campo a Exibir:Campo de Referência)
				array('options' => array("Cultivar:safra/class/cultivar.class.php:1"=>null)), // Parâmetros para fazer o link (Tabela:Campo a Exibir:Campo de Referência)
				1,                                        // Sempre editável
				'',
				'isModEnabled("safra")'                                        // Sempre editável
			);



			// Adicionar extrafield para referenciar Talhão
			$extrafields->addExtraField(
				'fk_talhao',                             // Nome do campo
				'Talhão',                                // Rótulo do campo
				'link',                               // Tipo do campo (sellist)
				100,                                       // Posição
				'',                                      // Tamanho
				'projet',                               // Módulo/elemento (project)
				0,                                       // Campo não único
				1,                                       // Não obrigatório
				'',                                      // Valor padrão
				array('options' => array("Talhao:safra/class/talhao.class.php:1"=>null)), // Parâmetros para fazer o link (Tabela:Campo a Exibir:Campo de Referência)
				1,                                        // Sempre editável
				'',
				'isModEnabled("safra")'                                        // Sempre editável
			);


		// Create extrafields during init
		//include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		//$extrafields = new ExtraFields($this->db);
		//$result1=$extrafields->addExtraField('safra_myattr1', "New Attr 1 label", 'boolean', 1,  3, 'thirdparty',   0, 0, '', '', 1, '', 0, 0, '', '', 'safra@safra', 'isModEnabled("safra")');
		//$result2=$extrafields->addExtraField('safra_myattr2', "New Attr 2 label", 'varchar', 1, 10, 'project',      0, 0, '', '', 1, '', 0, 0, '', '', 'safra@safra', 'isModEnabled("safra")');
		//$result3=$extrafields->addExtraField('safra_myattr3', "New Attr 3 label", 'varchar', 1, 10, 'bank_account', 0, 0, '', '', 1, '', 0, 0, '', '', 'safra@safra', 'isModEnabled("safra")');
		//$result4=$extrafields->addExtraField('safra_myattr4', "New Attr 4 label", 'select',  1,  3, 'thirdparty',   0, 1, '', array('options'=>array('code1'=>'Val1','code2'=>'Val2','code3'=>'Val3')), 1,'', 0, 0, '', '', 'safra@safra', 'isModEnabled("safra")');
		//$result5=$extrafields->addExtraField('safra_myattr5', "New Attr 5 label", 'text',    1, 10, 'user',         0, 0, '', '', 1, '', 0, 0, '', '', 'safra@safra', 'isModEnabled("safra")');

		// Permissions
		$this->remove($options);

		$sql = array();

		// Document templates
		$moduledir = dol_sanitizeFileName('safra');
		$myTmpObjects = array();
		$myTmpObjects['Cultura'] = array('includerefgeneration'=>0, 'includedocgeneration'=>0);

		foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
			if ($myTmpObjectKey == 'Cultura') {
				continue;
			}
			if ($myTmpObjectArray['includerefgeneration']) {
				$src = DOL_DOCUMENT_ROOT.'/install/doctemplates/'.$moduledir.'/template_culturas.odt';
				$dirodt = DOL_DATA_ROOT.'/doctemplates/'.$moduledir;
				$dest = $dirodt.'/template_culturas.odt';

				if (file_exists($src) && !file_exists($dest)) {
					require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
					dol_mkdir($dirodt);
					$result = dol_copy($src, $dest, 0, 0);
					if ($result < 0) {
						$langs->load("errors");
						$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
						return 0;
					}
				}

				$sql = array_merge($sql, array(
					"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'standard_".strtolower($myTmpObjectKey)."' AND type = '".$this->db->escape(strtolower($myTmpObjectKey))."' AND entity = ".((int) $conf->entity),
					"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('standard_".strtolower($myTmpObjectKey)."', '".$this->db->escape(strtolower($myTmpObjectKey))."', ".((int) $conf->entity).")",
					"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'generic_".strtolower($myTmpObjectKey)."_odt' AND type = '".$this->db->escape(strtolower($myTmpObjectKey))."' AND entity = ".((int) $conf->entity),
					"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('generic_".strtolower($myTmpObjectKey)."_odt', '".$this->db->escape(strtolower($myTmpObjectKey))."', ".((int) $conf->entity).")"
				));
			}
		}

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
