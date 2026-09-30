<?php
declare(strict_types=1);

/*
 * Prepara el video "adelanto del curso" que se ve en la landing, debajo de "Cómo empecé" (ver app/lib/adelanto.php).
 * Necesita ffmpeg (ffmpeg.org): Hostinger no lo tiene, así que se ejecuta en una computadora.
 *
 *     php bin/optimizar-video.php RUTA/DEL/VIDEO.mp4        vista previa sin sonido y portada desde el segundo 1
 *     php bin/optimizar-video.php RUTA/DEL/VIDEO.mp4 12     …desde el segundo 12 (un momento que dé curiosidad)
 *     php bin/optimizar-video.php --quitar                  quita el adelanto de la landing
 *
 * Después sube a Git la carpeta public_html/assets/video/ y actualiza la web como siempre.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$argumentos = array_slice($argv, 1);
if (($argumentos[0] ?? '') === '--quitar') {
    quitar_adelanto();
    echo "✓ Adelanto quitado: la landing ya no lo muestra.\n";
    exit(0);
}
if (!$argumentos || !is_numeric($argumentos[1] ?? '1')) {
    fwrite(STDERR, "Uso: php bin/optimizar-video.php RUTA/DEL/VIDEO.mp4 [SEGUNDO]\n");
    exit(1);
}
try {
    echo implode("\n", optimizar_adelanto($argumentos[0], (float) ($argumentos[1] ?? 1))) . "\n";
    echo "Listo. Súbelo a Git: public_html/assets/video/\n";
} catch (RuntimeException $error) {
    fwrite(STDERR, '✗ ' . $error->getMessage() . "\n");
    exit(1);
}
