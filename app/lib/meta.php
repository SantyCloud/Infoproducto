<?php
declare(strict_types=1);

/*
 * Meta (Facebook/Instagram): Pixel en el navegador + API de Conversiones desde el servidor.
 *
 * - Contact: cuando alguien toca el botón de WhatsApp. Lleva el mismo event_id que el Pixel,
 *   así Meta lo cuenta una sola vez.
 * - Purchase: cuando registras la venta en el panel, con los datos del clic original
 *   (cookies de Meta, IP, navegador) más el email y el teléfono en hash (SHA-256).
 *
 * Cada evento se guarda en eventos_meta y se envía al momento. Si falla, bin/tareas.php lo reintenta.
 */

function meta_pixel_id(): ?string
{
    $id = config('meta.pixel_id');
    return preg_match('/^\d{5,20}$/', $id) ? $id : null;
}

function meta_capi_activa(): bool
{
    return meta_pixel_id() !== null && config('meta.token') !== '';
}

/** Si hay Pixel configurado, autoriza sus dominios en la CSP y devuelve su id para la plantilla. */
function meta_pixel_para_esta_pagina(): ?string
{
    $id = meta_pixel_id();
    if ($id !== null) {
        csp_permitir('script-src', 'https://connect.facebook.net');
        csp_permitir('img-src', 'https://www.facebook.com');
        csp_permitir('connect-src', 'https://www.facebook.com', 'https://connect.facebook.net');
    }
    return $id;
}

/* ---------- Normalización y hash (reglas de Meta: minúsculas, sin espacios, SHA-256) ---------- */

function meta_hash(?string $valor): ?string
{
    return $valor === null || $valor === '' ? null : hash('sha256', $valor);
}

