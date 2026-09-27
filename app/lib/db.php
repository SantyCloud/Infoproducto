<?php
declare(strict_types=1);

/*
 * Base de datos SQLite. Todas las consultas van con parámetros (?, :nombre):
 * nunca se concatenan datos que vengan del usuario.
 */

/** Conexión única por petición. Pasándole un PDO se reemplaza (lo usan las pruebas). */
function db(?PDO $reemplazo = null): PDO
{
    static $pdo = null;
    if ($reemplazo !== null) {
        $pdo = $reemplazo;
    }
    return $pdo ??= db_conectar(config('db.ruta'));
}

function db_conectar(string $ruta): PDO
{
    $pdo = new PDO('sqlite:' . $ruta, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');    // respeta las relaciones entre tablas
    $pdo->exec('PRAGMA busy_timeout = 5000');  // si otra petición está escribiendo, espera hasta 5 s
    $pdo->exec('PRAGMA journal_mode = WAL');   // permite leer mientras otro escribe
    $pdo->exec('PRAGMA synchronous = NORMAL'); // seguro con WAL y más rápido
    return $pdo;
}

/** Primera fila del resultado, o null si no hay. */
function db_fila(string $sql, array $parametros = []): ?array
{
    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);
    $fila = $consulta->fetch();
    return $fila === false ? null : $fila;
}

/** Todas las filas del resultado. */
function db_filas(string $sql, array $parametros = []): array
{
    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);
    return $consulta->fetchAll();
}

/** Primer valor de la primera fila (para COUNT, SUM…), o null. */
function db_valor(string $sql, array $parametros = []): mixed
{
    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);
    $valor = $consulta->fetchColumn();
    return $valor === false ? null : $valor;
}

/** Ejecuta un INSERT, UPDATE o DELETE y devuelve cuántas filas cambió. */
function db_ejecutar(string $sql, array $parametros = []): int
{
    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);
    return $consulta->rowCount();
}

/** Inserta una fila ([columna => valor]) y devuelve su id. */
function db_insertar(string $tabla, array $datos): int
{
    $columnas = array_keys($datos);
    foreach ([$tabla, ...$columnas] as $nombre) {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $nombre)) {
            throw new InvalidArgumentException("Nombre de tabla o columna no válido: $nombre");
        }
    }
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $tabla,
        implode(', ', $columnas),
        implode(', ', array_map(fn (string $columna) => ':' . $columna, $columnas))
    );
    db_ejecutar($sql, $datos);
    return (int) db()->lastInsertId();
}

/**
 * Ejecuta $funcion dentro de una transacción: o se guarda todo, o nada.
 * Usa BEGIN IMMEDIATE para reservar la escritura desde el principio (evita bloqueos en SQLite).
 */
function db_transaccion(callable $funcion): mixed
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $resultado = $funcion();
        $pdo->exec('COMMIT');
        return $resultado;
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

/** Fecha y hora actual en UTC, en el formato que se guarda en la base de datos. */
function ahora_bd(): string
{
    return gmdate('Y-m-d H:i:s');
}
