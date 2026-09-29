<?php
/**
 * Estructura del área de miembros.
 * @var string $cuerpo
 * @var string $titulo
 * @var array  $comprador
 */
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
        <a class="marca" href="/miembros"><?= e(contenido('negocio')['producto']) ?></a>
        <ul class="menu">
            <li><a href="/miembros"><?= icono('libro') ?> Curso</a></li>
            <li>
                <form method="post" action="/miembros/salir"><?= csrf_campo() ?><button class="enlace-boton" type="submit"><?= icono('salir') ?> Salir</button></form>
            </li>
        </ul>
    </div>
</header>
<main class="contenedor">
<?= $cuerpo ?>
</main>
<script src="<?= e(asset('js/formularios.js')) ?>" defer></script>
</body>
</html>
