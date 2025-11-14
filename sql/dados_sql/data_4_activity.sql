-- Safra activity workflow seed data
-- Provides ready-made scenarios covering validation, in-progress, completion and cancellation states.

INSERT INTO llx_safra_activity (
    rowid,
    entity,
    ref,
    label,
    activity_type,
    date_activity,
    qty,
    amount,
    fk_soc,
    fk_project,
    fk_task,
    description,
    note_public,
    note_private,
    mixture_note,
    date_creation,
    fk_user_creat,
    status
) VALUES
    (9001, 1, 'ACT-VALIDATED', 'Aplicação validada', 'aplicacao', DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY), 12.50, 150.00, NULL, NULL, NULL, 'Cenário de validação disponível para testes.', 'Observação pública validação', 'Observação privada validação', 'Mistura fungicida', NOW(), 1, 1),
    (9002, 1, 'ACT-STARTED', 'Aplicação iniciada', 'aplicacao', DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY), 8.00, 95.00, NULL, NULL, NULL, 'Cenário de atividade em andamento.', 'Observação pública início', 'Observação privada início', 'Mistura herbicida', NOW(), 1, 2),
    (9003, 1, 'ACT-COMPLETED', 'Aplicação concluída', 'aplicacao', DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY), 15.75, 210.00, NULL, NULL, NULL, 'Cenário de atividade concluída para auditoria.', 'Observação pública concluído', 'Observação privada concluído', 'Mistura inseticida', NOW(), 1, 3),
    (9004, 1, 'ACT-CANCELED', 'Aplicação cancelada', 'aplicacao', CURRENT_DATE, 5.00, 0.00, NULL, NULL, NULL, 'Cenário de atividade cancelada.', 'Observação pública cancelado', 'Observação privada cancelado', 'Mistura cancelada', NOW(), 1, 9);

INSERT INTO llx_safra_activity_line (
    rowid,
    entity,
    fk_activity,
    fk_product,
    fk_formulated_product,
    fk_technical_product,
    fk_warehouse,
    fk_unit,
    label,
    dose,
    dose_unit,
    area_ha,
    total_qty,
    note,
    movement,
    movement_type,
    date_creation
) VALUES
    (9501, 1, 9001, 1, NULL, NULL, 1, NULL, 'Fungicida validado', 0.50, 'L/ha', 10.00, 5.00, 'Linha validada', 1, 'consume', NOW()),
    (9502, 1, 9002, 1, NULL, NULL, 1, NULL, 'Herbicida em aplicação', 0.30, 'L/ha', 8.00, 2.40, 'Linha em andamento', 1, 'consume', NOW()),
    (9503, 1, 9002, NULL, NULL, NULL, 1, NULL, 'Ajuste de estoque retorno', 0.00, '', 0.00, 1.00, 'Reabastecimento parcial', 0, 'return', NOW()),
    (9504, 1, 9003, 1, NULL, NULL, 2, NULL, 'Inseticida concluído', 0.45, 'L/ha', 15.75, 7.09, 'Linha concluída', 1, 'consume', NOW()),
    (9505, 1, 9004, 1, NULL, NULL, 1, NULL, 'Mistura cancelada', 0.20, 'L/ha', 5.00, 1.00, 'Linha cancelada', 0, 'return', NOW());

INSERT INTO llx_safra_activity_fleet (
    rowid,
    entity,
    fk_activity,
    resource_type,
    fk_fleet_equipment,
    fk_user_responsible,
    planned_hours,
    note,
    date_creation
) VALUES
    (9801, 1, 9001, 'vehicle', 101, 1, 4.0, 'Trator principal para aplicação', NOW()),
    (9802, 1, 9002, 'implement', 202, NULL, 3.5, 'Pulverizador acoplado', NOW());

INSERT INTO llx_safra_activity_team (
    rowid,
    entity,
    fk_activity,
    fk_user,
    planned_hours,
    is_responsible,
    note,
    date_creation
) VALUES
    (9901, 1, 9001, 2, 6.0, 1, 'Supervisor da aplicação', NOW()),
    (9902, 1, 9001, 3, 6.0, 0, 'Auxiliar operacional', NOW()),
    (9903, 1, 9002, 4, 5.0, 1, 'Responsável pela calibração', NOW());
