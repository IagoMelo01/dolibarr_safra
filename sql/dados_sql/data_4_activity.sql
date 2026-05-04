-- Safra agricultural activity seed data for the rebuilt schema.

INSERT INTO llx_safra_activity (
    rowid,
    entity,
    ref,
    label,
    type,
    status,
    priority,
    progress,
    season,
    crop_name,
    cultivar_name,
    fk_project,
    fk_task,
    fk_thirdparty,
    fk_fieldplot,
    area_planned,
    area_done,
    area_total,
    date_planned_start,
    date_planned_end,
    date_start,
    date_end,
    weather,
    note_public,
    note_private,
    date_creation,
    fk_user_creat,
    fk_user_modif
) VALUES
    (9001, 1, 'ACT-PLANTIO-001', 'Plantio soja talhao piloto', 'plantio', 1, 1, 0, '2026/2027', 'Soja', 'Piloto', NULL, NULL, NULL, NULL, 10.00, 0.00, 10.00, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), NULL, NULL, NULL, 'Planejamento inicial.', NULL, NOW(), 1, 1),
    (9002, 1, 'ACT-ADUBO-001', 'Adubacao de cobertura', 'fertilizacao', 2, 2, 40, '2026/2027', 'Milho', 'Hibrido A', NULL, NULL, NULL, NULL, 8.00, 3.20, 8.00, NOW(), DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NULL, NULL, 'Execucao em andamento.', NULL, NOW(), 1, 1),
    (9003, 1, 'ACT-COLHEITA-001', 'Colheita area demonstrativa', 'colheita', 3, 1, 100, '2025/2026', 'Soja', 'Demo', NULL, NULL, NULL, NULL, 15.75, 15.75, 15.75, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), NULL, 'Atividade concluida para auditoria.', NULL, NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    type = VALUES(type),
    status = VALUES(status),
    priority = VALUES(priority),
    progress = VALUES(progress),
    season = VALUES(season),
    crop_name = VALUES(crop_name),
    cultivar_name = VALUES(cultivar_name),
    area_planned = VALUES(area_planned),
    area_done = VALUES(area_done),
    area_total = VALUES(area_total),
    note_public = VALUES(note_public),
    fk_user_modif = VALUES(fk_user_modif);

INSERT INTO llx_safra_activity_line (
    rowid,
    entity,
    fk_activity,
    position,
    fk_product,
    fk_warehouse,
    movement_type,
    area_planned,
    area_done,
    dose_planned,
    dose_done,
    dose_unit,
    qty_planned,
    qty_done,
    total,
    area_applied,
    dose,
    unit_cost,
    note,
    date_creation,
    fk_user_creat,
    fk_user_modif
) VALUES
    (9501, 1, 9001, 1, 1, 1, 'consume', 10.00, 0.00, 55.00, 0.00, 'kg/ha', 550.00, 0.00, 550.00, 10.00, 55.00, 0.00, NULL, NOW(), 1, 1),
    (9502, 1, 9002, 1, 1, 1, 'consume', 8.00, 3.20, 180.00, 180.00, 'kg/ha', 1440.00, 576.00, 576.00, 3.20, 180.00, 0.00, NULL, NOW(), 1, 1),
    (9503, 1, 9003, 1, 1, 2, 'consume', 15.75, 15.75, 0.45, 0.45, 'L/ha', 7.0875, 7.0875, 7.0875, 15.75, 0.45, 0.00, NULL, NOW(), 1, 1)
ON DUPLICATE KEY UPDATE
    fk_activity = VALUES(fk_activity),
    position = VALUES(position),
    fk_product = VALUES(fk_product),
    fk_warehouse = VALUES(fk_warehouse),
    movement_type = VALUES(movement_type),
    area_planned = VALUES(area_planned),
    area_done = VALUES(area_done),
    dose_planned = VALUES(dose_planned),
    dose_done = VALUES(dose_done),
    dose_unit = VALUES(dose_unit),
    qty_planned = VALUES(qty_planned),
    qty_done = VALUES(qty_done),
    total = VALUES(total),
    fk_user_modif = VALUES(fk_user_modif);

INSERT IGNORE INTO llx_safra_activity_vehicle (entity, fk_activity, fk_vehicle, vehicle_class, planned_hours, done_hours, date_creation)
VALUES
    (1, 9001, 101, 'Veiculo', 4.00, 0.00, NOW()),
    (1, 9002, 102, 'Veiculo', 3.00, 1.50, NOW());

INSERT IGNORE INTO llx_safra_activity_implement (entity, fk_activity, fk_implement, implement_class, planned_hours, done_hours, date_creation)
VALUES
    (1, 9001, 201, 'Implemento', 4.00, 0.00, NOW()),
    (1, 9002, 202, 'Implemento', 3.00, 1.50, NOW());

INSERT IGNORE INTO llx_safra_activity_user (entity, fk_activity, fk_user, role, planned_hours, done_hours, date_creation)
VALUES
    (1, 9001, 2, 'Operador', 4.00, 0.00, NOW()),
    (1, 9001, 3, 'Auxiliar', 4.00, 0.00, NOW()),
    (1, 9002, 4, 'Operador', 3.00, 1.50, NOW());
