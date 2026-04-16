-- Safra activity workflow seed data (canonical schema).

INSERT INTO llx_safra_activity (
    rowid,
    entity,
    ref,
    label,
    fk_project,
    fk_task,
    fk_thirdparty,
    fk_fieldplot,
    area_total,
    type,
    status,
    note_public,
    note_private,
    date_creation,
    fk_user_creat,
    fk_user_modif
) VALUES
    (9001, 1, 'ACT-DRAFT', 'Atividade em rascunho', NULL, NULL, NULL, NULL, 10.00, 'aplicacao', 0, 'Rascunho para validação do fluxo.', 'Nota privada rascunho', NOW(), 1, 1),
    (9002, 1, 'ACT-INPROGRESS', 'Atividade em andamento', NULL, NULL, NULL, NULL, 8.00, 'fertilizacao', 3, 'Atividade iniciada.', 'Nota privada em andamento', NOW(), 1, 1),
    (9003, 1, 'ACT-COMPLETED', 'Atividade concluída', NULL, NULL, NULL, NULL, 15.75, 'colheita', 1, 'Atividade concluída para auditoria.', 'Nota privada concluída', NOW(), 1, 1),
    (9004, 1, 'ACT-CANCELED', 'Atividade cancelada', NULL, NULL, NULL, NULL, 5.00, 'monitoramento', 2, 'Atividade cancelada para testes.', 'Nota privada cancelada', NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    type = VALUES(type),
    status = VALUES(status),
    area_total = VALUES(area_total),
    note_public = VALUES(note_public),
    note_private = VALUES(note_private),
    fk_user_modif = VALUES(fk_user_modif);

INSERT INTO llx_safra_activity_line (
    rowid,
    entity,
    fk_activity,
    fk_product,
    area_applied,
    dose,
    dose_unit,
    total,
    movement_type,
    fk_warehouse,
    date_creation,
    fk_user_creat,
    fk_user_modif
) VALUES
    (9501, 1, 9001, 1, 10.00, 0.50, 'L/ha', 5.00, 'consume', 1, NOW(), 1, 1),
    (9502, 1, 9002, 1, 8.00, 0.30, 'L/ha', 2.40, 'consume', 1, NOW(), 1, 1),
    (9503, 1, 9002, 1, 0.00, 0.00, '', 1.00, 'return', 1, NOW(), 1, 1),
    (9504, 1, 9003, 1, 15.75, 0.45, 'L/ha', 7.09, 'consume', 2, NOW(), 1, 1),
    (9505, 1, 9004, 1, 5.00, 0.20, 'L/ha', 1.00, 'return', 1, NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
    fk_activity = VALUES(fk_activity),
    fk_product = VALUES(fk_product),
    area_applied = VALUES(area_applied),
    dose = VALUES(dose),
    dose_unit = VALUES(dose_unit),
    total = VALUES(total),
    movement_type = VALUES(movement_type),
    fk_warehouse = VALUES(fk_warehouse),
    fk_user_modif = VALUES(fk_user_modif);

INSERT IGNORE INTO llx_safra_activity_machine (entity, fk_activity, fk_machine, date_creation)
VALUES
    (1, 9001, 101, NOW()),
    (1, 9002, 102, NOW());

INSERT IGNORE INTO llx_safra_activity_implement (entity, fk_activity, fk_implement, date_creation)
VALUES
    (1, 9001, 201, NOW()),
    (1, 9002, 202, NOW());

INSERT IGNORE INTO llx_safra_activity_user (entity, fk_activity, fk_user, date_creation)
VALUES
    (1, 9001, 2, NOW()),
    (1, 9001, 3, NOW()),
    (1, 9002, 4, NOW());