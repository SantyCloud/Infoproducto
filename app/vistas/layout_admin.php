<?php
/**
 * Estructura del panel de administración.
 * @var string $cuerpo
 * @var string $titulo
 * @var string $seccion  sección activa del menú
 */
$menu = [
    'inicio' => ['/admin', 'Inicio'],
    'leads' => ['/admin/leads', 'Clics'],
    'ventas' => ['/admin/ventas', 'Ventas'],
    'compradores' => ['/admin/compradores', 'Compradores'],
    'acceso' => ['/admin/accesos/nuevo', 'Dar acceso'],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titulo) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="barra-superior">
    <div class="barra-superior__interior">
        <a class="marca" href="/admin">Panel · <?= e(contenido('negocio')['producto']) ?></a>
        <ul class="menu">
            <?php foreach ($menu as $clave => [$url, $texto]): ?>
                <li><a href="<?= e($url) ?>"<?= $seccion === $clave ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a></li>
            <?php endforeach; ?>
            <li>
                <form method="post" action="/admin/salir"><?= csrf_campo() ?><button class="enlace-boton" type="submit">Salir</button></form>
            </li>
        </ul>
    </div>
</header>
<main class="contenedor">
<?= $cuerpo ?>
</main>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
