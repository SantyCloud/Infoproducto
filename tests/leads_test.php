<?php
declare(strict_types=1);

/*
 * Botón de WhatsApp: leads con código, reutilización, límites y bots.
 */

function visita_de_prueba(array $cambios = []): array
{
    return $cambios + [
        'visitante_id' => str_repeat('b', 32),
        'boton' => 'hero',
        'event_id' => nuevo_event_id(),
        'atribucion' => ['utm_source' => 'facebook', 'utm_campaign' => 'lanzamiento', 'utm_content' => 'video-1', 'fbclid' => 'IwAR9'],
        'fbc' => null,
        'fbp' => 'fb.1.1700000000000.123',
        'ip' => '190.1.2.3',
        'user_agent' => 'Mozilla/5.0 (iPhone) Instagram',
        'url_origen' => 'https://sitio.com/?utm_source=facebook',
        'referer' => 'https://sitio.com/',
    ];
}

prueba('los códigos son cortos y sin letras confusas (0, O, 1, I, L)', function () {
    for ($i = 0; $i < 200; $i++) {
        afirmar((bool) preg_match('/^[2-9A-HJKMNP-Z]{4}$/', generar_codigo()), 'Código con caracteres no permitidos.');
    }
    afirmar_igual('K7Q2', normalizar_codigo(' k7-q2 '));
});

prueba('el primer clic crea un lead con código y guarda de qué anuncio vino', function () {
    bd_de_prueba();
    $lead = lead_registrar(visita_de_prueba());
    afirmar($lead['nuevo']);
    afirmar((bool) preg_match('/^[2-9A-Z]{4}$/', $lead['codigo']));
    afirmar_igual('lanzamiento', $lead['utm_campaign']);
    afirmar_igual('video-1', $lead['utm_content']);
    afirmar_igual('hero', $lead['boton']);
    afirmar_igual('190.1.2.3', $lead['ip']);
});

prueba('si el mismo visitante vuelve a tocar el botón, conserva su código', function () {
    bd_de_prueba();
    $primero = lead_registrar(visita_de_prueba());
    $segundo = lead_registrar(visita_de_prueba(['fbc' => 'fb.1.1.nuevo']));
    afirmar(!$segundo['nuevo']);
    afirmar_igual($primero['codigo'], $segundo['codigo']);
    afirmar_igual(2, (int) $segundo['clics']);
    afirmar_igual('fb.1.1.nuevo', $segundo['fbc'], 'Completa el fbc si antes no lo tenía.');
    afirmar_igual(1, (int) db_valor('SELECT COUNT(*) FROM leads'));
});

prueba('un lead que ya compró no se reutiliza: el siguiente clic crea otro', function () {
    bd_de_prueba();
    $primero = lead_registrar(visita_de_prueba());
    $comprador = db_insertar('compradores', ['nombre' => 'Ana', 'email' => 'ana@correo.com', 'creado_en' => ahora_bd(), 'actualizado_en' => ahora_bd()]);
    db_insertar('ventas', ['comprador_id' => $comprador, 'lead_id' => $primero['id'], 'monto_centavos' => 1000, 'creado_en' => ahora_bd()]);
    $segundo = lead_registrar(visita_de_prueba());
    afirmar($segundo['nuevo']);
    afirmar($segundo['codigo'] !== $primero['codigo']);
});

prueba('un event_id repetido no impide registrar el lead', function () {
    bd_de_prueba();
    $eid = nuevo_event_id();
    lead_registrar(visita_de_prueba(['event_id' => $eid]));
    $otro = lead_registrar(visita_de_prueba(['event_id' => $eid, 'visitante_id' => str_repeat('c', 32)]));
    afirmar($otro['nuevo']);
    afirmar($otro['event_id'] !== $eid);
});

prueba('datos_de_la_visita toma la atribución de la cookie y valida lo que manda el navegador', function () {
    $cookies = ['vis' => str_repeat('d', 32), 'atrib' => base64url((string) json_encode(['utm_campaign' => 'camp', 'url' => 'https://x.com/?a=1'])), '_fbp' => 'fb.1.2.3'];
    $visita = datos_de_la_visita(['b' => 'Hero<script>', 'eid' => 'malo'], $cookies, ['HTTP_REFERER' => 'https://otro.com/']);
    afirmar_igual(str_repeat('d', 32), $visita['visitante_id']);
    afirmar_igual('heroscript', $visita['boton']);
    afirmar(event_id_valido($visita['event_id']) && $visita['event_id'] !== 'malo');
    afirmar_igual('camp', $visita['atribucion']['utm_campaign']);
    afirmar_igual('https://x.com/?a=1', $visita['url_origen'], 'Si el referer es de otro sitio, usa la URL guardada.');
    afirmar_igual('fb.1.2.3', $visita['fbp']);
});

prueba('el enlace de WhatsApp lleva el mensaje con el precio y el código', function () {
    $enlace = enlace_whatsapp('K7Q2');
    $numero = preg_replace('/\D/', '', contenido('negocio')['whatsapp']['numero']);
    afirmar(str_starts_with($enlace, "https://wa.me/$numero?text="), $enlace);
    $mensaje = rawurldecode(explode('?text=', $enlace)[1]);
    afirmar_contiene('K7Q2', $mensaje);
    afirmar_contiene(formatear_precio(precio_actual()), $mensaje);
    afirmar(!str_contains(rawurldecode(explode('?text=', enlace_whatsapp(null))[1]), '{codigo}'), 'Sin código no debe quedar {codigo}.');
});

prueba('el límite de intentos bloquea al pasarse y se reinicia con la ventana', function () {
    bd_de_prueba();
    for ($i = 0; $i < 3; $i++) {
        afirmar(limite_permitir('prueba:1', 3, 60));
    }
    afirmar(!limite_permitir('prueba:1', 3, 60), 'El cuarto intento debe bloquearse.');
    afirmar(limite_permitir('prueba:2', 3, 60), 'Otra clave tiene su propio contador.');
    db_ejecutar('UPDATE limites SET ventana_inicio = ventana_inicio - 61');
    afirmar(limite_permitir('prueba:1', 3, 60), 'Pasada la ventana, vuelve a permitir.');
});

prueba('reconoce robots (revisor de anuncios, vistas previas) pero no navegadores reales', function () {
    afirmar(es_bot('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'));
    afirmar(es_bot('WhatsApp/2.23 A') === false, 'WhatsApp no es un bot aquí (abre el enlace una persona).');
    afirmar(es_bot('Googlebot/2.1'));
    afirmar(es_bot(''));
    afirmar(!es_bot('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Instagram 340.0'));
    afirmar(!es_bot('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 [FB_IAB/FB4A;FBAV/450.0]'));
});
