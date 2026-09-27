<?php
/**
 * Portada provisional: la landing completa se construye en la fase 2.
 * @var array $negocio
 * @var bool  $promo
 * @var float $precio
 */
?>
<main class="contenedor">
    <h1><?= e($negocio['producto']) ?></h1>
    <p class="precio">
        <?php if ($promo): ?>
            <s><?= e(formatear_precio($negocio['precio_normal'])) ?></s>
        <?php endif; ?>
        <strong><?= e(formatear_precio($precio)) ?></strong>
    </p>
    <p>Página en construcción.</p>
</main>
