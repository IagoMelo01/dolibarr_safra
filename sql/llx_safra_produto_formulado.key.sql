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

ALTER TABLE __MAIN_DB_PREFIX__safra_produto_formulado ADD INDEX idx_safra_pf_status (status);
ALTER TABLE __MAIN_DB_PREFIX__safra_produto_formulado ADD INDEX idx_safra_pf_fk_user_creat (fk_user_creat);
