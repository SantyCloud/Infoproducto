<?php
declare(strict_types=1);

/*
 * Lectura del archivo .env (configuración y claves secretas).
 */

/** Carga las variables del archivo .env, si existe. */
function env_cargar(string $archivo): void
{
    env_valores(is_file($archivo) ? env_parsear((string) file_get_contents($archivo)) : []);
}

/**
 * Convierte el texto de un .env en [CLAVE => valor].
 * Admite líneas vacías, comentarios con # y valores entre comillas.
 */
function env_parsear(string $texto): array
{
    $valores = [];
    foreach (preg_split('/\R/', $texto) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
        if (preg_match('/^(["\'])(.*)\1$/', $valor, $partes)) {
            $valor = $partes[2]; // entre comillas: se respeta tal cual, incluso un #
        } else {
            $valor = trim((string) preg_replace('/\s#.*$/', '', $valor)); // quita el comentario final
        }
        $valores[$clave] = $valor;
    }
    return $valores;
}

/** Guarda (si se le pasan) y devuelve los valores leídos del .env. */
function env_valores(?array $nuevos = null): array
{
    static $valores = [];
    if ($nuevos !== null) {
        $valores = $nuevos;
    }
    return $valores;
}

/**
 * Devuelve una variable de configuración. Las variables del sistema tienen prioridad
 * sobre el .env (así las pruebas pueden usar su propia configuración).
 * Un valor vacío cuenta como no definido y devuelve $defecto.
 */
function env(string $clave, ?string $defecto = null): ?string
{
    $valor = getenv($clave);
    if ($valor === false) {
        $valor = env_valores()[$clave] ?? null;
    }
    return ($valor === null || $valor === '') ? $defecto : $valor;
}

/**
 * Pone CLAVE=valor en el texto de un .env: reemplaza la línea si existe o la agrega al final.
 * El valor va entre comillas simples para que se lea tal cual (sirve para hashes con $).
 */
function env_poner(string $texto, string $clave, string $valor): string
{
    $linea = $clave . "='" . str_replace("'", '', $valor) . "'";
    $patron = '/^' . preg_quote($clave, '/') . '=.*$/m';
    if (preg_match($patron, $texto)) {
        return (string) preg_replace_callback($patron, fn () => $linea, $texto, 1);
    }
    return rtrim($texto, "\n") . "\n" . $linea . "\n";
}
