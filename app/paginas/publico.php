<?php
declare(strict_types=1);

/*
 * Páginas públicas (no necesitan sesión).
 */

function pagina_inicio(): array
{
    $negocio = contenido('negocio');
    return html(vista('inicio', [
        'titulo' => $negocio['producto'],
        'negocio' => $negocio,
        'promo' => promo_vigente($negocio),
        'precio' => precio_actual($negocio),
    ]));
}
