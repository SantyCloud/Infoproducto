<?php
declare(strict_types=1);

/*
 * Pruebas de punta a punta: arrancan la web con el servidor de PHP y le hacen peticiones reales.
 */

/** Arranca `php -S` en un puerto libre con una base de datos temporal. */
function servidor_de_prueba(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $puerto = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $base = sys_get_temp_dir() . '/infoproducto-http-' . getmypid();
    migrar(db_conectar("$base.sqlite"));

    $proceso = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', RAIZ . '/public_html', RAIZ . '/public_html/index.php'],
        [0 => ['pipe', 'r'], 1 => ['file', "$base.log", 'a'], 2 => ['file', "$base.log", 'a']],
        $tuberias,
        RAIZ,
        [
            'ENTORNO' => 'pruebas',
            'URL_SITIO' => "http://127.0.0.1:$puerto",
            'CLAVE_APP' => 'clave-de-pruebas',
            'RUTA_BD' => "$base.sqlite",
            'PATH' => (string) getenv('PATH'),
        ]
    );

    $servidor = [
        'url' => "http://127.0.0.1:$puerto",
        'proceso' => $proceso,
        'archivos' => ["$base.sqlite", "$base.sqlite-wal", "$base.sqlite-shm", "$base.log"],
    ];
    for ($intento = 0; $intento < 50; $intento++) {
        $conexion = @fsockopen('127.0.0.1', $puerto, $codigo, $error, 0.1);
        if ($conexion) {
            fclose($conexion);
            return $servidor;
        }
        usleep(100_000);
    }
    detener_servidor($servidor);
    throw new RuntimeException('No arrancó el servidor de prueba.');
}

function detener_servidor(array $servidor): void
{
    proc_terminate($servidor['proceso']);
    proc_close($servidor['proceso']);
    foreach ($servidor['archivos'] as $archivo) {
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }
}

/** Hace una petición HTTP y devuelve [estado, cabeceras, cuerpo]. */
function pedir(string $url, string $metodo = 'GET'): array
{
    $cuerpo = file_get_contents($url, false, stream_context_create([
        'http' => ['method' => $metodo, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5],
    ]));
    $cabeceras = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?? [])
        : ($http_response_header ?? []);
    preg_match('#^HTTP/\S+ (\d{3})#', $cabeceras[0] ?? '', $partes);
    return [(int) ($partes[1] ?? 0), implode("\n", $cabeceras), (string) $cuerpo];
}

prueba('la portada responde con el producto y las cabeceras de seguridad', function () {
    $servidor = servidor_de_prueba();
    try {
        [$estado, $cabeceras, $cuerpo] = pedir($servidor['url'] . '/?utm_source=facebook');
        afirmar_igual(200, $estado);
        afirmar_contiene(e(contenido('negocio')['producto']), $cuerpo);
        afirmar_contiene('X-Content-Type-Options: nosniff', $cabeceras);
        afirmar_contiene('Content-Security-Policy:', $cabeceras);
        afirmar_contiene("frame-ancestors 'none'", $cabeceras);
        afirmar(!str_contains($cabeceras, 'X-Powered-By'), 'No debe revelar la versión de PHP.');

        [$estado] = pedir($servidor['url'] . '/assets/css/app.css');
        afirmar_igual(200, $estado, 'Los archivos estáticos deben servirse.');

        [$estado] = pedir($servidor['url'] . '/no-existe');
        afirmar_igual(404, $estado);

        [$estado] = pedir($servidor['url'] . '/', 'POST');
        afirmar_igual(405, $estado);
    } finally {
        detener_servidor($servidor);
    }
});

prueba('no se puede descargar nada privado (.env, base de datos, código)', function () {
    $servidor = servidor_de_prueba();
    try {
        $privados = ['/.env', '/../.env', '/../storage/base.sqlite', '/storage/base.sqlite', '/app/bootstrap.php', '/contenido/negocio.php', '/../app/rutas.php'];
        foreach ($privados as $ruta) {
            [$estado, , $cuerpo] = pedir($servidor['url'] . $ruta);
            afirmar_igual(404, $estado, "Debería dar 404: $ruta.");
            afirmar(!str_contains($cuerpo, 'CLAVE_APP'), "Se filtró el .env en $ruta.");
            afirmar(!str_contains($cuerpo, '<?php'), "Se filtró código en $ruta.");
        }
    } finally {
        detener_servidor($servidor);
    }
});
