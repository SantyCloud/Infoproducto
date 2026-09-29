<?php
declare(strict_types=1);

/*
 * Landing: textos, promo, capturas y la página completa.
 */

prueba('formato() escapa el HTML y convierte **negritas**', function () {
    afirmar_igual('Hola <strong>mundo</strong> &lt;b&gt;', formato('Hola **mundo** <b>'));
});

prueba('las variables {…} se reemplazan con los datos del negocio', function () {
    $negocio = contenido('negocio');
    $texto = texto('{producto} por {precio} (antes {precio_normal})');
    afirmar_contiene($negocio['producto'], $texto);
    afirmar(!str_contains($texto, '{'), 'Quedó una variable sin reemplazar: ' . $texto);
    $landing = textos(contenido('landing'));
    afirmar(!str_contains((string) json_encode($landing, JSON_UNESCAPED_UNICODE), '{precio}'), 'La landing tiene variables sin reemplazar.');
});

prueba('markdown() convierte títulos, listas y enlaces, y escapa el HTML', function () {
    $html = markdown("# Título\n\nUn **párrafo** con [enlace](https://ejemplo.com) y <script>x</script>.\n\n- uno\n- dos\n\n1. primero\n2. segundo\n\n> cita");
    afirmar_contiene('<h1>Título</h1>', $html);
    afirmar_contiene('<strong>párrafo</strong>', $html);
    afirmar_contiene('<a href="https://ejemplo.com" target="_blank" rel="noopener noreferrer">enlace</a>', $html);
    afirmar_contiene('&lt;script&gt;', $html);
    afirmar_contiene('<ul><li>uno</li><li>dos</li></ul>', $html);
    afirmar_contiene('<ol><li>primero</li><li>segundo</li></ol>', $html);
    afirmar_contiene('<blockquote><p>cita</p></blockquote>', $html);
});

prueba('markdown() no permite enlaces peligrosos', function () {
    $html = markdown('[clic](javascript:alert(1)) y [otro](data:text/html,x)');
    afirmar(!str_contains($html, '<a '), 'No debe crear enlaces javascript: ni data:');
});

prueba('el texto de urgencia sale de la fecha real de fin de la promo', function () {
    $negocio = ['precio_normal' => 15, 'promo' => ['activa' => true, 'precio' => 10, 'termina' => '2026-10-31 23:59']];
    $utc = fn (string $fecha) => new DateTimeImmutable($fecha, new DateTimeZone('UTC'));
    afirmar_igual('Hasta el 31 de octubre', texto_tiempo_promo($negocio, $utc('2026-10-01 12:00')));
    afirmar_igual('Quedan 5 días', texto_tiempo_promo($negocio, $utc('2026-10-26 12:00')));
    afirmar_igual('Termina mañana', texto_tiempo_promo($negocio, $utc('2026-10-30 12:00')));
    afirmar_igual('Termina hoy', texto_tiempo_promo($negocio, $utc('2026-10-31 12:00')));
    afirmar_igual(null, texto_tiempo_promo($negocio, $utc('2026-11-02 12:00')));
    afirmar_igual(33, porcentaje_descuento($negocio, $utc('2026-10-01 12:00')));
    afirmar_igual(0, porcentaje_descuento($negocio, $utc('2026-11-02 12:00')));
});

prueba('sin garantía se quitan las frases que la mencionan', function () {
    $landing = sin_menciones_de_garantia(contenido('landing'));
    $json = (string) json_encode([$landing['hero']['ventajas'], $landing['faq']['lista']], JSON_UNESCAPED_UNICODE);
    afirmar(!str_contains($json, '{garantia_dias}'), 'Quedaron menciones de la garantía.');
});

