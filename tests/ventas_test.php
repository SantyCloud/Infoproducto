<?php
declare(strict_types=1);

/*
 * Registrar ventas, dar accesos y el panel de administración.
 */

function datos_venta(array $cambios = []): array
{
    [$datos, $errores] = venta_validar($cambios + [
        'nombre' => 'María Pérez',
        'email' => 'Maria@Correo.com',
        'whatsapp' => '+593 99 123 4567',
        'monto' => '10',
        'metodo_pago' => 'PayPal',
        'enviar_email' => '1',
        'clave_formulario' => token_aleatorio(),
    ]);
    afirmar_igual([], $errores, 'Los datos de ejemplo deberían ser válidos.');
    return $datos;
}

prueba('validar la venta detecta datos malos y códigos inexistentes', function () {
    bd_de_prueba();
    [, $errores] = venta_validar(['nombre' => '', 'email' => 'no-es-email', 'whatsapp' => '123', 'monto' => 'diez', 'codigo' => 'ZZZZ']);
    afirmar_igual(['nombre', 'email', 'whatsapp', 'monto', 'codigo'], array_keys($errores));
    [$datos] = venta_validar(['monto' => '12,50', 'email' => ' ANA@X.COM ', 'whatsapp' => '+593 (99) 123-4567']);
    afirmar_igual('12.50', $datos['monto']);
    afirmar_igual('ana@x.com', $datos['email']);
    afirmar_igual('593991234567', $datos['whatsapp']);
});

prueba('registrar una venta crea comprador, venta, acceso y enlace, y envía el email', function () {
    bd_de_prueba();
    $resultado = venta_registrar(datos_venta());
    afirmar(!$resultado['repetida']);
    afirmar_igual('maria@correo.com', $resultado['comprador']['email']);
    afirmar_igual(1000, (int) $resultado['venta']['monto_centavos']);
    afirmar(acceso_vigente((int) $resultado['comprador']['id']));
    afirmar((bool) preg_match('#^http://localhost/acceso/[A-Za-z0-9_-]{43}$#', $resultado['enlace']), $resultado['enlace']);
    afirmar(enlace_acceso_valido(basename($resultado['enlace'])) !== null, 'El enlace debe servir.');
    afirmar($resultado['email']['ok'] && $resultado['email']['simulado'], 'Sin Resend, el email queda simulado.');
    afirmar_igual('simulado', db_valor("SELECT estado FROM emails WHERE tipo = 'acceso'"));
    afirmar_igual(null, $resultado['meta'], 'Sin Meta configurado no se envía nada.');
});

prueba('registrar dos veces el mismo formulario no duplica la venta (doble clic o recargar)', function () {
    bd_de_prueba();
    $datos = datos_venta();
    venta_registrar($datos);
    $segunda = venta_registrar($datos);
    afirmar($segunda['repetida']);
    afirmar_igual(null, $segunda['enlace']);
    afirmar_igual(1, (int) db_valor('SELECT COUNT(*) FROM ventas'));
    afirmar_igual(1, (int) db_valor('SELECT COUNT(*) FROM emails'), 'No debe enviar un segundo email.');
});

prueba('un código de WhatsApp solo puede usarse en una venta', function () {
    bd_de_prueba();
    $lead = lead_registrar(visita_de_prueba());
    venta_registrar(datos_venta(['codigo' => strtolower($lead['codigo'])]));
    [, $errores] = venta_validar(['nombre' => 'Otro', 'email' => 'otro@x.com', 'monto' => '10', 'codigo' => $lead['codigo'], 'clave_formulario' => token_aleatorio()]);
    afirmar_contiene('ya tiene una venta', $errores['codigo'] ?? '');
});

prueba('el mismo comprador que vuelve a comprar no se duplica', function () {
    bd_de_prueba();
    venta_registrar(datos_venta());
    venta_registrar(datos_venta(['email' => 'MARIA@correo.com', 'nombre' => 'María P.']));
    afirmar_igual(1, (int) db_valor('SELECT COUNT(*) FROM compradores'));
    afirmar_igual(2, (int) db_valor('SELECT COUNT(*) FROM ventas'));
    afirmar_igual('María P.', db_valor('SELECT nombre FROM compradores'));
});

prueba('acceso manual: sin venta y sin avisar a Meta', function () {
    bd_de_prueba();
    $resultado = acceso_manual('Socio', 'socio@x.com', null, 'regalo', false);
    afirmar(acceso_vigente((int) $resultado['comprador']['id']));
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM ventas'));
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM eventos_meta'));
    afirmar_igual(null, $resultado['email']);
});

