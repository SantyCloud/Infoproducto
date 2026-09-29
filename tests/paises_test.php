<?php
declare(strict_types=1);

/*
 * Páginas por país (/ec, /mx…): precios y moneda propios, capturas del país, botón de WhatsApp,
 * clics con su país y ventas registradas en su moneda.
 */

/** Negocio de prueba con dos países (no depende de lo que el dueño ponga en contenido/negocio.php). */
function negocio_con_paises(): array
{
    return [
        'precio_normal' => 15,
        'promo' => ['activa' => true, 'precio' => 10, 'nombre' => 'Promo', 'termina' => null],
        'paises' => [
            'ec' => ['nombre' => 'Ecuador'],
            'mx' => ['nombre' => 'México', 'moneda' => 'MXN', 'precio_normal' => 300, 'precio_promo' => 200],
        ],
    ] + contenido('negocio');
}

/** Un país configurado en contenido/negocio.php que cobra en otra moneda (o, si no hay, el último). */
function pais_con_otra_moneda(): string
{
    $paises = paises();
    foreach ($paises as $codigo => $datos) {
        if (($datos['moneda'] ?? 'USD') !== 'USD') {
            return $codigo;
        }
    }
    return (string) array_key_last($paises);
}

prueba('cada país tiene sus precios y su moneda; sin país, los generales en dólares', function () {
    $negocio = negocio_con_paises();
    $mx = negocio_de_pais('mx', $negocio);
    afirmar_igual(['mx', 'MXN', 300, 200.0], [$mx['pais'], $mx['moneda'], $mx['precio_normal'], precio_actual($mx)]);
    $variables = variables_texto($mx);
    afirmar_igual(['$200 MXN', '$300 MXN', '$100 MXN', '33%'], [$variables['{precio}'], $variables['{precio_normal}'], $variables['{ahorro}'], $variables['{descuento}']]);

    $ec = negocio_de_pais('ec', $negocio);
    afirmar_igual(['ec', 'USD', '$10'], [$ec['pais'], $ec['moneda'], variables_texto($ec)['{precio}']]);
    $general = negocio_de_pais(null, $negocio);
    afirmar_igual([null, 'USD', 10.0], [$general['pais'], $general['moneda'], precio_actual($general)]);
    afirmar_igual(null, negocio_de_pais('co', $negocio)['pais'], 'Un país que no está configurado usa lo general.');
});

prueba('los montos de varias monedas se suman sin mezclarse', function () {
    afirmar_igual('$200 MXN', formatear_precio(200, 'MXN'));
    afirmar_igual('$200<span class="moneda">MXN</span>', precio_html(200, 'MXN'));
    afirmar_igual('$10', precio_html(10));
    afirmar_igual('$15 · $200 MXN', formatear_montos('MXN:20000,USD:1000,USD:500'));
    afirmar_igual('$0', formatear_montos(null));
    afirmar_igual('$10.50', formatear_centavos(1050, 'USD'));
});

prueba('/ec y /mx llevan a la página del país, sin tapar las demás rutas', function () {
    $rutas = require RAIZ . '/app/rutas.php';
    afirmar_igual(['funcion' => 'pagina_pais', 'parametros' => ['pais' => 'mx']], resolver_ruta($rutas, 'GET', '/mx'));
    foreach (['/terminos' => 'pagina_terminos', '/entrar' => 'miembro_entrar_formulario', '/admin' => 'admin_inicio', '/wa' => 'pagina_whatsapp'] as $ruta => $funcion) {
        afirmar_igual($funcion, resolver_ruta($rutas, 'GET', $ruta)['funcion'] ?? null, "$ruta debe seguir yendo a $funcion.");
    }
    bd_de_prueba();
    simular_peticion();
    afirmar_igual(404, pagina_pais('zz')['estado'], 'Un país que no existe da 404.');
    afirmar_igual(200, pagina_pais(strtoupper(pais_con_otra_moneda()))['estado'], 'También sirve en mayúsculas.');
});

prueba('la página de México muestra pesos, "Ahorra $100 MXN" y botones que llevan el país', function () {
    $negocio = negocio_con_paises();
    $landing = contenido('landing');
    $landing['promo']['etiqueta'] = 'Ahorra {ahorro}';
    $mx = vista('landing', datos_landing(negocio_de_pais('mx', $negocio), $landing), 'layout_landing');
    afirmar_contiene('<span class="precio-antes">$300</span>', $mx);
    afirmar_contiene('$200<span class="moneda">MXN</span>', $mx);
    afirmar_contiene('<span class="chip-promo">Ahorra $100 MXN</span>', $mx);
    afirmar_contiene('href="/wa?b=hero&amp;p=mx"', $mx);
    afirmar_contiene('$200 MXN (antes $300 MXN)', strip_tags($mx), 'La franja amarilla también va en pesos.');

    $general = vista('landing', datos_landing(negocio_de_pais(null, $negocio), $landing), 'layout_landing');
    afirmar_contiene('href="/wa?b=hero"', $general);
    afirmar(!str_contains($general, 'MXN'), 'La página general va en dólares.');
});

