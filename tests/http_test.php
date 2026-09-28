<?php
declare(strict_types=1);

/*
 * Pruebas de punta a punta: arrancan la web con el servidor de PHP y le hacen peticiones reales.
 */

/** Arranca `php -S` en un puerto libre con una base de datos temporal (y configuración extra). */
function servidor_de_prueba(array $extra = []): array
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
        $extra + [
            'ENTORNO' => 'pruebas',
            'URL_SITIO' => "http://127.0.0.1:$puerto",
            'CLAVE_APP' => 'clave-de-pruebas',
            'RUTA_BD' => "$base.sqlite",
            'PATH' => (string) getenv('PATH'),
        ]
    );

    $servidor = [
        'url' => "http://127.0.0.1:$puerto",
        'bd' => "$base.sqlite",
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
function pedir(string $url, string $metodo = 'GET', array $cabeceras = [], ?string $cuerpoEnviado = null): array
{
    if (!preg_grep('/^User-Agent:/i', $cabeceras)) {
        $cabeceras[] = 'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Instagram 340.0';
    }
    $opciones = ['method' => $metodo, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5, 'header' => implode("\r\n", $cabeceras)];
    if ($cuerpoEnviado !== null) {
        $opciones['content'] = $cuerpoEnviado;
    }
    $cuerpo = file_get_contents($url, false, stream_context_create(['http' => $opciones]));
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

prueba('el botón de WhatsApp crea el lead con su código y redirige a wa.me (los robots no crean leads)', function () {
    $servidor = servidor_de_prueba();
    try {
        [$estado, $cabeceras] = pedir($servidor['url'] . '/wa?b=hero&utm_campaign=prueba&eid=0f8c2e0a-1111-4222-8333-944455556666');
        afirmar_igual(302, $estado);
        preg_match('/^Location: (.+)$/mi', $cabeceras, $m);
        $destino = trim($m[1] ?? '');
        afirmar(str_starts_with($destino, 'https://wa.me/'), "Destino inesperado: $destino");

        $pdo = db_conectar($servidor['bd']);
        $lead = $pdo->query('SELECT * FROM leads')->fetch();
        afirmar(is_array($lead), 'No se creó el lead.');
        afirmar_contiene($lead['codigo'], rawurldecode($destino), 'El mensaje de WhatsApp debe llevar el código.');
        afirmar_igual('prueba', $lead['utm_campaign']);
        afirmar_igual('0f8c2e0a-1111-4222-8333-944455556666', $lead['event_id'], 'Usa el id de evento del navegador (deduplicación con el Pixel).');
        afirmar_contiene('Set-Cookie: vis=', $cabeceras, 'Si no tenía identificador de visitante, se le asigna.');

        [$estado] = pedir($servidor['url'] . '/wa?b=hero', 'GET', ['User-Agent: facebookexternalhit/1.1']);
        afirmar_igual(302, $estado, 'Los robots también son redirigidos…');
        afirmar_igual(1, (int) $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn(), '…pero no crean leads.');
    } finally {
        detener_servidor($servidor);
    }
});

/** Navegador mínimo para las pruebas: guarda y envía las cookies entre peticiones. */
function navegar(array &$cookies, string $url, string $metodo = 'GET', array $campos = []): array
{
    $cabeceras = $cookies ? ['Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies))] : [];
    $cuerpo = null;
    if ($metodo === 'POST') {
        $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
        $cuerpo = http_build_query($campos);
    }
    [$estado, $crudas, $html] = pedir($url, $metodo, $cabeceras, $cuerpo);
    foreach (explode("\n", $crudas) as $linea) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
            if ($m[2] === '' || stripos($linea, 'Max-Age=0') !== false) {
                unset($cookies[$m[1]]);
            } else {
                $cookies[$m[1]] = $m[2];
            }
        }
    }
    return [$estado, $crudas, $html];
}

function campo_oculto(string $html, string $nombre): string
{
    preg_match('/name="' . preg_quote($nombre, '/') . '" value="([^"]*)"/', $html, $m);
    return html_entity_decode($m[1] ?? '');
}

prueba('recorrido completo: panel → venta → enlace → área de miembros → descarga', function () {
    $servidor = servidor_de_prueba([
        'ADMIN_USUARIO' => 'dueno',
        'ADMIN_CLAVE_HASH' => password_hash('clave-de-prueba-123', PASSWORD_BCRYPT, ['cost' => 4]),
    ]);
    $url = $servidor['url'];
    try {
        // 1. Sin sesión, el panel y el área de miembros no se ven
        $anonimo = [];
        [$estado, $cabeceras] = navegar($anonimo, "$url/admin");
        afirmar_igual(302, $estado);
        afirmar_contiene('Location: /admin/entrar', $cabeceras);
        [$estado, $cabeceras] = navegar($anonimo, "$url/miembros");
        afirmar_contiene('Location: /entrar', $cabeceras);

        // 2. El dueño entra al panel
        $dueno = [];
        [, , $html] = navegar($dueno, "$url/admin/entrar");
        [$estado, $cabeceras] = navegar($dueno, "$url/admin/entrar", 'POST', ['usuario' => 'dueno', 'clave' => 'clave-de-prueba-123', '_csrf' => campo_oculto($html, '_csrf')]);
        afirmar_igual(302, $estado, 'El acceso con la contraseña correcta redirige al panel.');
        afirmar(isset($dueno['admin']), 'Debe recibir la cookie de sesión del panel.');
        afirmar_contiene('HttpOnly', $cabeceras);

        // 3. Un formulario sin token CSRF se rechaza
        [$estado] = navegar($dueno, "$url/admin/ventas", 'POST', ['nombre' => 'X', 'email' => 'x@x.com', 'monto' => '10']);
        afirmar_igual(403, $estado);

        // 4. Registra una venta
        [, , $html] = navegar($dueno, "$url/admin/ventas/nueva");
        [$estado, , $html] = navegar($dueno, "$url/admin/ventas", 'POST', [
            '_csrf' => campo_oculto($html, '_csrf'),
            'clave_formulario' => campo_oculto($html, 'clave_formulario'),
            'nombre' => 'Luis Mora', 'email' => 'luis@correo.com', 'whatsapp' => '593998887766',
            'monto' => '10', 'metodo_pago' => 'PayPal', 'enviar_email' => '1',
        ]);
        afirmar_igual(200, $estado);
        afirmar_contiene('registrada', $html);
        preg_match('#id="enlace-acceso" type="text" value="([^"]+)"#', $html, $m);
        $enlace = html_entity_decode($m[1] ?? '');
        afirmar(str_starts_with($enlace, "$url/acceso/"), 'El resultado muestra el enlace de acceso.');

        // 5. El comprador abre el enlace: primero ve un botón (el enlace aún no se gasta)
        $alumno = [];
        [$estado, , $html] = navegar($alumno, $enlace);
        afirmar_igual(200, $estado);
        afirmar_contiene('Entrar al curso', $html);
        [$estado] = navegar($alumno, $enlace);
        afirmar_igual(200, $estado, 'Abrirlo otra vez (como hacen los antivirus) no lo gasta.');

        // 6. Pulsa "Entrar": se abre su sesión
        [$estado, $cabeceras] = navegar($alumno, $enlace, 'POST', ['_csrf' => campo_oculto($html, '_csrf')]);
        afirmar_igual(302, $estado);
        afirmar_contiene('Location: /miembros', $cabeceras);
        [$estado, , $html] = navegar($alumno, "$url/miembros");
        afirmar_igual(200, $estado);
        afirmar_contiene('Hola, Luis', $html);
        [$estado] = navegar($alumno, "$url/miembros/leccion/bienvenida");
        afirmar_igual(200, $estado);
        [$estado, $cabeceras, $archivo] = navegar($alumno, "$url/miembros/descargar/lista-de-precios");
        afirmar_igual(200, $estado);
        afirmar_contiene('Content-Disposition: attachment', $cabeceras);
        afirmar_contiene('Servicio;Cantidad', $archivo);

        // 7. El enlace ya no sirve para nadie más
        $otro = [];
        [$estado, , $html] = navegar($otro, $enlace);
        afirmar_igual(410, $estado);
        afirmar_contiene('ya no sirve', $html);

        // 8. El descargable no se puede bajar sin sesión
        [$estado] = navegar($otro, "$url/miembros/descargar/lista-de-precios");
        afirmar_igual(302, $estado);

        // 9. Si el dueño revoca el acceso, la sesión del alumno deja de servir al instante
        [, , $html] = navegar($dueno, "$url/admin/compradores/1");
        navegar($dueno, "$url/admin/compradores/1/revocar", 'POST', ['_csrf' => campo_oculto($html, '_csrf')]);
        [$estado] = navegar($alumno, "$url/miembros");
        afirmar_igual(302, $estado, 'Con el acceso revocado, vuelve a /entrar.');
    } finally {
        detener_servidor($servidor);
    }
});