prueba('la landing muestra la oferta, el cierre y botones a /wa', function () {
    bd_de_prueba();
    $respuesta = pagina_inicio();
    afirmar_igual(200, $respuesta['estado']);
    $html = $respuesta['cuerpo'];
    foreach (['oferta', 'cierre'] as $seccion) {
        afirmar_contiene('id="' . $seccion . '"', $html, "Falta la sección $seccion.");
    }
    foreach (['hero', 'oferta', 'cierre', 'barra'] as $boton) {
        afirmar_contiene('href="/wa?b=' . $boton . '"', $html, "Falta el botón $boton.");
    }
    afirmar_contiene('/terminos', $html);
    afirmar_contiene('/privacidad', $html);
    afirmar_contiene('<style nonce="' . csp_nonce() . '">', $html, 'El CSS va en línea con nonce.');
    afirmar(!str_contains($html, 'fbevents.js'), 'Sin META_PIXEL_ID no se carga el Pixel.');
});

prueba('la primera captura de mensajes va en el celular de la portada y no se repite en la galería', function () {
    $captura = fn (string $nombre): array => [
        'nombre' => $nombre, 'ancho' => 590, 'alto' => 1080,
        'src' => "/assets/img/capturas/$nombre-720.webp", 'src_chico' => "/assets/img/capturas/$nombre-480.webp",
        'srcset' => "/assets/img/capturas/$nombre-480.webp 480w, /assets/img/capturas/$nombre-720.webp 720w",
    ];
    $datos = datos_landing(contenido('negocio'), contenido('landing'));

    $datos['capturas']['demanda'] = [$captura('mensajes-1')];
    $html = vista('landing', $datos, 'layout_landing');
    afirmar_igual(1, substr_count($html, 'mensajes-1-'), 'Con una sola captura, solo aparece en el celular.');
    afirmar_contiene('telefono--captura', $html, 'El marco toma las proporciones de la captura.');

    $datos['capturas']['demanda'] = [$captura('mensajes-1'), $captura('mensajes-2')];
    $html = vista('landing', $datos, 'layout_landing');
    afirmar_igual(1, substr_count($html, 'mensajes-1-'), 'La primera no se repite en la galería.');
    afirmar_contiene('mensajes-2-480', $html, 'Las demás van en la galería.');

    $datos['capturas']['demanda'] = [];
    $datos['mostrar_huecos'] = true;
    $html = vista('landing', $datos, 'layout_landing');
    afirmar_contiene('Ejemplo ilustrativo', $html, 'Sin capturas, el celular dibujado lo aclara.');
    afirmar_contiene('class="hueco"', $html, 'Y en local se ve dónde van las capturas.');
});

prueba('las secciones con la lista vacía no se muestran (la página queda corta) y con contenido sí', function () {
    $landing = contenido('landing');
    $landing['problema']['puntos'] = $landing['como_funciona']['pasos'] = $landing['modulos']['lista'] = [];
    $landing['bonos']['lista'] = $landing['para_quien']['si'] = $landing['para_quien']['no'] = $landing['faq']['lista'] = [];
    $datos = datos_landing(contenido('negocio'), $landing);
    $html = vista('landing', $datos, 'layout_landing');
    foreach (['problema', 'como-funciona', 'modulos', 'para-quien', 'faq'] as $seccion) {
        afirmar(!str_contains($html, 'id="' . $seccion . '"'), "La sección $seccion debería estar oculta.");
    }

    $landing['como_funciona']['pasos'] = [['titulo' => 'Paso', 'texto' => 'Texto']];
    $landing['modulos']['lista'] = [['titulo' => 'Módulo', 'texto' => 'Texto']];
    $landing['para_quien']['si'] = ['Quieres empezar'];
    $html = vista('landing', datos_landing(contenido('negocio'), $landing), 'layout_landing');
    foreach (['como-funciona', 'modulos', 'para-quien'] as $seccion) {
        afirmar_contiene('id="' . $seccion . '"', $html, "Con contenido, la sección $seccion se muestra.");
    }
});

