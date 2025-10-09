ALTER TABLE llx_safra_aplicacao_line ADD INDEX idx_safra_aplicacao_line_fk_aplicacao (fk_aplicacao);
ALTER TABLE llx_safra_aplicacao_line ADD INDEX idx_safra_aplicacao_line_fk_product (fk_product);
ALTER TABLE llx_safra_aplicacao_line ADD INDEX idx_safra_aplicacao_line_fk_linked (fk_linked);
ALTER TABLE llx_safra_aplicacao_line ADD INDEX idx_safra_aplicacao_line_fk_warehouse (fk_warehouse);
