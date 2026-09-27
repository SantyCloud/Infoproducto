<?php
declare(strict_types=1);

/*
 * Todas las direcciones de la web: [método, ruta, función que la atiende].
 * {nombre} captura un trozo de la ruta (letras, números, - y _) y llega a la función como parámetro.
 */

return [
    ['GET', '/', 'pagina_inicio'],
];
