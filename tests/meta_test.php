<?php
declare(strict_types=1);

/*
 * Meta: Pixel en la landing y API de Conversiones (Contact y Purchase).
 * Las llamadas a Meta se simulan: nunca se contacta a internet desde las pruebas.
 */

const CONFIG_META = ['META_PIXEL_ID' => '123456789012345', 'META_CAPI_TOKEN' => 'token-de-prueba', 'META_GRAPH_VERSION' => 'v25.0'];

/** Simula la API: guarda las llamadas en $llamadas y responde con $respuestas (en orden). */
function simular_meta(array &$llamadas, array $respuestas = [[true, '{"events_received":1}', 200]]): void
{
    http_simulador(function (string $url, array $datos) use (&$llamadas, &$respuestas): array {
        $llamadas[] = ['url' => $url, 'datos' => $datos];
        return count($respuestas) > 1 ? array_shift($respuestas) : $respuestas[0];
    });
}

prueba('los datos personales se normalizan y van en hash (SHA-256); IP, navegador y fbc van tal cual', function () {
    $lead = ['visitante_id' => str_repeat('a', 32), 'ip' => '190.1.2.3', 'user_agent' => 'Mozilla', 'fbc' => null,
        'fbclid' => 'IwAR1', 'fbp' => 'fb.1.2.3', 'creado_en' => '2026-10-01 12:00:00'];
    $comprador = ['id' => 7, 'nombre' => 'María José Pérez', 'email' => ' Maria@Correo.COM ', 'whatsapp' => '+593 99 123 4567'];
    $datos = meta_datos_usuario($lead, $comprador);
    afirmar_igual(hash('sha256', 'maria@correo.com'), $datos['em']);
    afirmar_igual(hash('sha256', '593991234567'), $datos['ph']);
    afirmar_igual(hash('sha256', 'maría'), $datos['fn']);
    afirmar_igual(hash('sha256', 'josé pérez'), $datos['ln']);
    afirmar_igual(hash('sha256', str_repeat('a', 32)), $datos['external_id']);
    afirmar_igual('190.1.2.3', $datos['client_ip_address']);
    afirmar_igual('fb.1.' . (strtotime('2026-10-01 12:00:00 UTC') * 1000) . '.IwAR1', $datos['fbc'], 'Sin cookie _fbc se arma con el fbclid.');
    afirmar(!str_contains((string) json_encode($datos), 'maria@'), 'El email nunca va en claro.');
});

prueba('sin configurar Meta no se guarda ni se envía nada', function () {
    bd_de_prueba();
    $lead = lead_registrar(visita_de_prueba());
    afirmar_igual(null, meta_contact_para_lead($lead));
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM eventos_meta'));
});

prueba('Contact: se envía después de responder, con el mismo event_id que el Pixel', function () {
    bd_de_prueba();
    con_config(CONFIG_META, function () {
        $llamadas = [];
        simular_meta($llamadas);
        $lead = lead_registrar(visita_de_prueba(['event_id' => '11111111-2222-4333-8444-555555555555']));
        meta_contact_para_lead($lead);
        afirmar_igual(0, count($llamadas), 'No se envía mientras el visitante espera…');
        ejecutar_tareas_de_fondo();
        afirmar_igual(1, count($llamadas), '…sino justo después de redirigirlo.');
        afirmar_igual('https://graph.facebook.com/v25.0/123456789012345/events?access_token=token-de-prueba', $llamadas[0]['url']);
        $evento = $llamadas[0]['datos']['data'][0];
        afirmar_igual('Contact', $evento['event_name']);
        afirmar_igual('11111111-2222-4333-8444-555555555555', $evento['event_id']);
        afirmar_igual('website', $evento['action_source']);
        afirmar_igual('enviado', db_valor('SELECT estado FROM eventos_meta'));
        http_simulador(quitar: true);
    });
});

