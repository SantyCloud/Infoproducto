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

    // WhatsApp donde cierras las ventas: código de país + número, sin "+" ni espacios (Ecuador: 593…)
    'whatsapp' => [
        'numero' => '593968473532',
        // Mensaje que aparece ya escrito al abrir WhatsApp
        'mensaje' => 'Hola 👋 Quiero el {producto} a {precio}.',
        // Se añade al final del mensaje. {codigo} es lo que te dice qué anuncio trajo la venta.
        'texto_codigo' => 'Mi código: {codigo}',
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
