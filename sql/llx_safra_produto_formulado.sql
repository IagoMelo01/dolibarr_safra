-- Copyright (C) 2025 SuperAdmin
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

CREATE TABLE IF NOT EXISTS __MAIN_DB_PREFIX__safra_produto_formulado (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(128) NOT NULL,
  label VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat INT NOT NULL,
  fk_user_modif INT NULL,
  UNIQUE KEY uk_safra_pf_ref (ref)
) ENGINE=innodb;
