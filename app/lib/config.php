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
            ],
            'db' => [
                'ruta' => ruta_proyecto((string) env('RUTA_BD', 'storage/base.sqlite')),
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