function meta_normalizar_email(?string $email): ?string
{
    $email = strtolower(trim((string) $email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/** Solo dígitos, con código de país (ej. 593991234567). */
function meta_normalizar_telefono(?string $telefono): ?string
{
    $digitos = (string) preg_replace('/\D/', '', (string) $telefono);
    return strlen($digitos) >= 8 ? $digitos : null;
}

/** Nombre en minúsculas, sin signos de puntuación (se conservan tildes y ñ, en UTF-8). */
function meta_normalizar_nombre(?string $nombre): ?string
{
    $nombre = trim((string) preg_replace('/[^\p{L}\s-]/u', '', (string) $nombre));
    $nombre = function_exists('mb_strtolower') ? mb_strtolower($nombre, 'UTF-8') : strtolower($nombre);
    return $nombre === '' ? null : $nombre;
}

/**
 * Datos de la persona para Meta. Lo identificable (email, teléfono, nombre) va en hash;
 * la IP, el navegador y las cookies de Meta (fbc, fbp) van tal cual, como pide la API.
 */
function meta_datos_usuario(?array $lead, ?array $comprador = null): array
{
    $datos = [];
    if ($comprador !== null) {
        $partes = preg_split('/\s+/', trim((string) $comprador['nombre']), 2) ?: [];
        $datos['em'] = meta_hash(meta_normalizar_email($comprador['email']));
        $datos['ph'] = meta_hash(meta_normalizar_telefono($comprador['whatsapp'] ?? null));
        $datos['fn'] = meta_hash(meta_normalizar_nombre($partes[0] ?? null));
        $datos['ln'] = meta_hash(meta_normalizar_nombre($partes[1] ?? null));
        $datos['external_id'] = hash('sha256', 'comprador-' . $comprador['id']);
    }
    if ($lead !== null) {
        if (!empty($lead['visitante_id'])) {
            $datos['external_id'] = hash('sha256', $lead['visitante_id']); // mismo id en Contact y Purchase
        }
        $datos['client_ip_address'] = $lead['ip'] ?: null;
        $datos['client_user_agent'] = $lead['user_agent'] ?: null;
        $datos['fbc'] = $lead['fbc'] ?: (empty($lead['fbclid']) ? null
            : 'fb.1.' . (strtotime($lead['creado_en'] . ' UTC') * 1000) . '.' . $lead['fbclid']);
        $datos['fbp'] = $lead['fbp'] ?: null;
    }
    return array_filter($datos, fn (?string $valor) => $valor !== null && $valor !== '');
}

/* ---------- Eventos ---------- */

/** Contact: alguien tocó el botón de WhatsApp (se envía después de redirigirlo). */
function meta_contact_para_lead(array $lead): ?int
{
    if (!meta_capi_activa()) {
        return null;
    }
    $id = meta_encolar('Contact', [
        'event_name' => 'Contact',
        'event_time' => strtotime($lead['creado_en'] . ' UTC'),
        'event_id' => $lead['event_id'],
        'action_source' => 'website',
        'event_source_url' => $lead['url_origen'],
        'user_data' => meta_datos_usuario($lead),
    ], (int) $lead['id'], null);
    despues_de_responder(fn () => meta_enviar_evento($id));
    return $id;
}

/**
 * Purchase: venta registrada en el panel. Si vino por la web (hay lead), se envía como evento
 * web con los datos de ese clic; si no, como venta por chat con el email y el teléfono.
 */
function meta_purchase_para_venta(array $venta, array $comprador, ?array $lead): ?int
{
    if (!meta_capi_activa()) {
        return null;
    }
    $desdeLaWeb = $lead !== null && !empty($lead['user_agent']) && !empty($lead['url_origen']);
    $evento = [
        'event_name' => 'Purchase',
        'event_time' => time(),
        'event_id' => 'venta-' . $venta['id'],
        'action_source' => $desdeLaWeb ? 'website' : 'chat',
        'user_data' => meta_datos_usuario($lead, $comprador),
        'custom_data' => [
            'currency' => $venta['moneda'],
            'value' => round($venta['monto_centavos'] / 100, 2),
            'content_name' => contenido('negocio')['producto'],
            'order_id' => (string) $venta['id'],
        ],
    ];
    if ($desdeLaWeb) {
        $evento['event_source_url'] = $lead['url_origen'];
    }
    return meta_encolar('Purchase', $evento, $lead !== null ? (int) $lead['id'] : null, (int) $venta['id']);
}

/** Guarda el evento (una sola vez por event_id) y devuelve su id en eventos_meta. */
function meta_encolar(string $nombre, array $evento, ?int $leadId, ?int $ventaId): int
{
    $existente = db_valor('SELECT id FROM eventos_meta WHERE event_id = ?', [$evento['event_id']]);
    if ($existente !== null) {
        return (int) $existente;
    }
    return db_insertar('eventos_meta', [
        'evento' => $nombre,
        'event_id' => $evento['event_id'],
        'lead_id' => $leadId,
        'venta_id' => $ventaId,
        'datos' => (string) json_encode($evento, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'estado' => 'pendiente',
        'creado_en' => ahora_bd(),
    ]);
}

/** Envía un evento guardado. Devuelve true si Meta lo aceptó. */
function meta_enviar_evento(int $id): bool
{
    // Reserva el evento: si otro proceso ya lo está enviando, no se manda dos veces
    $reservado = db_ejecutar(
        "UPDATE eventos_meta SET estado = 'enviando', intentos = intentos + 1, ultimo_intento_en = ?
         WHERE id = ? AND estado IN ('pendiente', 'error')",
        [ahora_bd(), $id]
    );
    if ($reservado === 0 || !meta_capi_activa()) {
        return false;
    }
    $evento = json_decode((string) db_valor('SELECT datos FROM eventos_meta WHERE id = ?', [$id]), true);
    $url = 'https://graph.facebook.com/' . rawurlencode(config('meta.version')) . '/' . meta_pixel_id()
        . '/events?access_token=' . rawurlencode(config('meta.token'));
    $cuerpo = ['data' => [$evento]];
    if (config('meta.test_event_code') !== '') {
        $cuerpo['test_event_code'] = config('meta.test_event_code');
    }
    [$ok, $respuesta] = http_post_json($url, $cuerpo, [], 8);
    db_ejecutar(
        'UPDATE eventos_meta SET estado = ?, respuesta = ?, enviado_en = ? WHERE id = ?',
        [$ok ? 'enviado' : 'error', limpiar($respuesta, 2000), $ok ? ahora_bd() : null, $id]
    );
    if (!$ok) {
        registrar('meta', 'Meta no aceptó el evento', ['id' => $id, 'respuesta' => limpiar($respuesta, 500)]);
    }
    return $ok;
}

/**
 * Reintenta los eventos que fallaron o quedaron a medias (lo llama bin/tareas.php).
 * Meta solo acepta eventos web de los últimos 7 días: los más viejos se descartan.
 */
function meta_reintentar_pendientes(): array
{
    db_ejecutar(
        "UPDATE eventos_meta SET estado = 'error' WHERE estado = 'enviando' AND ultimo_intento_en < ?",
        [gmdate('Y-m-d H:i:s', time() - 600)]
    );
    db_ejecutar(
        "UPDATE eventos_meta SET estado = 'descartado' WHERE estado IN ('pendiente', 'error') AND (creado_en < ? OR intentos >= 8)",
        [gmdate('Y-m-d H:i:s', time() - 6 * 86400)]
    );
    $resultado = ['enviados' => 0, 'fallidos' => 0];
    foreach (db_filas("SELECT id FROM eventos_meta WHERE estado IN ('pendiente', 'error') ORDER BY id LIMIT 50") as $fila) {
        meta_enviar_evento((int) $fila['id']) ? $resultado['enviados']++ : $resultado['fallidos']++;
    }
    return $resultado;
}
