-- Reset Safra activity schema and remove legacy attempts.
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_user;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_implement;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_machine;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_line;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_team;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_activity_fleet;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_aplicacao_line;
DROP TABLE IF EXISTS __MAIN_DB_PREFIX__safra_aplicacao_resource;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    ref VARCHAR(128) NOT NULL,
    label VARCHAR(255) NOT NULL,
    fk_project INTEGER,
    fk_task INTEGER,
    fk_thirdparty INTEGER,
    fk_fieldplot INTEGER,
    area_total DOUBLE DEFAULT 0,
    type VARCHAR(32) NOT NULL,
    status INTEGER NOT NULL DEFAULT 0,
    note_public TEXT,
    note_private TEXT,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER NOT NULL,
    fk_user_modif INTEGER,
    UNIQUE INDEX uk_safra_activity_ref (entity, ref),
    INDEX idx_safra_activity_entity (entity),
    INDEX idx_safra_activity_fk_project (fk_project),
    INDEX idx_safra_activity_fk_task (fk_task),
    INDEX idx_safra_activity_fk_thirdparty (fk_thirdparty),
    INDEX idx_safra_activity_fk_fieldplot (fk_fieldplot),
    INDEX idx_safra_activity_status (status),
    INDEX idx_safra_activity_type (type),
    CONSTRAINT llx_safra_activity_fk_project FOREIGN KEY (fk_project) REFERENCES __MAIN_DB_PREFIX__projet(rowid) ON DELETE SET NULL,
    CONSTRAINT llx_safra_activity_fk_task FOREIGN KEY (fk_task) REFERENCES __MAIN_DB_PREFIX__projet_task(rowid) ON DELETE SET NULL,
    CONSTRAINT llx_safra_activity_fk_thirdparty FOREIGN KEY (fk_thirdparty) REFERENCES __MAIN_DB_PREFIX__societe(rowid) ON DELETE SET NULL,
    CONSTRAINT llx_safra_activity_fk_fieldplot FOREIGN KEY (fk_fieldplot) REFERENCES __MAIN_DB_PREFIX__safra_talhao(rowid) ON DELETE SET NULL,
    CONSTRAINT llx_safra_activity_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES __MAIN_DB_PREFIX__user(rowid),
    CONSTRAINT llx_safra_activity_fk_user_modif FOREIGN KEY (fk_user_modif) REFERENCES __MAIN_DB_PREFIX__user(rowid) ON DELETE SET NULL
) ENGINE=innodb;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity_line (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_product INTEGER,
    area_applied DOUBLE DEFAULT 0,
    dose DOUBLE DEFAULT 0,
    dose_unit VARCHAR(32),
    total DOUBLE DEFAULT 0,
    movement_type VARCHAR(16) DEFAULT 'consume',
    fk_warehouse INTEGER,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER,
    fk_user_modif INTEGER,
    INDEX idx_safra_activity_line_entity (entity),
    INDEX idx_safra_activity_line_fk_activity (fk_activity),
    INDEX idx_safra_activity_line_fk_product (fk_product),
    INDEX idx_safra_activity_line_fk_warehouse (fk_warehouse),
    CONSTRAINT llx_safra_activity_line_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity(rowid) ON DELETE CASCADE,
    CONSTRAINT llx_safra_activity_line_fk_product FOREIGN KEY (fk_product) REFERENCES __MAIN_DB_PREFIX__product(rowid) ON DELETE SET NULL,
    CONSTRAINT llx_safra_activity_line_fk_warehouse FOREIGN KEY (fk_warehouse) REFERENCES __MAIN_DB_PREFIX__entrepot(rowid) ON DELETE SET NULL,
    CONSTRAINT llx_safra_activity_line_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES __MAIN_DB_PREFIX__user(rowid) ON DELETE SET NULL,
    CONSTRAINT llx_safra_activity_line_fk_user_modif FOREIGN KEY (fk_user_modif) REFERENCES __MAIN_DB_PREFIX__user(rowid) ON DELETE SET NULL
) ENGINE=innodb;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity_machine (
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
    CONSTRAINT llx_safra_activity_machine_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity(rowid) ON DELETE CASCADE
) ENGINE=innodb;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity_implement (
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
    CONSTRAINT llx_safra_activity_implement_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity(rowid) ON DELETE CASCADE
) ENGINE=innodb;

CREATE TABLE __MAIN_DB_PREFIX__safra_activity_user (
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
    CONSTRAINT llx_safra_activity_user_fk_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity(rowid) ON DELETE CASCADE,
    CONSTRAINT llx_safra_activity_user_fk_user FOREIGN KEY (fk_user) REFERENCES __MAIN_DB_PREFIX__user(rowid) ON DELETE CASCADE
) ENGINE=innodb;
