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
 * Lightweight DAO for cultures.
 */
class SafraCultura extends CommonObject
{
    /** @var string */
    public $module = 'safra';

    /** @var string */
    public $element = 'safra_cultura';

    /** @var string */
    public $table_element = 'safra_cultura';

    /** @var array */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'visible' => 0),
        'code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'visible' => 1),
        'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'visible' => 1),
    );

    /**
     * Cached column name for culture code.
     *
     * @var string|null
     */
    private $codeField;

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
     * Fetch record by id.
     *
     * @param int $id Identifier
     *
     * @return int
     */
    public function fetch($id, $ref = null)
    {
        return $this->fetchCommon($id, $ref);
    }

    /**
     * Fetch list of cultures.
     *
     * @param string $search Search fragment
     * @param array  $excludeIds IDs to exclude
     *
     * @return array
     */
    public function fetchAllForSelect($search = '', array $excludeIds = array())
    {
        $codeField = $this->getCodeField();
        $sql = 'SELECT rowid, '.$codeField.' AS code, label FROM '.MAIN_DB_PREFIX.'safra_cultura';
        $conditions = array();
        if ($search !== '') {
            $escaped = $this->db->escape($search);
            $conditions[] = "(label LIKE '%".$escaped."%' OR ".$codeField." LIKE '%".$escaped."%')";
        }
        if (!empty($excludeIds)) {
            $exclude = array_map('intval', $excludeIds);
            $conditions[] = 'rowid NOT IN ('.implode(',', $exclude).')';
        }
        if ($conditions) {
            $sql .= ' WHERE '.implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY label';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
            return array();
        }

        $rows = array();
        while ($obj = $this->db->fetch_object($resql)) {
            $rows[] = $obj;
        }
        $this->db->free($resql);

        return $rows;
    }

    /**
     * Resolve culture code field.
     *
     * @return string
     */
    private function getCodeField()
    {
        if ($this->codeField !== null) {
            return $this->codeField;
        }

        $this->codeField = 'ref';
        $sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."safra_cultura LIKE 'code'";
        $res = $this->db->query($sql);
        if ($res) {
            if ($this->db->num_rows($res) > 0) {
                $this->codeField = 'code';
            }
            $this->db->free($res);
        }

        return $this->codeField;
    }
}