prueba('las páginas públicas no nombran la web de proveedor (se revela solo dentro del curso)', function () {
    $host = (string) parse_url((string) (contenido('negocio')['smmclixy']['url_registro'] ?? ''), PHP_URL_HOST);
    $nombre = strtolower((string) preg_replace('/^www\./', '', explode('.', preg_replace('/^www\./', '', $host))[0] ?? ''));
    if ($nombre === '') {
        return; // sin web de proveedor configurada no hay nada que ocultar
    }
    bd_de_prueba();
    simular_peticion();
    $paginas = ['landing' => pagina_inicio()];
    foreach (array_keys(paises()) as $pais) {
        $paginas["/$pais"] = pagina_pais($pais);
    }
    foreach (['terminos', 'privacidad'] as $legal) {
        $paginas[$legal] = pagina_legal($legal);
    }
    $paginas['reembolsos'] = pagina_reembolsos();
    $paginas['/activar'] = miembro_activar_formulario();
    $paginas['/entrar'] = miembro_entrar_formulario();
    foreach ($paginas as $pagina => $respuesta) {
        afirmar(!str_contains(strtolower($respuesta['cuerpo']), $nombre), "$pagina menciona $nombre.");
    }
});

prueba('la landing guarda de qué anuncio viene la visita (UTM, fbclid y visitante)', function () {
    $respuesta = con_cookies_de_visita(html(''), ['utm_source' => 'facebook', 'utm_campaign' => 'lanzamiento', 'fbclid' => 'IwAR123'], [], 'https://sitio.com/?utm_source=facebook');
    $cookies = array_column($respuesta['cookies'], 1, 0);
    afirmar(isset($cookies['atrib'], $cookies['_fbc'], $cookies['vis']), 'Faltan cookies de seguimiento.');
    afirmar_igual('facebook', atribucion_guardada($cookies)['utm_source']);
    afirmar((bool) preg_match('/^fb\.1\.\d{13}\.IwAR123$/', $cookies['_fbc']), 'Formato de _fbc incorrecto: ' . $cookies['_fbc']);

    // Si ya tiene visitante y _fbc, y la visita no trae anuncio, no se toca nada
    $sinCambios = con_cookies_de_visita(html(''), [], ['vis' => str_repeat('a', 32), '_fbc' => 'fb.1.1.x'], 'https://sitio.com/');
    afirmar_igual([], $sinCambios['cookies'] ?? []);
});

prueba('la atribución guardada ignora datos manipulados', function () {
    afirmar_igual([], atribucion_guardada(['atrib' => 'no-es-base64-json']));
    $trucada = base64url((string) json_encode(['utm_source' => ['array'], 'evil' => 'x', 'utm_campaign' => str_repeat('a', 5000)]));
    $datos = atribucion_guardada(['atrib' => $trucada]);
    afirmar(!isset($datos['evil']) && !isset($datos['utm_source']), 'Solo se aceptan campos conocidos de texto.');
    afirmar_igual(200, strlen($datos['utm_campaign']));
});

prueba('optimizar_capturas crea versiones WebP livianas y un índice', function () {
    $base = sys_get_temp_dir() . '/capturas-prueba-' . getmypid();
    @mkdir("$base/origen", 0775, true);
    $imagen = imagecreatetruecolor(1200, 2400);
    imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 30, 60, 90));
    imagepng($imagen, "$base/origen/mensajes-1.png");
    imagejpeg($imagen, "$base/origen/Ingresos 1.JPG");

    $cambios = optimizar_capturas("$base/origen", "$base/destino");
    afirmar_igual(2, count($cambios), implode(' | ', $cambios));
    $indice = json_decode((string) file_get_contents("$base/destino/indice.json"), true);
    afirmar(isset($indice['mensajes-1'], $indice['ingresos-1']), 'Los nombres se normalizan (minúsculas, sin espacios).');
    afirmar_igual(960, $indice['mensajes-1']['ancho']);
    afirmar_igual(1920, $indice['mensajes-1']['alto']);
    afirmar(is_file("$base/destino/mensajes-1-480." . formato_capturas()));
    afirmar_igual([], optimizar_capturas("$base/origen", "$base/destino"), 'La segunda vez no hay nada que hacer.');

    unlink("$base/origen/mensajes-1.png");
    afirmar_igual(['✓ Eliminada mensajes-1'], optimizar_capturas("$base/origen", "$base/destino"));
    afirmar(!is_file("$base/destino/mensajes-1-480." . formato_capturas()));

    array_map('unlink', glob("$base/*/*") ?: []);
    @rmdir("$base/origen");
    @rmdir("$base/destino");
    @rmdir($base);
    capturas_indice(true);
});
