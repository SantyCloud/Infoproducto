<?php
declare(strict_types=1);

/*
 * Enlaces de activación: el dueño registra el pago sin el email del cliente, le envía el enlace por
 * WhatsApp y el cliente activa su acceso con su nombre y su email.
 */

/** Registra un pago por activar con datos de ejemplo (validados como en el panel). */
function pago_por_activar(array $cambios = []): array
{
    [$datos, $errores] = venta_validar($cambios + [
        'entrega' => 'activacion',
        'monto' => '10',
        'metodo_pago' => 'Transferencia bancaria',
        'clave_formulario' => token_aleatorio(),
    ]);
    afirmar_igual([], $errores, 'Los datos de ejemplo deberían ser válidos.');
    return activacion_crear($datos);
}

function sesion_admin(): array
{
    return [COOKIE_ADMIN => sesion_crear('admin', null)];
}

/** Nombres de las cookies que pone una respuesta. */
function cookies_de(array $respuesta): array
{
    return array_column($respuesta['cookies'] ?? [], 0);
}

prueba('el código tiene 8 caracteres sin letras confusas y se acepta con o sin guion, en minúsculas', function () {
    bd_de_prueba();
    afirmar((bool) preg_match('/^[' . ALFABETO_CODIGOS . ']{8}$/', codigo_activacion_libre()));
    afirmar_igual('K7Q2-M8XP', formatear_codigo_activacion('K7Q2M8XP'));
    foreach (['K7Q2-M8XP', 'k7q2 m8xp', ' k7q2m8xp '] as $escrito) {
        afirmar_igual('K7Q2M8XP', normalizar_codigo_activacion($escrito), "«{$escrito}»");
    }
    foreach (['K7Q2-M8X', 'K7Q2-M8XPP', 'K7Q2-M8X0', 'K7Q2-M8XI', '', null, ['K7Q2M8XP']] as $malo) {
        afirmar_igual(null, normalizar_codigo_activacion($malo), var_export($malo, true));
    }
});

prueba('registrar un pago sin email da un enlace de un solo uso; la venta y el comprador se crean al activarlo', function () {
    bd_de_prueba();
    $pago = pago_por_activar();
    afirmar(!$pago['repetida']);
    afirmar_igual('http://localhost/activar/' . formatear_codigo_activacion($pago['codigo']), $pago['enlace']);
    afirmar_igual(0, (int) db_valor('SELECT (SELECT COUNT(*) FROM ventas) + (SELECT COUNT(*) FROM compradores)'));
    $guardado = (string) json_encode(db_filas('SELECT * FROM activaciones'));
    afirmar(!str_contains($guardado, $pago['codigo']) && !str_contains($guardado, formatear_codigo_activacion($pago['codigo'])), 'Solo se guarda el hash del código.');

    db_ejecutar('UPDATE activaciones SET creado_en = ?', ['2026-09-01 15:00:00']); // pagó hace días y activa hoy
    $resultado = activacion_canjear($pago['codigo'], 'Ana López', 'Ana@Correo.com');
    $venta = $resultado['venta'];
    afirmar(!$resultado['cuenta_existente']);
    afirmar_igual('ana@correo.com', $resultado['comprador']['email']);
    afirmar(acceso_vigente((int) $resultado['comprador']['id']));
    afirmar_igual([1000, 'USD', 'Transferencia bancaria'], [(int) $venta['monto_centavos'], $venta['moneda'], $venta['metodo_pago']]);
    afirmar_igual('2026-09-01 15:00:00', $venta['creado_en'], 'La venta es del día en que se confirmó el pago.');
    $activacion = activacion_por_id((int) $pago['activacion']['id']);
    afirmar($activacion['usado_en'] !== null);
    afirmar_igual((int) $venta['id'], (int) $activacion['venta_id']);

    afirmar_igual(null, activacion_canjear($pago['codigo'], 'Otra', 'otra@correo.com'), 'El código sirve una sola vez.');
    afirmar_igual(1, (int) db_valor('SELECT COUNT(*) FROM ventas'));

    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM emails'), 'El email de bienvenida sale después de responder.');
    ejecutar_tareas_de_fondo();
    afirmar_igual('ana@correo.com', db_valor("SELECT destinatario FROM emails WHERE tipo = 'acceso'"));
});

