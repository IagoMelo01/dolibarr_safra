ALTER TABLE llx_safra_aplicacao_resource ADD UNIQUE INDEX uk_safra_aplicacao_resource (fk_aplicacao, resource_type, fk_target);
ALTER TABLE llx_safra_aplicacao_resource ADD INDEX idx_safra_aplicacao_resource_fk_aplicacao (fk_aplicacao);
