-- 004 · De qué página por país vino cada clic a WhatsApp (ec, mx…; vacío = página general).
-- Sirve para saber en qué moneda registrar la venta y qué anuncios de cada país venden.

ALTER TABLE leads ADD COLUMN pais TEXT;
