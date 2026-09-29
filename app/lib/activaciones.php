<?php
declare(strict_types=1);

/*
 * Pagos por activar (enlaces de activación).
 *
 * Cobras por WhatsApp, registras el pago en el panel y te da un enlace (tudominio.com/activar/K7Q2M-8XPRT)
 * para enviárselo al cliente. Él lo abre, escribe su nombre y su email y entra al curso: en ese momento
 * se crean el comprador, la venta y su acceso, se avisa a Meta (Purchase) y le llega el email de bienvenida.
 *
 * - El código tiene 10 caracteres (unos 50 bits) y solo se guarda su hash, como los demás tokens.
 * - Sirve una sola vez: se marca como usado en la misma transacción que crea la venta. Vence a los 30 días.
 * - Probar códigos al azar está limitado por IP (ver miembro_activar_limitado()). No hay tope global: con él,
 *   cualquiera podría dejar sin activar a todos los compradores equivocándose a propósito desde varias IP.
 * - Si el email ya es de un comprador, NO se abre esa cuenta: la compra se le suma, se cierran sus sesiones
 *   abiertas y el enlace para entrar le llega a su correo. Así nadie entra a una cuenta ajena escribiendo un
 *   email que no es suyo, ni se queda en ella si la ocupó antes que su dueño.
 */

const LARGO_CODIGO_ACTIVACION = 10;
const VALIDEZ_ACTIVACION = 30 * 86400;

/** Lo que escribe el cliente ("k7q2m 8xprt", "K7Q2M-8XPRT") → "K7Q2M8XPRT"; null si no puede ser un código. */
function normalizar_codigo_activacion(mixed $texto): ?string
{
    $codigo = strtoupper((string) preg_replace('/[\s-]/', '', limpiar($texto, 40)));
    $valido = strlen($codigo) === LARGO_CODIGO_ACTIVACION && strspn($codigo, ALFABETO_CODIGOS) === LARGO_CODIGO_ACTIVACION;
    return $valido ? $codigo : null;
}

/** "K7Q2M8XPRT" → "K7Q2M-8XPRT", más fácil de leer y de dictar. */
function formatear_codigo_activacion(string $codigo): string
{
    return substr($codigo, 0, 5) . '-' . substr($codigo, 5);
}

/**
 * Versión del enlace actual de un pago: va oculta en el botón "Enlace nuevo" del panel. Si el navegador
 * reenvía ese formulario (recargar, volver atrás), la versión ya no coincide y no se crea otro enlace:
 * el que ya enviaste al cliente sigue sirviendo.
 */
function version_enlace_activacion(array $activacion): string
{
    return substr((string) $activacion['codigo_hash'], 0, 16);
}

function hash_codigo_activacion(string $codigo): string
{
    return hash_token('activacion:' . $codigo);
}

function enlace_activacion(string $codigo): string
{
    return config('app.url') . '/activar/' . formatear_codigo_activacion($codigo);
}

/** Un código que no tiene ninguna activación, ni de ahora ni pasada. */
function codigo_activacion_libre(): string
{
    for ($intento = 0; $intento < 10; $intento++) {
        $codigo = generar_codigo(LARGO_CODIGO_ACTIVACION);
        if (!db_valor('SELECT 1 FROM activaciones WHERE codigo_hash = ?', [hash_codigo_activacion($codigo)])) {
            return $codigo;
        }
    }
    throw new RuntimeException('No se encontró un código de activación libre.');
}

function activacion_por_id(int $id): ?array
{
    return db_fila('SELECT * FROM activaciones WHERE id = ?', [$id]);
}

function activacion_por_clave(?string $clave): ?array
{
    return $clave === null ? null : db_fila('SELECT * FROM activaciones WHERE clave_formulario = ?', [$clave]);
}

/** Pagos que el cliente todavía no activa (sin contar los anulados), del más nuevo al más viejo. */
function activaciones_pendientes(): array
{
    return db_filas(
        'SELECT a.*, l.codigo AS codigo_clic FROM activaciones a LEFT JOIN leads l ON l.id = a.lead_id
         WHERE a.usado_en IS NULL AND a.anulado_en IS NULL ORDER BY a.id DESC LIMIT 100'
    );
}

/**
 * Registra un pago por activar (datos ya validados con venta_validar) y devuelve
 * ['repetida' => bool, 'activacion' => fila, 'codigo' => 'K7Q2M8XPRT' o null, 'enlace' => URL o null],
 * o ['error' => mensaje] si otro formulario ya registró un pago de ese clic (dos pestañas a la vez).
 * El código solo se conoce ahora: si se pierde, se crea otro con activacion_nuevo_codigo().
 */
