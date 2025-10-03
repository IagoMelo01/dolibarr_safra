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

/**
 * Helper responsible for linking Dolibarr products with Safra entities.
 */
class SafraProductLink
{
    /**
     * Track schema initialization to avoid running the creation queries repeatedly.
     *
     * @var bool
     */
    private static $schemaReady = false;

    /**
     * Ensure auxiliary tables required to link products with Safra entities exist.
     *
     * @param DoliDB $db Database handler
     *
     * @return bool True when schema is ready, false on failure
     */
    public static function ensureSchema(DoliDB $db)
    {
        if (self::$schemaReady) {
            return true;
        }

        $prefix = MAIN_DB_PREFIX;
        $queries = array(
            'CREATE TABLE IF NOT EXISTS '.$prefix."safra_product_formulado (\n"
            ."  rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
            ."  fk_product INT NOT NULL,\n"
            ."  fk_produto_formulado INT NOT NULL,\n"
            ."  UNIQUE KEY uk_safra_pf (fk_product, fk_produto_formulado),\n"
            ."  INDEX idx_safra_pf_prod (fk_product),\n"
            ."  INDEX idx_safra_pf_form (fk_produto_formulado),\n"
            ."  CONSTRAINT fk_safra_pf_product FOREIGN KEY (fk_product) REFERENCES ".$prefix."product(rowid) ON DELETE CASCADE,\n"
            ."  CONSTRAINT fk_safra_pf_form FOREIGN KEY (fk_produto_formulado) REFERENCES ".$prefix."safra_produto_formulado(rowid) ON DELETE CASCADE\n"
            .') ENGINE=innodb',
            'CREATE TABLE IF NOT EXISTS '.$prefix."safra_product_tecnico (\n"
            ."  rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
            ."  fk_product INT NOT NULL,\n"
            ."  fk_produto_tecnico INT NOT NULL,\n"
            ."  UNIQUE KEY uk_safra_pt (fk_product, fk_produto_tecnico),\n"
            ."  INDEX idx_safra_pt_prod (fk_product),\n"
            ."  INDEX idx_safra_pt_tecnico (fk_produto_tecnico),\n"
            ."  CONSTRAINT fk_safra_pt_product FOREIGN KEY (fk_product) REFERENCES ".$prefix."product(rowid) ON DELETE CASCADE,\n"
            ."  CONSTRAINT fk_safra_pt_tecnico FOREIGN KEY (fk_produto_tecnico) REFERENCES ".$prefix."safra_produtostecnicos(rowid) ON DELETE CASCADE\n"
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
     * Replace product links for a given type.
     *
     * @param DoliDB $db        Database handler
     * @param int    $productId Product identifier
     * @param string $type      Either 'formulados' or 'tecnicos'
     * @param int[]  $ids       Target Safra identifiers
     *
     * @return bool
     */
    public static function replaceProductLinks(DoliDB $db, $productId, $type, array $ids)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return false;
        }

        if (!self::ensureSchema($db)) {
            return false;
        }

        $mapping = self::getTypeMapping($type);
        if (!$mapping) {
            return false;
        }

        $db->begin();

        $sql = 'DELETE FROM '.MAIN_DB_PREFIX.$mapping['table'].' WHERE fk_product='.(int) $productId;
        if (!$db->query($sql)) {
            $db->rollback();
            dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
            return false;
        }

        if (!empty($ids)) {
            $sql = 'INSERT INTO '.MAIN_DB_PREFIX.$mapping['table'].' (fk_product, '.$mapping['column'].') VALUES ';
            $values = array();
            foreach ($ids as $id) {
                $values[] = '('.(int) $productId.', '.(int) $id.')';
            }
            $sql .= implode(',', $values);

            if (!$db->query($sql)) {
                $db->rollback();
                dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
                return false;
            }
        }

        $db->commit();

        return true;
    }

    /**
     * Fetch identifiers linked to a product.
     *
     * @param DoliDB $db        Database handler
     * @param int    $productId Product identifier
     * @param string $type      Either 'formulados' or 'tecnicos'
     *
     * @return int[]
     */
    public static function fetchLinkedIds(DoliDB $db, $productId, $type)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return array();
        }

        if (!self::ensureSchema($db)) {
            return array();
        }

        $mapping = self::getTypeMapping($type);
        if (!$mapping) {
            return array();
        }

        $sql = 'SELECT '.$mapping['column'].' AS target_id FROM '.MAIN_DB_PREFIX.$mapping['table'].' WHERE fk_product='.(int) $productId;
        $resql = $db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
            return array();
        }

        $ids = array();
        while ($obj = $db->fetch_object($resql)) {
            $ids[] = (int) $obj->target_id;
        }
        $db->free($resql);

        return $ids;
    }

    /**
     * Retrieve available Safra entries to be displayed in a selector.
     *
     * @param DoliDB $db   Database handler
     * @param string $type Either 'formulados' or 'tecnicos'
     *
     * @return array<int, string>
     */
    public static function fetchOptions(DoliDB $db, $type)
    {
        if (!self::ensureSchema($db)) {
            return array();
        }

        if ($type === 'formulados') {
            $sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'safra_produto_formulado ORDER BY label ASC';
        } elseif ($type === 'tecnicos') {
            $sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'safra_produtostecnicos WHERE status <> 9 ORDER BY label ASC';
        } else {
            return array();
        }

        $resql = $db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
            return array();
        }

        $options = array();
        while ($obj = $db->fetch_object($resql)) {
            $label = $obj->label;
            if (!empty($obj->ref)) {
                $label = $obj->ref.' - '.$label;
            }
            $options[(int) $obj->rowid] = $label;
        }
        $db->free($resql);

        return $options;
    }

    /**
     * Get SQL mapping metadata for a supported relation type.
     *
     * @param string $type Relation type identifier
     *
     * @return array<string, string>|null
     */
    private static function getTypeMapping($type)
    {
        $mapping = array(
            'formulados' => array(
                'table' => 'safra_product_formulado',
                'column' => 'fk_produto_formulado',
            ),
            'tecnicos' => array(
                'table' => 'safra_product_tecnico',
                'column' => 'fk_produto_tecnico',
            ),
        );

        return isset($mapping[$type]) ? $mapping[$type] : null;
    }
}
