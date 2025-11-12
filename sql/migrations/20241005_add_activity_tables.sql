-- Migration: create Safra activity tables with multi-entity support.
-- Dolibarr executes this script during upgrade operations.

SET @schema = DATABASE();

CREATE TABLE IF NOT EXISTS __MAIN_DB_PREFIX__safra_activity (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    ref VARCHAR(128) NOT NULL,
    label VARCHAR(255),
    amount DOUBLE DEFAULT NULL,
    qty DOUBLE,
    fk_soc INTEGER,
    fk_project INTEGER,
    fk_task INTEGER,
    activity_type VARCHAR(32) NOT NULL DEFAULT 'application',
    date_activity DATE,
    description TEXT,
    note_public TEXT,
    note_private TEXT,
    mixture_note TEXT,
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER NOT NULL,
    fk_user_modif INTEGER,
    last_main_doc VARCHAR(255),
    import_key VARCHAR(14),
    model_pdf VARCHAR(255),
    status INTEGER NOT NULL
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS __MAIN_DB_PREFIX__safra_activity_line (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_product INTEGER,
    fk_formulated_product INTEGER,
    fk_technical_product INTEGER,
    fk_warehouse INTEGER,
    label VARCHAR(255),
    dose DOUBLE,
    dose_unit VARCHAR(10),
    area_ha DOUBLE,
    total_qty DOUBLE,
    note TEXT,
    movement INTEGER NOT NULL DEFAULT 1,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=innodb;

-- Ensure the entity column exists on the header table
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND COLUMN_NAME = 'entity'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD COLUMN entity INTEGER NOT NULL DEFAULT 1 AFTER rowid;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure the entity column exists on the line table
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
              AND COLUMN_NAME = 'entity'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD COLUMN entity INTEGER NOT NULL DEFAULT 1 AFTER rowid;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure unique reference per entity
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND INDEX_NAME = 'uk_safra_activity_ref'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD UNIQUE INDEX uk_safra_activity_ref (entity, ref);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add supporting entity/date indexes on the header table
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND INDEX_NAME = 'idx_safra_activity_entity'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD INDEX idx_safra_activity_entity (entity);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND INDEX_NAME = 'idx_safra_activity_date'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD INDEX idx_safra_activity_date (date_activity);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add entity index and warehouse lookup support on the line table
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
              AND INDEX_NAME = 'idx_safra_activity_line_entity'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD INDEX idx_safra_activity_line_entity (entity);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
              AND INDEX_NAME = 'idx_safra_activity_line_fk_warehouse'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD INDEX idx_safra_activity_line_fk_warehouse (fk_warehouse);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign keys introduced with the activity consolidation
SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND CONSTRAINT_NAME = 'fk_safra_activity_societe'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD CONSTRAINT fk_safra_activity_societe FOREIGN KEY (fk_soc) REFERENCES __MAIN_DB_PREFIX__societe (rowid) ON DELETE SET NULL;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND CONSTRAINT_NAME = 'fk_safra_activity_project'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD CONSTRAINT fk_safra_activity_project FOREIGN KEY (fk_project) REFERENCES __MAIN_DB_PREFIX__projet (rowid) ON DELETE SET NULL;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND CONSTRAINT_NAME = 'fk_safra_activity_task'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD CONSTRAINT fk_safra_activity_task FOREIGN KEY (fk_task) REFERENCES __MAIN_DB_PREFIX__projet_task (rowid) ON DELETE SET NULL;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND CONSTRAINT_NAME = 'fk_safra_activity_user_creat'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD CONSTRAINT fk_safra_activity_user_creat FOREIGN KEY (fk_user_creat) REFERENCES __MAIN_DB_PREFIX__user (rowid);'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity'
              AND CONSTRAINT_NAME = 'fk_safra_activity_user_modif'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD CONSTRAINT fk_safra_activity_user_modif FOREIGN KEY (fk_user_modif) REFERENCES __MAIN_DB_PREFIX__user (rowid) ON DELETE SET NULL;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
              AND CONSTRAINT_NAME = 'fk_safra_activity_line_activity'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD CONSTRAINT fk_safra_activity_line_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity (rowid) ON DELETE CASCADE;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
              AND CONSTRAINT_NAME = 'fk_safra_activity_line_product'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD CONSTRAINT fk_safra_activity_line_product FOREIGN KEY (fk_product) REFERENCES __MAIN_DB_PREFIX__product (rowid) ON DELETE SET NULL;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
              AND CONSTRAINT_NAME = 'fk_safra_activity_line_formulated'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD CONSTRAINT fk_safra_activity_line_formulated FOREIGN KEY (fk_formulated_product) REFERENCES __MAIN_DB_PREFIX__safra_produto_formulado (rowid) ON DELETE SET NULL;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
              AND CONSTRAINT_NAME = 'fk_safra_activity_line_technical'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD CONSTRAINT fk_safra_activity_line_technical FOREIGN KEY (fk_technical_product) REFERENCES __MAIN_DB_PREFIX__safra_produtostecnicos (rowid) ON DELETE SET NULL;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = @schema
              AND TABLE_NAME = '__MAIN_DB_PREFIX__safra_activity_line'
              AND CONSTRAINT_NAME = 'fk_safra_activity_line_warehouse'
        ),
        'SELECT 1',
        'ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD CONSTRAINT fk_safra_activity_line_warehouse FOREIGN KEY (fk_warehouse) REFERENCES __MAIN_DB_PREFIX__entrepot (rowid) ON DELETE SET NULL;'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
