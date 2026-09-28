<?php
declare(strict_types=1);

/*
 * Datos de la visita: IP, navegador, bots y de qué anuncio viene (UTM y fbclid).
 * La atribución se guarda en cookies al entrar a la landing y se lee al tocar el botón de WhatsApp.
 */

const CAMPOS_ATRIBUCION = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid'];

function ip_cliente(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (config('app.confiar_proxy')) {
        $reenviada = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($reenviada !== '') {
            $ip = trim(explode(',', $reenviada)[0]);
        }
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function agente_usuario(): string
{
    return limpiar($_SERVER['HTTP_USER_AGENT'] ?? '', 500);
}

/** ¿Es un robot (el revisor de anuncios de Meta, buscadores, vistas previas de enlaces…)? */
function es_bot(string $agente): bool
{
    return $agente === ''
        || (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|meta-external|preview|headless|lighthouse|curl|wget|python|httpclient|okhttp|java\//i', $agente);
}

/** Un identificador de visitante válido: 32 caracteres hexadecimales. */
function visitante_valido(mixed $id): bool
{
    return is_string($id) && (bool) preg_match('/^[a-f0-9]{32}$/', $id);
}

function base64url(string $datos): string
{
    return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
}

function base64url_decodificar(string $texto): string
{
    return (string) base64_decode(strtr($texto, '-_', '+/'), true);
}

/** Extrae los UTM y el fbclid de los parámetros de una URL. */
function atribucion_de(array $consulta): array
{
    $datos = [];
    foreach (CAMPOS_ATRIBUCION as $campo) {
        $valor = limpiar($consulta[$campo] ?? '', $campo === 'fbclid' ? 500 : 200);
        if ($valor !== '') {
            $datos[$campo] = $valor;
        }
    }
    return $datos;
}

/** Lee la atribución guardada en la cookie 'atrib' (validando todo lo que trae). */
function atribucion_guardada(array $cookies): array
{
    $datos = json_decode(base64url_decodificar((string) ($cookies['atrib'] ?? '')), true);
    if (!is_array($datos)) {
        return [];
    }
    $limpios = atribucion_de($datos);
    if (isset($datos['url'])) {
        $limpios['url'] = limpiar($datos['url'], 500);
    }
    return $limpios;
}

/**
 * Añade a la respuesta de la landing las cookies de seguimiento:
 * - 'vis': identificador del visitante (para no crear un lead nuevo cada vez que toca el botón).
 * - 'atrib': UTM y fbclid del anuncio (solo si la visita los trae; si no, se conserva lo anterior).
 * - '_fbc': identificador de clic de Meta, en el formato oficial, si viene un fbclid y no existía.
 */
function con_cookies_de_visita(array $respuesta, array $consulta, array $cookies, string $urlActual): array
{
    $atribucion = atribucion_de($consulta);
    if ($atribucion) {
        $atribucion['url'] = limpiar($urlActual, 500);
        $respuesta = con_cookie($respuesta, 'atrib', base64url((string) json_encode($atribucion)), 30 * 86400);
        if (isset($atribucion['fbclid']) && empty($cookies['_fbc'])) {
            $fbc = 'fb.1.' . (int) floor(microtime(true) * 1000) . '.' . $atribucion['fbclid'];
            $respuesta = con_cookie($respuesta, '_fbc', $fbc, 90 * 86400, httpOnly: false);
        }
    }
    if (!visitante_valido($cookies['vis'] ?? null)) {
        $respuesta = con_cookie($respuesta, 'vis', bin2hex(random_bytes(16)), 365 * 86400);
    }
    return $respuesta;
}

/** URL completa de la petición actual (con su consulta), usando el dominio configurado. */
function url_actual(): string
{
    return config('app.url') . limpiar($_SERVER['REQUEST_URI'] ?? '/', 1000);
}
