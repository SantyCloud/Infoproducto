<?php
declare(strict_types=1);

/*
 * Crea o cambia el usuario y la contraseña del panel de administración (/admin):
 *
 *     php bin/crear-admin.php
 *
 * En el .env se guarda el usuario y el HASH de la contraseña (nunca la contraseña).
 * Al cambiarla se cierran las sesiones de admin abiertas.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$archivoEnv = getenv('ARCHIVO_ENV') ?: RAIZ . '/.env';
if (!is_file($archivoEnv)) {
    fwrite(STDERR, "✗ No existe el .env. Ejecuta primero: php bin/instalar.php\n");
    exit(1);
}

function preguntar(string $texto, bool $oculto = false): string
{
    echo $texto;
    $ocultar = $oculto && DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($ocultar) {
        shell_exec('stty -echo'); // no mostrar la contraseña mientras se escribe
    }
    $respuesta = fgets(STDIN);
    if ($ocultar) {
        shell_exec('stty echo');
        echo "\n";
    }
    return trim((string) $respuesta);
}

$actual = config('admin.usuario');
$usuario = preguntar('Usuario del panel' . ($actual !== '' ? " [$actual]" : '') . ': ') ?: $actual;
if (!preg_match('/^[A-Za-z0-9._@-]{3,60}$/', $usuario)) {
    fwrite(STDERR, "✗ Usuario no válido: usa de 3 a 60 letras, números, puntos, guiones o @.\n");
    exit(1);
}
$clave = preguntar('Contraseña nueva (mínimo 10 caracteres): ', true);
if (strlen($clave) < 10) {
    fwrite(STDERR, "✗ La contraseña es muy corta: usa al menos 10 caracteres (mejor una frase).\n");
    exit(1);
}
if (preguntar('Repite la contraseña: ', true) !== $clave) {
    fwrite(STDERR, "✗ Las contraseñas no coinciden.\n");
    exit(1);
}

$texto = (string) file_get_contents($archivoEnv);
$texto = env_poner($texto, 'ADMIN_USUARIO', $usuario);
$texto = env_poner($texto, 'ADMIN_CLAVE_HASH', password_hash($clave, PASSWORD_DEFAULT));
file_put_contents($archivoEnv, $texto);

db_ejecutar("DELETE FROM sesiones WHERE tipo = 'admin'");
echo "✓ Listo. Entra en " . config('app.url') . "/admin con el usuario \"$usuario\".\n";
