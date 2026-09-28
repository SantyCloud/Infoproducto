<?php
declare(strict_types=1);

/*
 * Leads: cada persona que toca el botón de WhatsApp. El código corto que viaja en el mensaje
 * permite saber, al registrar la venta, de qué anuncio vino.
 */

// Sin 0/O ni 1/I/L, para que no se confundan al leerlos en el chat
const ALFABETO_CODIGOS = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

function generar_codigo(int $largo = 4): string
{
    $codigo = '';
    for ($i = 0; $i < $largo; $i++) {
        $codigo .= ALFABETO_CODIGOS[random_int(0, strlen(ALFABETO_CODIGOS) - 1)];
    }
    return $codigo;
}

/** Normaliza lo que el admin escribe como código: "k7q2 " → "K7Q2". */
function normalizar_codigo(string $codigo): string
{
    return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $codigo));
}

function nuevo_event_id(): string
{
    $hex = bin2hex(random_bytes(16));
    return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
}

/** ¿Es aceptable el id de evento que manda el navegador? */
function event_id_valido(mixed $id): bool
{
    return is_string($id) && (bool) preg_match('/^[A-Za-z0-9-]{16,64}$/', $id);
}

/**
 * Reúne los datos de la visita que toca el botón. Separado de lead_registrar() para poder probarlo.
 * $consulta = $_GET, $cookies = $_COOKIE, $servidor = $_SERVER.
 */
function datos_de_la_visita(array $consulta, array $cookies, array $servidor): array
{
    $atribucion = atribucion_de($consulta) ?: atribucion_guardada($cookies);
    $referer = limpiar($servidor['HTTP_REFERER'] ?? '', 1000);
    $mismoSitio = $referer !== '' && parse_url($referer, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST);
    return [
        'visitante_id' => visitante_valido($cookies['vis'] ?? null) ? $cookies['vis'] : bin2hex(random_bytes(16)),
        'boton' => limpiar(preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($consulta['b'] ?? ''))) ?? '', 30),
        'event_id' => event_id_valido($consulta['eid'] ?? null) ? $consulta['eid'] : nuevo_event_id(),
        'atribucion' => $atribucion,
        'fbc' => limpiar($cookies['_fbc'] ?? '', 600) ?: null,
        'fbp' => limpiar($cookies['_fbp'] ?? '', 200) ?: null,
        'ip' => ip_cliente(),
        'user_agent' => agente_usuario(),
        'url_origen' => $mismoSitio ? $referer : ($atribucion['url'] ?? config('app.url') . '/'),
        'referer' => $referer,
    ];
}

/**
 * Registra el clic. Si el mismo visitante tiene un lead de los últimos 7 días que aún no compró,
 * reutiliza su código. Devuelve la fila del lead más 'nuevo' => true|false.
 */
function lead_registrar(array $visita): array
{
    return db_transaccion(function () use ($visita): array {
        $ahora = ahora_bd();
        $existente = db_fila(
            'SELECT l.id FROM leads l LEFT JOIN ventas v ON v.lead_id = l.id
             WHERE l.visitante_id = ? AND l.creado_en >= ? AND v.id IS NULL
             ORDER BY l.id DESC LIMIT 1',
            [$visita['visitante_id'], gmdate('Y-m-d H:i:s', time() - 7 * 86400)]
        );
        if ($existente !== null) {
            db_ejecutar(
                'UPDATE leads SET clics = clics + 1, ultimo_clic_en = ?, fbc = COALESCE(fbc, ?), fbp = COALESCE(fbp, ?) WHERE id = ?',
                [$ahora, $visita['fbc'], $visita['fbp'], $existente['id']]
            );
            return ['nuevo' => false] + db_fila('SELECT * FROM leads WHERE id = ?', [$existente['id']]);
        }

        $eventId = $visita['event_id'];
        if (db_valor('SELECT 1 FROM leads WHERE event_id = ?', [$eventId])) {
            $eventId = nuevo_event_id(); // un id repetido no debe impedir registrar al lead
        }
        $a = $visita['atribucion'];
        $id = db_insertar('leads', [
            'codigo' => codigo_libre(),
            'event_id' => $eventId,
            'visitante_id' => $visita['visitante_id'],
            'boton' => $visita['boton'] ?: null,
            'utm_source' => $a['utm_source'] ?? null,
            'utm_medium' => $a['utm_medium'] ?? null,
            'utm_campaign' => $a['utm_campaign'] ?? null,
            'utm_content' => $a['utm_content'] ?? null,
            'utm_term' => $a['utm_term'] ?? null,
            'fbclid' => $a['fbclid'] ?? null,
            'fbc' => $visita['fbc'],
            'fbp' => $visita['fbp'],
            'ip' => $visita['ip'] ?: null,
            'user_agent' => $visita['user_agent'] ?: null,
            'url_origen' => $visita['url_origen'],
            'referer' => $visita['referer'] ?: null,
            'creado_en' => $ahora,
            'ultimo_clic_en' => $ahora,
        ]);
        return ['nuevo' => true] + db_fila('SELECT * FROM leads WHERE id = ?', [$id]);
    });
}

/** Un código que todavía no usa ningún lead (4 caracteres; 5 si hubiera muchos choques). */
function codigo_libre(): string
{
    for ($intento = 0; $intento < 30; $intento++) {
        $codigo = generar_codigo($intento < 15 ? 4 : 5);
        if (!db_valor('SELECT 1 FROM leads WHERE codigo = ?', [$codigo])) {
            return $codigo;
        }
    }
    throw new RuntimeException('No se encontró un código libre para el lead.');
}

/** Enlace de WhatsApp con el mensaje ya escrito (y el código, si hay). */
function enlace_whatsapp(?string $codigo, ?array $negocio = null): string
{
    $negocio ??= contenido('negocio');
    $whatsapp = $negocio['whatsapp'];
    $mensaje = texto($whatsapp['mensaje'], variables_texto($negocio));
    if ($codigo !== null && !empty($whatsapp['texto_codigo'])) {
        $mensaje .= ' ' . strtr($whatsapp['texto_codigo'], ['{codigo}' => $codigo]);
    }
    return 'https://wa.me/' . preg_replace('/\D/', '', (string) $whatsapp['numero']) . '?text=' . rawurlencode($mensaje);
}
