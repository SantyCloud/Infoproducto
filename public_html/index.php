<?php
declare(strict_types=1);

/*
 * Único punto de entrada de la web. El código, la base de datos y el .env
 * viven fuera de public_html, donde nadie puede descargarlos.
 */

// Solo en el servidor de desarrollo de PHP (php -S): los archivos reales (css, imágenes) se sirven tal cual.
if (PHP_SAPI === 'cli-server') {
    $archivo = realpath(__DIR__ . urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    if ($archivo !== false && is_file($archivo) && !str_ends_with($archivo, '.php')
        && str_starts_with($archivo, __DIR__ . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

despachar(require RAIZ . '/app/rutas.php');
