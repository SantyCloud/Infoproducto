-- 005 · Pagos por activar. Cuando registras un pago sin el email del cliente, el panel te da un enlace de
-- activación (tudominio.com/activar/K7Q2-M8XP) para enviárselo por WhatsApp. Al abrirlo, el cliente escribe
-- su nombre y su email: en ese momento se crean el comprador, la venta y su acceso.

CREATE TABLE activaciones (
    id                INTEGER PRIMARY KEY,
    codigo_hash       TEXT    NOT NULL UNIQUE,                -- solo el hash: el código viaja únicamente en el enlace
    lead_id           INTEGER REFERENCES leads (id),
    monto_centavos    INTEGER NOT NULL CHECK (monto_centavos >= 0),
    moneda            TEXT    NOT NULL DEFAULT 'USD',
    metodo_pago       TEXT,
    referencia_pago   TEXT,
    whatsapp          TEXT,                                   -- si lo escribiste: para enviarle el enlace y para Meta
    clave_formulario  TEXT    UNIQUE,                         -- el mismo formulario enviado dos veces no crea dos enlaces
    creado_en         TEXT    NOT NULL,                       -- cuando confirmaste el pago (es la fecha de la venta)
    expira_en         TEXT    NOT NULL,
    usado_en          TEXT,                                   -- cuando el cliente lo activó
    venta_id          INTEGER REFERENCES ventas (id),         -- la venta que se creó al activarlo
    anulado_en        TEXT                                    -- lo anulaste (devolución, error…)
);
-- Un clic solo puede tener un pago sin anular
CREATE UNIQUE INDEX activaciones_por_lead ON activaciones (lead_id) WHERE anulado_en IS NULL;
