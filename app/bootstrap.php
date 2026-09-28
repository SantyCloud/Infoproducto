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
require RAIZ . '/app/lib/texto.php';
require RAIZ . '/app/lib/iconos.php';
require RAIZ . '/app/lib/imagenes.php';
require RAIZ . '/app/lib/visitas.php';
require RAIZ . '/app/lib/limites.php';
require RAIZ . '/app/lib/leads.php';
require RAIZ . '/app/lib/cliente_http.php';
require RAIZ . '/app/lib/meta.php';
require RAIZ . '/app/lib/seguridad.php';
require RAIZ . '/app/lib/accesos.php';
require RAIZ . '/app/lib/emails.php';
require RAIZ . '/app/lib/ventas.php';
require RAIZ . '/app/lib/curso.php';
require RAIZ . '/app/paginas/publico.php';
require RAIZ . '/app/paginas/miembros.php';
require RAIZ . '/app/paginas/admin.php';

env_cargar(RAIZ . '/.env');

// En la base de datos todo se guarda en UTC; para mostrar fechas se usa ZONA_HORARIA.
date_default_timezone_set('UTC');

// Cualquier aviso de PHP (warning, notice…) se trata como error, para que ningún fallo pase desapercibido.
// Los avisos de "función obsoleta" solo se anotan en el log: una actualización de PHP no debe tumbar la web.
set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if (!(error_reporting() & $nivel)) {
        return false; // aviso silenciado a propósito con @
    }
    if ($nivel === E_DEPRECATED || $nivel === E_USER_DEPRECATED) {
        registrar('errores', "Obsoleto: $mensaje", ['donde' => "$archivo:$linea"]);
        return true;
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});
