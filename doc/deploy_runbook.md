# Safra deploy runbook

## Pré-deploy

1. Confirmar backup completo do banco e diretório de documentos.
2. Confirmar módulo API ativo no Dolibarr.
3. Colocar instância em manutenção.
4. Garantir credenciais Sentinel/Embrapa configuradas em `admin/setup.php`.

## Deploy

1. Publicar código do módulo atualizado.
2. Executar `custom/safra/upgrade.php` como admin.
3. Validar migração concluída sem erros.
4. Limpar cache do Dolibarr.
5. Reativar cron jobs.

## Smoke checks obrigatórios

1. UI Activity: listar, criar, salvar, iniciar, concluir, cancelar, excluir.
2. Estoque: movimentos com `origintype = 'safra_activity'`.
3. API:
   - `GET /api/index.php/sfactivities?limit=5`
   - `GET /api/index.php/sfactivities/{id}?include_lines=1`
   - `POST /api/index.php/sfactivities/{id}/start`

## Rollback

1. Ativar manutenção.
2. Restaurar backup do banco pré-upgrade.
3. Reimplantar pacote anterior do módulo.
4. Limpar cache e validar telas/fluxos básicos.
5. Reabrir acesso somente após validação funcional.

## Pós-deploy

1. Monitorar logs de trigger (`SAFRA_ACTIVITY_*`).
2. Monitorar erros de integração satelital (Sentinel/Embrapa).
3. Registrar versão implantada em changelog interno.