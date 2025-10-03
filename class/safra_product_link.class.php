<?php
/*
 * Copyright (C) 2025
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

/**
 * Manage links between Dolibarr products and Safra catalog objects.
 */
class SafraProductLink
{
    public const TYPE_FORMULADO = 'formulado';
    public const TYPE_TECNICO = 'tecnico';

    /**
     * Ensure linking tables exist.
     *
     * @param DoliDB $db Database handler
     *
     * @return bool
     */
    public static function ensureDatabaseSchema(DoliDB $db)
    {
        $prefix = MAIN_DB_PREFIX;

        $queries = array(
            'CREATE TABLE IF NOT EXISTS '.$prefix."safra_product_formulado (\n"
            ."  rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
            ."  fk_product INT NOT NULL,\n"
            ."  fk_produto_formulado INT NOT NULL,\n"
            ."  date_link DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            ."  UNIQUE KEY uk_product_formulado (fk_product, fk_produto_formulado),\n"
            ."  INDEX idx_spf_product (fk_product),\n"
            ."  INDEX idx_spf_formulado (fk_produto_formulado)\n"
            .') ENGINE=innodb',
            'CREATE TABLE IF NOT EXISTS '.$prefix."safra_product_produtostecnico (\n"
            ."  rowid INT AUTO_INCREMENT PRIMARY KEY,\n"
            ."  fk_product INT NOT NULL,\n"
            ."  fk_produtotecnico INT NOT NULL,\n"
            ."  date_link DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            ."  UNIQUE KEY uk_product_tecnico (fk_product, fk_produtotecnico),\n"
            ."  INDEX idx_spt_product (fk_product),\n"
            ."  INDEX idx_spt_tecnico (fk_produtotecnico)\n"
            .') ENGINE=innodb',
        );

        foreach ($queries as $sql) {
            if (!$db->query($sql)) {
                dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
                return false;
            }
        }

        return true;
    }