function activacion_crear(array $datos): array
{
    $repetida = activacion_por_clave($datos['clave_formulario']);
    if ($repetida !== null) {
        return activacion_ya_creada($repetida);
    }
    $codigo = codigo_activacion_libre();
    try {
        $id = db_transaccion(function () use ($datos, $codigo): int|string {
            // El clic se vuelve a revisar dentro de la transacción: dos formularios del mismo clic enviados
            // a la vez no pueden registrar dos pagos
            if ($datos['lead'] !== null && ($ocupado = lead_ya_pagado($datos['lead'], $datos['clave_formulario'])) !== null) {
                return $ocupado;
            }
            return db_insertar('activaciones', [
                'codigo_hash' => hash_codigo_activacion($codigo),
                'lead_id' => $datos['lead']['id'] ?? null,
                'monto_centavos' => (int) round((float) $datos['monto'] * 100),
                'moneda' => $datos['moneda'] ?? 'USD',
                'metodo_pago' => $datos['metodo_pago'] ?: null,
                'referencia_pago' => $datos['referencia_pago'] ?: null,
                'whatsapp' => $datos['whatsapp'] ?: null,
                'clave_formulario' => $datos['clave_formulario'],
                'creado_en' => ahora_bd(),
                'expira_en' => gmdate('Y-m-d H:i:s', time() + VALIDEZ_ACTIVACION),
            ]);
        });
    } catch (PDOException $error) {
        // Dos envíos simultáneos del mismo formulario: el segundo choca con la restricción UNIQUE
        $repetida = activacion_por_clave($datos['clave_formulario']);
        if ($repetida !== null) {
            return activacion_ya_creada($repetida);
        }
        throw $error;
    }
    if (is_string($id)) {
        return ['error' => $id];
    }
    return ['repetida' => false, 'activacion' => activacion_por_id($id), 'codigo' => $codigo, 'enlace' => enlace_activacion($codigo)];
}

function activacion_ya_creada(array $activacion): array
{
    return ['repetida' => true, 'activacion' => $activacion, 'codigo' => null, 'enlace' => null];
}

/** El pago por activar de ese código si todavía sirve (sin usar, sin anular y sin vencer); si no, null. */
function activacion_vigente(string $codigo): ?array
{
    return db_fila(
        'SELECT * FROM activaciones WHERE codigo_hash = ? AND usado_en IS NULL AND anulado_en IS NULL AND expira_en > ?',
        [hash_codigo_activacion($codigo), ahora_bd()]
    );
}

/**
 * El cliente activa su acceso. En UNA transacción se marca el código como usado (solo una petición puede
 * hacerlo, aunque lleguen dos a la vez) y se crean el comprador, la venta y el acceso. El email de bienvenida
 * y el evento de Meta salen después de responder.
 *
 * Devuelve lo mismo que venta_crear() más 'cuenta_existente' (el email ya era de un comprador: no se abre
 * su cuenta aquí, el enlace le llega por email), o null si el código ya no servía.
 */
