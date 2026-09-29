<?php
/**
 * Estructura común de todas las páginas.
 * @var string $cuerpo  HTML de la página
 * @var string $titulo
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? '') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?= $cuerpo ?>
<script src="<?= e(asset('js/formularios.js')) ?>" defer></script>
</body>
</html>
