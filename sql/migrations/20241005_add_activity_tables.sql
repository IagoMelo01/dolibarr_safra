-- Migration: create Safra activity tables with multi-entity support.
-- Dolibarr executes this script during upgrade operations.

-- Drop legacy structures so the new definition is enforced.
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_team;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_fleet;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_line;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    ref VARCHAR(128) NOT NULL,
    fk_project INTEGER,
    fk_talhao INTEGER,
    label VARCHAR(255) NOT NULL,
    activity_type VARCHAR(32) NOT NULL,
    date_planned_start DATETIME,
    date_planned_end DATETIME,
    date_real_start DATETIME,
    date_real_end DATETIME,
    status INTEGER NOT NULL DEFAULT 0,
    note_public TEXT,
    note_private TEXT,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER NOT NULL,
    fk_user_modif INTEGER,
    last_main_doc VARCHAR(255),
    import_key VARCHAR(14),
    model_pdf VARCHAR(255)
) ENGINE=innodb;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity_line (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_product INTEGER,
    label VARCHAR(255),
    qty DOUBLE NOT NULL DEFAULT 0,
    unit VARCHAR(10),
    fk_unit INTEGER,
    fk_warehouse INTEGER,
    movement_type VARCHAR(16) NOT NULL DEFAULT 'consume',
    note TEXT,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity_fleet (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    resource_type VARCHAR(16) NOT NULL DEFAULT 'vehicle',
    fk_fleet_equipment INTEGER NOT NULL,
    fk_user_responsible INTEGER,
    planned_hours DOUBLE,
    note TEXT,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity_team (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_user INTEGER NOT NULL,
    planned_hours DOUBLE,
    is_responsible INTEGER NOT NULL DEFAULT 0,
    note TEXT,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

-- Indexes and constraints for the header table
ALTER TABLE __MAIN_DB_PREFIX__safra_activity
    ADD UNIQUE INDEX uk_safra_activity_ref (entity, ref),
    ADD INDEX idx_safra_activity_entity (entity),
    ADD INDEX idx_safra_activity_fk_project (fk_project),
    ADD INDEX idx_safra_activity_fk_talhao (fk_talhao),
    ADD INDEX idx_safra_activity_status (status),
    ADD INDEX idx_safra_activity_date_planned_start (date_planned_start),
    ADD INDEX idx_safra_activity_date_real_start (date_real_start),
    ADD CONSTRAINT llx_safra_activity_fk_project FOREIGN KEY (fk_project) REFERENCES __MAIN_DB_PREFIX__projet (rowid) ON DELETE SET NULL,
    ADD CONSTRAINT llx_safra_activity_fk_talhao FOREIGN KEY (fk_talhao) REFERENCES __MAIN_DB_PREFIX__safra_talhao (rowid) ON DELETE SET NULL,
    ADD CONSTRAINT llx_safra_activity_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES __MAIN_DB_PREFIX__user (rowid),
    ADD CONSTRAINT llx_safra_activity_fk_user_modif FOREIGN KEY (fk_user_modif) REFERENCES __MAIN_DB_PREFIX__user (rowid) ON DELETE SET NULL;

-- Indexes and constraints for the line table
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD INDEX idx_safra_activity_line_entity (entity),
    ADD INDEX idx_safra_activity_line_fk_activity (fk_activity),
    ADD INDEX idx_safra_activity_line_fk_product (fk_product),
    ADD INDEX idx_safra_activity_line_fk_unit (fk_unit),
    ADD INDEX idx_safra_activity_line_fk_warehouse (fk_warehouse),
    ADD INDEX idx_safra_activity_line_movement_type (movement_type),
    ADD CONSTRAINT llx_safra_activity_line_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity (rowid) ON DELETE CASCADE,
    ADD CONSTRAINT llx_safra_activity_line_fk_product FOREIGN KEY (fk_product) REFERENCES __MAIN_DB_PREFIX__product (rowid) ON DELETE SET NULL,
    ADD CONSTRAINT llx_safra_activity_line_fk_unit FOREIGN KEY (fk_unit) REFERENCES __MAIN_DB_PREFIX__c_units (rowid) ON DELETE SET NULL,
    ADD CONSTRAINT llx_safra_activity_line_fk_warehouse FOREIGN KEY (fk_warehouse) REFERENCES __MAIN_DB_PREFIX__entrepot (rowid) ON DELETE SET NULL;

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_fleet
    ADD INDEX idx_safra_activity_fleet_entity (entity),
    ADD INDEX idx_safra_activity_fleet_fk_activity (fk_activity),
    ADD INDEX idx_safra_activity_fleet_fk_equipment (fk_fleet_equipment),
    ADD INDEX idx_safra_activity_fleet_fk_user_responsible (fk_user_responsible),
    ADD CONSTRAINT llx_safra_activity_fleet_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity (rowid) ON DELETE CASCADE,
    ADD CONSTRAINT llx_safra_activity_fleet_fk_user FOREIGN KEY (fk_user_responsible) REFERENCES __MAIN_DB_PREFIX__user (rowid) ON DELETE SET NULL;

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_team
    ADD INDEX idx_safra_activity_team_entity (entity),
    ADD INDEX idx_safra_activity_team_fk_activity (fk_activity),
    ADD INDEX idx_safra_activity_team_fk_user (fk_user),
    ADD INDEX idx_safra_activity_team_is_responsible (is_responsible),
    ADD CONSTRAINT llx_safra_activity_team_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity (rowid) ON DELETE CASCADE,
    ADD CONSTRAINT llx_safra_activity_team_fk_user FOREIGN KEY (fk_user) REFERENCES __MAIN_DB_PREFIX__user (rowid) ON DELETE CASCADE;