function activacion_canjear(string $codigo, string $nombre, string $email): ?array
{
    $resultado = db_transaccion(function () use ($codigo, $nombre, $email): ?array {
        $activacion = activacion_vigente($codigo);
        $marcada = $activacion === null ? 0 : db_ejecutar(
            'UPDATE activaciones SET usado_en = ? WHERE id = ? AND usado_en IS NULL AND anulado_en IS NULL AND expira_en > ?',
            [ahora_bd(), $activacion['id'], ahora_bd()]
        );
        if ($marcada !== 1) {
            return null;
        }
        $existente = comprador_por_email($email);
        $comprador = $existente ?? comprador_guardar($nombre, $email, $activacion['whatsapp']);
        if ($existente !== null) {
            // Quien tenga una sesión abierta en esa cuenta (por ejemplo, alguien que la ocupó con un email
            // ajeno) sale: el dueño del email entra con el enlace que le llega a su correo
            comprador_cerrar_sesiones((int) $existente['id']);
        }
        $lead = $activacion['lead_id'] === null ? null : db_fila('SELECT * FROM leads WHERE id = ?', [$activacion['lead_id']]);
        if ($lead !== null && db_valor('SELECT 1 FROM ventas WHERE lead_id = ?', [$lead['id']])) {
            $lead = null; // ese clic ya tiene otra venta (el panel no lo permite, pero por si acaso)
        }
        $resultado = venta_crear($comprador, [
            'lead' => $lead,
            'monto_centavos' => (int) $activacion['monto_centavos'],
            'moneda' => $activacion['moneda'],
            'metodo_pago' => $activacion['metodo_pago'],
            'referencia_pago' => $activacion['referencia_pago'],
            'clave_formulario' => $activacion['clave_formulario'],
            'creado_en' => $activacion['creado_en'], // la venta es del día en que confirmaste el pago
        ]);
        db_ejecutar('UPDATE activaciones SET venta_id = ? WHERE id = ?', [$resultado['venta']['id'], $activacion['id']]);
        return $resultado + ['cuenta_existente' => $existente !== null];
    });
    if ($resultado === null) {
        return null;
    }

    $comprador = $resultado['comprador'];
    $enlace = $resultado['enlace'];
    $claveEmail = 'acceso-venta-' . $resultado['venta']['id'];
    $eventoMeta = $resultado['evento_meta'];
    despues_de_responder(function () use ($comprador, $enlace, $claveEmail, $eventoMeta): void {
        email_acceso($comprador, $enlace, $claveEmail);
        if ($eventoMeta !== null) {
            meta_enviar_evento($eventoMeta);
        }
    });
    return $resultado;
}

/**
 * ¿Ese código se acaba de activar con ese email? (el cliente tocó dos veces el botón, o volvió atrás
 * y lo envió otra vez). Así se le dice que ya está activado, en vez de que el código "no sirve".
 */
function activacion_recien_usada(string $codigo, string $email): bool
{
    return (bool) db_valor(
        'SELECT 1 FROM activaciones a JOIN ventas v ON v.id = a.venta_id JOIN compradores c ON c.id = v.comprador_id
         WHERE a.codigo_hash = ? AND a.usado_en >= ? AND c.email = ?',
        [hash_codigo_activacion($codigo), gmdate('Y-m-d H:i:s', time() - 30 * 60), strtolower($email)]
    );
}

/**
 * Código nuevo para un pago que aún no se activa (el cliente perdió el enlace o se venció):
 * el anterior deja de servir y el plazo vuelve a empezar. Con $version (la del enlace que se veía al tocar
 * el botón), solo si el enlace sigue siendo ese: así recargar la página no anula el que ya enviaste.
 * Null si no se cambió (ya se activó, se anuló o ya tenía otro enlace nuevo).
 */
function activacion_nuevo_codigo(int $id, ?string $version = null): ?array
{
    $codigo = codigo_activacion_libre();
    $cambiada = db_ejecutar(
        'UPDATE activaciones SET codigo_hash = ?, expira_en = ? WHERE id = ? AND usado_en IS NULL AND anulado_en IS NULL'
            . ($version !== null ? ' AND substr(codigo_hash, 1, 16) = ?' : ''),
        [hash_codigo_activacion($codigo), gmdate('Y-m-d H:i:s', time() + VALIDEZ_ACTIVACION), $id, ...($version !== null ? [$version] : [])]
    );
    if ($cambiada !== 1) {
        return null;
    }
    return ['repetida' => false, 'activacion' => activacion_por_id($id), 'codigo' => $codigo, 'enlace' => enlace_activacion($codigo)];
}

/** Anula un pago que aún no se activa (devolución, error al registrarlo…). False si ya se activó o se anuló. */
function activacion_anular(int $id): bool
{
    return db_ejecutar(
        'UPDATE activaciones SET anulado_en = ? WHERE id = ? AND usado_en IS NULL AND anulado_en IS NULL',
        [ahora_bd(), $id]
    ) === 1;
}

/** Mensaje para enviarle al cliente por WhatsApp su enlace (contenido/negocio.php → whatsapp → mensaje_activacion). */
function mensaje_activacion(string $codigo): string
{
    $negocio = contenido('negocio');
    $plantilla = (string) ($negocio['whatsapp']['mensaje_activacion'] ?? '')
        ?: "¡Gracias por tu compra! 🎉\n\nActiva tu acceso a {producto} aquí: {enlace}\n\nSolo te pedirá tu nombre y tu email.";
    return texto($plantilla, variables_texto($negocio) + [
        '{enlace}' => enlace_activacion($codigo),
        '{codigo}' => formatear_codigo_activacion($codigo),
    ]);
}