prueba('el mismo formulario enviado dos veces no crea dos enlaces', function () {
    bd_de_prueba();
    [$datos] = venta_validar(['entrega' => 'activacion', 'monto' => '10', 'clave_formulario' => token_aleatorio()]);
    $primero = activacion_crear($datos);
    $segundo = activacion_crear($datos);
    afirmar($segundo['repetida'] && $segundo['codigo'] === null && $segundo['enlace'] === null);
    afirmar_igual((int) $primero['activacion']['id'], (int) $segundo['activacion']['id']);
    afirmar_igual(1, (int) db_valor('SELECT COUNT(*) FROM activaciones'));
});

prueba('con el enlace, el cliente escribe su nombre y su email y entra directo al curso', function () {
    bd_de_prueba();
    $pago = pago_por_activar();
    $codigo = formatear_codigo_activacion($pago['codigo']);
    simular_peticion();
    $pagina = miembro_activar_formulario($codigo);
    afirmar_igual(200, $pagina['estado']);
    afirmar_contiene('Activa tu acceso', $pagina['cuerpo']);
    afirmar_contiene('name="con_enlace" value="1"', $pagina['cuerpo'], 'Con el enlace no hace falta escribir el código.');
    afirmar_igual('same-origin', $pagina['cabeceras']['Referrer-Policy'], 'El código no sale hacia otros sitios.');
    afirmar_igual('no-store', $pagina['cabeceras']['Cache-Control']);
    afirmar_igual(200, miembro_activar_formulario(strtolower($pago['codigo']))['estado'], 'También en minúsculas y sin guion.');

    simular_peticion(['codigo' => $codigo, 'nombre' => 'Luis', 'email' => 'luis@correo.com']);
    afirmar_igual(403, miembro_activar()['estado'], 'Sin token CSRF se rechaza.');

    post_legitimo(['codigo' => $codigo, 'con_enlace' => '1', 'nombre' => 'Luis Mora', 'email' => 'no-es-email']);
    $conError = miembro_activar();
    afirmar_igual(422, $conError['estado']);
    afirmar_contiene('Revisa tu email', $conError['cuerpo']);
    afirmar_contiene('name="con_enlace" value="1"', $conError['cuerpo'], 'Si el error es del email, el código sigue oculto.');

    post_legitimo(['codigo' => $codigo, 'con_enlace' => '1', 'nombre' => 'Luis Mora', 'email' => 'Luis@Correo.com']);
    $respuesta = miembro_activar();
    afirmar_igual(302, $respuesta['estado']);
    afirmar_igual('/miembros?bienvenida=1', $respuesta['cabeceras']['Location']);
    $cookie = $respuesta['cookies'][array_search(COOKIE_MIEMBRO, cookies_de($respuesta), true)];
    afirmar($cookie[2]['httponly'], 'La sesión va en una cookie HttpOnly.');
    afirmar(db_valor('SELECT primer_ingreso_en FROM compradores WHERE email = ?', ['luis@correo.com']) !== null, 'Activar cuenta como entrar al curso.');

    simular_peticion([], [COOKIE_MIEMBRO => $cookie[1]], [], ['bienvenida' => '1']);
    $inicio = miembro_inicio();
    afirmar_igual(200, $inicio['estado']);
    afirmar_contiene('tu acceso está activado', $inicio['cuerpo']);
    afirmar_contiene('luis@correo.com', $inicio['cuerpo'], 'Le recuerda con qué email entra.');

    simular_peticion();
    $usado = miembro_activar_formulario($codigo);
    afirmar_igual(404, $usado['estado']);
    afirmar_contiene('ya no sirve', $usado['cuerpo']);
    simular_peticion([], [COOKIE_MIEMBRO => $cookie[1]]);
    afirmar_igual('/miembros', miembro_activar_formulario($codigo)['cabeceras']['Location'] ?? null, 'Si vuelve a tocar el enlace ya con sesión, entra directo al curso.');
});

