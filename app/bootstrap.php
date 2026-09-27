<?php
declare(strict_types=1);

/*
 * Arranque común de la app. Lo usan la web (public_html/index.php),
 * los comandos (bin/) y las pruebas (tests/).
 */

define('RAIZ', dirname(__DIR__));

require RAIZ . '/app/lib/env.php';
require RAIZ . '/app/lib/config.php';
require RAIZ . '/app/lib/registro.php';
require RAIZ . '/app/lib/db.php';
require RAIZ . '/app/lib/migraciones.php';
require RAIZ . '/app/lib/http.php';
require RAIZ . '/app/lib/vista.php';
require RAIZ . '/app/lib/contenido.php';
require RAIZ . '/app/lib/negocio.php';
require RAIZ . '/app/paginas/publico.php';

env_cargar(RAIZ . '/.env');

// En la base de datos todo se guarda en UTC; para mostrar fechas se usa ZONA_HORARIA.
date_default_timezone_set('UTC');

// Cualquier aviso de PHP (warning, notice…) se trata como error, para que ningún fallo pase desapercibido.
set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if (!(error_reporting() & $nivel)) {
        return false; // aviso silenciado a propósito con @
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});
