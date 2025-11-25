-- N:N relation between activities and implements.
CREATE TABLE llx_safra_activity_implement (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_implement INTEGER NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_safra_activity_implement_entity (entity),
    INDEX idx_safra_activity_implement_fk_activity (fk_activity),
    INDEX idx_safra_activity_implement_fk_implement (fk_implement),
    UNIQUE INDEX uk_safra_activity_implement_unique (entity, fk_activity, fk_implement),
    CONSTRAINT llx_safra_activity_implement_fk_activity FOREIGN KEY (fk_activity) REFERENCES llx_safra_activity(rowid) ON DELETE CASCADE
) ENGINE=innodb;
