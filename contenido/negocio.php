<?php
/*
 * DATOS DEL NEGOCIO
 *
 * Nombre, precios, promoción, WhatsApp… Se usan en toda la web (landing, emails,
 * área de miembros, páginas legales), así que basta con cambiarlos aquí.
 * Lo que está [entre corchetes] es un ejemplo: cámbialo por tus datos reales.
 *
 * Al editar: respeta las comillas '…' y la coma al final de cada línea.
 */

return [
    // Nombre del producto (títulos, botones, emails)
    'producto' => 'Método Revendedor SMM',

    // Precio normal, en dólares
    'precio_normal' => 15,

    // Promoción. Tiene que ser REAL: al pasar la fecha de fin, la web muestra sola el precio normal.
    // Para quitarla: 'activa' => false
    'promo' => [
        'activa' => true,
        'precio' => 10,
        'nombre' => 'Precio de lanzamiento',
        'termina' => '2026-10-31 23:59', // hora de Ecuador. Pon null si la promo no tiene fecha de fin
    ],

    // Días de garantía de devolución. Pon 0 si no ofreces garantía: se ocultan las menciones en la landing y
    // la página de reembolsos muestra solo lo que exige la ley (contenido/legal/reembolsos-sin-garantia.md).
    'garantia_dias' => 0,

    // Páginas por país, para los anuncios de cada país: tudominio.com/ec, tudominio.com/mx…
    // Todas muestran el mismo contenido. Cada una usa las capturas de su país (ingresos-ec-1.jpg,
    // ingresos-mx-1.jpg…) y, si lo indicas, sus propios precios y moneda; lo que no indiques se toma
    // de arriba (dólares). La promo usa el mismo nombre y la misma fecha de fin para todos.
    // tudominio.com (sin país) muestra la versión general, en dólares y con las capturas de todos.
    'paises' => [
        'ec' => ['nombre' => 'Ecuador'],
        'mx' => [
            'nombre' => 'México',
            'moneda' => 'MXN',        // pesos mexicanos: se muestra "$200 MXN"
            'precio_normal' => 300,
            'precio_promo' => 200,
        ],
    ],

    // WhatsApp donde cierras las ventas: código de país + número, sin "+" ni espacios (Ecuador: 593…)
    'whatsapp' => [
        'numero' => '593968473532',
        // Mensaje que aparece ya escrito al abrir WhatsApp
        'mensaje' => 'Hola 👋 Quiero el {producto} a {precio}.',
        // Se añade al final del mensaje. {codigo} es lo que te dice qué anuncio trajo la venta.
        'texto_codigo' => 'Mi código: {codigo}',
        // Mensaje con el que le envías al cliente su enlace de activación, después de cobrar
        // (el panel lo deja listo al registrar la venta). {enlace} = su enlace · {codigo} = su código · \n = salto de línea.
        'mensaje_activacion' => "¡Gracias por tu compra! 🎉\n\nActiva tu acceso a {producto} aquí:\n{enlace}\n\nSolo te pedirá tu nombre y tu email. Si el enlace no abre, entra a {sitio}/activar y escribe el código {codigo}.",
    ],

    // Métodos de pago que aceptas (se muestran en la web para dar confianza)
    'metodos_pago' => ['Transferencia bancaria', 'PayPal', 'Binance (USDT)'],

    // Tu panel: enlace de registro (o de referido) y, si quieres, un código de bono
    'smmclixy' => [
        'url_registro' => 'https://smmclixy.com',
        'codigo_bono' => '',
    ],

    // Contacto de soporte (emails y páginas legales)
    'soporte' => [
        'email' => 'soporte@tudominio.com',
    ],

    // Datos para las páginas legales (términos, privacidad, reembolsos)
    'legal' => [
        'titular' => '[Tu nombre o razón social]',
        'identificacion' => '[Tu RUC o cédula]',
        'ciudad' => '[Tu ciudad]',
        'pais' => 'Ecuador',
        'fecha_actualizacion' => '29 de septiembre de 2026',
    ],
];
