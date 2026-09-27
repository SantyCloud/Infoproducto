<?php
/**
 * Página de error (404, 405, 500).
 * @var string $titulo
 * @var string $mensaje
 */
?>
<main class="contenedor">
    <h1><?= e($titulo) ?></h1>
    <p><?= e($mensaje) ?></p>
    <p><a href="/">Volver al inicio</a></p>
</main>