prueba('Purchase: al registrar la venta se envía con valor, moneda y los datos del clic original', function () {
    bd_de_prueba();
    con_config(CONFIG_META + ['META_TEST_EVENT_CODE' => 'TEST123'], function () {
        $llamadas = [];
        simular_meta($llamadas);
        $lead = lead_registrar(visita_de_prueba());
        $llamadas = [];
        $resultado = venta_registrar(datos_venta(['codigo' => $lead['codigo'], 'monto' => '12.50']));
        afirmar_igual(true, $resultado['meta']);
        $cuerpo = $llamadas[0]['datos'];
        afirmar_igual('TEST123', $cuerpo['test_event_code']);
        $evento = $cuerpo['data'][0];
        afirmar_igual('Purchase', $evento['event_name']);
        afirmar_igual('venta-' . $resultado['venta']['id'], $evento['event_id']);
        afirmar_igual('website', $evento['action_source']);
        afirmar_igual($lead['url_origen'], $evento['event_source_url']);
        afirmar_igual(['currency' => 'USD', 'value' => 12.5], array_intersect_key($evento['custom_data'], ['currency' => 1, 'value' => 1]));
        afirmar_igual('190.1.2.3', $evento['user_data']['client_ip_address']);
        afirmar_igual(hash('sha256', 'maria@correo.com'), $evento['user_data']['em']);
        http_simulador(quitar: true);
    });
});

prueba('Purchase sin código de WhatsApp: se envía como venta por chat', function () {
    bd_de_prueba();
    con_config(CONFIG_META, function () {
        $llamadas = [];
        simular_meta($llamadas);
        venta_registrar(datos_venta());
        $evento = $llamadas[0]['datos']['data'][0];
        afirmar_igual('chat', $evento['action_source']);
        afirmar(!isset($evento['event_source_url']));
        http_simulador(quitar: true);
    });
});

prueba('si Meta falla, el evento queda en error y se reintenta después; los muy viejos se descartan', function () {
    bd_de_prueba();
    con_config(CONFIG_META, function () {
        $llamadas = [];
        simular_meta($llamadas, [[false, '{"error":{"message":"caído"}}', 500], [true, '{}', 200]]);
        $resultado = venta_registrar(datos_venta());
        afirmar_igual(false, $resultado['meta']);
        afirmar_igual('error', db_valor('SELECT estado FROM eventos_meta'));
        afirmar_igual(['enviados' => 1, 'fallidos' => 0], meta_reintentar_pendientes());
        afirmar_igual('enviado', db_valor('SELECT estado FROM eventos_meta'));

        $otro = venta_registrar(datos_venta(['email' => 'otro@x.com']));
        db_ejecutar("UPDATE eventos_meta SET estado = 'error', creado_en = ? WHERE venta_id = ?", [gmdate('Y-m-d H:i:s', time() - 8 * 86400), $otro['venta']['id']]);
        meta_reintentar_pendientes();
        afirmar_igual('descartado', db_valor('SELECT estado FROM eventos_meta WHERE venta_id = ?', [$otro['venta']['id']]));
        http_simulador(quitar: true);
    });
});

prueba('un evento no se envía dos veces aunque se intente a la vez', function () {
    bd_de_prueba();
    con_config(CONFIG_META, function () {
        $llamadas = [];
        simular_meta($llamadas);
        $id = meta_encolar('Contact', ['event_id' => 'x-1', 'event_name' => 'Contact'], null, null);
        afirmar_igual($id, meta_encolar('Contact', ['event_id' => 'x-1', 'event_name' => 'Contact'], null, null), 'Mismo event_id, misma fila.');
        afirmar(meta_enviar_evento($id));
        afirmar(!meta_enviar_evento($id), 'Ya enviado: no se repite.');
        afirmar_igual(1, count($llamadas));
        http_simulador(quitar: true);
    });
});

prueba('con Pixel configurado, la landing lo carga y la CSP autoriza solo a Meta', function () {
    bd_de_prueba();
    csp_reiniciar();
    con_config(CONFIG_META, function () {
        simular_peticion();
        $html = pagina_inicio()['cuerpo'];
        afirmar_contiene("fbq('init', '123456789012345')", $html);
        afirmar_contiene("fbq('track', 'PageView')", $html);
        afirmar_contiene('https://connect.facebook.net', politica_csp());
    });
    csp_reiniciar();
});
