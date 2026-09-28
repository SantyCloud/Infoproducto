<?php
declare(strict_types=1);

/*
 * Registro de sucesos (logs) en storage/logs/.
 */

/**
 * Añade una línea JSON a storage/logs/{canal}-AAAA-MM.log.
 * Canales: errores, meta, emails…
 */
function registrar(string $canal, string $mensaje, array $contexto = []): void
{
    $linea = json_encode(
        ['fecha' => gmdate('Y-m-d H:i:s'), 'mensaje' => $mensaje] + $contexto,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    $carpeta = config('logs.carpeta');
    if (!is_dir($carpeta)) {
        @mkdir($carpeta, 0775, true);
    }
    $archivo = $carpeta . '/' . $canal . '-' . gmdate('Y-m') . '.log';
    if (@file_put_contents($archivo, $linea . "\n", FILE_APPEND | LOCK_EX) === false) {
        error_log("[$canal] $linea"); // si no se puede escribir, al log de PHP
    }
}
