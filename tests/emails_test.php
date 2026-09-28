<?php
declare(strict_types=1);

/*
 * Emails con Resend (simulado en las pruebas).
 */

prueba('sin RESEND_API_KEY el email queda simulado y registrado', function () {
    bd_de_prueba();
    $comprador = comprador_guardar('Ana López', 'ana@correo.com', null);
    $resultado = email_login($comprador, 'http://localhost/acceso/abc');
    afirmar($resultado['ok'] && $resultado['simulado']);
    afirmar_igual('simulado', db_valor('SELECT estado FROM emails'));
});

prueba('con Resend: envía con la clave, el remitente y la clave de idempotencia', function () {
    bd_de_prueba();
    con_config(['RESEND_API_KEY' => 're_prueba', 'EMAIL_REMITENTE' => 'Curso <acceso@sitio.com>', 'EMAIL_RESPONDER_A' => 'yo@sitio.com'], function () {
        $llamadas = [];
        http_simulador(function (string $url, array $datos, array $cabeceras) use (&$llamadas): array {
            $llamadas[] = compact('url', 'datos', 'cabeceras');
            return [true, '{"id":"email_123"}', 200];
        });
        $comprador = comprador_guardar('Ana López', 'ana@correo.com', null);
        $resultado = email_acceso($comprador, 'http://localhost/acceso/abc', 'acceso-venta-1');
        afirmar($resultado['ok'] && !$resultado['simulado']);
        afirmar_igual('https://api.resend.com/emails', $llamadas[0]['url']);
        afirmar(in_array('Authorization: Bearer re_prueba', $llamadas[0]['cabeceras'], true));
        afirmar(in_array('Idempotency-Key: acceso-venta-1', $llamadas[0]['cabeceras'], true));
        afirmar_igual(['ana@correo.com'], $llamadas[0]['datos']['to']);
        afirmar_igual('Curso <acceso@sitio.com>', $llamadas[0]['datos']['from']);
        afirmar_igual('yo@sitio.com', $llamadas[0]['datos']['reply_to']);
        afirmar_igual('email_123', db_valor('SELECT proveedor_id FROM emails'));
        http_simulador(quitar: true);
    });
});

prueba('si Resend rechaza el email, se registra el error', function () {
    bd_de_prueba();
    con_config(['RESEND_API_KEY' => 're_prueba', 'EMAIL_REMITENTE' => 'Curso <acceso@sitio.com>'], function () {
        http_simulador(fn () => [false, '{"message":"The sitio.com domain is not verified"}', 403]);
        $resultado = email_login(comprador_guardar('Ana', 'ana@correo.com', null), 'http://localhost/acceso/abc');
        afirmar(!$resultado['ok']);
        afirmar_contiene('not verified', $resultado['error']);
        afirmar_igual('error', db_valor('SELECT estado FROM emails'));
        http_simulador(quitar: true);
    });
});

prueba('el email de acceso lleva el enlace, el registro en smmclixy y el nombre del comprador', function () {
    bd_de_prueba();
    con_config(['RESEND_API_KEY' => 're_prueba', 'EMAIL_REMITENTE' => 'Curso <a@b.com>'], function () {
        $enviado = null;
        http_simulador(function (string $url, array $datos) use (&$enviado): array {
            $enviado = $datos;
            return [true, '{"id":"x"}', 200];
        });
        email_acceso(comprador_guardar('Ana López', 'ana@correo.com', null), 'http://localhost/acceso/TOKEN123');
        afirmar_contiene('http://localhost/acceso/TOKEN123', $enviado['html']);
        afirmar_contiene('http://localhost/acceso/TOKEN123', $enviado['text']);
        afirmar_contiene('utm_source=curso&amp;utm_medium=email', $enviado['html']);
        afirmar_contiene('¡Bienvenido/a, Ana!', $enviado['html']);
        afirmar(!str_contains($enviado['text'], '**'), 'El texto plano no lleva asteriscos.');
        afirmar(!str_contains($enviado['html'], '{'), 'No quedan variables sin reemplazar.');
        http_simulador(quitar: true);
    });
});
