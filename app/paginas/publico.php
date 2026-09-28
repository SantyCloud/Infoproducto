<?php
declare(strict_types=1);

/*
 * Páginas públicas (no necesitan sesión): landing, botón de WhatsApp y páginas legales.
 */

/** Landing de venta. */
function pagina_inicio(): array
{
    $respuesta = html(vista('landing', datos_landing(contenido('negocio'), contenido('landing')), 'layout_landing'));
    return con_cookies_de_visita($respuesta, $_GET, $_COOKIE, url_actual());
}

/** Todo lo que necesita la plantilla de la landing (separado para poder probarla con otros datos). */
function datos_landing(array $negocio, array $crudo): array
{
    $garantia = (int) ($negocio['garantia_dias'] ?? 0) > 0;
    $landing = textos($garantia ? $crudo : sin_menciones_de_garantia($crudo), variables_texto($negocio));

    return [
        'titulo' => $landing['seo']['titulo'],
        'descripcion' => $landing['seo']['descripcion'],
        'url' => config('app.url') . '/',
        'pixel_id' => meta_pixel_para_esta_pagina(),
        'l' => $landing,
        'negocio' => $negocio,
        'promo' => promo_vigente($negocio),
        'precio' => formatear_precio(precio_actual($negocio)),
        'precio_normal' => formatear_precio($negocio['precio_normal']),
        'tiempo_promo' => texto_tiempo_promo($negocio),
        'descuento' => porcentaje_descuento($negocio),
        'garantia' => $garantia,
        'capturas' => [
            'demanda' => capturas($landing['demanda']['capturas'] ?? 'mensajes'),
            'resultados' => capturas($landing['resultados']['capturas'] ?? 'ingresos'),
            'testimonios' => capturas($landing['testimonios']['capturas'] ?? 'testimonio'),
        ],
        'mostrar_huecos' => !es_produccion(),
    ];
}

/**
 * Botón de WhatsApp: registra el lead (con su código) y manda a WhatsApp.
 * Pase lo que pase con el registro, la persona SIEMPRE llega a WhatsApp.
 */
function pagina_whatsapp(): array
{
    $lead = null;
    $visita = datos_de_la_visita($_GET, $_COOKIE, $_SERVER);
    try {
        if (!es_bot($visita['user_agent']) && limite_permitir('wa:' . ip_para_limites($visita['ip']), 30, 3600)) {
            $lead = lead_registrar($visita);
        }
    } catch (Throwable $error) {
        registrar('errores', 'No se pudo registrar el lead: ' . $error->getMessage());
    }
    if ($lead !== null && $lead['nuevo']) {
        meta_contact_para_lead($lead);
    }

    $respuesta = redireccion(enlace_whatsapp($lead['codigo'] ?? null));
    $respuesta['cabeceras']['Cache-Control'] = 'no-store';
    $respuesta['cabeceras']['X-Robots-Tag'] = 'noindex';
    if ($lead !== null && !visitante_valido($_COOKIE['vis'] ?? null)) {
        $respuesta = con_cookie($respuesta, 'vis', $lead['visitante_id'], 365 * 86400);
    }
    return $respuesta;
}

function pagina_terminos(): array
{
    return pagina_legal('terminos');
}

function pagina_privacidad(): array
{
    return pagina_legal('privacidad');
}

function pagina_reembolsos(): array
{
    return pagina_legal('reembolsos');
}

/** Muestra un documento de contenido/legal/ (Markdown con variables). */
function pagina_legal(string $documento): array
{
    $archivo = RAIZ . '/contenido/legal/' . $documento . '.md';
    if (!is_file($archivo)) {
        return pagina_error(404, 'Página no encontrada', 'Este documento todavía no está disponible.');
    }
    $markdown = texto((string) file_get_contents($archivo));
    $titulo = preg_match('/^#\s+(.+)$/m', $markdown, $m) ? trim($m[1]) : ucfirst($documento);
    return html(vista('legal', [
        'titulo' => $titulo . ' · ' . contenido('negocio')['producto'],
        'html' => markdown($markdown),
    ]));
}