prueba('cada país muestra sus capturas; si no tiene, las generales, nunca las de otro país', function () {
    $captura = fn (string $nombre): array => ['nombre' => $nombre];
    $todas = array_map($captura, ['ingresos-1', 'ingresos-ec-1', 'ingresos-ec-2', 'ingresos-mx-1']);
    $nombres = fn (array $lista): array => array_column($lista, 'nombre');
    $elegir = fn (?string $pais) => $nombres(elegir_capturas_de_pais($todas, 'ingresos', $pais, ['ec', 'mx', 'co']));
    afirmar_igual(['ingresos-ec-1', 'ingresos-ec-2'], $elegir('ec'));
    afirmar_igual(['ingresos-mx-1'], $elegir('mx'));
    afirmar_igual(['ingresos-1'], $elegir('co'), 'Sin capturas propias: las generales, no las de otro país.');
    afirmar_igual(['ingresos-1', 'ingresos-ec-1', 'ingresos-ec-2', 'ingresos-mx-1'], $elegir(null), 'La página general muestra todas.');
});

prueba('el botón de la página de un país guarda el país en el clic y el mensaje de WhatsApp sale con su precio', function () {
    bd_de_prueba();
    $pais = pais_con_otra_moneda();
    simular_peticion([], [], [], ['b' => 'hero', 'p' => strtoupper($pais)]);
    $respuesta = pagina_whatsapp();
    afirmar_igual($pais, db_valor('SELECT pais FROM leads ORDER BY id DESC LIMIT 1'));
    $negocio = negocio_de_pais($pais);
    afirmar_contiene(rawurlencode(formatear_precio(precio_actual($negocio), $negocio['moneda'])), $respuesta['cabeceras']['Location']);

    simular_peticion([], [], [], ['b' => 'hero', 'p' => 'zz']);
    pagina_whatsapp();
    afirmar_igual(null, db_valor('SELECT pais FROM leads ORDER BY id DESC LIMIT 1'), 'Un país inventado no se guarda.');
});

prueba('la venta se registra en la moneda de la página del clic (o la que elijas) y así va a Meta', function () {
    bd_de_prueba();
    $pais = pais_con_otra_moneda();
    $moneda = negocio_de_pais($pais)['moneda'];
    $lead = lead_registrar(visita_de_prueba(['pais' => $pais]));
    $venta = fn (array $cambios) => venta_validar($cambios + [
        'codigo' => $lead['codigo'], 'nombre' => 'Ana', 'email' => 'ana@correo.com', 'monto' => '200',
        'moneda' => 'AUTO', 'clave_formulario' => token_aleatorio(),
    ]);

    [$datos, $errores] = $venta([]);
    afirmar_igual([], $errores);
    afirmar_igual($moneda, $datos['moneda'], 'En automático, la moneda de la página del clic.');
    afirmar_igual('USD', $venta(['moneda' => 'usd'])[0]['moneda'], 'Se puede elegir otra (p. ej., pagó con Binance).');
    afirmar(isset($venta(['moneda' => 'XYZ'])[1]['moneda']), 'Una moneda que no se usa se rechaza.');
    afirmar_igual('USD', $venta(['codigo' => ''])[0]['moneda'], 'Sin código, en dólares.');

    con_config(CONFIG_META, function () use ($datos, $moneda) {
        $llamadas = [];
        simular_meta($llamadas);
        $resultado = venta_registrar($datos);
        http_simulador(quitar: true);
        afirmar_igual($moneda, $resultado['venta']['moneda']);
        $evento = $llamadas[0]['datos']['data'][0];
        afirmar_igual(['currency' => $moneda, 'value' => 200], array_intersect_key($evento['custom_data'], ['currency' => 1, 'value' => 1]));
    });
});

prueba('el formulario de venta desde un clic de otro país trae su moneda y su precio; sin clic, el monto vacío', function () {
    bd_de_prueba();
    $pais = pais_con_otra_moneda();
    $negocio = negocio_de_pais($pais);
    $lead = lead_registrar(visita_de_prueba(['pais' => $pais]));
    simular_peticion([], [COOKIE_ADMIN => sesion_crear('admin', null)], [], ['codigo' => $lead['codigo']]);
    $html = admin_venta_formulario()['cuerpo'];
    afirmar_contiene('<option value="' . $negocio['moneda'] . '" selected>', $html);
    afirmar_contiene('name="monto" type="text" value="' . rtrim(rtrim(number_format(precio_actual($negocio), 2, '.', ''), '0'), '.') . '"', $html);

    simular_peticion([], [COOKIE_ADMIN => sesion_crear('admin', null)]);
    $vacio = admin_venta_formulario()['cuerpo'];
    afirmar_contiene('<option value="AUTO" selected>', $vacio);
    afirmar_contiene('name="monto" type="text" value=""', $vacio);
});

prueba('el panel suma cada moneda por separado', function () {
    bd_de_prueba();
    $pais = pais_con_otra_moneda();
    $moneda = negocio_de_pais($pais)['moneda'];
    venta_registrar(datos_venta(['monto' => '10']));
    venta_registrar(datos_venta(['email' => 'otro@correo.com', 'monto' => '200', 'moneda' => $moneda]));
    simular_peticion([], [COOKIE_ADMIN => sesion_crear('admin', null)]);
    afirmar_contiene(e(formatear_montos("USD:1000,$moneda:20000")), admin_inicio()['cuerpo']);
});
