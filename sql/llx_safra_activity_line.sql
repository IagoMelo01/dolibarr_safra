CREATE TABLE llx_safra_activity_line (
        rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
        entity INTEGER NOT NULL DEFAULT 1,
        fk_activity INTEGER NOT NULL,
        fk_product INTEGER,
        label VARCHAR(255),
        qty DOUBLE NOT NULL DEFAULT 0,
        unit VARCHAR(10),
        fk_warehouse INTEGER,
        note TEXT,
        date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
