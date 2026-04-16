# Safra - Execucao do Plano (Sem Legado `aplicacao`)

Data: 2026-04-09
Escopo: `C:\wamp64\www\dolibarr_23\htdocs\custom\safra`

## Status por fase

### Fase 0 - Seguranca e hardening
- [x] Removida superficie de debug: `info.php`, `teste.php`, `payload.json`, `project_debug.log`.
- [x] Removidos bypasses SSL (`CURLOPT_SSL_VERIFYPEER=false` e `CURLOPT_SSL_VERIFYHOST=false`) nas integracoes.
- [x] Mantido comportamento de cache satelital (`json/cache/*`).
- [ ] Rotacao de credenciais Sentinel/Embrapa (acao operacional fora do codigo).

Gate 0: **PARCIAL** (pendente apenas rotacao real de credenciais no ambiente).

### Fase 1 - Corte de legado + migracao unica
- [x] Schema canonico consolidado em `safra_activity*`.
- [x] Migracao unica implementada em `upgrade.php` com migracao de `safra_aplicacao` e `safra_aplicacao_line`.
- [x] Limpeza de legado apos migracao (drop de tabelas/views `safra_aplicacao*` e colunas extrafield legadas).
- [x] Dashboard ajustado para `safra_activity`.
- [x] Menus legados removidos de `modSafra.class.php`.
- [x] Compatibilidade legada podada no dominio (`FvActivity` sem fallback `fk_aplicacao`).

Gate 1: **CONCLUIDO**.

### Fase 2 - Upgrade e API oficial
- [x] `upgrade.php` reescrito para caminho canonico e idempotente.
- [x] Superficies SQL alinhadas com schema de Activity (`sql/mysql/activity.sql`, `sql/llx_safra_activity*.sql`, seeds de activity).
- [x] API oficial implementada em `class/api_sfactivities.class.php` com CRUD, transicoes (`start`, `complete`, `cancel`) e `include_lines=1`.
- [x] Permissao `SafraActivity` aplicada em UI/API; mutacoes da UI com validacao de token e POST-only.

Gate 2: **CONCLUIDO (validacao funcional local)**.

### Fase 3 - Qualidade, release e operacao
- [x] Suite minima criada em `tests/`:
  - `ActivityDomainTest.php`
  - `ActivityStockServiceTest.php`
  - `MigrationAndSchemaTest.php`
  - `ApiSfactivitiesTest.php`
- [x] Runner de testes: `tests/run.php`.
- [x] Script local oficial de checks: `build/ci/checks.ps1` (lint + testes + validacoes de artefatos).
- [x] Runbook de deploy/rollback: `doc/deploy_runbook.md`.
- [ ] Homologacao em staging com base espelho.

Gate 3: **PARCIAL** (pendente homologacao/UAT).

## Proximos passos imediatos

1. Rotacionar credenciais Sentinel/Embrapa em `Configurações > Safra` e invalidar tokens anteriores.
2. Executar `build/ci/checks.ps1` e corrigir qualquer regressao residual.
3. Rodar `upgrade.php` em base de desenvolvimento com snapshot legado para validar migracao unica ponta-a-ponta.
4. Homologar fluxo completo em staging:
   - UI Activity (`create/save/start/complete/cancel/delete`)
   - Estoque (`origintype = 'safra_activity'`)
   - API (`/api/index.php/sfactivities`)
5. Emitir go/no-go com checklist de deploy e rollback.

## Observacoes

- Nao ha camada de compatibilidade para `aplicacao` apos esta entrega.
- Rollback suportado via restauracao de backup anterior a migracao unica.
 