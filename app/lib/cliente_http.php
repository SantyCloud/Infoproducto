<?php
declare(strict_types=1);

/*
 * Llamadas a APIs externas (Meta, Resend) con curl.
 * En las pruebas se reemplazan por un simulador: nunca se llama a internet desde los tests.
 */

/**
 * POST con cuerpo JSON. Devuelve [bool $ok, string $respuesta, int $codigoHttp].
 * $ok es true si la API respondió 2xx.
 */
function http_post_json(string $url, array $datos, array $cabeceras = [], int $segundos = 10): array
{
    $simulador = http_simulador();
    if ($simulador !== null) {
        return $simulador($url, $datos, $cabeceras);
    }
    if (!function_exists('curl_init')) {
        return [false, 'Falta la extensión curl de PHP.', 0];
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => (string) json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', ...$cabeceras],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $segundos,
    ]);
    $respuesta = curl_exec($curl);
    $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($respuesta === false) {
        return [false, 'Error de conexión: ' . curl_error($curl), 0];
    }
    return [$codigo >= 200 && $codigo < 300, (string) $respuesta, $codigo];
}

/** Reemplaza las llamadas reales por una función (solo para pruebas). Sin argumentos, devuelve la actual. */
function http_simulador(?callable $simulador = null, bool $quitar = false): ?callable
{
    static $actual = null;
    if ($simulador !== null || $quitar) {
        $actual = $quitar ? null : $simulador;
    }
    return $actual;
}
