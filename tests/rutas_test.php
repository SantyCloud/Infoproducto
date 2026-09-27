<?php
declare(strict_types=1);

function rutas_de_prueba(): array
{
    return [
        ['GET', '/', 'inicio'],
        ['GET', '/miembros/leccion/{slug}', 'leccion'],
        ['POST', '/admin/ventas', 'crear_venta'],
    ];
}

prueba('encuentra la función y los parámetros de cada ruta', function () {
    $rutas = rutas_de_prueba();
    afirmar_igual(['funcion' => 'inicio', 'parametros' => []], resolver_ruta($rutas, 'GET', '/'));
    afirmar_igual(['funcion' => 'inicio', 'parametros' => []], resolver_ruta($rutas, 'HEAD', '/'));
    afirmar_igual(
        ['funcion' => 'leccion', 'parametros' => ['slug' => 'modulo-1']],
        resolver_ruta($rutas, 'GET', '/miembros/leccion/modulo-1')
    );
    afirmar_igual(['funcion' => 'crear_venta', 'parametros' => []], resolver_ruta($rutas, 'POST', '/admin/ventas'));
});

prueba('responde 404 si la ruta no existe y 405 si el método no corresponde', function () {
    $rutas = rutas_de_prueba();
    afirmar_igual(['estado' => 404], resolver_ruta($rutas, 'GET', '/no-existe'));
    afirmar_igual(['estado' => 405], resolver_ruta($rutas, 'GET', '/admin/ventas'));
    afirmar_igual(['estado' => 404], resolver_ruta($rutas, 'GET', '/miembros/leccion/../../.env'));
    afirmar_igual(['estado' => 404], resolver_ruta($rutas, 'GET', '/miembros/leccion/a/b'));
});

prueba('normaliza la ruta: sin barra final ni parámetros', function () {
    afirmar_igual('/admin', ruta_de('/admin/?pagina=2'));
    afirmar_igual('/', ruta_de('/'));
    afirmar_igual('/', ruta_de('/?utm_source=facebook'));
    afirmar_igual('/', ruta_de(''));
});
