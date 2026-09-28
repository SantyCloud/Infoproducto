<?php
/*
 * TEXTOS DE LOS EMAILS
 *
 * Variables: {nombre} (primer nombre del comprador), {producto}, {sitio}, {codigo_bono},
 * {email_soporte}, {whatsapp_soporte}. Para resaltar usa **dos asteriscos**.
 */

return [
    // Se envía al registrar la venta (o al dar un acceso a mano)
    'acceso' => [
        'asunto' => 'Tu acceso a {producto} 🎉',
        'titulo' => '¡Bienvenido/a, {nombre}!',
        'parrafos' => [
            'Tu pago está confirmado y ya tienes acceso a **{producto}**.',
            'Toca el botón para entrar. El enlace es personal y sirve una sola vez; después, para volver a entrar, pide uno nuevo en {sitio}/entrar con este mismo email.',
        ],
        'boton' => 'Entrar al curso',
        'paso_panel' => 'Tu primer paso: crea tu cuenta gratis en smmclixy.com, el panel que usaremos como proveedor.',
        'bono' => 'Usa el código **{codigo_bono}** para recibir tu bono de bienvenida.',
        'boton_panel' => 'Crear mi cuenta en smmclixy',
        'despedida' => '¿Dudas? Responde este email o escríbeme por WhatsApp al {whatsapp_soporte}.',
    ],

    // Se envía cuando alguien pide un enlace para entrar desde /entrar
    'login' => [
        'asunto' => 'Tu enlace para entrar a {producto}',
        'titulo' => 'Hola, {nombre}',
        'parrafos' => [
            'Toca el botón para entrar a tu área de miembros. El enlace vence en 30 minutos y sirve una sola vez.',
            'Si no lo pediste tú, ignora este email: nadie puede entrar sin él.',
        ],
        'boton' => 'Entrar ahora',
        'despedida' => '¿Problemas para entrar? Escríbeme por WhatsApp al {whatsapp_soporte}.',
    ],
];
