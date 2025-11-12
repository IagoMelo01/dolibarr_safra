-- Copyright (C) 2024 SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

--
-- Header table that stores the unified Safra activity document.
--
CREATE TABLE __MAIN_DB_PREFIX__safra_activity (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    ref VARCHAR(128) NOT NULL,
    label VARCHAR(255),
    amount DOUBLE DEFAULT NULL,
    qty DOUBLE,
    fk_soc INTEGER,
    fk_project INTEGER,
    fk_task INTEGER,
    activity_type VARCHAR(32) NOT NULL DEFAULT 'application',
    date_activity DATE,
    description TEXT,
    note_public TEXT,
    note_private TEXT,
    mixture_note TEXT,
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER NOT NULL,
    fk_user_modif INTEGER,
    last_main_doc VARCHAR(255),
    import_key VARCHAR(14),
    model_pdf VARCHAR(255),
    status INTEGER NOT NULL
) ENGINE=innodb;

--
-- Detail lines describing the resources allocated to an activity.
--
CREATE TABLE __MAIN_DB_PREFIX__safra_activity_line (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    fk_activity INTEGER NOT NULL,
    fk_product INTEGER,
    fk_formulated_product INTEGER,
    fk_technical_product INTEGER,
    fk_warehouse INTEGER,
    label VARCHAR(255),
    dose DOUBLE,
    dose_unit VARCHAR(10),
    area_ha DOUBLE,
    total_qty DOUBLE,
    note TEXT,
    movement INTEGER NOT NULL DEFAULT 1,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=innodb;

-- -----------------------------------------------------------------------------
-- Indexes
-- -----------------------------------------------------------------------------
ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD UNIQUE INDEX uk_safra_activity_ref (entity, ref);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD INDEX idx_safra_activity_entity (entity);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD INDEX idx_safra_activity_fk_soc (fk_soc);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD INDEX idx_safra_activity_fk_project (fk_project);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD INDEX idx_safra_activity_fk_task (fk_task);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD INDEX idx_safra_activity_status (status);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity ADD INDEX idx_safra_activity_date (date_activity);

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD INDEX idx_safra_activity_line_entity (entity);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD INDEX idx_safra_activity_line_fk_activity (fk_activity);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD INDEX idx_safra_activity_line_fk_product (fk_product);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD INDEX idx_safra_activity_line_fk_formulated (fk_formulated_product);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD INDEX idx_safra_activity_line_fk_technical (fk_technical_product);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line ADD INDEX idx_safra_activity_line_fk_warehouse (fk_warehouse);

-- -----------------------------------------------------------------------------
-- Foreign keys
-- -----------------------------------------------------------------------------
ALTER TABLE __MAIN_DB_PREFIX__safra_activity
    ADD CONSTRAINT fk_safra_activity_societe FOREIGN KEY (fk_soc) REFERENCES __MAIN_DB_PREFIX__societe (rowid)
        ON DELETE SET NULL;
ALTER TABLE __MAIN_DB_PREFIX__safra_activity
    ADD CONSTRAINT fk_safra_activity_project FOREIGN KEY (fk_project) REFERENCES __MAIN_DB_PREFIX__projet (rowid)
        ON DELETE SET NULL;
ALTER TABLE __MAIN_DB_PREFIX__safra_activity
    ADD CONSTRAINT fk_safra_activity_task FOREIGN KEY (fk_task) REFERENCES __MAIN_DB_PREFIX__projet_task (rowid)
        ON DELETE SET NULL;
ALTER TABLE __MAIN_DB_PREFIX__safra_activity
    ADD CONSTRAINT fk_safra_activity_user_creat FOREIGN KEY (fk_user_creat) REFERENCES __MAIN_DB_PREFIX__user (rowid);
ALTER TABLE __MAIN_DB_PREFIX__safra_activity
    ADD CONSTRAINT fk_safra_activity_user_modif FOREIGN KEY (fk_user_modif) REFERENCES __MAIN_DB_PREFIX__user (rowid)
        ON DELETE SET NULL;

ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD CONSTRAINT fk_safra_activity_line_activity FOREIGN KEY (fk_activity) REFERENCES __MAIN_DB_PREFIX__safra_activity (rowid)
        ON DELETE CASCADE;
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD CONSTRAINT fk_safra_activity_line_product FOREIGN KEY (fk_product) REFERENCES __MAIN_DB_PREFIX__product (rowid)
        ON DELETE SET NULL;
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD CONSTRAINT fk_safra_activity_line_formulated FOREIGN KEY (fk_formulated_product) REFERENCES __MAIN_DB_PREFIX__safra_produto_formulado (rowid)
        ON DELETE SET NULL;
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD CONSTRAINT fk_safra_activity_line_technical FOREIGN KEY (fk_technical_product) REFERENCES __MAIN_DB_PREFIX__safra_produtostecnicos (rowid)
        ON DELETE SET NULL;
ALTER TABLE __MAIN_DB_PREFIX__safra_activity_line
    ADD CONSTRAINT fk_safra_activity_line_warehouse FOREIGN KEY (fk_warehouse) REFERENCES __MAIN_DB_PREFIX__entrepot (rowid)
        ON DELETE SET NULL;
