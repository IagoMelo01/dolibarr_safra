# Safra - atividade (workflow) testes manuais

Roteiro manual para validar o fluxo Activity no schema canônico (`safra_activity*`).

## Seeds

| Arquivo | Objetivo |
| --- | --- |
| `sql/dados_sql/data_4_activity.sql` | Cria atividades de rascunho, em andamento, concluída e cancelada com linhas e relações auxiliares. |

## Cenários obrigatórios

| Cenário | Passos | Resultado esperado |
| --- | --- | --- |
| Criar atividade | Criar registro com projeto/talhão e ao menos 1 linha de produto | Registro salvo com `ref` e status `Draft` |
| Iniciar | Acionar ação **Iniciar atividade** | Status `In Progress` |
| Concluir | Acionar ação **Concluir atividade** | Status `Completed` e consumo de estoque sem duplicidade |
| Cancelar | Acionar ação **Cancelar atividade** | Status `Canceled` |
| Excluir | Acionar ação **Excluir atividade** | Registro removido e sincronização de task coerente |

## Verificações complementares

1. Permissões:
   - `safra->SafraActivity->read` para listar/visualizar.
   - `safra->SafraActivity->write` para mutações.
   - `safra->SafraActivity->delete` para exclusão.
2. Estoque:
   - conferir `llx_stock_mouvement.origintype = 'safra_activity'`.
3. Triggers:
   - eventos `SAFRA_ACTIVITY_*` sem duplicidade.
4. API:
   - `GET /api/index.php/sfactivities?sortfield=ref`
   - `GET /api/index.php/sfactivities/{id}?include_lines=1`
   - `POST /api/index.php/sfactivities/{id}/start|complete|cancel`

## Observações

- O legado `safra_aplicacao*` foi removido nesta linha de desenvolvimento.
- Qualquer relatório SQL antigo deve ser migrado para `safra_activity*`.