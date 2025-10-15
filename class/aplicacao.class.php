<?php
/* Copyright (C) 2017  Laurent Destailleur      <eldy@users.sourceforge.net>
 * Copyright (C) 2023  Frédéric France          <frederic.france@netlogic.fr>
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
 * \file        class/aplicacao.class.php
 * \ingroup     safra
 * \brief       This file is a CRUD class file for Aplicacao (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
//require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
//require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

/**
 * Class for Aplicacao
 */
class Aplicacao extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'safra';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'aplicacao';

	/**
	 * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
	 */
	public $table_element = 'safra_aplicacao';

	/**
	 * @var int  	Does this object support multicompany module ?
	 * 0=No test on entity, 1=Test with field entity, 'field@table'=Test with link by field@table
	 */
	public $ismultientitymanaged = 0;

	/**
	 * @var int  Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var string String with name of icon for aplicacao. Must be a 'fa-xxx' fontawesome code (or 'fa-xxx_fa_color_size') or 'aplicacao@safra' if picto is file 'img/object_aplicacao.png'.
	 */
	public $picto = 'fa-fill-drip';


        const STATUS_DRAFT = 0;
        const STATUS_VALIDATED = 1;
        const STATUS_CANCELED = 9;

        const OPERATION_PREPARO = 'preparo_solo';
        const OPERATION_TRATAMENTO = 'tratamento_semente';
        const OPERATION_PLANTIO = 'plantio';
        const OPERATION_FERTILIZACAO = 'fertilizacao';
        const OPERATION_APLICACAO = 'aplicacao';
        const OPERATION_COLHEITA = 'colheita';
        const OPERATION_OUTRO = 'outro';

        /**
         * Return available operation types translated if possible.
         *
         * @param Translate|null $langs
         * @return array<string,string>
         */
        public static function getOperationTypeList($langs = null)
        {
                $map = array(
                        self::OPERATION_PREPARO => 'SafraOperationPreparoSolo',
                        self::OPERATION_TRATAMENTO => 'SafraOperationTratamentoSemente',
                        self::OPERATION_PLANTIO => 'SafraOperationPlantio',
                        self::OPERATION_FERTILIZACAO => 'SafraOperationFertilizacao',
                        self::OPERATION_APLICACAO => 'SafraOperationAplicacao',
                        self::OPERATION_COLHEITA => 'SafraOperationColheita',
                        self::OPERATION_OUTRO => 'SafraOperationOutro',
                );

                if ($langs instanceof Translate) {
                        $translated = array();
                        foreach ($map as $key => $labelKey) {
                                $translated[$key] = $langs->trans($labelKey);
                        }

                        return $translated;
                }

                return $map;
        }

        /**
         * Normalize an operation type code.
         *
         * @param string $value
         * @return string
         */
        public static function normalizeOperationType($value)
        {
                $value = trim((string) $value);
                $allowed = array_keys(self::getOperationTypeList());
                if (!in_array($value, $allowed, true)) {
                        return self::OPERATION_APLICACAO;
                }

                return $value;
        }

        /**
         * Get human readable label for an operation type.
         *
         * @param string $value
         * @param Translate|null $langs
         * @return string
         */
        public static function getOperationTypeLabel($value, $langs = null)
        {
                $value = self::normalizeOperationType($value);
                $list = self::getOperationTypeList($langs);

                return isset($list[$value]) ? $list[$value] : $value;
        }

	/**
	 *  'type' field format:
	 *  	'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
	 *  	'select' (list of values are in 'options'),
	 *  	'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:CategoryIdType[:CategoryIdList[:SortField]]]]]]',
	 *  	'chkbxlst:...',
	 *  	'varchar(x)',
	 *  	'text', 'text:none', 'html',
	 *   	'double(24,8)', 'real', 'price', 'stock',
	 *  	'date', 'datetime', 'timestamp', 'duration',
	 *  	'boolean', 'checkbox', 'radio', 'array',
	 *  	'mail', 'phone', 'url', 'password', 'ip'
	 *		Note: Filter must be a Dolibarr Universal Filter syntax string. Example: "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.status:!=:0) or (t.nature:is:NULL)"
	 *  'label' the translation key.
	 *  'picto' is code of a picto to show before value in forms
	 *  'enabled' is a condition when the field must be managed (Example: 1 or 'getDolGlobalInt("MY_SETUP_PARAM")' or 'isModEnabled("multicurrency")' ...)
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'alwayseditable' says if field can be modified also when status is not draft ('1' or '0')
	 *  'default' is a default value for creation (can still be overwrote by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
	 *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
	 *  'help' and 'helplist' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
	 *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
	 *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *	'validate' is 1 if need to validate with $this->validateField()
	 *  'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	// BEGIN MODULEBUILDER PROPERTIES
	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields=array(
		"rowid" => array("type"=>"integer", "label"=>"TechnicalID", "enabled"=>"1", 'position'=>1, 'notnull'=>1, "visible"=>"0", "noteditable"=>"1", "index"=>"1", "css"=>"left", "comment"=>"Id"),
		"ref" => array("type"=>"varchar(128)", "label"=>"Ref", "enabled"=>"1", 'position'=>20, 'notnull'=>1, "visible"=>"1", "index"=>"1", "searchall"=>"1", "showoncombobox"=>"1", "validate"=>"1", "comment"=>"Reference of object"),
		"label" => array("type"=>"varchar(255)", "label"=>"Label", "enabled"=>"1", 'position'=>30, 'notnull'=>0, "visible"=>"1", "alwayseditable"=>"1", "searchall"=>"1", "css"=>"minwidth300", "cssview"=>"wordbreak", "help"=>"Help text", "showoncombobox"=>"2", "validate"=>"1",),
                "amount" => array("type"=>"price", "label"=>"Amount", "enabled"=>"1", 'position'=>40, 'notnull'=>0, "visible"=>"0", "default"=>"null", "isameasure"=>"1", "help"=>"Help text for amount", "validate"=>"1",),
                "qty" => array("type"=>"double(28,8)", "label"=>"SafraAplicacaoAreaHa", "enabled"=>"1", 'position'=>45, 'notnull'=>0, "visible"=>"1", "default"=>"0", "isameasure"=>"1", "css"=>"maxwidth75imp", "validate"=>"1",),
                "fk_soc" => array("type"=>"integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))", "label"=>"ThirdParty", "picto"=>"company", "enabled"=>"isModEnabled('societe')", 'position'=>50, 'notnull'=>-1, "visible"=>"1", "index"=>"1", "css"=>"maxwidth500 widthcentpercentminusxx", "csslist"=>"tdoverflowmax150", "help"=>"OrganizationEventLinkToThirdParty", "validate"=>"1",),
                "fk_project" => array("type"=>"integer:Project:projet/class/project.class.php:1", "label"=>"Project", "picto"=>"project", "enabled"=>"isModEnabled('project')", 'position'=>52, 'notnull'=>-1, "visible"=>"1", "index"=>"1", "css"=>"maxwidth500 widthcentpercentminusxx", "csslist"=>"tdoverflowmax150", "validate"=>"1",),
                "fk_task" => array("type"=>"integer:Task:projet/class/task.class.php:1", "label"=>"Task", "picto"=>"projecttask", "enabled"=>"isModEnabled('project')", 'position'=>53, 'notnull'=>-1, "visible"=>"-2", "index"=>"1", "csslist"=>"tdoverflowmax150"),
                "operation_type" => array("type"=>"varchar(32)", "label"=>"SafraOperationType", "enabled"=>"1", 'position'=>54, 'notnull'=>1, "visible"=>"1", "default"=>self::OPERATION_APLICACAO, "css"=>"maxwidth200", "arrayofkeyval"=>array()),
                "date_application" => array("type"=>"date", "label"=>"SafraAplicacaoDate", "enabled"=>"1", 'position'=>54, 'notnull'=>0, "visible"=>"1", "validate"=>"1"),
                "description" => array("type"=>"text", "label"=>"Description", "enabled"=>"1", 'position'=>60, 'notnull'=>0, "visible"=>"3", "validate"=>"1",),
                "note_public" => array("type"=>"html", "label"=>"NotePublic", "enabled"=>"1", 'position'=>61, 'notnull'=>0, "visible"=>"0", "cssview"=>"wordbreak", "validate"=>"1",),
                "note_private" => array("type"=>"html", "label"=>"NotePrivate", "enabled"=>"1", 'position'=>62, 'notnull'=>0, "visible"=>"0", "cssview"=>"wordbreak", "validate"=>"1",),
                "calda_observacao" => array("type"=>"text", "label"=>"SafraAplicacaoCaldaObservation", "enabled"=>"1", 'position'=>63, 'notnull'=>0, "visible"=>"3", "cssview"=>"wordbreak"),
		"date_creation" => array("type"=>"datetime", "label"=>"DateCreation", "enabled"=>"1", 'position'=>500, 'notnull'=>1, "visible"=>"-2",),
		"tms" => array("type"=>"timestamp", "label"=>"DateModification", "enabled"=>"1", 'position'=>501, 'notnull'=>0, "visible"=>"-2",),
		"fk_user_creat" => array("type"=>"integer:User:user/class/user.class.php", "label"=>"UserAuthor", "picto"=>"user", "enabled"=>"1", 'position'=>510, 'notnull'=>1, "visible"=>"-2", "foreignkey"=>"0", "csslist"=>"tdoverflowmax150",),
		"fk_user_modif" => array("type"=>"integer:User:user/class/user.class.php", "label"=>"UserModif", "picto"=>"user", "enabled"=>"1", 'position'=>511, 'notnull'=>-1, "visible"=>"-2", "csslist"=>"tdoverflowmax150",),
		"last_main_doc" => array("type"=>"varchar(255)", "label"=>"LastMainDoc", "enabled"=>"1", 'position'=>600, 'notnull'=>0, "visible"=>"0",),
		"import_key" => array("type"=>"varchar(14)", "label"=>"ImportId", "enabled"=>"1", 'position'=>1000, 'notnull'=>-1, "visible"=>"-2",),
		"model_pdf" => array("type"=>"varchar(255)", "label"=>"Model pdf", "enabled"=>"1", 'position'=>1010, 'notnull'=>-1, "visible"=>"0",),
		"status" => array("type"=>"integer", "label"=>"Status", "enabled"=>"1", 'position'=>2000, 'notnull'=>1, "visible"=>"1", "index"=>"1", "arrayofkeyval"=>array("0" => "Rascunho", "1" => "Validado", "9" => "Cancelado"), "validate"=>"1",),
	);
	public $rowid;
	public $ref;
	public $label;
        public $amount;
        public $qty;
        public $fk_soc;
        public $fk_project;
        public $fk_task;
        public $operation_type;
        public $date_application;
        public $description;
        public $note_public;
        public $note_private;
        public $calda_observacao;
        public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $last_main_doc;
	public $import_key;
	public $model_pdf;
        public $status;
	// END MODULEBUILDER PROPERTIES


	// If this object has a subtable with lines

	/**
	 * @var string Name of subtable line
	 */
	public $table_element_line = 'safra_aplicacao_line';

	/**
	 * @var string Field with ID of parent key if this object has a parent
	 */
	public $fk_element = 'fk_aplicacao';

	/**
	 * @var string Name of subtable class that manage subtable lines
	 */
	public $class_element_line = 'AplicacaoLine';

	/**
	 * @var array List of child tables. To test if we can delete object.
	 */
	protected $childtables = array('safra_aplicacao_resource');

	/**
	 * @var array[] Indexed list of allocated resources grouped by element_type
	 */
	public $resources = array();

	/**
	 * @var AplicacaoLine[] Array of subtable lines
	 */
        public $lines = array();

        /**
         * @var bool Flag to avoid running schema checks more than once per request
         */
        protected static $schemaVerified = false;

        /**
         * Constructor
         *
         * @param DoliDb $db Database handler
         */
        public function __construct(DoliDB $db)
	{
		global $conf, $langs;

                $this->db = $db;

                self::ensureDatabaseSchema($db);

                if (isset($this->fields['operation_type'])) {
                        $this->fields['operation_type']['arrayofkeyval'] = self::getOperationTypeList($langs);
                }

                if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid']) && !empty($this->fields['ref'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		// Example to show how to set values of fields definition dynamically
		/*if ($user->hasRight('safra', 'aplicacao', 'read')) {
			$this->fields['myfield']['visible'] = 1;
			$this->fields['myfield']['noteditable'] = 0;
		}*/

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = false)
	{
		$resultcreate = $this->createCommon($user, $notrigger);

		if ($resultcreate > 0) {
			$this->id = $resultcreate;
			$this->syncTask($user);
		}

		return $resultcreate;
	}

	/**
	 * Clone an object into another one
	 *
	 * @param  	User 	$user      	User that creates
	 * @param  	int 	$fromid     Id of object to clone
	 * @return 	mixed 				New object created, <0 if KO
	 */
	public function createFromClone(User $user, $fromid)
	{
		global $langs, $extrafields;
		$error = 0;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$object = new self($this->db);

		$this->db->begin();

		// Load source object
		$result = $object->fetchCommon($fromid);
		if ($result > 0 && !empty($object->table_element_line)) {
			$object->fetchLines();
		}

		// get lines so they will be clone
		//foreach($this->lines as $line)
		//	$line->fetch_optionals();

		// Reset some properties
		unset($object->id);
		unset($object->fk_user_creat);
		unset($object->import_key);

		// Clear fields
		if (property_exists($object, 'ref')) {
			$object->ref = empty($this->fields['ref']['default']) ? "Copy_Of_".$object->ref : $this->fields['ref']['default'];
		}
		if (property_exists($object, 'label')) {
			$object->label = empty($this->fields['label']['default']) ? $langs->trans("CopyOf")." ".$object->label : $this->fields['label']['default'];
		}
		if (property_exists($object, 'status')) {
			$object->status = self::STATUS_DRAFT;
		}
		if (property_exists($object, 'date_creation')) {
			$object->date_creation = dol_now();
		}
		if (property_exists($object, 'date_modification')) {
			$object->date_modification = null;
		}
		// ...
		// Clear extrafields that are unique
		if (is_array($object->array_options) && count($object->array_options) > 0) {
			$extrafields->fetch_name_optionals_label($this->table_element);
			foreach ($object->array_options as $key => $option) {
				$shortkey = preg_replace('/options_/', '', $key);
				if (!empty($extrafields->attributes[$this->table_element]['unique'][$shortkey])) {
					//var_dump($key);
					//var_dump($clonedObj->array_options[$key]); exit;
					unset($object->array_options[$key]);
				}
			}
		}

		// Create clone
		$object->context['createfromclone'] = 'createfromclone';
		$result = $object->createCommon($user);
		if ($result < 0) {
			$error++;
			$this->setErrorsFromObject($object);
		}

		if (!$error) {
			// copy internal contacts
			if ($this->copy_linked_contact($object, 'internal') < 0) {
				$error++;
			}
		}

		if (!$error) {
			// copy external contacts if same company
			if (!empty($object->socid) && property_exists($this, 'fk_soc') && $this->fk_soc == $object->socid) {
				if ($this->copy_linked_contact($object, 'external') < 0) {
					$error++;
				}
			}
		}

		unset($object->context['createfromclone']);

		// End
		if (!$error) {
			$this->db->commit();
			return $object;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param 	int    	$id   			Id object
	 * @param 	string 	$ref  			Ref
	 * @param	int		$noextrafields	0=Default to load extrafields, 1=No extrafields
	 * @param	int		$nolines		0=Default to load extrafields, 1=No extrafields
	 * @return 	int     				Return integer <0 if KO, 0 if not found, >0 if OK
	 */
        public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
        {
                $result = $this->fetchCommon($id, $ref, '', $noextrafields);
                if ($result > 0) {
                        if (!empty($this->table_element_line) && empty($nolines)) {
                                $this->fetchLines($noextrafields);
                        }
                        $this->fetchResources();
                }
                return $result;
        }

	/**
	 * Load object lines in memory from the database
	 *
	 * @param	int		$noextrafields	0=Default to load extrafields, 1=No extrafields
	 * @return 	int         			Return integer <0 if KO, 0 if not found, >0 if OK
	 */
        public function fetchLines($noextrafields = 0)
        {
                $this->lines = array();
                if (empty($this->id)) return 0;

                $sql = 'SELECT rowid, fk_product, fk_produto_formulado, fk_produtotecnico, fk_entrepot, label, dose, dose_unit, area_ha, total_qty, note, movement'
                    .' FROM '.MAIN_DB_PREFIX.$this->table_element_line
                    .' WHERE '.$this->fk_element.' = '.((int) $this->id)
                    .' ORDER BY rowid ASC';

                $resql = $this->db->query($sql);
                if ($resql) {
                        while ($obj = $this->db->fetch_object($resql)) {
                                $line = new stdClass();
                                $line->id = (int) $obj->rowid;
                                $line->rowid = (int) $obj->rowid;
                                $line->fk_product = isset($obj->fk_product) ? (int) $obj->fk_product : 0;
                                $line->fk_produto_formulado = isset($obj->fk_produto_formulado) ? (int) $obj->fk_produto_formulado : 0;
                                $line->fk_produtotecnico = isset($obj->fk_produtotecnico) ? (int) $obj->fk_produtotecnico : 0;
                                $line->fk_entrepot = isset($obj->fk_entrepot) ? (int) $obj->fk_entrepot : 0;
                                $line->label = (string) $obj->label;
                                $line->dose = (float) $obj->dose;
                                $line->dose_unit = (string) $obj->dose_unit;
                                $line->area_ha = (float) $obj->area_ha;
                                $line->total_qty = (float) $obj->total_qty;
                                $line->note = (string) $obj->note;
                                $line->movement = isset($obj->movement) ? (int) $obj->movement : 1;
                                $this->lines[] = $line;
                        }
                        $this->db->free($resql);
                        return count($this->lines);
                }
                $this->error = $this->db->lasterror();
                return -1;
        }

        /**
         * Load allocated resources for the application.
         *
         * @return void
         */
        public function fetchResources()
        {
                $this->resources = array(
                        'vehicle' => array(),
                        'implement' => array(),
                        'person' => array(),
                );

                if (empty($this->id)) {
                        return;
                }

                $sql = 'SELECT rowid, element_type, fk_target, label, note'
                        .' FROM '.MAIN_DB_PREFIX."safra_aplicacao_resource"
                        .' WHERE fk_aplicacao = '.((int) $this->id)
                        .' ORDER BY rowid ASC';

                $resql = $this->db->query($sql);
                if ($resql) {
                        while ($obj = $this->db->fetch_object($resql)) {
                                $type = (string) $obj->element_type;
                                if (!isset($this->resources[$type])) {
                                        $this->resources[$type] = array();
                                }

                                $this->resources[$type][] = array(
                                        'rowid' => (int) $obj->rowid,
                                        'fk_target' => (int) $obj->fk_target,
                                        'label' => $obj->label,
                                        'note' => $obj->note,
                                );
                        }
                        $this->db->free($resql);
                }
        }

        /**
         * Replace existing resources with provided set.
         *
         * @param array $resources Array of associative arrays with keys type,fk_target,label,note
         *
         * @return int  <0 if error, >0 success
         */
        public function replaceResources(array $resources)
        {
                if (empty($this->id)) {
                        return -1;
                }

                $this->db->begin();

                $sql = 'DELETE FROM '.MAIN_DB_PREFIX."safra_aplicacao_resource WHERE fk_aplicacao = ".((int) $this->id);
                if (!$this->db->query($sql)) {
                        $this->db->rollback();
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                foreach ($resources as $resource) {
                        if (empty($resource['type'])) {
                                continue;
                        }

                        $type = preg_replace('/[^a-z0-9_]/i', '', $resource['type']);
                        if ($type === '') {
                                continue;
                        }

                        $fkTarget = isset($resource['fk_target']) ? (int) $resource['fk_target'] : 0;
                        $label = isset($resource['label']) ? trim((string) $resource['label']) : '';
                        $note = isset($resource['note']) ? trim((string) $resource['note']) : '';

                        $sql = "INSERT INTO ".MAIN_DB_PREFIX."safra_aplicacao_resource (fk_aplicacao, element_type, fk_target, label, note) VALUES (";
                        $sql .= ((int) $this->id).", '".$this->db->escape($type)."', ".($fkTarget > 0 ? $fkTarget : 'NULL').", '".$this->db->escape($label)."', '".$this->db->escape($note)."')";

                        if (!$this->db->query($sql)) {
                                $this->error = $this->db->lasterror();
                                $this->db->rollback();
                                return -1;
                        }
                }

                $this->db->commit();
                $this->fetchResources();

                global $user;
                if (!empty($user) && $user instanceof User) {
                        $this->syncTask($user);
                }

                return 1;
        }

        /**
         * Build stock summary (grouped by product/warehouse) from provided lines.
         *
         * @param array $lines             List of lines (objects or associative arrays)
         * @param bool  $missingWarehouse  Flag set to true when a line lacks warehouse information
         *
         * @return array
         */
        protected function summarizeLinesForStock(array $lines, User $user = null, &$missingWarehouse = false)
        {
                $summary = array();
                foreach ($lines as $line) {
                        $productId = 0;
                        $warehouseId = 0;
                        $qty = 0.0;
                        $label = '';
                        $movement = 1;

                        if (is_object($line)) {
                                $productId = empty($line->fk_product) ? 0 : (int) $line->fk_product;
                                $warehouseId = empty($line->fk_entrepot) ? 0 : (int) $line->fk_entrepot;
                                $qty = isset($line->total_qty) ? (float) $line->total_qty : 0.0;
                                $label = isset($line->label) ? trim((string) $line->label) : '';
                                if (isset($line->movement)) {
                                        $movement = (int) $line->movement;
                                }
                        } else {
                                $productId = empty($line['fk_product']) ? 0 : (int) $line['fk_product'];
                                $warehouseId = empty($line['fk_entrepot']) ? 0 : (int) $line['fk_entrepot'];
                                $qty = isset($line['total_qty']) ? (float) $line['total_qty'] : 0.0;
                                $label = !empty($line['label']) ? trim((string) $line['label']) : '';
                                if (isset($line['movement'])) {
                                        $movement = (int) $line['movement'];
                                }
                        }

                        if ($productId <= 0) {
                                continue;
                        }
                        if ($warehouseId <= 0) {
                                $missingWarehouse = true;
                                if ($user instanceof User) {
                                        $resolvedWarehouse = $this->resolveWarehouseIdForProduct($productId, $user);
                                        if ($resolvedWarehouse > 0) {
                                                $warehouseId = $resolvedWarehouse;
                                        }
                                }
                        }
                        if (abs($qty) < 1e-9) {
                                continue;
                        }

                        $movement = $movement === 0 ? 0 : 1;

                        $key = $productId.':'.$movement.':'.$warehouseId;

                        if (!isset($summary[$key])) {
                                $summary[$key] = array(
                                        'fk_product' => $productId,
                                        'fk_entrepot' => $warehouseId,
                                        'qty' => 0.0,
                                        'movement' => $movement,
                                        'labels' => array(),
                                );
                        } else {
                                if (empty($summary[$key]['fk_entrepot']) && $warehouseId > 0) {
                                        $summary[$key]['fk_entrepot'] = $warehouseId;
                                }
                        }

                        $summary[$key]['qty'] += abs($qty);

                        if ($label !== '') {
                                $summary[$key]['labels'][$label] = true;
                        }
                }

                return $summary;
        }

        protected function resolveWarehouseIdForProduct($productId, User $user = null)
        {
                if (empty($productId)) {
                        return 0;
                }

                if ($user instanceof User && getDolGlobalString('MAIN_DEFAULT_WAREHOUSE_USER') && !empty($user->fk_warehouse)) {
                        return (int) $user->fk_warehouse;
                }
                if (getDolGlobalString('MAIN_DEFAULT_WAREHOUSE')) {
                        return (int) getDolGlobalInt('MAIN_DEFAULT_WAREHOUSE');
                }

                $productId = (int) $productId;
                $sql = 'SELECT fk_entrepot FROM '.MAIN_DB_PREFIX.'product_stock WHERE fk_product = '.$productId.' ORDER BY reel DESC LIMIT 1';
                $resql = $this->db->query($sql);
                if ($resql) {
                        $obj = $this->db->fetch_object($resql);
                        if ($obj && !empty($obj->fk_entrepot)) {
                                return (int) $obj->fk_entrepot;
                        }
                }

                $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'entrepot WHERE entity IN ('.getEntity('stock').') ORDER BY rowid ASC LIMIT 1';
                $resql = $this->db->query($sql);
                if ($resql) {
                        $obj = $this->db->fetch_object($resql);
                        if ($obj && !empty($obj->rowid)) {
                                return (int) $obj->rowid;
                        }
                }

                return 0;
        }

        /**
         * Apply stock movements based on provided summary (all movements are decreases).
         *
         * @param User  $user     Current user
         * @param array $summary  Summary built with summarizeLinesForStock
         *
         * @return int            >0 success, <0 error
         */
        protected function applyStockMovementsFromSummary(User $user, array $summary, array &$applied = array())
        {
                if (empty($summary)) {
                        return 1;
                }

                global $langs;
                $langs->loadLangs(array('safra@safra', 'stocks', 'product'));

                require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

                foreach ($summary as $key => $item) {
                        $productId = (int) $item['fk_product'];
                        $warehouseId = (int) $item['fk_entrepot'];
                        $qty = (float) $item['qty'];
                        $movement = isset($item['movement']) ? (int) $item['movement'] : 1;

                        if ($productId <= 0 || $qty <= 0) {
                                continue;
                        }
                        if ($warehouseId <= 0) {
                                $warehouseId = $this->resolveWarehouseIdForProduct($productId, $user);
                                if ($warehouseId <= 0) {
                                        $this->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                                        $this->rollbackStockOperations($user, $applied);
                                        return -1;
                                }
                                $summary[$key]['fk_entrepot'] = $warehouseId;
                        }

                        $product = new Product($this->db);
                        if ($product->fetch($productId) <= 0) {
                                $this->error = $product->error ?: $product->errors;
                                $this->rollbackStockOperations($user, $applied);
                                return -1;
                        }

                        $label = $langs->trans('SafraAplicacaoStockMovementLabel', $this->ref ?: $this->id);
                        if (!empty($item['labels'])) {
                                $label .= ' - '.implode(', ', array_slice(array_keys($item['labels']), 0, 3));
                        }

                        $res = $product->correct_stock($user, $warehouseId, $qty, $movement === 0 ? 0 : 1, $label, 0, '', $this->element, $this->id);
                        if ($res <= 0) {
                                $this->error = $product->error ?: $product->errors;
                                $this->rollbackStockOperations($user, $applied);
                                return -1;
                        }

                        $applied[] = array(
                                'fk_product' => $productId,
                                'fk_entrepot' => $warehouseId,
                                'movement' => ($movement === 0 ? 0 : 1),
                                'qty' => $qty,
                        );
                }

                return 1;
        }

                protected function revertStockMovements(User $user, array $summary, array &$applied = array())
        {
                if (empty($summary)) {
                        return 1;
                }

                global $langs;
                $langs->loadLangs(array('safra@safra', 'stocks', 'product'));

                require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

                foreach ($summary as $item) {
                        $productId = (int) $item['fk_product'];
                        $warehouseId = (int) $item['fk_entrepot'];
                        $qty = (float) $item['qty'];

                        if ($productId <= 0 || $qty <= 0) {
                                continue;
                        }
                        if ($warehouseId <= 0) {
                                $warehouseId = $this->resolveWarehouseIdForProduct($productId, $user);
                                if ($warehouseId <= 0) {
                                        $this->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                                        return -1;
                                }
                        }

                        $product = new Product($this->db);
                        if ($product->fetch($productId) <= 0) {
                                $this->error = $product->error ?: $product->errors;
                                $this->rollbackStockOperations($user, $applied);
                                return -1;
                        }

                        $label = $langs->trans('SafraAplicacaoStockMovementLabel', $this->ref ?: $this->id).' (delete)';
                        $res = $product->correct_stock($user, $warehouseId, $qty, $movement === 0 ? 1 : 0, $label, 0, '', $this->element, $this->id);
                        if ($res <= 0) {
                                $this->error = $product->error ?: $product->errors;
                                $this->rollbackStockOperations($user, $applied);
                                return -1;
                        }

                        $applied[] = array(
                                'fk_product' => $productId,
                                'fk_entrepot' => $warehouseId,
                                'movement' => ($movement === 0 ? 1 : 0),
                                'qty' => $qty,
                        );
                }

                return 1;
        }

        /**
         * Adjust stock movements
         * Adjust stock movements when a validated application is edited.
         *
         * @param User  $user        Current user
         * @param array $oldSummary  Summary before edit
         * @param array $newSummary  Summary after edit
         *
         * @return int               >0 success, <0 error
         */
        protected function adjustStockForLineChanges(User $user, array $oldSummary, array $newSummary, array &$applied = array())
        {
                $keys = array_unique(array_merge(array_keys($oldSummary), array_keys($newSummary)));
                if (empty($keys)) {
                        return 1;
                }

                global $langs;
                $langs->loadLangs(array('safra@safra', 'stocks', 'product'));

                require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

                foreach ($keys as $key) {
                        $oldQty = isset($oldSummary[$key]) ? (float) $oldSummary[$key]['qty'] : 0.0;
                        $newQty = isset($newSummary[$key]) ? (float) $newSummary[$key]['qty'] : 0.0;
                        $delta = $newQty - $oldQty;
                        if (abs($delta) < 1e-9) {
                                continue;
                        }

                        $item = isset($newSummary[$key]) ? $newSummary[$key] : $oldSummary[$key];
                        $productId = (int) $item['fk_product'];
                        $warehouseId = (int) $item['fk_entrepot'];

                        if ($warehouseId <= 0) {
                                $warehouseId = $this->resolveWarehouseIdForProduct($productId, $user);
                                if ($warehouseId <= 0) {
                                        $this->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                                        $this->rollbackStockOperations($user, $applied);
                                        return -1;
                                }
                                if (!isset($newSummary[$key])) {
                                        $oldSummary[$key]['fk_entrepot'] = $warehouseId;
                                } else {
                                        $newSummary[$key]['fk_entrepot'] = $warehouseId;
                                }
                        }

                        $product = new Product($this->db);
                        if ($product->fetch($productId) <= 0) {
                                $this->error = $product->error ?: $product->errors;
                                $this->rollbackStockOperations($user, $applied);
                                return -1;
                        }

                        $label = $langs->trans('SafraAplicacaoStockMovementLabel', $this->ref ?: $this->id);
                        $labels = array();
                        if (!empty($item['labels'])) {
                                $labels = array_keys($item['labels']);
                        } elseif (!empty($oldSummary[$key]['labels'])) {
                                $labels = array_keys($oldSummary[$key]['labels']);
                        }
                        if (!empty($labels)) {
                                $label .= ' - '.implode(', ', array_slice($labels, 0, 3));
                        }

                        $movementNew = isset($newSummary[$key]) && isset($newSummary[$key]['movement']) ? (int) $newSummary[$key]['movement'] : null;
                        $movementOld = isset($oldSummary[$key]) && isset($oldSummary[$key]['movement']) ? (int) $oldSummary[$key]['movement'] : null;
                        $baseMovement = $movementNew !== null ? $movementNew : ($movementOld !== null ? $movementOld : 1);
                        $baseMovement = $baseMovement === 0 ? 0 : 1;

                        $movement = ($delta > 0) ? $baseMovement : ($baseMovement === 0 ? 1 : 0);
                        $qty = abs($delta);

                        $res = $product->correct_stock($user, $warehouseId, $qty, $movement, $label, 0, '', $this->element, $this->id);
                        if ($res <= 0) {
                                $this->error = $product->error ?: $product->errors;
                                $this->rollbackStockOperations($user, $applied);
                                return -1;
                        }

                        $applied[] = array(
                                'fk_product' => $productId,
                                'fk_entrepot' => $warehouseId,
                                'movement' => $movement,
                                'qty' => $qty,
                        );
                }

                return 1;
        }

        /**
         * Rollback previously applied stock operations.
         *
         * @param User  $user        Current user
         * @param array $operations  List of operations (movement 0/1)
         *
         * @return void
         */
        protected function rollbackStockOperations(User $user, array $operations)
        {
                if (empty($operations)) {
                        return;
                }

                global $langs;
                $langs->loadLangs(array('safra@safra', 'stocks', 'product'));

                require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

                foreach (array_reverse($operations) as $operation) {
                        $productId = (int) $operation['fk_product'];
                        $warehouseId = (int) $operation['fk_entrepot'];
                        $movement = (int) $operation['movement'];
                        $qty = (float) $operation['qty'];

                        if ($productId <= 0 || $warehouseId <= 0 || $qty <= 0) {
                                continue;
                        }

                        $product = new Product($this->db);
                        if ($product->fetch($productId) <= 0) {
                                continue;
                        }

                        $rollbackMovement = $movement === 1 ? 0 : 1;
                        $label = $langs->trans('SafraAplicacaoStockMovementLabel', $this->ref ?: $this->id).' (rollback)';
                        $product->correct_stock($user, $warehouseId, $qty, $rollbackMovement, $label, 0, '', $this->element, $this->id);
                }
        }

        /**
         * Replace all application lines with provided set.
         *
         * @param array $lines Lines definition
         *
         * @return int <0 if error, >0 success
         */
        public function replaceLines(array $lines, User $user = null)
        {
                if (empty($this->id)) {
                        return -1;
                }

                if (!$user instanceof User && !empty($GLOBALS['user']) && $GLOBALS['user'] instanceof User) {
                        $user = $GLOBALS['user'];
                }

                if (empty($this->lines)) {
                        $this->fetchLines();
                }

                $oldSummary = $this->summarizeLinesForStock($this->lines, $user);

                $this->db->begin();

                $sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element_line.' WHERE '.$this->fk_element.' = '.((int) $this->id);
                if (!$this->db->query($sql)) {
                        $this->db->rollback();
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                $totalApplied = 0.0;

                foreach ($lines as $line) {
                        $fkProduct = empty($line['fk_product']) ? 0 : (int) $line['fk_product'];
                        $fkFormulado = empty($line['fk_produto_formulado']) ? 0 : (int) $line['fk_produto_formulado'];
                        $fkTecnico = empty($line['fk_produtotecnico']) ? 0 : (int) $line['fk_produtotecnico'];
                        $fkWarehouse = empty($line['fk_entrepot']) ? 0 : (int) $line['fk_entrepot'];
                        $label = isset($line['label']) ? trim((string) $line['label']) : '';
                        $dose = isset($line['dose']) ? (float) $line['dose'] : 0;
                        $doseUnit = isset($line['dose_unit']) ? substr(trim((string) $line['dose_unit']), 0, 10) : '';
                        $areaHa = isset($line['area_ha']) ? (float) $line['area_ha'] : 0;
                        $totalQty = isset($line['total_qty']) ? (float) $line['total_qty'] : ($dose * $areaHa);
                        $note = isset($line['note']) ? trim((string) $line['note']) : '';
                        $movement = isset($line['movement']) ? (int) $line['movement'] : 1;
                        $movement = $movement === 0 ? 0 : 1;

                        if ($fkProduct <= 0 && empty($label)) {
                                continue;
                        }

                        $totalApplied += abs($totalQty);

                        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element_line
                                .' ('.$this->fk_element.', fk_product, fk_produto_formulado, fk_produtotecnico, fk_entrepot, label, dose, dose_unit, area_ha, total_qty, note, movement) VALUES (';
                        $sql .= ((int) $this->id).', ';
                        $sql .= ($fkProduct > 0 ? (int) $fkProduct : 'NULL').', ';
                        $sql .= ($fkFormulado > 0 ? (int) $fkFormulado : 'NULL').', ';
                        $sql .= ($fkTecnico > 0 ? (int) $fkTecnico : 'NULL').', ';
                        $sql .= ($fkWarehouse > 0 ? (int) $fkWarehouse : 'NULL').', ';
                        $sql .= "'".$this->db->escape($label)."'".', ';
                        $sql .= ((float) $dose).', ';
                        $sql .= "'".$this->db->escape($doseUnit)."'".', ';
                        $sql .= ((float) $areaHa).', ';
                        $sql .= ((float) $totalQty).', ';
                        $sql .= "'".$this->db->escape($note)."'".', ';
                        $sql .= ((int) $movement).')';

                        if (!$this->db->query($sql)) {
                                $this->error = $this->db->lasterror();
                                $this->db->rollback();
                                return -1;
                        }
                }

                $this->amount = $totalApplied;

                $sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element
                        .' SET amount = '.((float) $this->amount)
                        .' WHERE rowid = '.((int) $this->id);
                if (!$this->db->query($sql)) {
                        $this->db->rollback();
                        $this->error = $this->db->lasterror();
                        return -1;
                }

                $missingWarehouse = false;
                $newSummary = $this->summarizeLinesForStock($lines, $user, $missingWarehouse);

                $appliedStock = array();
                if ($this->status == self::STATUS_VALIDATED && $user instanceof User) {
                        global $langs;
                        foreach ($newSummary as $item) {
                                if (empty($item['fk_entrepot'])) {
                                        $this->db->rollback();
                                        $langs->loadLangs(array('safra@safra'));
                                        $this->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                                        return -1;
                                }
                        }

                        $adjustRes = $this->adjustStockForLineChanges($user, $oldSummary, $newSummary, $appliedStock);
                        if ($adjustRes < 0) {
                                $this->db->rollback();
                                return -1;
                        }
                }

                $this->fetchLines();

                if ($user instanceof User) {
                        $syncRes = $this->syncTask($user);
                        if ($syncRes < 0) {
                                if (!empty($appliedStock)) {
                                        $this->rollbackStockOperations($user, $appliedStock);
                                }
                                $this->db->rollback();
                                return -1;
                        }
                }

                $this->db->commit();

                return 1;
        }

        /**
         * Ensure required database schema exists.
         *
         * @param DoliDB $db Database handler
         *
         * @return void
         */
        protected static function ensureDatabaseSchema(DoliDB $db)
        {
                if (self::$schemaVerified) {
                        return;
                }

                self::$schemaVerified = true;

                $mainTable = MAIN_DB_PREFIX.'safra_aplicacao';
                $info = $db->DDLDescTable($mainTable, $mainTable);
                $hasFkTask = false;
                $hasOperationType = false;
                if (is_array($info)) {
                        foreach ($info as $fieldInfo) {
                                if (!empty($fieldInfo['field'])) {
                                        if ($fieldInfo['field'] === 'fk_task') {
                                                $hasFkTask = true;
                                        }
                                        if ($fieldInfo['field'] === 'operation_type') {
                                                $hasOperationType = true;
                                        }
                                }
                        }
                }

                if (!$hasFkTask) {
                        $sql = 'ALTER TABLE '.$mainTable.' ADD COLUMN fk_task integer AFTER fk_project';
                        if (!$db->query($sql)) {
                                dol_syslog(__METHOD__.' failed to add fk_task column: '.$db->lasterror(), LOG_WARNING);
                        }
                }

                if (!$hasOperationType) {
                        $sql = 'ALTER TABLE '.$mainTable." ADD COLUMN operation_type varchar(32) NOT NULL DEFAULT '".self::OPERATION_APLICACAO."' AFTER fk_task";
                        if (!$db->query($sql)) {
                                dol_syslog(__METHOD__.' failed to add operation_type column: '.$db->lasterror(), LOG_WARNING);
                        }
                }

                $lineTable = MAIN_DB_PREFIX.'safra_aplicacao_line';
                $lineInfo = $db->DDLDescTable($lineTable, $lineTable);
                if (!is_array($lineInfo)) {
                        $sql = 'CREATE TABLE IF NOT EXISTS '.$lineTable.' ('
                                .'rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, '
                                .'fk_aplicacao integer NOT NULL, '
                                .'fk_product integer, '
                                .'fk_produto_formulado integer, '
                                .'fk_produtotecnico integer, '
                                .'fk_entrepot integer, '
                                .'label varchar(255), '
                                .'dose double, '
                                .'dose_unit varchar(10), '
                                .'area_ha double, '
                                .'total_qty double, '
                                .'note text, '
                                .'movement integer NOT NULL DEFAULT 1, '
                                .'date_creation datetime NOT NULL DEFAULT CURRENT_TIMESTAMP'
                                .') ENGINE=innodb;';
                        if ($db->query($sql)) {
                                $db->query('ALTER TABLE '.$lineTable.' ADD INDEX idx_safra_aplicacao_line_fk_aplicacao (fk_aplicacao)');
                                $db->query('ALTER TABLE '.$lineTable.' ADD INDEX idx_safra_aplicacao_line_fk_product (fk_product)');
                                $db->query('ALTER TABLE '.$lineTable.' ADD INDEX idx_safra_aplicacao_line_fk_formulado (fk_produto_formulado)');
                                $db->query('ALTER TABLE '.$lineTable.' ADD INDEX idx_safra_aplicacao_line_fk_produtotecnico (fk_produtotecnico)');
                                $db->query('ALTER TABLE '.$lineTable.' ADD CONSTRAINT llx_safra_aplicacao_line_fk_aplicacao FOREIGN KEY (fk_aplicacao) REFERENCES '.MAIN_DB_PREFIX.'safra_aplicacao(rowid)');
                        } else {
                                dol_syslog(__METHOD__.' failed to create safra_aplicacao_line: '.$db->lasterror(), LOG_WARNING);
                        }
                        $lineInfo = $db->DDLDescTable($lineTable, $lineTable);
                }

                $hasMovementColumn = false;
                if (is_array($lineInfo)) {
                        foreach ($lineInfo as $fieldInfo) {
                                if (!empty($fieldInfo['field']) && $fieldInfo['field'] === 'movement') {
                                        $hasMovementColumn = true;
                                        break;
                                }
                        }
                }
                if (!$hasMovementColumn) {
                        $sql = 'ALTER TABLE '.$lineTable.' ADD COLUMN movement integer NOT NULL DEFAULT 1';
                        if (!$db->query($sql)) {
                                dol_syslog(__METHOD__.' failed to add movement column: '.$db->lasterror(), LOG_WARNING);
                        }
                }

                $taskTable = MAIN_DB_PREFIX.'projet_task';
                $taskInfo = $db->DDLDescTable($taskTable, $taskTable);
                $hasFkAplicacao = false;
                if (is_array($taskInfo)) {
                        foreach ($taskInfo as $fieldInfo) {
                                if (!empty($fieldInfo['field']) && $fieldInfo['field'] === 'fk_aplicacao') {
                                        $hasFkAplicacao = true;
                                        break;
                                }
                        }
                }
                if (!$hasFkAplicacao) {
                        $sql = 'ALTER TABLE '.$taskTable.' ADD COLUMN fk_aplicacao integer DEFAULT NULL';
                        if ($db->query($sql)) {
                                $db->query('CREATE INDEX idx_projet_task_fk_aplicacao ON '.$taskTable.' (fk_aplicacao)');
                        } else {
                                dol_syslog(__METHOD__.' failed to add fk_aplicacao column on projet_task: '.$db->lasterror(), LOG_WARNING);
                        }
                }

                require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
                $extrafields = new ExtraFields($db);
                $labels = $extrafields->fetch_name_optionals_label('projet_task');
                if (!is_array($labels) || !array_key_exists('fk_aplicacao', $labels)) {
                        $extrafields->addExtraField(
                                'fk_aplicacao',
                                'Aplicação',
                                'link',
                                150,
                                '',
                                'projet_task',
                                0,
                                0,
                                '',
                                array('options' => array('Aplicacao:safra/class/aplicacao.class.php:1' => null)),
                                1,
                                '',
                                'isModEnabled("safra")'
                        );
                }

                $resourceTable = MAIN_DB_PREFIX.'safra_aplicacao_resource';
                if (!is_array($db->DDLDescTable($resourceTable, $resourceTable))) {
                        $sql = 'CREATE TABLE IF NOT EXISTS '.$resourceTable.' ('
                                .'rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, '
                                .'fk_aplicacao integer NOT NULL, '
                                .'element_type varchar(32) NOT NULL, '
                                .'fk_target integer, '
                                .'label varchar(255), '
                                .'note text'
                                .') ENGINE=innodb;';
                        if ($db->query($sql)) {
                                $db->query('ALTER TABLE '.$resourceTable.' ADD INDEX idx_safra_aplicacao_resource_fk_aplicacao (fk_aplicacao)');
                                $db->query('ALTER TABLE '.$resourceTable.' ADD INDEX idx_safra_aplicacao_resource_type (element_type)');
                                $db->query('ALTER TABLE '.$resourceTable.' ADD CONSTRAINT llx_safra_aplicacao_resource_fk_aplicacao FOREIGN KEY (fk_aplicacao) REFERENCES '.MAIN_DB_PREFIX.'safra_aplicacao(rowid)');
                        } else {
                                dol_syslog(__METHOD__.' failed to create safra_aplicacao_resource: '.$db->lasterror(), LOG_WARNING);
                        }
                }
        }

        /**
         * Build textual description for linked task.
         *
         * @return string
         */
        public function buildTaskDescription()
        {
                global $langs;
                $langs->loadLangs(array('safra@safra', 'projects', 'stocks', 'product'));

                $output = array();

                if (!empty($this->label)) {
                        $output[] = $langs->trans('Label').': '.dol_escape_htmltag($this->label);
                }

                if (!empty($this->operation_type)) {
                        $output[] = $langs->trans('SafraOperationType').': '.self::getOperationTypeLabel($this->operation_type, $langs);
                }

                if (!empty($this->qty)) {
                        $output[] = $langs->trans('SafraAplicacaoAreaHa').': '.price2num($this->qty, '2');
                }

                if (!empty($this->date_application)) {
                        $output[] = $langs->trans('Date').': '.dol_print_date($this->date_application, 'day');
                }

                if (!empty($this->description)) {
                        $output[] = trim($this->description);
                }

                if (!empty($this->lines)) {
                        $output[] = $langs->trans('SafraAplicacaoTaskProducts');
                        foreach ($this->lines as $line) {
                                $lineLabel = trim($line->label);
                                if ($line->fk_product > 0 && $lineLabel === '') {
                                        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
                                        $product = new Product($this->db);
                                        if ($product->fetch($line->fk_product) > 0) {
                                                $lineLabel = $product->ref;
                                                if (!empty($product->label)) {
                                                        $lineLabel .= ' - '.$product->label;
                                                }
                                        }
                                }

                                $doseParts = array();
                                if (!empty($line->dose)) {
                                        $doseParts[] = price2num($line->dose, 4).' '.$line->dose_unit;
                                }
                                if (!empty($line->total_qty)) {
                                        $doseParts[] = $langs->trans('Total').': '.price2num($line->total_qty, 4);
                                }
                                if (!empty($line->area_ha)) {
                                        $doseParts[] = $langs->trans('SafraAplicacaoAreaHa').': '.price2num($line->area_ha, 4);
                                }

                                $movementLabel = '';
                                if (isset($line->movement)) {
                                        $movementLabel = ((int) $line->movement === 0) ? $langs->trans('SafraLineMovementReceive') : $langs->trans('SafraLineMovementConsume');
                                }

                                $details = array();
                                if (!empty($doseParts)) {
                                        $details[] = implode(', ', $doseParts);
                                }
                                if ($movementLabel !== '') {
                                        $details[] = $movementLabel;
                                }

                                $output[] = '- '.($lineLabel !== '' ? $lineLabel : $langs->trans('Product')).(!empty($details) ? ' ('.implode('; ', $details).')' : '');
                        }
                }

                if (!empty($this->resources)) {
                        foreach ($this->resources as $type => $items) {
                                if (empty($items)) {
                                        continue;
                                }

                                $labelKey = 'SafraAplicacaoResource'.ucfirst($type);
                                $output[] = $langs->trans($labelKey);
                                foreach ($items as $item) {
                                        $resourceLabel = $item['label'];
                                        if ($resourceLabel === '' && !empty($item['fk_target'])) {
                                                $resourceLabel = '#'.$item['fk_target'];
                                        }

                                        $lineText = '- '.$resourceLabel;
                                        if (!empty($item['note'])) {
                                                $lineText .= ' ('.$item['note'].')';
                                        }
                                        $output[] = $lineText;
                                }
                        }
                }

                if (!empty($this->calda_observacao)) {
                        $output[] = $langs->trans('SafraAplicacaoCaldaObservation').': '.$this->calda_observacao;
                }

                return implode("\n", $output);
        }

        /**
         * Mark application as completed: close task and consume stock.
         *
         * @param User $user User triggering completion
         *
         * @return int <0 if error, >0 success
         */
        public function markAsCompleted(User $user)
        {
                global $langs;
                $langs->loadLangs(array('safra@safra', 'stocks', 'product'));

                if (empty($this->id)) {
                        $this->error = 'NotLoaded';
                        return -1;
                }

                if ($this->status == self::STATUS_VALIDATED) {
                        return 0;
                }

                if (empty($this->lines)) {
                        $this->fetchLines();
                }

                $this->db->begin();

                $missingWarehouse = false;
                $summary = $this->summarizeLinesForStock($this->lines, $user, $missingWarehouse);
                if ($missingWarehouse) {
                        foreach ($summary as $item) {
                                if (empty($item['fk_entrepot'])) {
                                        $this->db->rollback();
                                        $this->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                                        return -1;
                                }
                        }
                }

                $appliedMovements = array();
                if ($this->applyStockMovementsFromSummary($user, $summary, $appliedMovements) < 0) {
                        $this->db->rollback();
                        return -1;
                }

                $sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element
                        .' SET status = '.self::STATUS_VALIDATED
                        .', tms = tms'
                        .' WHERE rowid = '.((int) $this->id);
                if (!$this->db->query($sql)) {
                        $this->error = $this->db->lasterror();
                        $this->rollbackStockOperations($user, $appliedMovements);
                        $this->db->rollback();
                        return -1;
                }

                $this->status = self::STATUS_VALIDATED;

                if ($this->syncTask($user) < 0) {
                        $this->rollbackStockOperations($user, $appliedMovements);
                        $this->db->rollback();
                        return -1;
                }

                $this->db->commit();

                return 1;
        }

        /**
         * Create or update the project task linked to this application.
         *
         * @param User $user Current user
         * @return int               Task rowid if synced, <0 on error
         */
        public function syncTask(User $user)
        {
                global $langs;

                if (!isModEnabled('project')) {
                        return 0;
                }

                if (empty($this->id)) {
                        return 0;
                }

                if (empty($this->fk_project)) {
                        $this->unlinkTask();
                        return 0;
                }

                $langs->loadLangs(array('projects', 'safra@safra'));

                require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

                if (empty($this->lines)) {
                        $this->fetchLines();
                }
                if (empty($this->resources) || !array_key_exists('vehicle', $this->resources)) {
                        $this->fetchResources();
                }

                $refLabel = trim((string) $this->ref);
                if ($refLabel === '') {
                        $refLabel = '#'.((int) $this->id);
                }
                $operationLabel = self::getOperationTypeLabel($this->operation_type, $langs);
                $label = $operationLabel.' - '.$refLabel;
                $description = $this->buildTaskDescription();

                $taskId = (int) $this->fk_task;
                $task = new Task($this->db);
                if ($taskId > 0 && $task->fetch($taskId) > 0) {
                        // keep existing link
                } else {
                        $taskId = $this->findExistingTaskId();
                        if ($taskId > 0 && $task->fetch($taskId) > 0) {
                                $this->fk_task = $taskId;
                        } else {
                                $taskId = 0;
                        }
                }

                if ($taskId === 0) {
                        $task->fk_project = (int) $this->fk_project;
                        $task->label = $label;
                        $task->description = $description;
                        if (!empty($this->date_application)) {
                                $task->date_start = $this->date_application;
                                if ($this->status == self::STATUS_VALIDATED) {
                                        $task->date_end = $this->date_application;
                                }
                        }
                        $task->progress = ($this->status == self::STATUS_VALIDATED) ? 100 : 0;
                        $task->array_options = array('options_fk_aplicacao' => (int) $this->id);
                        $task->fk_aplicacao = (int) $this->id;
                        $res = $task->create($user);
                        if ($res <= 0) {
                                $this->error = $task->error ?: $task->errors;
                                return -1;
                        }
                        $taskId = $task->id;
                        $this->fk_task = $taskId;
                } else {
                        $task->fk_project = (int) $this->fk_project;
                        $task->label = $label;
                        $task->description = $description;
                        if (!is_array($task->array_options)) {
                                $task->array_options = array();
                        }
                        $task->array_options['options_fk_aplicacao'] = (int) $this->id;
                        $task->fk_aplicacao = (int) $this->id;
                        if (!empty($this->date_application)) {
                                $task->date_start = $this->date_application;
                                if ($this->status == self::STATUS_VALIDATED) {
                                        $task->date_end = $this->date_application;
                                } elseif ((int) $task->progress >= 100) {
                                        $task->date_end = null;
                                }
                        }
                        if ($this->status == self::STATUS_VALIDATED) {
                                $task->progress = 100;
                                if (empty($task->date_end)) {
                                        $task->date_end = !empty($this->date_application) ? $this->date_application : dol_now();
                                }
                        } elseif ((int) $task->progress >= 100) {
                                $task->progress = 0;
                                $task->date_end = null;
                        }

                        $res = $task->update($user, 1);
                        if ($res <= 0) {
                                $this->error = $task->error ?: $task->errors;
                                return -1;
                        }
                }

                $this->updateTaskLinking($taskId);

                return $taskId;
        }

        /**
         * Persist relation between application and provided task.
         *
         * @param int $taskId Task identifier
         * @return void
         */
        protected function updateTaskLinking($taskId)
        {
                if (empty($taskId) || empty($this->id)) {
                        return;
                }

                $taskId = (int) $taskId;
                $appId = (int) $this->id;

                if ((int) $this->fk_task !== $taskId) {
                        $sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET fk_task = '.$taskId.' WHERE rowid = '.$appId;
                        if ($this->db->query($sql)) {
                                $this->fk_task = $taskId;
                        } else {
                                $this->error = $this->db->lasterror();
                        }
                }

                $sql = 'UPDATE '.MAIN_DB_PREFIX."projet_task SET fk_aplicacao = ".$appId." WHERE rowid = ".$taskId;
                if (!$this->db->query($sql)) {
                        dol_syslog(__METHOD__.' failed to bind task '.$taskId.' to application '.$appId.': '.$this->db->lasterror(), LOG_WARNING);
                }
        }

        /**
         * Try to find an already existing task linked to this application.
         *
         * @return int Task identifier or 0 if not found
         */
        protected function findExistingTaskId()
        {
                if (empty($this->id)) {
                        return 0;
                }

                $appId = (int) $this->id;

                $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX."projet_task WHERE fk_aplicacao = ".$appId.' ORDER BY rowid DESC';
                $resql = $this->db->query($sql);
                if ($resql) {
                        $obj = $this->db->fetch_object($resql);
                        $this->db->free($resql);
                        if ($obj && !empty($obj->rowid)) {
                                return (int) $obj->rowid;
                        }
                }

                $sql = 'SELECT fk_object FROM '.MAIN_DB_PREFIX."projet_task_extrafields WHERE options_fk_aplicacao = ".$appId.' ORDER BY fk_object DESC';
                $resql = $this->db->query($sql);
                if ($resql) {
                        $obj = $this->db->fetch_object($resql);
                        $this->db->free($resql);
                        if ($obj && !empty($obj->fk_object)) {
                                return (int) $obj->fk_object;
                        }
                }

                return 0;
        }

        /**
         * Detach current linked task if any.
         *
         * @return void
         */
        protected function unlinkTask()
        {
                if (empty($this->fk_task)) {
                        return;
                }

                $taskId = (int) $this->fk_task;

                $sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET fk_task = NULL WHERE rowid = '.((int) $this->id);
                if ($this->db->query($sql)) {
                        $this->fk_task = null;
                }

                $sql = 'UPDATE '.MAIN_DB_PREFIX."projet_task SET fk_aplicacao = NULL WHERE rowid = ".$taskId;
                $this->db->query($sql);

                require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
                $task = new Task($this->db);
                if ($task->fetch($taskId) > 0) {
                        if (!is_array($task->array_options)) {
                                $task->array_options = array();
                        }
                        $task->array_options['options_fk_aplicacao'] = '';
                        $task->update(null, 1);
                }
        }


	/**
	 * Load list of objects in memory from the database. Using a fetchAll is a bad practice, instead try to forge you optimized and limited SQL request.
	 *
	 * @param  string      $sortorder    Sort Order
	 * @param  string      $sortfield    Sort field
	 * @param  int         $limit        limit
	 * @param  int         $offset       Offset
	 * @param  array       $filter       Filter array. Example array('mystringfield'=>'value', 'myintfield'=>4, 'customsql'=>...)
	 * @param  string      $filtermode   Filter mode (AND or OR)
	 * @return array|int                 int <0 if KO, array of pages if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, array $filter = array(), $filtermode = 'AND')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql = "SELECT ";
		$sql .= $this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		if (isset($this->isextrafieldmanaged) && $this->isextrafieldmanaged == 1) {
			$sql .= " LEFT JOIN ".$this->db->prefix().$this->table_element."_extrafields as te ON te.fk_object = t.rowid";
		}
		if (isset($this->ismultientitymanaged) && $this->ismultientitymanaged == 1) {
			$sql .= " WHERE t.entity IN (".getEntity($this->element).")";
		} else {
			$sql .= " WHERE 1 = 1";
		}
		// Manage filter
		$sqlwhere = array();
		if (count($filter) > 0) {
			foreach ($filter as $key => $value) {
				$columnName = preg_replace('/^t\./', '', $key);
				if ($key === 'customsql') {
					// Never use 'customsql' with a value from user input since it is injected as is. The value must be hard coded.
					$sqlwhere[] = $value;
					continue;
				} elseif (isset($this->fields[$columnName])) {
					$type = $this->fields[$columnName]['type'];
					if (preg_match('/^integer/', $type)) {
						if (is_int($value)) {
							// single value
							$sqlwhere[] = $key . " = " . intval($value);
						} elseif (is_array($value)) {
							if (empty($value)) {
								continue;
							}
							$sqlwhere[] = $key . ' IN (' . $this->db->sanitize(implode(',', array_map('intval', $value))) . ')';
						}
						continue;
					} elseif (in_array($type, array('date', 'datetime', 'timestamp'))) {
						$sqlwhere[] = $key . " = '" . $this->db->idate($value) . "'";
						continue;
					}
				}

				// when the $key doesn't fall into the previously handled categories, we do as if the column were a varchar/text
				if (is_array($value) && count($value)) {
					$value = implode(',', array_map(function ($v) {
						return "'" . $this->db->sanitize($this->db->escape($v)) . "'";
					}, $value));
					$sqlwhere[] = $key . ' IN (' . $this->db->sanitize($value, true) . ')';
				} elseif (is_scalar($value)) {
					if (strpos($value, '%') === false) {
						$sqlwhere[] = $key . " = '" . $this->db->sanitize($this->db->escape($value)) . "'";
					} else {
						$sqlwhere[] = $key . " LIKE '%" . $this->db->escape($this->db->escapeforlike($value)) . "%'";
					}
				}
			}
		}
		if (count($sqlwhere) > 0) {
			$sql .= " AND (".implode(" ".$filtermode." ", $sqlwhere).")";
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);

				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				$records[$record->id] = $record;

				$i++;
			}
			$this->db->free($resql);

			return $records;
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = false)
	{
		$res = $this->updateCommon($user, $notrigger);
		if ($res > 0) {
			$this->syncTask($user);
		}
		return $res;
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user       User that deletes
	 * @param bool $notrigger  false=launch triggers, true=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = false)
        {
                if (empty($this->id) && !empty($this->rowid)) {
                        $this->id = (int) $this->rowid;
                }
                if (empty($this->id)) {
                        return 0;
                }

                if (empty($this->lines)) {
                        $this->fetchLines();
                }

                $this->db->begin();

                global $langs;

                $summary = $this->summarizeLinesForStock($this->lines, $user);
                $revertedOperations = array();
                if ($this->status == self::STATUS_VALIDATED && $user instanceof User) {
                        $langs->loadLangs(array('safra@safra'));
                        foreach ($summary as $item) {
                                if (empty($item['fk_entrepot'])) {
                                        $this->error = $langs->trans('SafraAplicacaoMissingWarehouse');
                                        $this->db->rollback();
                                        return -1;
                                }
                        }

                        if ($this->revertStockMovements($user, $summary, $revertedOperations) < 0) {
                                $this->db->rollback();
                                return -1;
                        }
                }

                $sql = 'DELETE FROM '.MAIN_DB_PREFIX."safra_aplicacao_line WHERE fk_aplicacao = ".((int) $this->id);
                if (!$this->db->query($sql)) {
                        $this->error = $this->db->lasterror();
                        if (!empty($revertedOperations) && $user instanceof User) {
                                $this->rollbackStockOperations($user, $revertedOperations);
                        }
                        $this->db->rollback();
                        return -1;
                }

                $sql = 'DELETE FROM '.MAIN_DB_PREFIX."safra_aplicacao_resource WHERE fk_aplicacao = ".((int) $this->id);
                if (!$this->db->query($sql)) {
                        $this->error = $this->db->lasterror();
                        if (!empty($revertedOperations) && $user instanceof User) {
                                $this->rollbackStockOperations($user, $revertedOperations);
                        }
                        $this->db->rollback();
                        return -1;
                }

                $this->unlinkTask();

                $res = $this->deleteCommon($user, $notrigger);
                if ($res > 0) {
                        $this->db->commit();
                } else {
                        if (!empty($revertedOperations) && $user instanceof User) {
                                $this->rollbackStockOperations($user, $revertedOperations);
                        }
                        $this->db->rollback();
                }

                return $res;
        }

	/**
	 *  Delete a line of object in database
	 *
	 *	@param  User	$user       User that delete
	 *  @param	int		$idline		Id of line to delete
	 *  @param 	bool 	$notrigger  false=launch triggers after, true=disable triggers
	 *  @return int         		>0 if OK, <0 if KO
	 */
	public function deleteLine(User $user, $idline, $notrigger = false)
	{
		if ($this->status < 0) {
			$this->error = 'ErrorDeleteLineNotAllowedByObjectStatus';
			return -2;
		}

		return $this->deleteLineCommon($user, $idline, $notrigger);
	}


	/**
	 *	Validate object
	 *
	 *	@param		User	$user     		User making status change
	 *  @param		int		$notrigger		1=Does not execute triggers, 0= execute triggers
	 *	@return  	int						Return integer <=0 if OK, 0=Nothing done, >0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$error = 0;

		// Protection
		if ($this->status == self::STATUS_VALIDATED) {
			dol_syslog(get_class($this)."::validate action abandonned: already validated", LOG_WARNING);
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('safra', 'aplicacao', 'write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('safra', 'aplicacao_advance', 'validate')))
		 {
		 $this->error='NotEnoughPermissions';
		 dol_syslog(get_class($this)."::valid ".$this->error, LOG_ERR);
		 return -1;
		 }*/

		$now = dol_now();

		$this->db->begin();

		// Define new ref
		if (!$error && (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref))) { // empty should not happened, but when it occurs, the test save life
			$num = $this->getNextNumRef();
		} else {
			$num = $this->ref;
		}
		$this->newref = $num;

		if (!empty($num)) {
			// Validate
			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET ref = '".$this->db->escape($num)."',";
			$sql .= " status = ".self::STATUS_VALIDATED;
			if (!empty($this->fields['date_validation'])) {
				$sql .= ", date_validation = '".$this->db->idate($now)."'";
			}
			if (!empty($this->fields['fk_user_valid'])) {
				$sql .= ", fk_user_valid = ".((int) $user->id);
			}
			$sql .= " WHERE rowid = ".((int) $this->id);

			dol_syslog(get_class($this)."::validate()", LOG_DEBUG);
			$resql = $this->db->query($sql);
			if (!$resql) {
				dol_print_error($this->db);
				$this->error = $this->db->lasterror();
				$error++;
			}

			if (!$error && !$notrigger) {
				// Call trigger
				$result = $this->call_trigger('MYOBJECT_VALIDATE', $user);
				if ($result < 0) {
					$error++;
				}
				// End call triggers
			}
		}

		if (!$error) {
			$this->oldref = $this->ref;

			// Rename directory if dir was a temporary ref
			if (preg_match('/^[\(]?PROV/i', $this->ref)) {
				// Now we rename also files into index
				$sql = 'UPDATE '.MAIN_DB_PREFIX."ecm_files set filename = CONCAT('".$this->db->escape($this->newref)."', SUBSTR(filename, ".(strlen($this->ref) + 1).")), filepath = 'aplicacao/".$this->db->escape($this->newref)."'";
				$sql .= " WHERE filename LIKE '".$this->db->escape($this->ref)."%' AND filepath = 'aplicacao/".$this->db->escape($this->ref)."' and entity = ".$conf->entity;
				$resql = $this->db->query($sql);
				if (!$resql) {
					$error++;
					$this->error = $this->db->lasterror();
				}
				$sql = 'UPDATE '.MAIN_DB_PREFIX."ecm_files set filepath = 'aplicacao/".$this->db->escape($this->newref)."'";
				$sql .= " WHERE filepath = 'aplicacao/".$this->db->escape($this->ref)."' and entity = ".$conf->entity;
				$resql = $this->db->query($sql);
				if (!$resql) {
					$error++;
					$this->error = $this->db->lasterror();
				}

				// We rename directory ($this->ref = old ref, $num = new ref) in order not to lose the attachments
				$oldref = dol_sanitizeFileName($this->ref);
				$newref = dol_sanitizeFileName($num);
				$dirsource = $conf->safra->dir_output.'/aplicacao/'.$oldref;
				$dirdest = $conf->safra->dir_output.'/aplicacao/'.$newref;
				if (!$error && file_exists($dirsource)) {
					dol_syslog(get_class($this)."::validate() rename dir ".$dirsource." into ".$dirdest);

					if (@rename($dirsource, $dirdest)) {
						dol_syslog("Rename ok");
						// Rename docs starting with $oldref with $newref
						$listoffiles = dol_dir_list($conf->safra->dir_output.'/aplicacao/'.$newref, 'files', 1, '^'.preg_quote($oldref, '/'));
						foreach ($listoffiles as $fileentry) {
							$dirsource = $fileentry['name'];
							$dirdest = preg_replace('/^'.preg_quote($oldref, '/').'/', $newref, $dirsource);
							$dirsource = $fileentry['path'].'/'.$dirsource;
							$dirdest = $fileentry['path'].'/'.$dirdest;
							@rename($dirsource, $dirdest);
						}
					}
				}
			}
		}

		// Set new ref and current status
		if (!$error) {
			$this->ref = $num;
			$this->status = self::STATUS_VALIDATED;
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Set draft status
	 *
	 *	@param	User	$user			Object user that modify
	 *  @param	int		$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int						Return integer <0 if KO, >0 if OK
	 */
	public function setDraft($user, $notrigger = 0)
	{
		// Protection
		if ($this->status <= self::STATUS_DRAFT) {
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('safra','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('safra','safra_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		$result = $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'SAFRA_MYOBJECT_UNVALIDATE');
		if ($result > 0) {
			$this->status = self::STATUS_DRAFT;
			$this->syncTask($user);
		}

		return $result;
	}

	/**
	 *	Set cancel status
	 *
	 *	@param	User	$user			Object user that modify
	 *  @param	int		$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int						Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function cancel($user, $notrigger = 0)
	{
		// Protection
		if ($this->status != self::STATUS_VALIDATED) {
			return 0;
		}

		/* if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('safra','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('safra','safra_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		$result = $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, 'SAFRA_MYOBJECT_CANCEL');
		if ($result > 0) {
			$this->status = self::STATUS_CANCELED;
			$this->syncTask($user);
		}

		return $result;
	}

	/**
	 *	Set back to validated status
	 *
	 *	@param	User	$user			Object user that modify
	 *  @param	int		$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int						Return integer <0 if KO, 0=Nothing done, >0 if OK
	 */
	public function reopen($user, $notrigger = 0)
	{
		// Protection
		if ($this->status == self::STATUS_VALIDATED) {
			return 0;
		}

		/*if (! ((!getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('safra','write'))
		 || (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && $user->hasRight('safra','safra_advance','validate'))))
		 {
		 $this->error='Permission denied';
		 return -1;
		 }*/

		$result = $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'SAFRA_MYOBJECT_REOPEN');
		if ($result > 0) {
			$this->status = self::STATUS_VALIDATED;
			$this->syncTask($user);
		}

		return $result;
	}

	/**
	 * getTooltipContentArray
	 *
	 * @param 	array 	$params 	Params to construct tooltip data
	 * @since 	v18
	 * @return 	array
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;

		$datas = [];

		if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
			return ['optimize' => $langs->trans("ShowAplicacao")];
		}
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("Aplicacao").'</u>';
		if (isset($this->status)) {
			$datas['picto'] .= ' '.$this->getLibStatut(5);
		}
		if (property_exists($this, 'ref')) {
			$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
		}
		if (property_exists($this, 'label')) {
			$datas['ref'] = '<br>'.$langs->trans('Label').':</b> '.$this->label;
		}

		return $datas;
	}

	/**
	 *  Return a link to the object card (with optionaly the picto)
	 *
	 *  @param  int     $withpicto                  Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 *  @param  string  $option                     On what the link point to ('nolink', ...)
	 *  @param  int     $notooltip                  1=Disable tooltip
	 *  @param  string  $morecss                    Add more css on link
	 *  @param  int     $save_lastsearch_value      -1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *  @return	string                              String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs, $hookmanager;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1; // Force disable tooltips
		}

		$result = '';
		$params = [
			'id' => $this->id,
			'objecttype' => $this->element.($this->module ? '@'.$this->module : ''),
			'option' => $option,
		];
		$classfortooltip = 'classfortooltip';
		$dataparams = '';
		if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
			$classfortooltip = 'classforajaxtooltip';
			$dataparams = ' data-params="'.dol_escape_htmltag(json_encode($params)).'"';
			$label = '';
		} else {
			$label = implode($this->getTooltipContentArray($params));
		}

		$url = dol_buildpath('/safra/aplicacao_card.php', 1).'?id='.$this->id;

		if ($option !== 'nolink') {
			// Add param to save lastsearch_values or not
			$add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
			if ($save_lastsearch_value == -1 && isset($_SERVER["PHP_SELF"]) && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
				$add_save_lastsearch_values = 1;
			}
			if ($url && $add_save_lastsearch_values) {
				$url .= '&save_lastsearch_values=1';
			}
		}

		$linkclose = '';
		if (empty($notooltip)) {
			if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
				$label = $langs->trans("ShowAplicacao");
				$linkclose .= ' alt="'.dol_escape_htmltag($label, 1).'"';
			}
			$linkclose .= ($label ? ' title="'.dol_escape_htmltag($label, 1).'"' : ' title="tocomplete"');
			$linkclose .= $dataparams.' class="'.$classfortooltip.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
		}

		if ($option == 'nolink' || empty($url)) {
			$linkstart = '<span';
		} else {
			$linkstart = '<a href="'.$url.'"';
		}
		$linkstart .= $linkclose.'>';
		if ($option == 'nolink' || empty($url)) {
			$linkend = '</span>';
		} else {
			$linkend = '</a>';
		}

		$result .= $linkstart;

		if (empty($this->showphoto_on_popup)) {
			if ($withpicto) {
				$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
			}
		} else {
			if ($withpicto) {
				require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

				list($class, $module) = explode('@', $this->picto);
				$upload_dir = $conf->$module->multidir_output[$conf->entity]."/$class/".dol_sanitizeFileName($this->ref);
				$filearray = dol_dir_list($upload_dir, "files");
				$filename = $filearray[0]['name'];
				if (!empty($filename)) {
					$pospoint = strpos($filearray[0]['name'], '.');

					$pathtophoto = $class.'/'.$this->ref.'/thumbs/'.substr($filename, 0, $pospoint).'_mini'.substr($filename, $pospoint);
					if (!getDolGlobalString(strtoupper($module.'_'.$class).'_FORMATLISTPHOTOSASUSERS')) {
						$result .= '<div class="floatleft inline-block valignmiddle divphotoref"><div class="photoref"><img class="photo'.$module.'" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div></div>';
					} else {
						$result .= '<div class="floatleft inline-block valignmiddle divphotoref"><img class="photouserphoto userphoto" alt="No photo" border="0" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart='.$module.'&entity='.$conf->entity.'&file='.urlencode($pathtophoto).'"></div>';
					}

					$result .= '</div>';
				} else {
					$result .= img_object(($notooltip ? '' : $label), ($this->picto ? $this->picto : 'generic'), ($notooltip ? (($withpicto != 2) ? 'class="paddingright"' : '') : 'class="'.(($withpicto != 2) ? 'paddingright ' : '').'"'), 0, 0, $notooltip ? 0 : 1);
				}
			}
		}

		if ($withpicto != 2) {
			$result .= $this->ref;
		}

		$result .= $linkend;
		//if ($withpicto != 2) $result.=(($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');

		global $action, $hookmanager;
		$hookmanager->initHooks(array($this->element.'dao'));
		$parameters = array('id' => $this->id, 'getnomurl' => &$result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) {
			$result = $hookmanager->resPrint;
		} else {
			$result .= $hookmanager->resPrint;
		}

		return $result;
	}

	/**
	 *	Return a thumb for kanban views
	 *
	 *	@param      string	    $option                 Where point the link (0=> main card, 1,2 => shipment, 'nolink'=>No link)
	 *  @param		array		$arraydata				Array of data
	 *  @return		string								HTML Code for Kanban thumb.
	 */
	public function getKanbanView($option = '', $arraydata = null)
	{
		global $conf, $langs;

		$selected = (empty($arraydata['selected']) ? 0 : $arraydata['selected']);

		$return = '<div class="box-flex-item box-flex-grow-zero">';
		$return .= '<div class="info-box info-box-sm">';
		$return .= '<span class="info-box-icon bg-infobox-action">';
		$return .= img_picto('', $this->picto);
		$return .= '</span>';
		$return .= '<div class="info-box-content">';
		$return .= '<span class="info-box-ref inline-block tdoverflowmax150 valignmiddle">'.(method_exists($this, 'getNomUrl') ? $this->getNomUrl() : $this->ref).'</span>';
		if ($selected >= 0) {
			$return .= '<input id="cb'.$this->id.'" class="flat checkforselect fright" type="checkbox" name="toselect[]" value="'.$this->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		if (property_exists($this, 'label')) {
			$return .= ' <div class="inline-block opacitymedium valignmiddle tdoverflowmax100">'.$this->label.'</div>';
		}
		if (property_exists($this, 'thirdparty') && is_object($this->thirdparty)) {
			$return .= '<br><div class="info-box-ref tdoverflowmax150">'.$this->thirdparty->getNomUrl(1).'</div>';
		}
		if (property_exists($this, 'amount')) {
			$return .= '<br>';
			$return .= '<span class="info-box-label amount">'.price($this->amount, 0, $langs, 1, -1, -1, $conf->currency).'</span>';
		}
		if (method_exists($this, 'getLibStatut')) {
			$return .= '<br><div class="info-box-status">'.$this->getLibStatut(3).'</div>';
		}
		$return .= '</div>';
		$return .= '</div>';
		$return .= '</div>';

		return $return;
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 			       Label of status
	 */
	public function getLabelStatus($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 			       Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return the label of a given status
	 *
	 *  @param	int		$status        Id status
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 			       Label of status
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		if (is_null($status)) {
			return '';
		}

		if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
			global $langs;
			//$langs->load("safra@safra");
			$this->labelStatus[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('Draft');
			$this->labelStatus[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Enabled');
			$this->labelStatus[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('Disabled');
			$this->labelStatusShort[self::STATUS_DRAFT] = $langs->transnoentitiesnoconv('Draft');
			$this->labelStatusShort[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Enabled');
			$this->labelStatusShort[self::STATUS_CANCELED] = $langs->transnoentitiesnoconv('Disabled');
		}

		$statusType = 'status'.$status;
		//if ($status == self::STATUS_VALIDATED) $statusType = 'status1';
		if ($status == self::STATUS_CANCELED) {
			$statusType = 'status6';
		}

		return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
	}

	/**
	 *	Load the info information in the object
	 *
	 *	@param  int		$id       Id of object
	 *	@return	void
	 */
	public function info($id)
	{
		$sql = "SELECT rowid,";
		$sql .= " date_creation as datec, tms as datem";
		if (!empty($this->fields['date_validation'])) {
			$sql .= ", date_validation as datev";
		}
		if (!empty($this->fields['fk_user_creat'])) {
			$sql .= ", fk_user_creat";
		}
		if (!empty($this->fields['fk_user_modif'])) {
			$sql .= ", fk_user_modif";
		}
		if (!empty($this->fields['fk_user_valid'])) {
			$sql .= ", fk_user_valid";
		}
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
		$sql .= " WHERE t.rowid = ".((int) $id);

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);

				$this->id = $obj->rowid;

				if (!empty($this->fields['fk_user_creat'])) {
					$this->user_creation_id = $obj->fk_user_creat;
				}
				if (!empty($this->fields['fk_user_modif'])) {
					$this->user_modification_id = $obj->fk_user_modif;
				}
				if (!empty($this->fields['fk_user_valid'])) {
					$this->user_validation_id = $obj->fk_user_valid;
				}
				$this->date_creation     = $this->db->jdate($obj->datec);
				$this->date_modification = empty($obj->datem) ? '' : $this->db->jdate($obj->datem);
				if (!empty($obj->datev)) {
					$this->date_validation   = empty($obj->datev) ? '' : $this->db->jdate($obj->datev);
				}
			}

			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}

	/**
	 * Initialise object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		// Set here init that are not commonf fields
		// $this->property1 = ...
		// $this->property2 = ...

		$this->initAsSpecimenCommon();
	}

	/**
	 * 	Create an array of lines
	 *
	 * 	@return array|int		array of lines if OK, <0 if KO
	 */
	public function getLinesArray()
	{
		$this->lines = array();

		$objectline = new AplicacaoLine($this->db);
		$result = $objectline->fetchAll('ASC', 'position', 0, 0, array('customsql'=>'fk_aplicacao = '.((int) $this->id)));

		if (is_numeric($result)) {
			$this->setErrorsFromObject($objectline);
			return $result;
		} else {
			$this->lines = $result;
			return $this->lines;
		}
	}

	/**
	 *  Returns the reference to the following non used object depending on the active numbering module.
	 *
	 *  @return string      		Object free reference
	 */
	public function getNextNumRef()
	{
		global $langs, $conf;
		$langs->load("safra@safra");

		if (!getDolGlobalString('SAFRA_MYOBJECT_ADDON')) {
			$conf->global->SAFRA_MYOBJECT_ADDON = 'mod_aplicacao_standard';
		}

		if (getDolGlobalString('SAFRA_MYOBJECT_ADDON')) {
			$mybool = false;

			$file = getDolGlobalString('SAFRA_MYOBJECT_ADDON').".php";
			$classname = getDolGlobalString('SAFRA_MYOBJECT_ADDON');

			// Include file with class
			$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
			foreach ($dirmodels as $reldir) {
				$dir = dol_buildpath($reldir."core/modules/safra/");

				// Load file with numbering class (if found)
				$mybool |= @include_once $dir.$file;
			}

			if ($mybool === false) {
				dol_print_error('', "Failed to include file ".$file);
				return '';
			}

			if (class_exists($classname)) {
				$obj = new $classname();
				$numref = $obj->getNextValue($this);

				if ($numref != '' && $numref != '-1') {
					return $numref;
				} else {
					$this->error = $obj->error;
					//dol_print_error($this->db,get_class($this)."::getNextNumRef ".$obj->error);
					return "";
				}
			} else {
				print $langs->trans("Error")." ".$langs->trans("ClassNotFound").' '.$classname;
				return "";
			}
		} else {
			print $langs->trans("ErrorNumberingModuleNotSetup", $this->element);
			return "";
		}
	}

	/**
	 *  Create a document onto disk according to template module.
	 *
	 *  @param	    string		$modele			Force template to use ('' to not force)
	 *  @param		Translate	$outputlangs	objet lang a utiliser pour traduction
	 *  @param      int			$hidedetails    Hide details of lines
	 *  @param      int			$hidedesc       Hide description
	 *  @param      int			$hideref        Hide ref
	 *  @param      null|array  $moreparams     Array to provide more information
	 *  @return     int         				0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $conf, $langs;

		$result = 0;
		$includedocgeneration = 0;

		$langs->load("safra@safra");

		if (!dol_strlen($modele)) {
			$modele = 'standard_aplicacao';

			if (!empty($this->model_pdf)) {
				$modele = $this->model_pdf;
			} elseif (getDolGlobalString('MYOBJECT_ADDON_PDF')) {
				$modele = getDolGlobalString('MYOBJECT_ADDON_PDF');
			}
		}

		$modelpath = "core/modules/safra/doc/";

		if ($includedocgeneration && !empty($modele)) {
			$result = $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
		}

		return $result;
	}

	/**
	 * Action executed by scheduler
	 * CAN BE A CRON TASK. In such a case, parameters come from the schedule job setup field 'Parameters'
	 * Use public function doScheduledJob($param1, $param2, ...) to get parameters
	 *
	 * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function doScheduledJob()
	{
		//global $conf, $langs;

		//$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_mydedicatedlogfile.log';

		$error = 0;
		$this->output = '';
		$this->error = '';

		dol_syslog(__METHOD__." start", LOG_INFO);

		$now = dol_now();

		$this->db->begin();

		// ...

		$this->db->commit();

		dol_syslog(__METHOD__." end", LOG_INFO);

		return $error;
	}
}


require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

/**
 * Class AplicacaoLine. You can also remove this and generate a CRUD class for lines objects.
 */
class AplicacaoLine extends CommonObjectLine
{
	// To complete with content of an object AplicacaoLine
	// We should have a field rowid, fk_aplicacao and position

	/**
	 * @var int  Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}
}