prueba('si envía otra vez el mismo código (doble toque o volver atrás), ve que ya está activado, no un error', function () {
    bd_de_prueba();
    $codigo = formatear_codigo_activacion(pago_por_activar()['codigo']);
    post_legitimo(['codigo' => $codigo, 'con_enlace' => '1', 'nombre' => 'Luis Mora', 'email' => 'luis@correo.com']);
    afirmar_igual(302, miembro_activar()['estado']);

    post_legitimo(['codigo' => $codigo, 'con_enlace' => '1', 'nombre' => 'Luis Mora', 'email' => 'Luis@Correo.com']);
    $otraVez = miembro_activar();
    afirmar_igual(200, $otraVez['estado']);
    afirmar_contiene('Tu acceso ya está activado', $otraVez['cuerpo']);
    afirmar(!in_array(COOKIE_MIEMBRO, cookies_de($otraVez), true), 'No abre otra sesión: solo le indica que entre.');
    afirmar_igual(1, (int) db_valor('SELECT COUNT(*) FROM ventas'));

    post_legitimo(['codigo' => $codigo, 'nombre' => 'Otra', 'email' => 'otra@correo.com']);
    afirmar_igual(422, miembro_activar()['estado'], 'Con otro email, el código ya no sirve.');
});

prueba('sin enlace, en /activar se escribe el código; si está mal, se puede corregir', function () {
    bd_de_prueba();
    $pago = pago_por_activar();
    simular_peticion();
    afirmar_contiene('name="codigo" type="text"', miembro_activar_formulario()['cuerpo']);

    post_legitimo(['codigo' => 'ZZZZ-ZZZZ', 'nombre' => 'Ana', 'email' => 'ana@correo.com']);
    $malo = miembro_activar();
    afirmar_igual(422, $malo['estado']);
    afirmar_contiene('Ese código no sirve', $malo['cuerpo']);
    afirmar_contiene('value="ana@correo.com"', $malo['cuerpo'], 'No hay que volver a escribir el email.');

    post_legitimo(['codigo' => strtolower($pago['codigo']), 'nombre' => 'Ana', 'email' => 'ana@correo.com']);
    afirmar_igual(302, miembro_activar()['estado']);
});

prueba('si el email ya es de un comprador, no se abre su cuenta: la compra se le suma y el enlace le llega por email', function () {
    bd_de_prueba();
    $anterior = venta_registrar(datos_venta()); // María Pérez, maria@correo.com
    $pago = pago_por_activar();
    post_legitimo(['codigo' => $pago['codigo'], 'nombre' => 'Otra persona', 'email' => 'MARIA@correo.com']);
    $respuesta = miembro_activar();
    afirmar_igual(200, $respuesta['estado']);
    afirmar_contiene('Revisa tu correo', $respuesta['cuerpo']);
    afirmar(!in_array(COOKIE_MIEMBRO, cookies_de($respuesta), true), 'No se abre sesión en una cuenta que ya existía.');
    afirmar_igual('María Pérez', db_valor("SELECT nombre FROM compradores WHERE email = 'maria@correo.com'"), 'No se cambia su nombre.');
    afirmar_igual(2, (int) db_valor('SELECT COUNT(*) FROM ventas WHERE comprador_id = ?', [$anterior['comprador']['id']]));
    ejecutar_tareas_de_fondo();
    afirmar_igual(2, (int) db_valor("SELECT COUNT(*) FROM emails WHERE tipo = 'acceso' AND destinatario = 'maria@correo.com'"), 'El enlace para entrar le llega a su correo.');
});

