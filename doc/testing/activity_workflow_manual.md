# Safra - Activity workflow manual test

Last updated: 2026-06-03

Manual roteiro para validar o fluxo canonico `safra_activity*` em uma instancia Dolibarr real.

## Seeds

| Arquivo | Objetivo |
| --- | --- |
| `sql/dados_sql/data_4_activity.sql` | Cria atividades de rascunho, em andamento, concluida e cancelada com linhas e relacoes auxiliares. |

## Cenarios obrigatorios

| Cenario | Passos | Resultado esperado |
| --- | --- | --- |
| Criar atividade | Abrir nova atividade, informar nome, tipo e projeto. Se o projeto tiver extrafields de talhao, cultura e cultivar, nao preencher esses campos manualmente. | Registro salvo com `ref`, status planejado e talhao/area/cultura/cultivar vindos do projeto. |
| Aba Insumos | Abrir a aba Insumos, clicar Adicionar, preencher o modal com produto, armazem, movimento, area executada e dose. Conferir quantidade calculada e salvar. | Linha aparece na lista e movimento em `llx_stock_mouvement` com `origintype = 'safra_activity'`; `fk_stock_movement` gravado na linha. |
| Alterar insumo | Clicar no lapis da linha, alterar dose ou quantidade executada no modal e salvar. | Movimento anterior estornado e novo movimento gravado, sem duplicar saldo. |
| Remover insumo | Clicar na lixeira da linha e confirmar. | Movimento ativo estornado e linha removida. |
| Aba Calda | Abrir Calculo de calda, informar vazao por ha e capacidade do tanque, clicar Atualizar calculo e recarregar a aba. | Tela persiste area, vazao, tanque, volume total, numero de tanques e area por tanque; tabela mostra quantidade de cada insumo por tanque. |
| Abas opcionais | Em Equipe, Veiculos e Implementos, clicar Adicionar, preencher o modal e salvar. Depois editar pelo lapis e remover pela lixeira. | Cada aba salva somente seus dados, sem exigir que o produtor preencha as outras abas. |
| Iniciar | Acionar **Iniciar atividade**. | Status `In Progress`. |
| Concluir | Acionar **Concluir atividade** ou salvar/concluir pela aba Geral. | Status `Completed`; movimentos existentes permanecem consistentes. |
| Cancelar | Acionar **Cancelar atividade**. | Status `Canceled` e movimentos ativos estornados. |
| Excluir | Acionar **Excluir atividade**. | Registro removido; nao deve criar, fechar ou excluir tarefa de projeto automaticamente. |

## Verificacoes complementares

1. Permissoes:
   - `safra->SafraActivity->read` para listar/visualizar.
   - `safra->SafraActivity->write` para mutacoes.
   - `safra->SafraActivity->delete` para exclusao.
2. Estoque:
   - conferir `llx_stock_mouvement.origintype = 'safra_activity'`.
   - conferir que `llx_safra_activity_line.fk_stock_movement` aponta para o movimento ativo.
3. Projetos:
   - validar os extrafields aceitos para talhao: `fk_talhao`, `fk_fieldplot`, `talhao` ou `fieldplot`, com ou sem prefixo `options_`.
   - validar cultura/cultivar quando existirem no projeto.
4. Triggers:
   - eventos `SAFRA_ACTIVITY_*` sem duplicidade.
5. API:
   - `GET /api/index.php/sfactivities?sortfield=ref`
   - `GET /api/index.php/sfactivities/{id}?include_lines=1`
   - `POST /api/index.php/sfactivities/{id}/start|complete|cancel`

## Observacoes

- O legado `safra_aplicacao*` foi removido nesta linha de desenvolvimento.
- Tarefa do Dolibarr e apenas vinculo opcional para tempo/referencia, nao fonte da verdade da atividade agricola.
- Enquanto nao houver base produtiva de cliente ativo, migracoes destrutivas de Activity estao autorizadas para corrigir o modelo.
- Qualquer relatorio SQL antigo deve ser migrado para `safra_activity*`.
