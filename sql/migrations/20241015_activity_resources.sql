-- Migration: extend Safra activity resources with fleet and team tables.

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD COLUMN fk_unit INTEGER AFTER unit,
    ADD COLUMN movement_type VARCHAR(16) NOT NULL DEFAULT 'consume' AFTER fk_warehouse;

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD INDEX idx_safra_activity_line_fk_unit (fk_unit),
    ADD INDEX idx_safra_activity_line_movement_type (movement_type);

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD CONSTRAINT llx_safra_activity_line_fk_unit FOREIGN KEY (fk_unit) REFERENCES __MAIN_DB_PREFIX__c_units (rowid) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS __MAIN_DB_PREFIX__safra_activity_fleet (
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

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_fleet
    ADD INDEX idx_safra_activity_fleet_entity (entity),
    ADD INDEX idx_safra_activity_fleet_fk_activity (fk_activity),
    ADD INDEX idx_safra_activity_fleet_fk_equipment (fk_fleet_equipment),
    ADD INDEX idx_safra_activity_fleet_fk_user_responsible (fk_user_responsible);

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_fleet
    ADD CONSTRAINT llx_safra_activity_fleet_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity (rowid) ON DELETE CASCADE,
    ADD CONSTRAINT llx_safra_activity_fleet_fk_user FOREIGN KEY (fk_user_responsible) REFERENCES __MAIN_DB_PREFIX__user (rowid) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS __MAIN_DB_PREFIX__safra_activity_team (
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

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_team
    ADD INDEX idx_safra_activity_team_entity (entity),
    ADD INDEX idx_safra_activity_team_fk_activity (fk_activity),
    ADD INDEX idx_safra_activity_team_fk_user (fk_user),
    ADD INDEX idx_safra_activity_team_is_responsible (is_responsible);

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_team
    ADD CONSTRAINT llx_safra_activity_team_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity (rowid) ON DELETE CASCADE,
    ADD CONSTRAINT llx_safra_activity_team_fk_user FOREIGN KEY (fk_user) REFERENCES __MAIN_DB_PREFIX__user (rowid) ON DELETE CASCADE;