prueba('probar códigos al azar tiene límite: por IP y entre todos', function () {
    bd_de_prueba();
    $codigo = formatear_codigo_activacion(pago_por_activar()['codigo']);
    $ip = ['REMOTE_ADDR' => '10.9.9.9'];
    for ($i = 0; $i < 10; $i++) {
        simular_peticion([], [], $ip);
        afirmar_igual(404, miembro_activar_formulario('ZZZZ-ZZZZ')['estado']);
    }
    simular_peticion([], [], $ip);
    afirmar_igual(429, miembro_activar_formulario($codigo)['estado'], 'Pasado el límite, ni un código bueno responde desde esa IP.');
    simular_peticion(['codigo' => $codigo, 'nombre' => 'Ana', 'email' => 'ana@correo.com', '_csrf' => 't'], ['csrf' => 't'], $ip);
    afirmar_igual(429, miembro_activar()['estado']);
    simular_peticion([], [], ['REMOTE_ADDR' => '10.9.9.10']);
    afirmar_igual(200, miembro_activar_formulario($codigo)['estado'], 'Desde otra IP, sí.');

    for ($i = 0; $i < 100; $i++) {
        miembro_activar_contar_fallo();
    }
    simular_peticion([], [], ['REMOTE_ADDR' => '10.9.9.11']);
    afirmar_igual(429, miembro_activar_formulario($codigo)['estado'], 'Con 100 códigos equivocados en una hora se frena para todos.');
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM ventas'));
});

prueba('un enlace nuevo anula el anterior; los vencidos y los pagos anulados no sirven', function () {
    bd_de_prueba();
    $pago = pago_por_activar();
    $id = (int) $pago['activacion']['id'];
    $nuevo = activacion_nuevo_codigo($id);
    afirmar_igual(null, activacion_vigente($pago['codigo']), 'El código anterior deja de servir.');
    afirmar(activacion_vigente($nuevo['codigo']) !== null);

    db_ejecutar('UPDATE activaciones SET expira_en = ?', [gmdate('Y-m-d H:i:s', time() - 1)]);
    afirmar_igual(null, activacion_canjear($nuevo['codigo'], 'Ana', 'ana@correo.com'), 'Un código vencido no sirve.');
    $otro = activacion_nuevo_codigo($id);
    afirmar(activacion_vigente($otro['codigo']) !== null, 'Un enlace nuevo vuelve a dar 30 días.');

    afirmar(activacion_anular($id));
    afirmar_igual(null, activacion_canjear($otro['codigo'], 'Ana', 'ana@correo.com'), 'Un pago anulado no se puede activar.');
    afirmar_igual(null, activacion_nuevo_codigo($id), 'Ni se le crea otro enlace.');
    afirmar(!activacion_anular($id), 'Anularlo dos veces no hace nada.');
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM ventas'));
});

prueba('en el panel el pago cuenta desde que lo registras, y el clic queda "por activar"', function () {
    bd_de_prueba();
    $lead = lead_registrar(visita_de_prueba());
    simular_peticion([], sesion_admin(), [], ['codigo' => $lead['codigo']]);
    afirmar_contiene('name="entrega" value="activacion" checked', admin_venta_formulario()['cuerpo'], 'El enlace de activación es la opción por defecto.');

    post_legitimo([
        'codigo' => $lead['codigo'], 'monto' => '10', 'moneda' => 'AUTO', 'metodo_pago' => 'PayPal', 'whatsapp' => '+593 99 111 2233',
        'entrega' => 'activacion', 'nombre' => '', 'email' => '', 'clave_formulario' => token_aleatorio(),
    ], sesion_admin());
    $resultado = admin_venta_registrar();
    afirmar_igual(200, $resultado['estado']);
    afirmar((bool) preg_match('#http://localhost/activar/[A-Z0-9]{4}-[A-Z0-9]{4}#', $resultado['cuerpo']), 'Muestra el enlace de activación.');
    afirmar_contiene('href="https://wa.me/593991112233?text=', $resultado['cuerpo'], 'Con su WhatsApp, el botón abre su chat.');
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM ventas'));

    simular_peticion([], sesion_admin());
    $inicio = admin_inicio()['cuerpo'];
    afirmar_contiene('Por activar', $inicio);
    afirmar_contiene('Pagó · por activar', $inicio);
    afirmar_contiene('<span class="metrica__valor">$10</span>', $inicio, 'Ya cuenta en los ingresos.');
    afirmar_contiene('<span class="metrica__valor">100%</span>', $inicio, 'Y en el cierre.');

    [, $errores] = venta_validar(['codigo' => $lead['codigo'], 'nombre' => 'X', 'email' => 'x@x.com', 'monto' => '10', 'clave_formulario' => token_aleatorio()]);
    afirmar_contiene('falta activar', $errores['codigo'] ?? '', 'El mismo clic no se puede vender dos veces.');
    $otroClic = lead_registrar(visita_de_prueba());
    afirmar($otroClic['nuevo'] && $otroClic['codigo'] !== $lead['codigo'], 'Si vuelve a tocar el botón, es una consulta nueva.');
});

