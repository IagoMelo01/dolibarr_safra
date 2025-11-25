-- N:N relation between activities and users/employees.
CREATE TABLE llx_safra_activity_user (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_user INTEGER NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_safra_activity_user_entity (entity),
    INDEX idx_safra_activity_user_fk_activity (fk_activity),
    INDEX idx_safra_activity_user_fk_user (fk_user),
    UNIQUE INDEX uk_safra_activity_user_unique (entity, fk_activity, fk_user),
    CONSTRAINT llx_safra_activity_user_fk_activity FOREIGN KEY (fk_activity) REFERENCES llx_safra_activity(rowid) ON DELETE CASCADE,
    CONSTRAINT llx_safra_activity_user_fk_user FOREIGN KEY (fk_user) REFERENCES llx_user(rowid) ON DELETE CASCADE
) ENGINE=innodb;
