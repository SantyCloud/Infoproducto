<?php
/*
 * DATOS DEL NEGOCIO
 *
 * Nombre, precios, promoción, WhatsApp… Se usan en toda la web (landing, emails,
 * área de miembros), así que basta con cambiarlos aquí.
 *
 * Al editar: respeta las comillas '…' y la coma al final de cada línea.
 */

return [
    // Nombre del producto (títulos, botones, emails)
    'producto' => 'Sistema de Reventa SMM',

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

    // WhatsApp donde cierras las ventas: código de país + número, sin "+" ni espacios (Ecuador: 593…)
    'whatsapp' => [
        'numero' => '593900000000',
        // Mensaje que aparece ya escrito al abrir WhatsApp.
        // {codigo} es obligatorio: es lo que te dice qué anuncio trajo la venta.
        'mensaje' => 'Hola 👋 Quiero el {producto} a {precio}. Mi código: {codigo}',
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
];
