<?php
/*
 * Base line object for Safra activity inputs.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobjectline.class.php';

class FvActivityLine extends CommonObjectLine
{
    /** @var string */
    public $module = 'safra';

    /** @var string */
    public $element = 'safra_activity_line';

    /** @var string */
    public $fk_element = 'fk_activity';

    /** @var string */
    public $table_element = 'safra_activity_line';

    /** @var int */
    public $ismultientitymanaged = 1;

    /** @var int */
    public $isextrafieldmanaged = 1;

    /**
     * Create activity line.
     *
     * @param User $user
     * @return int
     */
    public function create($user)
    {
        global $conf;

        $this->entity = $this->entity ?: $conf->entity;

        return $this->createCommon($user);
    }

    /**
     * Delete every line linked to an activity.
     *
     * @param DoliDB $db
     * @param int    $activityId
     * @return int
     */
    public static function deleteForActivity($db, $activityId)
    {
        $activityId = (int) $activityId;
        if ($activityId <= 0) {
            return 0;
        }

        $sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'safra_activity_line WHERE fk_activity = ' . $activityId;
        if (!$db->query($sql)) {
            return -1;
        }

        return 1;
    }

    /**
     * Field description for automatic CRUD helpers.
     *
     * @var array
     */
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'visible' => -1, 'notnull' => 1),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'default' => 1),
        'fk_activity' => array('type' => 'integer', 'label' => 'Activity', 'enabled' => '1', 'visible' => -2, 'notnull' => 1, 'index' => 1, 'foreignkey' => 'safra_activity.rowid'),
        'fk_product' => array('type' => 'integer:Product:product/class/product.class.php:1', 'label' => 'Product', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'index' => 1),
        'area_applied' => array('type' => 'double(24,8)', 'label' => 'AreaApplied', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0'),
        'dose' => array('type' => 'double(24,8)', 'label' => 'Dose', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0'),
        'dose_unit' => array('type' => 'varchar(32)', 'label' => 'DoseUnit', 'enabled' => '1', 'visible' => 1, 'notnull' => 0),
        'total' => array('type' => 'double(24,8)', 'label' => 'Total', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => '0'),
        'movement_type' => array('type' => 'varchar(16)', 'label' => 'MovementType', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'default' => 'consume'),
        'fk_warehouse' => array('type' => 'integer:Entrepot:product/stock/class/entrepot.class.php:1', 'label' => 'Warehouse', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'index' => 1),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'visible' => -2, 'notnull' => 1),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'visible' => -2, 'notnull' => 0),
        'fk_user_creat' => array('type' => 'integer', 'label' => 'UserAuthor', 'enabled' => '1', 'visible' => -2, 'notnull' => 0),
        'fk_user_modif' => array('type' => 'integer', 'label' => 'UserModif', 'enabled' => '1', 'visible' => -2, 'notnull' => 0),
    );
}
