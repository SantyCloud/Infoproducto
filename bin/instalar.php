<?php
declare(strict_types=1);

/*
 * Prepara el proyecto, en tu computadora o en el servidor:
 *
 *     php bin/instalar.php
 *
 * 1. Crea el .env a partir de .env.example si no existe, con una CLAVE_APP aleatoria.
 * 2. Crea las carpetas de storage/.
 * 3. Crea la base de datos o le aplica los cambios pendientes.
 *
 * Se puede ejecutar todas las veces que quieras (también después de cada actualización).
 */

$raiz = dirname(__DIR__);
$env = "$raiz/.env";

if (!is_file($env)) {
    copy("$raiz/.env.example", $env);
    chmod($env, 0600);
    echo "✓ Creado .env (revisa sus valores)\n";
}

// Si CLAVE_APP está vacía, se genera una aleatoria
$textoEnv = (string) file_get_contents($env);
if (preg_match('/^CLAVE_APP=[ \t\r]*$/m', $textoEnv)) {
    $textoEnv = (string) preg_replace('/^CLAVE_APP=[ \t\r]*$/m', 'CLAVE_APP=' . bin2hex(random_bytes(32)), $textoEnv);
    file_put_contents($env, $textoEnv);
    echo "✓ Generada CLAVE_APP\n";
}

require "$raiz/app/bootstrap.php";

foreach (['storage/logs', 'storage/respaldos', 'storage/descargables'] as $carpeta) {
    if (!is_dir("$raiz/$carpeta")) {
        mkdir("$raiz/$carpeta", 0775, true);
        echo "✓ Creada la carpeta $carpeta/\n";
    }
}

$aplicadas = migrar(db());
echo $aplicadas
    ? '✓ Base de datos actualizada: ' . implode(', ', $aplicadas) . "\n"
    : "✓ La base de datos ya estaba al día\n";

$cambios = optimizar_capturas();
echo $cambios ? implode("\n", $cambios) . "\n" : "✓ Las capturas ya estaban optimizadas\n";

echo "Listo.\n";
