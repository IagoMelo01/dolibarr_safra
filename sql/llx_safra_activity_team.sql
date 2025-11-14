CREATE TABLE llx_safra_activity_team (
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
