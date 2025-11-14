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


CREATE TABLE llx_safra_activity (
        rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
        entity INTEGER NOT NULL DEFAULT 1,
        ref VARCHAR(128) NOT NULL,
        fk_project INTEGER,
        fk_talhao INTEGER,
        label VARCHAR(255) NOT NULL,
        activity_type VARCHAR(32) NOT NULL,
        date_planned_start DATETIME,
        date_planned_end DATETIME,
        date_real_start DATETIME,
        date_real_end DATETIME,
        status INTEGER NOT NULL DEFAULT 0,
        note_public TEXT,
        note_private TEXT,
        date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fk_user_creat INTEGER NOT NULL,
        fk_user_modif INTEGER,
        last_main_doc VARCHAR(255),
        import_key VARCHAR(14),
        model_pdf VARCHAR(255)
) ENGINE=innodb;
