<?php
declare(strict_types=1);

/*
 * Peticiones, respuestas y enrutador.
 * Una respuesta es un array ['estado' => 200, 'cabeceras' => [...], 'cuerpo' => '...'].
 * Las funciones de app/paginas/ devuelven una respuesta y despachar() la envía al navegador.
 */

function respuesta(string $cuerpo, int $estado = 200, array $cabeceras = []): array
{
    return ['estado' => $estado, 'cabeceras' => $cabeceras, 'cuerpo' => $cuerpo];
}

function html(string $cuerpo, int $estado = 200): array
{
    return respuesta($cuerpo, $estado, ['Content-Type' => 'text/html; charset=UTF-8']);
}

function redireccion(string $url, int $estado = 302): array
{
    return respuesta('', $estado, ['Location' => $url]);
}

function pagina_error(int $estado, string $titulo, string $mensaje): array
{
    return html(vista('error', ['titulo' => $titulo, 'mensaje' => $mensaje]), $estado);
}

/** "/admin/?x=1" → "/admin". La portada es "/". */
function ruta_de(string $uri): string
{
    $ruta = parse_url($uri, PHP_URL_PATH);
    return '/' . trim(is_string($ruta) ? $ruta : '', '/');
}

/**
 * Busca qué función atiende la petición.
 * $rutas es una lista de [método, patrón, función]; el patrón admite {parametros}.
 * Devuelve ['funcion' => ..., 'parametros' => [...]] o ['estado' => 404|405].
 */
function resolver_ruta(array $rutas, string $metodo, string $ruta): array
{
    $metodo = $metodo === 'HEAD' ? 'GET' : $metodo;
    $existeConOtroMetodo = false;
    foreach ($rutas as [$metodoRuta, $patron, $funcion]) {
        // "/leccion/{slug}" → "#^/leccion/(?P<slug>[A-Za-z0-9_-]+)$#"
        $regex = '#^' . preg_replace('/\\\\\{([a-z_]+)\\\\\}/', '(?P<$1>[A-Za-z0-9_-]+)', preg_quote($patron, '#')) . '$#';
        if (!preg_match($regex, $ruta, $partes)) {
            continue;
        }
        if ($metodoRuta !== $metodo) {
            $existeConOtroMetodo = true;
            continue;
        }
        return ['funcion' => $funcion, 'parametros' => array_filter($partes, 'is_string', ARRAY_FILTER_USE_KEY)];
    }
    return ['estado' => $existeConOtroMetodo ? 405 : 404];
}

/** Atiende la petición actual y envía la respuesta al navegador. */
function despachar(array $rutas): void
{
    try {
        $respuesta = atender($rutas, $_SERVER['REQUEST_METHOD'] ?? 'GET', ruta_de($_SERVER['REQUEST_URI'] ?? '/'));
    } catch (Throwable $error) {
        $respuesta = respuesta_de_error($error);
    }
    enviar_respuesta($respuesta);
}

function atender(array $rutas, string $metodo, string $ruta): array
{
    $aHttps = redireccion_a_https();
    if ($aHttps !== null) {
        return $aHttps;
    }
    $destino = resolver_ruta($rutas, $metodo, $ruta);
    return match ($destino['estado'] ?? null) {
        404 => pagina_error(404, 'Página no encontrada', 'La página que buscas no existe.'),
        405 => pagina_error(405, 'Método no permitido', 'Esta dirección no acepta ese tipo de petición.'),
        default => ($destino['funcion'])(...$destino['parametros']),
    };
}

/** Registra el error y devuelve una página 500 (con detalles solo fuera de producción). */
function respuesta_de_error(Throwable $error): array
{
    registrar('errores', $error->getMessage(), [
        'donde' => $error->getFile() . ':' . $error->getLine(),
        'ruta' => $_SERVER['REQUEST_URI'] ?? '',
    ]);
    $detalle = es_produccion()
        ? 'Inténtalo de nuevo en unos minutos.'
        : $error->getMessage() . ' — ' . $error->getFile() . ':' . $error->getLine();
    try {
        return pagina_error(500, 'Algo salió mal', $detalle);
    } catch (Throwable) {
        return respuesta('Error interno', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

function enviar_respuesta(array $respuesta): void
{
    header_remove('X-Powered-By');
    http_response_code($respuesta['estado']);
    foreach ($respuesta['cabeceras'] + cabeceras_seguridad() as $nombre => $valor) {
        header($nombre . ': ' . $valor);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $respuesta['cuerpo'];
    }
}

/** Cabeceras que protegen al visitante en todas las páginas. */
function cabeceras_seguridad(): array
{
    $cabeceras = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Frame-Options' => 'DENY',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Content-Security-Policy' => politica_csp(),
    ];
    if (es_https()) {
        $cabeceras['Strict-Transport-Security'] = 'max-age=31536000';
    }
    return $cabeceras;
}

/**
 * Política de seguridad de contenido: el navegador solo carga recursos de la propia web.
 * Se ampliará en la fase 6 (Pixel de Meta) y en la 4 (videos de YouTube/Drive).
 */
function politica_csp(): string
{
    $nonce = csp_nonce();
    return implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-$nonce'",
        "style-src 'self' 'nonce-$nonce'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]);
}

/** Valor aleatorio por petición que autoriza nuestros <script>/<style> en línea: <style nonce="<?= csp_nonce() ?>">. */
function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** En producción, si la web tiene https, cualquier visita por http se redirige a https. */
function redireccion_a_https(): ?array
{
    $url = config('app.url');
    if (PHP_SAPI === 'cli' || !es_produccion() || !str_starts_with($url, 'https://') || es_https()) {
        return null;
    }
    return redireccion($url . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
}
