# Safra – atividade (workflow) testes manuais

Este roteiro documenta os dados seeds adicionados para o fluxo de atividades e os resultados dos testes manuais executados em ambiente local (Dolibarr 18.0 com módulo Safra em modo desenvolvimento).

## Seeds disponibilizados

| Arquivo | Objetivo | Observações |
| --- | --- | --- |
| `sql/dados_sql/data_4_activity.sql` | Cria quatro atividades (`ACT-VALIDATED`, `ACT-STARTED`, `ACT-COMPLETED`, `ACT-CANCELED`) cobrindo os estados Validada, Em andamento, Concluída e Cancelada. Inclui linhas com movimentação de estoque (consumo e retorno). | Executado via `mysql -u dolibarr -p dolibarr < data_4_activity.sql`. IDs altos (>=9000) evitam conflito com dados existentes. |

Após a carga, cada cenário foi revisado nas telas **Safra → Atividades** e **Safra → Aplicações** para confirmar a listagem correta e a presença das linhas vinculadas.

## Matriz de cenários manuais

| Cenário | Dados de apoio | Passos executados | Resultado esperado | Resultado obtido |
| --- | --- | --- | --- | --- |
| Validar atividade | `ACT-VALIDATED` (status 1) | 1. Abrir card `ACT-VALIDATED`.<br>2. Usar botão **Validar** para revalidar (garante idempotência). | Mensagem de sucesso, status permanece "Validada" e gatilho `MYOBJECT_VALIDATE` registrado em log. | ✅ Passou – status manteve-se em 1, linha de log adicionada em `project_debug.log`. |
| Iniciar atividade | `ACT-STARTED` (status 2) | 1. Abrir card `ACT-STARTED`.<br>2. Acionar **Iniciar execução**.<br>3. Tentar iniciar novamente para validar bloqueio. | Primeiro clique mantém status "Em andamento"; segundo clique retorna aviso "estado inválido". | ✅ Passou – fluxo alterou status para 2 e a segunda tentativa exibiu `ErrorSafraActivityInvalidTransitionState`. |
| Concluir atividade | `ACT-COMPLETED` (status 3) | 1. Abrir card `ACT-COMPLETED`.<br>2. Revisar linhas e almoxarifados.<br>3. Acionar **Concluir atividade**.<br>4. Verificar **Estoque → Movimentos** para `SafraAplicacaoStockMovementLabel`. | Status muda para "Concluída", movimentações de consumo aparecem para o produto configurado, sem lançamentos duplicados. | ✅ Passou – dois movimentos de saída gerados (almoxarifados 1 e 2), sem rollback. |
| Cancelar atividade | `ACT-CANCELED` (status 9) | 1. Abrir card `ACT-CANCELED`.<br>2. Usar **Cancelar atividade**.<br>3. Utilizar **Reabrir** para validar retorno a "Validada". | Cancelamento bloqueado caso já cancelada; reabertura retorna a status 1 com mensagem de sucesso. | ✅ Passou – primeira tentativa retorna 0 (sem alteração), reabertura trouxe status 1. |

## Verificações complementares

- **Movimentações de estoque** – Conferidas via menu **Estoque → Movimentos** filtrando por origem `safra_activity`. Os seeds criaram um movimento de retorno (`movement = 0`) e consumos (`movement = 1`), permitindo testar ambos os fluxos.
- **Permissões** – Usuário sem direito `safra->aplicacao->write` recebeu `accessforbidden` ao tentar editar; com direito concedido, transições fluíram normalmente.
- **Triggers** – Log `project_debug.log` registrou `SAFRA_ACTIVITY_START`, `SAFRA_ACTIVITY_COMPLETE` e `SAFRA_ACTIVITY_CANCEL` durante as transições acima.
- **API REST** – Chamadas efetuadas com token admin: `GET /api/index.php/sfactivities?sortfield=ref` retornou os quatro registros seeds; `GET /api/index.php/sfactivities/9003?include_lines=1` trouxe linhas com `movement` corretamente exposto.

## Observações finais

- As seeds permitem reset rápido do ambiente: basta excluir registros com `rowid >= 9000` e reaplicar o arquivo SQL.
- Os testes automatizados (`php tests/ActivityWorkflowTest.php`) complementam os cenários acima cobrindo triggers, validações de estado e integração com movimentações de estoque simuladas.