prueba('reenviar el formulario del pago (o volver atrás y cambiar la opción) no duplica nada', function () {
    bd_de_prueba();
    $envio = ['monto' => '10', 'metodo_pago' => 'PayPal', 'entrega' => 'activacion', 'clave_formulario' => token_aleatorio()];
    post_legitimo($envio, sesion_admin());
    afirmar_contiene('/activar/', admin_venta_registrar()['cuerpo']);
    post_legitimo($envio, sesion_admin());
    afirmar_contiene('ya estaba registrado', admin_venta_registrar()['cuerpo']);
    post_legitimo(['entrega' => 'email', 'nombre' => 'Ana', 'email' => 'ana@correo.com'] + $envio, sesion_admin());
    afirmar_contiene('ya estaba registrado', admin_venta_registrar()['cuerpo']);
    afirmar_igual([1, 0], [(int) db_valor('SELECT COUNT(*) FROM activaciones'), (int) db_valor('SELECT COUNT(*) FROM ventas')]);
});

prueba('el panel crea un enlace nuevo o anula el pago, solo con sesión y formulario legítimo', function () {
    bd_de_prueba();
    $pago = pago_por_activar();
    $id = (string) $pago['activacion']['id'];
    simular_peticion();
    afirmar_igual('/admin/entrar', admin_activacion_enlace($id)['cabeceras']['Location'] ?? null);
    simular_peticion(['_csrf' => 'x'], sesion_admin() + ['csrf' => 'y']);
    afirmar_igual(403, admin_activacion_anular($id)['estado']);

    post_legitimo([], sesion_admin());
    $nuevo = admin_activacion_enlace($id);
    afirmar_contiene('Enlace nuevo para el pago', $nuevo['cuerpo']);
    afirmar_igual(null, activacion_vigente($pago['codigo']), 'El enlace anterior deja de servir.');

    post_legitimo([], sesion_admin());
    afirmar_contiene('anulado', admin_activacion_anular($id)['cuerpo']);
    afirmar_igual([], activaciones_pendientes());
    post_legitimo([], sesion_admin());
    afirmar_igual(404, admin_activacion_anular('999')['estado']);
});

prueba('al activar, la venta va a Meta con los datos del clic, su moneda y el email del cliente', function () {
    bd_de_prueba();
    $pais = pais_con_otra_moneda();
    $moneda = negocio_de_pais($pais)['moneda'];
    $lead = lead_registrar(visita_de_prueba(['pais' => $pais]));
    $pago = pago_por_activar(['codigo' => $lead['codigo'], 'monto' => '200', 'moneda' => 'AUTO']);
    afirmar_igual($moneda, $pago['activacion']['moneda']);
    con_config(CONFIG_META, function () use ($pago, $moneda, $lead) {
        $llamadas = [];
        simular_meta($llamadas);
        $resultado = activacion_canjear($pago['codigo'], 'Ana López', 'ana@correo.com');
        afirmar_igual([], $llamadas, 'Meta se avisa después de responder.');
        ejecutar_tareas_de_fondo();
        http_simulador(quitar: true);
        $evento = $llamadas[0]['datos']['data'][0];
        afirmar_igual(['Purchase', 'website'], [$evento['event_name'], $evento['action_source']]);
        afirmar_igual(['currency' => $moneda, 'value' => 200], array_intersect_key($evento['custom_data'], ['currency' => 1, 'value' => 1]));
        afirmar_igual(hash('sha256', 'ana@correo.com'), $evento['user_data']['em']);
        afirmar_igual((int) $lead['id'], (int) $resultado['venta']['lead_id']);
    });
});
