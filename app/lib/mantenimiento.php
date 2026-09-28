<?php
declare(strict_types=1);

/*
 * Mantenimiento que ejecuta bin/tareas.php (cron): limpieza de datos y respaldos.
 */

const DIAS_DATOS_DE_CLICS = 90;
const RESPALDOS_A_CONSERVAR = 14;

/**
 * Borra lo que ya no hace falta y devuelve cuántas filas tocó en cada caso:
 * - límites de intentos de hace más de un día,
 * - enlaces de acceso vencidos hace más de 30 días y sesiones vencidas,
 * - IP, navegador e identificadores de Meta de los clics de hace más de 90 días
 *   (se conservan los UTM para las estadísticas), y los mismos datos en los eventos enviados.
 */
function mantenimiento_limpiar(): array
{
    $hace90 = gmdate('Y-m-d H:i:s', time() - DIAS_DATOS_DE_CLICS * 86400);
    return [
        'limites' => db_ejecutar('DELETE FROM limites WHERE ventana_inicio < ?', [time() - 86400]),
        'enlaces' => db_ejecutar('DELETE FROM tokens_login WHERE expira_en < ?', [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]),
        'sesiones' => db_ejecutar('DELETE FROM sesiones WHERE expira_en < ?', [ahora_bd()]),
        'clics' => db_ejecutar(
            'UPDATE leads SET ip = NULL, user_agent = NULL, fbc = NULL, fbp = NULL, fbclid = NULL
             WHERE creado_en < ? AND (ip IS NOT NULL OR user_agent IS NOT NULL OR fbc IS NOT NULL OR fbp IS NOT NULL OR fbclid IS NOT NULL)',
            [$hace90]
        ),
        'eventos' => db_ejecutar("UPDATE eventos_meta SET datos = '{}' WHERE creado_en < ? AND datos <> '{}'", [$hace90]),
    ];
}

/**
 * Copia de la base de datos una vez al día en storage/respaldos/base-AAAA-MM-DD.sqlite
 * (conserva las últimas 14). Devuelve la ruta creada o null si hoy ya había copia.
 */
function respaldo_diario(?string $carpeta = null): ?string
{
    $carpeta ??= RAIZ . '/storage/respaldos';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0775, true);
    }
    $hoy = (new DateTimeImmutable('now', new DateTimeZone(config('app.zona_horaria'))))->format('Y-m-d');
    $archivo = "$carpeta/base-$hoy.sqlite";
    if (is_file($archivo)) {
        return null;
    }
    try {
        db()->exec('VACUUM INTO ' . db()->quote($archivo)); // copia consistente aunque la web esté en uso
    } catch (PDOException) {
        // SQLite antiguo (sin VACUUM INTO): se vuelca el diario y se copia el archivo
        db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        copy(config('db.ruta'), $archivo);
    }
    $copias = glob("$carpeta/base-*.sqlite") ?: [];
    rsort($copias);
    foreach (array_slice($copias, RESPALDOS_A_CONSERVAR) as $vieja) {
        unlink($vieja);
    }
    return $archivo;
}
