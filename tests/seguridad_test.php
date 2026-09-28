<?php
declare(strict_types=1);

/*
 * Correcciones de la revisión de seguridad (ver SEGURIDAD.md).
 */

function config_admin_prueba(): array
{
    return ['ADMIN_USUARIO' => 'dueno', 'ADMIN_CLAVE_HASH' => password_hash('clave-de-prueba-123', PASSWORD_BCRYPT, ['cost' => 4])];
}

function intentar_entrar(string $clave, string $ip, array $cookies = []): array
{
    simular_peticion(['usuario' => 'dueno', 'clave' => $clave, '_csrf' => 't'], $cookies + ['csrf' => 't'], ['REMOTE_ADDR' => $ip]);
    return admin_entrar();
}

prueba('nadie puede bloquear al dueño equivocándose a propósito desde muchas IP', function () {
    bd_de_prueba();
    con_config(config_admin_prueba(), function () {
        for ($ip = 1; $ip <= 6; $ip++) {
            for ($i = 0; $i < 5; $i++) {
                intentar_entrar('mala', "203.0.113.$ip");
            }
        }
        afirmar_igual(302, intentar_entrar('clave-de-prueba-123', '198.51.100.20')['estado'], 'El dueño entra desde su IP.');
    });
});

prueba('en un dispositivo donde ya entró, el dueño entra aunque su IP esté bloqueada', function () {
    bd_de_prueba();
    con_config(config_admin_prueba(), function () {
        $ok = intentar_entrar('clave-de-prueba-123', '198.51.100.30');
        $cookies = array_column($ok['cookies'], 1, 0);
        afirmar(isset($cookies[COOKIE_DISPOSITIVO_ADMIN]), 'Al entrar, el dispositivo queda recordado.');
        for ($i = 0; $i < 5; $i++) {
            intentar_entrar('mala', '198.51.100.30'); // alguien en la misma red (misma IP)
        }
        afirmar_igual(429, intentar_entrar('clave-de-prueba-123', '198.51.100.30')['estado'], 'Sin la marca, la IP está bloqueada…');
        $conMarca = intentar_entrar('clave-de-prueba-123', '198.51.100.30', [COOKIE_DISPOSITIVO_ADMIN => $cookies[COOKIE_DISPOSITIVO_ADMIN]]);
        afirmar_igual(302, $conMarca['estado'], '…pero el dueño, desde su dispositivo, entra.');
        $falsa = intentar_entrar('clave-de-prueba-123', '198.51.100.30', [COOKIE_DISPOSITIVO_ADMIN => str_repeat('a', 43) . '.' . str_repeat('0', 32)]);
        afirmar_igual(429, $falsa['estado'], 'Una marca inventada no sirve.');
    });
});

prueba('con usuario incorrecto también se comprueba la contraseña (mismo tiempo de respuesta)', function () {
    bd_de_prueba();
    con_config(config_admin_prueba(), function () {
        simular_peticion(['usuario' => 'otro', 'clave' => 'clave-de-prueba-123', '_csrf' => 't'], ['csrf' => 't']);
        afirmar_igual(401, admin_entrar()['estado']);
        $codigo = (string) file_get_contents(RAIZ . '/app/paginas/admin.php');
        afirmar_contiene("password_verify(\$clave, admin_configurado() ? config('admin.clave_hash') : HASH_FALSO)", $codigo,
            'El hash que se verifica no debe depender de si el usuario es correcto.');
    });
});

