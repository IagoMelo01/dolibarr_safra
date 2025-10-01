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

CREATE TABLE IF NOT EXISTS __MAIN_DB_PREFIX__safra_produto_praga (
  rowid INT AUTO_INCREMENT PRIMARY KEY,
  fk_produto INT NOT NULL,
  fk_praga INT NOT NULL,
  observacao VARCHAR(255) NULL,
  UNIQUE KEY uk_pf_praga (fk_produto, fk_praga),
  INDEX idx_pf (fk_produto),
  INDEX idx_pg (fk_praga),
  CONSTRAINT fk_spp_prod FOREIGN KEY (fk_produto)
    REFERENCES __MAIN_DB_PREFIX__safra_produto_formulado(rowid) ON DELETE CASCADE,
  CONSTRAINT fk_spp_prag FOREIGN KEY (fk_praga)
    REFERENCES __MAIN_DB_PREFIX__safra_pragas(rowid) ON DELETE CASCADE
) ENGINE=innodb;
