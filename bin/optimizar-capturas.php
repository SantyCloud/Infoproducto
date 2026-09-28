<?php
declare(strict_types=1);

/*
 * Crea versiones livianas (WebP) de las capturas de contenido/capturas/ para la landing:
 *
 *     php bin/optimizar-capturas.php
 *
 * Solo procesa las nuevas o modificadas. También lo ejecuta bin/instalar.php.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$cambios = optimizar_capturas();
echo $cambios ? implode("\n", $cambios) . "\n" : "✓ Las capturas ya estaban optimizadas\n";