prueba('/entrar: tope diario por email y tope global de emails (protege el cupo de Resend)', function () {
    bd_de_prueba();
    comprador_con_acceso();
    $pedir = function (): int {
        post_legitimo(['email' => 'ana@correo.com']);
        return miembro_pedir_enlace()['estado'];
    };
    for ($ronda = 0; $ronda < 2; $ronda++) {
        for ($i = 0; $i < 3; $i++) {
            afirmar_igual(200, $pedir());
        }
        db_ejecutar("UPDATE limites SET ventana_inicio = ventana_inicio - 901 WHERE clave LIKE 'entrar-email:%' OR clave LIKE 'entrar-ip:%'");
    }
    afirmar_igual(429, $pedir(), 'Más de 6 enlaces en un día para el mismo email se bloquean.');

    bd_de_prueba();
    comprador_con_acceso();
    db_ejecutar('INSERT INTO limites (clave, contador, ventana_inicio) VALUES (?, ?, ?)', ['emails-login-dia', MAXIMO_EMAILS_LOGIN_DIA, time()]);
    afirmar_igual(200, $pedir(), 'La respuesta es la misma…');
    ejecutar_tareas_de_fondo();
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM emails'), '…pero con el tope global alcanzado no se envía el email.');
});

prueba('con proxy de confianza se usa la IP que agrega el proxy (la última), no la que escribe el visitante', function () {
    simular_peticion([], [], ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 198.51.100.7']);
    afirmar_igual('10.0.0.1', ip_cliente(), 'Sin CONFIAR_PROXY se ignora la cabecera.');
    con_config(['CONFIAR_PROXY' => 'true'], function () {
        afirmar_igual('198.51.100.7', ip_cliente());
    });
});

prueba('las IPv6 se agrupan por su red /64 para contar intentos', function () {
    afirmar_igual('2001:db8:1:2::/64', ip_para_limites('2001:db8:1:2:aaaa:bbbb:cccc:1'));
    afirmar_igual('2001:db8:1:2::/64', ip_para_limites('2001:db8:1:2::ffff'));
    afirmar_igual('190.1.2.3', ip_para_limites('190.1.2.3'));
    afirmar_igual('190.1.2.3', ip_para_limites('::ffff:190.1.2.3'), 'Una IPv4 escrita como IPv6 cuenta como IPv4.');
});

prueba('/wa con parámetros o cookies raros (arrays) no falla: la persona igual llega a WhatsApp', function () {
    bd_de_prueba();
    simular_peticion([], ['atrib' => ['x' => '1'], 'vis' => ['y']], [], ['b' => ['1'], 'eid' => ['2'], 'utm_source' => ['z']]);
    $respuesta = pagina_whatsapp();
    afirmar_igual(302, $respuesta['estado']);
    afirmar(str_starts_with($respuesta['cabeceras']['Location'], 'https://wa.me/'));
    [, $errores] = venta_validar(['nombre' => ['x'], 'email' => ['y'], 'whatsapp' => ['1'], 'monto' => ['2'], 'codigo' => ['3']]);
    afirmar(isset($errores['nombre'], $errores['email'], $errores['monto']), 'Los arrays cuentan como campos vacíos.');
});

prueba('un formulario con Origin "null" (marco aislado) se rechaza, y ninguna página propia lo provoca', function () {
    afirmar(!envio_legitimo(['_csrf' => 'a'], ['csrf' => 'a'], ['HTTP_ORIGIN' => 'null', 'HTTP_HOST' => 'sitio.com']));
    // Con 'no-referrer', Chromium envía el botón del enlace mágico con "Origin: null" y nadie podría entrar
    bd_de_prueba();
    $token = basename(enlace_acceso_crear((int) comprador_con_acceso()['id']));
    afirmar_igual('same-origin', miembro_acceso_confirmar($token)['cabeceras']['Referrer-Policy']);
    afirmar_igual('strict-origin-when-cross-origin', cabeceras_seguridad()['Referrer-Policy']);
});

prueba('en producción, los emails simulados no dejan enlaces de acceso en el log', function () {
    bd_de_prueba();
    con_config(['ENTORNO' => 'produccion'], function () {
        email_login(comprador_guardar('Ana', 'ana@correo.com', null), 'https://sitio.com/acceso/TOKENSECRETO123456789012345678901234567890');
    });
    $log = (string) file_get_contents(config('logs.carpeta') . '/emails-' . gmdate('Y-m') . '.log');
    afirmar(!str_contains($log, 'TOKENSECRETO'), 'El enlace no debe quedar en el log.');
    afirmar_contiene('/acceso/[oculto]', $log);
});
