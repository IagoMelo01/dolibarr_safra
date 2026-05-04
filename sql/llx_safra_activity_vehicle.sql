-- Links between agricultural activities and fleet vehicles.
CREATE TABLE llx_safra_activity_vehicle (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_vehicle INTEGER NOT NULL,
    vehicle_class VARCHAR(64) NOT NULL DEFAULT 'Veiculo',
    planned_hours DOUBLE(24,8) DEFAULT 0,
    done_hours DOUBLE(24,8) DEFAULT 0,
    note TEXT,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_safra_activity_vehicle_entity (entity),
    INDEX idx_safra_activity_vehicle_fk_activity (fk_activity),
    INDEX idx_safra_activity_vehicle_fk_vehicle (fk_vehicle),
    UNIQUE INDEX uk_safra_activity_vehicle_unique (entity, fk_activity, fk_vehicle),
    CONSTRAINT llx_safra_activity_vehicle_fk_activity FOREIGN KEY (fk_activity) REFERENCES llx_safra_activity(rowid) ON DELETE CASCADE
) ENGINE=innodb;
