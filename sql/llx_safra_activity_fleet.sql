CREATE TABLE llx_safra_activity_fleet (
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