    /**
     * Fetch linked objects for a product.
     *
     * @param DoliDB $db
     * @param int    $productId
     * @param string $type
     *
     * @return array
     */
    public static function fetchLinks(DoliDB $db, $productId, $type)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return array();
        }

        if ($type === self::TYPE_FORMULADO) {
            $sql = 'SELECT l.rowid, l.fk_produto_formulado AS target_id, pf.ref, pf.label'
                .' FROM '.MAIN_DB_PREFIX.'safra_product_formulado AS l'
                .' INNER JOIN '.MAIN_DB_PREFIX.'safra_produto_formulado AS pf ON pf.rowid = l.fk_produto_formulado'
                .' WHERE l.fk_product = '.$productId
                .' ORDER BY pf.ref ASC';
        } elseif ($type === self::TYPE_TECNICO) {
            $sql = 'SELECT l.rowid, l.fk_produtotecnico AS target_id, pt.ref, pt.label'
                .' FROM '.MAIN_DB_PREFIX.'safra_product_produtostecnico AS l'
                .' INNER JOIN '.MAIN_DB_PREFIX.'safra_produtostecnicos AS pt ON pt.rowid = l.fk_produtotecnico'
                .' WHERE l.fk_product = '.$productId
                .' ORDER BY pt.ref ASC';
        } else {
            return array();
        }

        $links = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetchObject($resql)) {
                $links[] = $obj;
            }
            $db->free($resql);
        }

        return $links;
    }

    /**
     * Get IDs of linked targets.
     *
     * @param DoliDB $db
     * @param int    $productId
     * @param string $type
     *
     * @return int[]
     */
    public static function fetchLinkedIds(DoliDB $db, $productId, $type)
    {
        $links = self::fetchLinks($db, $productId, $type);
        $ids = array();
        foreach ($links as $link) {
            $ids[] = (int) $link->target_id;
        }

        return $ids;
    }

    /**
     * Replace current links for a type with provided IDs.
     *
     * @param DoliDB $db
     * @param int    $productId
     * @param string $type
     * @param int[]  $ids
     *
     * @return bool
     */
    public static function replaceLinks(DoliDB $db, $productId, $type, array $ids)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return false;
        }

        if ($type === self::TYPE_FORMULADO) {
            $table = MAIN_DB_PREFIX.'safra_product_formulado';
            $field = 'fk_produto_formulado';
        } elseif ($type === self::TYPE_TECNICO) {
            $table = MAIN_DB_PREFIX.'safra_product_produtostecnico';
            $field = 'fk_produtotecnico';
        } else {
            return false;
        }

        $db->begin();
        $sql = 'DELETE FROM '.$table.' WHERE fk_product = '.$productId;
        if (!$db->query($sql)) {
            $db->rollback();
            dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
            return false;
        }

        $cleanIds = array();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $cleanIds[$id] = $id;
            }
        }

        if (!empty($cleanIds)) {
            foreach ($cleanIds as $targetId) {
                $sql = 'INSERT INTO '.$table.' (fk_product, '.$field.', date_link) VALUES ('
                    .$productId.', '.$targetId.', NOW())';
                if (!$db->query($sql)) {
                    $db->rollback();
                    dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
                    return false;
                }
            }
        }

        $db->commit();

        return true;
    }

    /**
     * Remove a single link row.
     *
     * @param DoliDB $db
     * @param int    $rowid
     * @param string $type
     *
     * @return bool
     */
    public static function deleteLink(DoliDB $db, $rowid, $type)
    {
        $rowid = (int) $rowid;
        if ($rowid <= 0) {
            return false;
        }

        if ($type === self::TYPE_FORMULADO) {
            $table = MAIN_DB_PREFIX.'safra_product_formulado';
        } elseif ($type === self::TYPE_TECNICO) {
            $table = MAIN_DB_PREFIX.'safra_product_produtostecnico';
        } else {
            return false;
        }

        $sql = 'DELETE FROM '.$table.' WHERE rowid = '.$rowid;

        return (bool) $db->query($sql);
    }

    /**
     * Fetch available catalog entries for linking.
     *
     * @param DoliDB $db
     * @param string $type
     * @param int[]  $excludeIds
     *
     * @return array<int, string>
     */
    public static function fetchAvailableOptions(DoliDB $db, $type, array $excludeIds = array())
    {
        $exclude = array();
        foreach ($excludeIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $exclude[$id] = $id;
            }
        }

        if ($type === self::TYPE_FORMULADO) {
            $sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'safra_produto_formulado'
                .' WHERE status = 1';
        } elseif ($type === self::TYPE_TECNICO) {
            $sql = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'safra_produtostecnicos'
                .' WHERE status = 1';
        } else {
            return array();
        }

        if (!empty($exclude)) {
            $sql .= ' AND rowid NOT IN ('.implode(',', $exclude).')';
        }

        $sql .= ' ORDER BY ref ASC';

        $options = array();
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetchObject($resql)) {
                $label = $obj->ref;
                if (!empty($obj->label)) {
                    $label .= ' - '.$obj->label;
                }
                $options[(int) $obj->rowid] = $label;
            }
            $db->free($resql);
        }

        return $options;
    }

    /**
     * Fetch a single option label.
     *
     * @param DoliDB $db
     * @param string $type
     * @param int    $id
     *
     * @return string|null
     */
    public static function fetchOptionLabel(DoliDB $db, $type, $id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        if ($type === self::TYPE_FORMULADO) {
            $table = MAIN_DB_PREFIX.'safra_produto_formulado';
        } elseif ($type === self::TYPE_TECNICO) {
            $table = MAIN_DB_PREFIX.'safra_produtostecnicos';
        } else {
            return null;
        }

        $sql = 'SELECT ref, label FROM '.$table.' WHERE rowid = '.$id;
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetchObject($resql);
            $db->free($resql);
            if ($obj) {
                $label = $obj->ref;
                if (!empty($obj->label)) {
                    $label .= ' - '.$obj->label;
                }

                return $label;
            }
        }

        return null;
    }
}