prueba('revocar el acceso anula enlaces y sesiones al instante', function () {
    bd_de_prueba();
    $resultado = venta_registrar(datos_venta());
    $id = (int) $resultado['comprador']['id'];
    $sesion = sesion_crear('miembro', $id);
    acceso_revocar($id);
    afirmar(!acceso_vigente($id));
    afirmar_igual(null, sesion_de_token('miembro', $sesion));
    afirmar_igual(null, enlace_acceso_valido(basename($resultado['enlace'])));
    acceso_otorgar($id, null);
    afirmar(acceso_vigente($id), 'Restaurar vuelve a dar acceso.');
});

prueba('el panel exige iniciar sesión en todas sus páginas', function () {
    bd_de_prueba();
    simular_peticion();
    foreach (['admin_inicio', 'admin_leads', 'admin_ventas', 'admin_venta_formulario', 'admin_compradores', 'admin_acceso_formulario'] as $pagina) {
        $respuesta = $pagina();
        afirmar_igual(302, $respuesta['estado'], "$pagina debería redirigir");
        afirmar_igual('/admin/entrar', $respuesta['cabeceras']['Location']);
    }
    post_legitimo(['nombre' => 'X', 'email' => 'x@x.com', 'monto' => '10']);
    afirmar_igual(302, admin_venta_registrar()['estado'], 'Registrar venta sin sesión debe redirigir.');
    afirmar_igual(302, admin_comprador_revocar('1')['estado']);
    afirmar_igual(302, admin_exportar('ventas')['estado']);
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM ventas'));
});

prueba('entrar al panel: contraseña correcta abre sesión; incorrecta no; se bloquea tras 5 intentos', function () {
    bd_de_prueba();
    con_config(['ADMIN_USUARIO' => 'dueno', 'ADMIN_CLAVE_HASH' => password_hash('clave-de-prueba-123', PASSWORD_BCRYPT, ['cost' => 4])], function () {
        post_legitimo(['usuario' => 'dueno', 'clave' => 'mala']);
        afirmar_igual(401, admin_entrar()['estado']);

        post_legitimo(['usuario' => 'dueno', 'clave' => 'clave-de-prueba-123']);
        $ok = admin_entrar();
        afirmar_igual(302, $ok['estado']);
        $cookie = $ok['cookies'][0];
        afirmar_igual(COOKIE_ADMIN, $cookie[0]);
        afirmar($cookie[2]['httponly'] && $cookie[2]['samesite'] === 'Strict', 'Cookie HttpOnly y SameSite=Strict.');
        afirmar(sesion_de_token('admin', $cookie[1]) !== null);

        $ip = ['REMOTE_ADDR' => '10.9.9.9'];
        for ($i = 0; $i < 5; $i++) {
            simular_peticion(['usuario' => 'dueno', 'clave' => 'mala', '_csrf' => 't'], ['csrf' => 't'], $ip);
            admin_entrar();
        }
        simular_peticion(['usuario' => 'dueno', 'clave' => 'clave-de-prueba-123', '_csrf' => 't'], ['csrf' => 't'], $ip);
        afirmar_igual(429, admin_entrar()['estado'], 'Tras 5 fallos, ni la contraseña correcta entra por 15 minutos.');
    });
});

prueba('los formularios sin token CSRF válido se rechazan', function () {
    bd_de_prueba();
    afirmar(!envio_legitimo(['_csrf' => 'a'], ['csrf' => 'b'], []), 'Tokens distintos.');
    afirmar(!envio_legitimo([], ['csrf' => 'b'], []), 'Sin token en el formulario.');
    afirmar(!envio_legitimo(['_csrf' => 'a'], [], []), 'Sin cookie.');
    afirmar(!envio_legitimo(['_csrf' => 'a'], ['csrf' => 'a'], ['HTTP_ORIGIN' => 'https://malicioso.com', 'HTTP_HOST' => 'sitio.com']), 'Otro origen.');
    afirmar(envio_legitimo(['_csrf' => 'a'], ['csrf' => 'a'], ['HTTP_ORIGIN' => 'https://sitio.com', 'HTTP_HOST' => 'sitio.com']));
    simular_peticion(['usuario' => 'dueno', 'clave' => 'x']);
    afirmar_igual(403, admin_entrar()['estado']);
});

prueba('exportar CSV: con BOM, separado por ; y sin fórmulas peligrosas', function () {
    bd_de_prueba();
    venta_registrar(datos_venta(['nombre' => '=HYPERLINK("http://malo")']));
    $token = sesion_crear('admin', null);
    simular_peticion([], [COOKIE_ADMIN => $token]);
    $csv = admin_exportar('ventas');
    afirmar_igual(200, $csv['estado']);
    afirmar(str_starts_with($csv['cuerpo'], "\xEF\xBB\xBF"), 'Debe empezar con BOM.');
    afirmar_contiene("'=HYPERLINK", $csv['cuerpo'], 'Las fórmulas se neutralizan con un apóstrofo.');
    afirmar_contiene(';', $csv['cuerpo']);
    afirmar_igual(404, admin_exportar('contrasenas')['estado']);
});
