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


CREATE TABLE llx_safra_analisesolo(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	label varchar(255), 
	amount double DEFAULT NULL, 
	qty real, 
	fk_soc integer, 
	fk_project integer, 
	description text, 
	note_public text, 
	note_private text, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer NOT NULL, 
	fk_user_modif integer, 
	last_main_doc varchar(255), 
	import_key varchar(14), 
	model_pdf varchar(255), 
	status integer NOT NULL, 
	data_coleta date NOT NULL, 
	localizacao varchar(255) NOT NULL, 
	profundidade_amostra double(28,4), 
	ph double(28,4) NOT NULL, 
	materia_organica double(28,4) NOT NULL, 
	n_total double(28,4) NOT NULL, 
	fosforo double(28,4) NOT NULL, 
	potassio double(28,4) NOT NULL, 
	calcio double(28,4) NOT NULL, 
	magnesio double(28,4) NOT NULL, 
	enxofre double(28,4), 
	textura varchar(128), 
	densidade double(28,4), 
	ctc integer NOT NULL, 
	saturacao_bases integer NOT NULL, 
	aluminio double(28,4), 
	hidrogenio double(28,4), 
	zinco double(28,4), 
	cobre double(28,4), 
	manganes double(28,4), 
	ferro double(28,4), 
	boro double(28,4)
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
