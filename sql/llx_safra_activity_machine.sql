-- N:N relation between activities and machines.
CREATE TABLE llx_safra_activity_machine (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_machine INTEGER NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_safra_activity_machine_entity (entity),
    INDEX idx_safra_activity_machine_fk_activity (fk_activity),
    INDEX idx_safra_activity_machine_fk_machine (fk_machine),
    UNIQUE INDEX uk_safra_activity_machine_unique (entity, fk_activity, fk_machine),
    CONSTRAINT llx_safra_activity_machine_fk_activity FOREIGN KEY (fk_activity) REFERENCES llx_safra_activity(rowid) ON DELETE CASCADE
) ENGINE=innodb;
