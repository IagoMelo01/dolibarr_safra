<?php
/*
 * Copyright (C) 2025 SuperAdmin
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

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class representing formulated products.
 */
class SafraProdutoFormulado extends CommonObject
{
    /** @var string */
    public $module = 'safra';

    /** @var string */
    public $element = 'safra_produto_formulado';

    /** @var string */
    public $table_element = 'safra_produto_formulado';

    /** @var string */
    public $picto = 'fa-flask';

    /** @var int */
    public $ismultientitymanaged = 0;

    /**
     * Object fields definition.
     *
     * @var array
     */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'index' => 1, 'noteditable' => 1),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1),
        'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth200'),
        'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 3),
        'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(
            self::STATUS_DISABLED => 'Disabled',
            self::STATUS_ACTIVE => 'Active',
        )),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
        'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
        'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
    );

    public $rowid;
    public $ref;
    public $label;
    public $description;
    public $status = self::STATUS_ACTIVE;
    public $date_creation;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;

    public const STATUS_DISABLED = 0;
    public const STATUS_ACTIVE = 1;

    /**
     * Cached culture code field name.
     *
     * @var string|null
     */
    private $cultureCodeField;

    /**
     * Track schema availability to avoid redundant checks.
     *
     * @var bool
     */
    private static $schemaReady = false;

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Ensure database schema exists for formulated products and their links.
     *
     * @param DoliDB $db Database handler
     *
     * @return bool True when schema is ready, false on failure
     */
    public static function ensureDatabaseSchema(DoliDB $db)
    {
        if (self::$schemaReady) {
            return true;
        }

        $prefix = MAIN_DB_PREFIX;
        $queries = array(
            'CREATE TABLE IF NOT EXISTS '.$prefix."safra_produto_formulado (\n"
            ."  rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
            ."  ref VARCHAR(128) NOT NULL,\n"
            ."  label VARCHAR(255) NOT NULL,\n"
            ."  description TEXT NULL,\n"
            ."  status TINYINT NOT NULL DEFAULT 1,\n"
            ."  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            ."  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            ."  fk_user_creat INT NOT NULL,\n"
            ."  fk_user_modif INT NULL,\n"
            ."  UNIQUE KEY uk_safra_pf_ref (ref)\n"
            .') ENGINE=innodb',
            'CREATE TABLE IF NOT EXISTS '.$prefix."safra_cultura (\n"
            ."  rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
            ."  code VARCHAR(64) NOT NULL,\n"
            ."  label VARCHAR(255) NOT NULL,\n"
            ."  UNIQUE KEY uk_safra_cultura_code (code)\n"
            .') ENGINE=innodb',
            'CREATE TABLE IF NOT EXISTS '.$prefix."safra_produto_cultura (\n"
            ."  rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
            ."  fk_produto INT NOT NULL,\n"
            ."  fk_cultura INT NOT NULL,\n"
            ."  dose_label VARCHAR(128) NULL,\n"
            ."  observacao VARCHAR(255) NULL,\n"
            ."  UNIQUE KEY uk_pf_cultura (fk_produto, fk_cultura),\n"
            ."  INDEX idx_pf (fk_produto),\n"
            ."  INDEX idx_cult (fk_cultura),\n"
            ."  CONSTRAINT fk_spc_prod FOREIGN KEY (fk_produto) REFERENCES ".$prefix."safra_produto_formulado(rowid) ON DELETE CASCADE,\n"
            ."  CONSTRAINT fk_spc_cult FOREIGN KEY (fk_cultura) REFERENCES ".$prefix."safra_cultura(rowid) ON DELETE CASCADE\n"
            .') ENGINE=innodb',
            'CREATE TABLE IF NOT EXISTS '.$prefix."safra_produto_praga (\n"
            ."  rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
            ."  fk_produto INT NOT NULL,\n"
            ."  fk_praga INT NOT NULL,\n"
            ."  observacao VARCHAR(255) NULL,\n"
            ."  UNIQUE KEY uk_pf_praga (fk_produto, fk_praga),\n"
            ."  INDEX idx_pf (fk_produto),\n"
            ."  INDEX idx_pg (fk_praga),\n"
            ."  CONSTRAINT fk_spp_prod FOREIGN KEY (fk_produto) REFERENCES ".$prefix."safra_produto_formulado(rowid) ON DELETE CASCADE,\n"
            ."  CONSTRAINT fk_spp_prag FOREIGN KEY (fk_praga) REFERENCES ".$prefix."safra_pragas(rowid) ON DELETE CASCADE\n"
            .') ENGINE=innodb',
        );

        foreach ($queries as $sql) {
            if (!$db->query($sql)) {
                dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
                return false;
            }
        }

        self::$schemaReady = true;

        return true;
    }

    /**
     * Load object from database.
     *
     * @param int         $id  Id of object
     * @param string|null $ref Ref of object
     *
     * @return int
     */
    public function fetch($id, $ref = null)
    {
        return $this->fetchCommon($id, $ref);
    }

    /**
     * Create object in database.
     *
     * @param User $user User creating
     * @param int  $notrigger Do not trigger
     *
     * @return int
     */
    public function create(User $user, $notrigger = 0)
    {
        if (empty($this->status)) {
            $this->status = self::STATUS_ACTIVE;
        }

        if (empty($this->date_creation)) {
            $this->date_creation = dol_now();
        }

        $this->fk_user_creat = $user->id;

        return $this->createCommon($user, $notrigger);
    }

    /**
     * Update object.
     *
     * @param User $user User updating
     * @param int  $notrigger Do not trigger
     *
     * @return int
     */
    public function update(User $user, $notrigger = 0)
    {
        $this->fk_user_modif = $user->id;

        return $this->updateCommon($user, $notrigger);
    }

    /**
     * Delete object.
     *
     * @param User $user User deleting
     * @param int  $notrigger Do not trigger
     *
     * @return int
     */
    public function delete(User $user, $notrigger = 0)
    {
        return $this->deleteCommon($user, $notrigger);
    }

    /**
     * Add culture relation.
     *
     * @param int         $cultureId  Culture identifier
     * @param string|null $doseLabel  Dose label
     * @param string|null $observacao Observation
     *
     * @return int
     */
    public function addCulture($cultureId, $doseLabel = null, $observacao = null)
    {
        $sql = 'INSERT IGNORE INTO '.MAIN_DB_PREFIX."safra_produto_cultura (fk_produto, fk_cultura, dose_label, observacao) VALUES (";
        $sql .= (int) $this->id.', '.(int) $cultureId.', ';
        $sql .= $doseLabel !== null ? "'".$this->db->escape($doseLabel)."'" : 'NULL';
        $sql .= ', ';
        $sql .= $observacao !== null ? "'".$this->db->escape($observacao)."'" : 'NULL';
        $sql .= ')';

        dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);

        if ($this->db->query($sql)) {
            return 1;
        }

        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);

        return -1;
    }

    /**
     * Remove culture relation.
     *
     * @param int $cultureId Culture identifier
     *
     * @return int
     */
    public function removeCulture($cultureId)
    {
        $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'safra_produto_cultura WHERE fk_produto='.(int) $this->id.' AND fk_cultura='.(int) $cultureId;
        dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);

        if ($this->db->query($sql)) {
            return 1;
        }

        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);

        return -1;
    }

    /**
     * Fetch related cultures.
     *
     * @return array
     */
    public function fetchCultures()
    {
        $result = array();
        $codeField = $this->getCultureCodeField();
        $sql = 'SELECT pc.rowid, pc.fk_cultura, pc.dose_label, pc.observacao, c.rowid as cultura_id, ';
        $sql .= 'c.'.$codeField.' as code, c.label ';
        $sql .= 'FROM '.MAIN_DB_PREFIX.'safra_produto_cultura as pc ';
        $sql .= 'INNER JOIN '.MAIN_DB_PREFIX.'safra_cultura as c ON c.rowid = pc.fk_cultura ';
        $sql .= 'WHERE pc.fk_produto = '.(int) $this->id.' ORDER BY c.label';

        dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
            return $result;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $result[] = $obj;
        }

        $this->db->free($resql);

        return $result;
    }

    /**
     * Detect culture code field.
     *
     * @return string
     */
    private function getCultureCodeField()
    {
        if ($this->cultureCodeField !== null) {
            return $this->cultureCodeField;
        }

        $this->cultureCodeField = 'ref';
        $sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."safra_cultura LIKE 'code'";
        $res = $this->db->query($sql);
        if ($res) {
            if ($this->db->num_rows($res) > 0) {
                $this->cultureCodeField = 'code';
            }
            $this->db->free($res);
        }

        return $this->cultureCodeField;
    }

    /**
     * Add praga relation.
     *
     * @param int         $pragaId    Pest identifier
     * @param string|null $observacao Observation
     *
     * @return int
     */
    public function addPraga($pragaId, $observacao = null)
    {
        $sql = 'INSERT IGNORE INTO '.MAIN_DB_PREFIX."safra_produto_praga (fk_produto, fk_praga, observacao) VALUES (";
        $sql .= (int) $this->id.', '.(int) $pragaId.', ';
        $sql .= $observacao !== null ? "'".$this->db->escape($observacao)."'" : 'NULL';
        $sql .= ')';

        dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);

        if ($this->db->query($sql)) {
            return 1;
        }

        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);

        return -1;
    }

    /**
     * Remove praga relation.
     *
     * @param int $pragaId Pest identifier
     *
     * @return int
     */
    public function removePraga($pragaId)
    {
        $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'safra_produto_praga WHERE fk_produto='.(int) $this->id.' AND fk_praga='.(int) $pragaId;
        dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);

        if ($this->db->query($sql)) {
            return 1;
        }

        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);

        return -1;
    }

    /**
     * Fetch related pests.
     *
     * @return array
     */
    public function fetchPragas()
    {
        $result = array();
        $sql = 'SELECT pp.rowid, pp.fk_praga, pp.observacao, p.rowid as praga_id, p.ref, p.label, p.label_cientifico ';
        $sql .= 'FROM '.MAIN_DB_PREFIX.'safra_produto_praga as pp ';
        $sql .= 'INNER JOIN '.MAIN_DB_PREFIX.'safra_pragas as p ON p.rowid = pp.fk_praga ';
        $sql .= 'WHERE pp.fk_produto = '.(int) $this->id.' ORDER BY p.label';

        dol_syslog(__METHOD__." sql=".$sql, LOG_DEBUG);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
            return $result;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $result[] = $obj;
        }

        $this->db->free($resql);

        return $result;
    }

    /**
     * Return status label.
     *
     * @param int $mode Output mode
     *
     * @return string
     */
    public function getLibStatut($mode = 0)
    {
        return self::LibStatut($this->status, $mode);
    }

    /**
     * Build status label.
     *
     * @param int $status Status value
     * @param int $mode   Output mode
     *
     * @return string
     */
    public static function LibStatut($status, $mode = 0)
    {
        global $langs;

        $langs->load('safra@safra');

        $labelStatus = array(
            self::STATUS_DISABLED => $langs->trans('ProdutoFormuladoStatusDisabled'),
            self::STATUS_ACTIVE => $langs->trans('ProdutoFormuladoStatusActive'),
        );

        $labelStatusShort = array(
            self::STATUS_DISABLED => $langs->trans('ProdutoFormuladoStatusDisabledShort'),
            self::STATUS_ACTIVE => $langs->trans('ProdutoFormuladoStatusActiveShort'),
        );

        $statusType = array(
            self::STATUS_DISABLED => 'status5',
            self::STATUS_ACTIVE => 'status4',
        );

        if (!isset($labelStatus[$status])) {
            $status = self::STATUS_DISABLED;
        }

        return dolGetStatus($labelStatus[$status], $labelStatusShort[$status], '', $statusType, $mode);
    }

    /**
     * Build URL with optional picto.
     *
     * @param int    $withpicto Add picto
     * @param string $option    Additional option
     *
     * @return string
     */
    public function getNomUrl($withpicto = 0, $option = '')
    {
        $label = dol_escape_htmltag($this->label);
        $url = dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $this->id;

        $result = '<a href="'.$url.'">';
        if ($withpicto) {
            $result .= img_object($label, $this->picto).' ';
        }
        $result .= dol_escape_htmltag($this->ref);
        $result .= '</a>';

        return $result;
    }
}
