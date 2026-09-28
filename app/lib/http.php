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
    return respuesta($cuerpo, $estado, [
        'Content-Type' => 'text/html; charset=UTF-8',
        // Cada página lleva un nonce distinto (CSP): no debe guardarse en ninguna caché compartida
        'Cache-Control' => 'no-cache, private',
        'X-LiteSpeed-Cache-Control' => 'no-cache',
    ]);
}

/** Añade una cookie a la respuesta. $segundos < 0 la borra; 0 = hasta cerrar el navegador. */
function con_cookie(array $respuesta, string $nombre, string $valor, int $segundos, bool $httpOnly = true, string $sameSite = 'Lax', string $ruta = '/'): array
{
    $respuesta['cookies'][] = [$nombre, $valor, [
        'expires' => $segundos > 0 ? time() + $segundos : ($segundos < 0 ? 1 : 0),
        'path' => $ruta,
        'secure' => str_starts_with(config('app.url'), 'https://') || es_https(),
        'httponly' => $httpOnly,
        'samesite' => $sameSite,
    ]];
    return $respuesta;
}

function sin_cookie(array $respuesta, string $nombre, string $ruta = '/'): array
{
    return con_cookie($respuesta, $nombre, '', -1, ruta: $ruta);
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

/** Atiende la petición actual, envía la respuesta y luego ejecuta las tareas pendientes. */
function despachar(array $rutas): void
{
    try {
        $respuesta = atender($rutas, $_SERVER['REQUEST_METHOD'] ?? 'GET', ruta_de($_SERVER['REQUEST_URI'] ?? '/'));
    } catch (Throwable $error) {
        $respuesta = respuesta_de_error($error);
    }
    enviar_respuesta($respuesta);
    ejecutar_tareas_de_fondo();
}

/** Lista de tareas para después de responder (el visitante no espera por ellas). */
function &tareas_de_fondo(): array
{
    static $tareas = [];
    return $tareas;
}

/** Programa una tarea (ej. avisar a Meta) para cuando el visitante ya tenga su respuesta. */
function despues_de_responder(callable $tarea): void
{
    $tareas = &tareas_de_fondo();
    $tareas[] = $tarea;
}

function ejecutar_tareas_de_fondo(): void
{
    $tareas = &tareas_de_fondo();
    $pendientes = $tareas;
    $tareas = [];
    if (!$pendientes) {
        return;
    }
    // Cierra la conexión con el navegador para que no espere (PHP-FPM o LiteSpeed, el de Hostinger)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }
    foreach ($pendientes as $tarea) {
        try {
            $tarea();
        } catch (Throwable $error) {
            registrar('errores', 'Falló una tarea de fondo: ' . $error->getMessage(), [
                'donde' => $error->getFile() . ':' . $error->getLine(),
            ]);
        }
    }
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
    foreach ($respuesta['cookies'] ?? [] as [$nombre, $valor, $opciones]) {
        setcookie($nombre, $valor, $opciones);
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
 * Política de seguridad de contenido: el navegador solo carga recursos de la propia web,
 * más los orígenes que cada página autorice con csp_permitir() (Pixel de Meta, videos…).
 */
function politica_csp(): string
{
    $nonce = csp_nonce();
    $politica = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'nonce-$nonce'"],
        'style-src' => ["'self'", "'nonce-$nonce'"],
        'img-src' => ["'self'", 'data:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'"],
        'frame-src' => ["'none'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'none'"],
    ];
    foreach (csp_permitir() as $directiva => $origenes) {
        $politica[$directiva] = array_values(array_unique([...array_diff($politica[$directiva] ?? [], ["'none'"]), ...$origenes]));
    }
    return implode('; ', array_map(fn (string $d, array $o) => $d . ' ' . implode(' ', $o), array_keys($politica), $politica));
}

/** Autoriza un origen externo en la CSP de esta respuesta. Sin argumentos, devuelve lo autorizado. */
function csp_permitir(?string $directiva = null, string ...$origenes): array
{
    static $permitidos = [];
    if ($directiva !== null) {
        $permitidos[$directiva] = array_values(array_unique([...($permitidos[$directiva] ?? []), ...$origenes]));
    }
    return $permitidos;
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
