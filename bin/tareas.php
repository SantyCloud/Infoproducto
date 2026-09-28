<?php
declare(strict_types=1);

/*
 * Tareas automáticas. En Hostinger: hPanel → Avanzado → Cron Jobs, cada 5 minutos:
 *
 *     /usr/bin/php /home/uXXXXXXX/domains/tudominio.com/bin/tareas.php
 *
 * - Reintenta los eventos de Meta que fallaron.
 * - Borra datos que ya no hacen falta (ver app/lib/mantenimiento.php).
 * - Hace una copia diaria de la base de datos en storage/respaldos/.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

// Si la ejecución anterior sigue en curso, esta no hace nada
$bloqueo = fopen(RAIZ . '/storage/tareas.lock', 'c');
if ($bloqueo === false || !flock($bloqueo, LOCK_EX | LOCK_NB)) {
    echo "Otra ejecución de las tareas sigue en curso.\n";
    exit(0);
}

try {
    $meta = meta_capi_activa() ? meta_reintentar_pendientes() : null;
    $limpieza = mantenimiento_limpiar();
    $respaldo = respaldo_diario();

    echo gmdate('Y-m-d H:i:s') . " UTC\n";
    echo $meta === null ? "Meta: sin configurar\n" : "Meta: {$meta['enviados']} reenviados, {$meta['fallidos']} con error\n";
    echo 'Limpieza: ' . implode(', ', array_map(fn ($clave, $n) => "$clave $n", array_keys($limpieza), $limpieza)) . "\n";
    echo $respaldo === null ? "Respaldo: el de hoy ya existía\n" : "Respaldo: $respaldo\n";
} catch (Throwable $error) {
    registrar('errores', 'Fallaron las tareas programadas: ' . $error->getMessage(), ['donde' => $error->getFile() . ':' . $error->getLine()]);
    fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n");
    exit(1);
}
