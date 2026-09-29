-- 003 · Primera vez que cada comprador entró al curso. La política de reembolsos depende de eso:
-- quien ya entró recibió el contenido digital. (Los enlaces usados y las sesiones se borran con el tiempo.)

ALTER TABLE compradores ADD COLUMN primer_ingreso_en TEXT;

-- Compradores anteriores: la fecha más antigua que quede de un enlace usado, una sesión o una lección vista
UPDATE compradores SET primer_ingreso_en = MIN(
    COALESCE((SELECT MIN(usado_en) FROM tokens_login WHERE comprador_id = compradores.id), '9999'),
    COALESCE((SELECT MIN(creado_en) FROM sesiones WHERE tipo = 'miembro' AND comprador_id = compradores.id), '9999'),
    COALESCE((SELECT MIN(vista_en) FROM progreso WHERE comprador_id = compradores.id), '9999')
);
UPDATE compradores SET primer_ingreso_en = NULL WHERE primer_ingreso_en = '9999';
