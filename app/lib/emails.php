<?php
declare(strict_types=1);

/*
 * Emails transaccionales con Resend (https://resend.com): el acceso tras la compra y el
 * enlace para entrar. Los textos están en contenido/emails.php.
 *
 * Sin RESEND_API_KEY no se envía nada: el email queda "simulado" en storage/logs/ y en el
 * panel, para poder probar todo en local.
 */

/** Envía un email y lo registra en la tabla emails. Devuelve ['ok', 'simulado', 'id', 'error']. */
function email_enviar(string $tipo, array $comprador, string $asunto, string $html, string $texto, ?string $claveIdempotencia = null): array
{
    $para = (string) $comprador['email'];
    if (config('email.resend_api_key') === '') {
        registrar('emails', "Email simulado ($tipo) para $para", ['asunto' => $asunto, 'texto' => $texto]);
        $resultado = ['ok' => true, 'simulado' => true, 'id' => null, 'error' => null];
    } else {
        $cabeceras = ['Authorization: Bearer ' . config('email.resend_api_key')];
        if ($claveIdempotencia !== null) {
            $cabeceras[] = 'Idempotency-Key: ' . $claveIdempotencia; // Resend no lo envía dos veces
        }
        $datos = ['from' => config('email.remitente'), 'to' => [$para], 'subject' => $asunto, 'html' => $html, 'text' => $texto];
        if (config('email.responder_a') !== '') {
            $datos['reply_to'] = config('email.responder_a');
        }
        [$ok, $respuesta, $codigo] = http_post_json('https://api.resend.com/emails', $datos, $cabeceras, 15);
        $json = json_decode($respuesta, true);
        $error = $ok ? null : "HTTP $codigo: " . limpiar(is_array($json) ? ($json['message'] ?? $respuesta) : $respuesta, 300);
        $resultado = ['ok' => $ok, 'simulado' => false, 'id' => is_array($json) ? ($json['id'] ?? null) : null, 'error' => $error];
        if (!$ok) {
            registrar('emails', "Resend no envió el email ($tipo) para $para", ['error' => $error]);
        }
    }
    db_insertar('emails', [
        'comprador_id' => $comprador['id'] ?? null,
        'tipo' => $tipo,
        'destinatario' => $para,
        'proveedor_id' => $resultado['id'],
        'estado' => $resultado['ok'] ? ($resultado['simulado'] ? 'simulado' : 'enviado') : 'error',
        'error' => $resultado['error'],
        'creado_en' => ahora_bd(),
    ]);
    return $resultado;
}

/** Textos de un email de contenido/emails.php con las variables del negocio y del comprador. */
function textos_email(string $tipo, array $comprador): array
{
    $negocio = contenido('negocio');
    $variables = variables_texto($negocio) + [
        '{nombre}' => primer_nombre((string) $comprador['nombre']),
        '{codigo_bono}' => (string) ($negocio['smmclixy']['codigo_bono'] ?? ''),
    ];
    return textos(contenido('emails')[$tipo], $variables);
}

function primer_nombre(string $nombre): string
{
    return preg_split('/\s+/', trim($nombre))[0] ?? $nombre;
}

/** Email de bienvenida con el enlace de acceso y el paso de registrarse en smmclixy. */
function email_acceso(array $comprador, string $enlace, ?string $claveIdempotencia = null): array
{
    $t = textos_email('acceso', $comprador);
    $negocio = contenido('negocio');
    $bono = (string) ($negocio['smmclixy']['codigo_bono'] ?? '');
    $caja = [
        'titulo' => $t['paso_panel'],
        'texto' => $bono !== '' ? $t['bono'] : '',
        'boton' => $t['boton_panel'],
        'url' => url_registro_panel(),
    ];
    $html = plantilla('emails/base', [
        'asunto' => $t['asunto'], 'titulo' => $t['titulo'], 'parrafos' => $t['parrafos'],
        'boton' => $t['boton'], 'url' => $enlace, 'caja' => $caja, 'despedida' => $t['despedida'],
    ]);
    $texto = email_texto_plano($t['titulo'], $t['parrafos'], $t['boton'], $enlace, $caja, $t['despedida']);
    return email_enviar('acceso', $comprador, $t['asunto'], $html, $texto, $claveIdempotencia);
}

/** Email con el enlace para entrar (pedido desde /entrar). */
function email_login(array $comprador, string $enlace): array
{
    $t = textos_email('login', $comprador);
    $html = plantilla('emails/base', [
        'asunto' => $t['asunto'], 'titulo' => $t['titulo'], 'parrafos' => $t['parrafos'],
        'boton' => $t['boton'], 'url' => $enlace, 'caja' => null, 'despedida' => $t['despedida'],
    ]);
    $texto = email_texto_plano($t['titulo'], $t['parrafos'], $t['boton'], $enlace, null, $t['despedida']);
    return email_enviar('login', $comprador, $t['asunto'], $html, $texto);
}

/** Versión en texto plano (la usan algunos programas de correo y ayuda a no caer en spam). */
function email_texto_plano(string $titulo, array $parrafos, string $boton, string $url, ?array $caja, string $despedida): string
{
    $lineas = [str_replace('**', '', $titulo), ''];
    foreach ($parrafos as $parrafo) {
        $lineas[] = str_replace('**', '', $parrafo);
        $lineas[] = '';
    }
    $lineas[] = "$boton: $url";
    $lineas[] = '';
    if ($caja !== null) {
        $lineas[] = str_replace('**', '', $caja['titulo']);
        if ($caja['texto'] !== '') {
            $lineas[] = str_replace('**', '', $caja['texto']);
        }
        $lineas[] = "{$caja['boton']}: {$caja['url']}";
        $lineas[] = '';
    }
    $lineas[] = str_replace('**', '', $despedida);
    return implode("\n", $lineas);
}

/** Enlace de registro en smmclixy, marcado para saber que la visita viene del curso (y desde dónde). */
function url_registro_panel(string $medio = 'email'): string
{
    $url = (string) (contenido('negocio')['smmclixy']['url_registro'] ?? 'https://smmclixy.com');
    return $url . (str_contains($url, '?') ? '&' : '?') . 'utm_source=curso&utm_medium=' . rawurlencode($medio);
}
