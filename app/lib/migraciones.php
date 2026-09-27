<?php
declare(strict_types=1);

/*
 * Migraciones: cambios de la base de datos guardados como archivos .sql en app/migraciones/.
 * Se aplican en orden (001_, 002_…) y cada uno una sola vez.
 * Nunca edites una migración ya aplicada en producción: crea una nueva.
 */

/** Aplica las migraciones pendientes y devuelve los nombres de las que aplicó. */
function migrar(PDO $pdo, ?string $carpeta = null): array
{
    $carpeta ??= RAIZ . '/app/migraciones';
    $pdo->exec('CREATE TABLE IF NOT EXISTS migraciones (nombre TEXT PRIMARY KEY, aplicada_en TEXT NOT NULL)');
    $hechas = $pdo->query('SELECT nombre FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);

    $archivos = glob($carpeta . '/*.sql') ?: [];
    sort($archivos);

    $aplicadas = [];
    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);
        if (in_array($nombre, $hechas, true)) {
            continue;
        }
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $pdo->exec((string) file_get_contents($archivo));
            $pdo->prepare('INSERT INTO migraciones (nombre, aplicada_en) VALUES (?, ?)')
                ->execute([$nombre, gmdate('Y-m-d H:i:s')]);
            $pdo->exec('COMMIT');
        } catch (Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw new RuntimeException("Falló la migración $nombre: " . $error->getMessage(), 0, $error);
        }
        $aplicadas[] = $nombre;
    }
    return $aplicadas;
}
