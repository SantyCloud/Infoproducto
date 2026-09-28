<?php
declare(strict_types=1);

/*
 * Todas las direcciones de la web: [método, ruta, función que la atiende].
 * {nombre} captura un trozo de la ruta (letras, números, - y _) y llega a la función como parámetro.
 */

return [
    // Públicas
    ['GET', '/', 'pagina_inicio'],
    ['GET', '/wa', 'pagina_whatsapp'],
    ['GET', '/terminos', 'pagina_terminos'],
    ['GET', '/privacidad', 'pagina_privacidad'],
    ['GET', '/reembolsos', 'pagina_reembolsos'],
];
