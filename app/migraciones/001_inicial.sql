-- 001 · Tablas iniciales.
-- Fechas en UTC con formato 'AAAA-MM-DD HH:MM:SS'. Dinero en centavos.

-- Cada visitante que toca el botón de WhatsApp. El código viaja en el mensaje
-- y permite saber qué anuncio trajo la venta.
CREATE TABLE leads (
    id              INTEGER PRIMARY KEY,
    codigo          TEXT    NOT NULL UNIQUE,
    event_id        TEXT    NOT NULL UNIQUE,  -- mismo id en el Pixel y en la API de Conversiones: Meta no lo cuenta dos veces
    visitante_id    TEXT,                     -- cookie del navegador: si vuelve a tocar el botón, conserva su código
    boton           TEXT,                     -- qué botón tocó (hero, precio, flotante…)
    utm_source      TEXT,
    utm_medium      TEXT,
    utm_campaign    TEXT,
    utm_content     TEXT,
    utm_term        TEXT,
    fbclid          TEXT,
    fbc             TEXT,
    fbp             TEXT,
    ip              TEXT,                     -- la IP y el navegador solo sirven para Meta: se borran a los 90 días
    user_agent      TEXT,
    url_origen      TEXT,
    referer         TEXT,
    clics           INTEGER NOT NULL DEFAULT 1,
    creado_en       TEXT    NOT NULL,
    ultimo_clic_en  TEXT    NOT NULL
);
CREATE INDEX leads_por_fecha ON leads (creado_en);
CREATE INDEX leads_por_visitante ON leads (visitante_id);

-- Personas que compraron o a las que diste acceso.
CREATE TABLE compradores (
    id              INTEGER PRIMARY KEY,
    nombre          TEXT    NOT NULL,
    email           TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    whatsapp        TEXT,
    notas           TEXT,
    creado_en       TEXT    NOT NULL,
    actualizado_en  TEXT    NOT NULL
);

-- Ventas registradas en el panel admin después de cobrar por WhatsApp.
CREATE TABLE ventas (
    id                INTEGER PRIMARY KEY,
    comprador_id      INTEGER NOT NULL REFERENCES compradores (id),
    lead_id           INTEGER UNIQUE REFERENCES leads (id),  -- un lead solo puede convertirse en una venta
    monto_centavos    INTEGER NOT NULL CHECK (monto_centavos >= 0),
    moneda            TEXT    NOT NULL DEFAULT 'USD',
    metodo_pago       TEXT,
    referencia_pago   TEXT,                                  -- nº de transferencia, id de PayPal…
    clave_formulario  TEXT    UNIQUE,                        -- evita registrar dos veces la misma venta (doble clic, recargar)
    creado_en         TEXT    NOT NULL
);
CREATE INDEX ventas_por_comprador ON ventas (comprador_id);
CREATE INDEX ventas_por_fecha ON ventas (creado_en);

-- Quién puede entrar al área de miembros. Sin venta asociada = acceso dado a mano.
CREATE TABLE accesos (
    id              INTEGER PRIMARY KEY,
    comprador_id    INTEGER NOT NULL REFERENCES compradores (id),
    producto        TEXT    NOT NULL DEFAULT 'curso',
    venta_id        INTEGER REFERENCES ventas (id),
    otorgado_en     TEXT    NOT NULL,
    revocado_en     TEXT,
    UNIQUE (comprador_id, producto)
);

-- Enlaces mágicos de acceso. Solo se guarda el hash: el token real viaja únicamente en el enlace.
CREATE TABLE tokens_login (
    id              INTEGER PRIMARY KEY,
    comprador_id    INTEGER NOT NULL REFERENCES compradores (id),
    token_hash      TEXT    NOT NULL UNIQUE,
    proposito       TEXT    NOT NULL CHECK (proposito IN ('primer_acceso', 'login')),
    creado_en       TEXT    NOT NULL,
    expira_en       TEXT    NOT NULL,
    usado_en        TEXT
);
CREATE INDEX tokens_login_por_comprador ON tokens_login (comprador_id);

-- Sesiones abiertas (miembros y admin). Solo se guarda el hash del token de la cookie.
CREATE TABLE sesiones (
    id              INTEGER PRIMARY KEY,
    token_hash      TEXT    NOT NULL UNIQUE,
    tipo            TEXT    NOT NULL CHECK (tipo IN ('miembro', 'admin')),
    comprador_id    INTEGER REFERENCES compradores (id),
    ip              TEXT,
    user_agent      TEXT,
    creado_en       TEXT    NOT NULL,
    ultimo_uso_en   TEXT    NOT NULL,
    expira_en       TEXT    NOT NULL,
    CHECK ((tipo = 'miembro') = (comprador_id IS NOT NULL))
);
CREATE INDEX sesiones_por_comprador ON sesiones (comprador_id);

-- Eventos enviados a Meta (API de Conversiones), para revisarlos y reintentarlos.
CREATE TABLE eventos_meta (
    id              INTEGER PRIMARY KEY,
    evento          TEXT    NOT NULL,        -- Contact, Purchase
    event_id        TEXT    NOT NULL UNIQUE,
    lead_id         INTEGER REFERENCES leads (id),
    venta_id        INTEGER REFERENCES ventas (id),
    datos             TEXT    NOT NULL,      -- JSON enviado (email y teléfono ya van en hash)
    estado            TEXT    NOT NULL DEFAULT 'pendiente'
                      CHECK (estado IN ('pendiente', 'enviando', 'enviado', 'error', 'descartado')),
    intentos          INTEGER NOT NULL DEFAULT 0,
    ultimo_intento_en TEXT,
    respuesta         TEXT,
    creado_en         TEXT    NOT NULL,
    enviado_en        TEXT
);
CREATE INDEX eventos_meta_por_estado ON eventos_meta (estado);

-- Emails enviados con Resend.
CREATE TABLE emails (
    id              INTEGER PRIMARY KEY,
    comprador_id    INTEGER REFERENCES compradores (id),
    tipo            TEXT    NOT NULL,        -- acceso, login
    destinatario    TEXT    NOT NULL,
    proveedor_id    TEXT,                    -- id que devuelve Resend
    estado          TEXT    NOT NULL CHECK (estado IN ('enviado', 'simulado', 'error')),
    error           TEXT,
    creado_en       TEXT    NOT NULL
);
CREATE INDEX emails_por_comprador ON emails (comprador_id);

-- Límite de intentos (inicios de sesión, clics…) por IP o por email, para frenar abusos.
CREATE TABLE limites (
    clave           TEXT    PRIMARY KEY,
    contador        INTEGER NOT NULL,
    ventana_inicio  INTEGER NOT NULL         -- segundos Unix
);
