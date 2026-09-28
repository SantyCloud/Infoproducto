<?php
declare(strict_types=1);

/*
 * Toda la configuración en un solo lugar (sale del .env).
 * Uso: config('app.url'), config('db.ruta').
 */

function config(string $clave): mixed
{
    $valor = config_todo();
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            throw new InvalidArgumentException("Configuración desconocida: $clave");
        }
        $valor = $valor[$parte];
    }
    return $valor;
}

/** Arma la configuración la primera vez que se pide (o de nuevo si $recargar es true). */
function config_todo(bool $recargar = false): array
{
    static $config = null;
    if ($config === null || $recargar) {
        $config = [
            'app' => [
                'entorno' => env('ENTORNO', 'produccion'),
                'url' => rtrim((string) env('URL_SITIO', 'http://localhost:8000'), '/'),
                'clave' => (string) env('CLAVE_APP', ''),
                'zona_horaria' => (string) env('ZONA_HORARIA', 'America/Guayaquil'),
                // Solo si la web está detrás de un proxy/CDN que envía la IP real en X-Forwarded-For
                'confiar_proxy' => env('CONFIAR_PROXY', 'false') === 'true',
            ],
            'db' => [
                'ruta' => ruta_proyecto((string) env('RUTA_BD', 'storage/base.sqlite')),
            ],
            'admin' => [
                'usuario' => (string) env('ADMIN_USUARIO', ''),
                'clave_hash' => (string) env('ADMIN_CLAVE_HASH', ''),
            ],
            'email' => [
                'resend_api_key' => (string) env('RESEND_API_KEY', ''),
                'remitente' => (string) env('EMAIL_REMITENTE', ''),
                'responder_a' => (string) env('EMAIL_RESPONDER_A', ''),
            ],
            'meta' => [
                'pixel_id' => (string) env('META_PIXEL_ID', ''),
                'token' => (string) env('META_CAPI_TOKEN', ''),
                'test_event_code' => (string) env('META_TEST_EVENT_CODE', ''),
                'version' => (string) env('META_GRAPH_VERSION', 'v25.0'),
            ],
        ];
    }
    return $config;
}

/** Convierte una ruta relativa a la carpeta del proyecto en absoluta (las absolutas se dejan igual). */
function ruta_proyecto(string $ruta): string
{
    return preg_match('#^([A-Za-z]:)?[/\\\\]#', $ruta) ? $ruta : RAIZ . '/' . $ruta;
}

function es_produccion(): bool
{
    return config('app.entorno') === 'produccion';
}
