<?php
/**
 * Galería deslizable de capturas; al tocar una se abre en grande.
 * @var array  $capturas        resultado de capturas()
 * @var string $alt             texto alternativo
 * @var bool   $mostrar_huecos  en local, muestra dónde irán las capturas si aún no hay
 * @var string $hueco           texto del hueco
 */
?>
<?php if ($capturas): ?>
    <div class="galeria<?= count($capturas) < 3 ? ' galeria--pocas' : '' ?>">
        <?php foreach ($capturas as $i => $captura): ?>
            <button type="button" class="galeria__item" data-grande="<?= e($captura['src']) ?>" aria-label="Ver captura <?= $i + 1 ?> en grande">
                <img src="<?= e($captura['src_chico']) ?>" srcset="<?= e($captura['srcset']) ?>"
                     sizes="(min-width: 900px) 260px, 68vw" width="<?= $captura['ancho'] ?>" height="<?= $captura['alto'] ?>"
                     alt="<?= e($alt) ?>" loading="lazy" decoding="async">
            </button>
        <?php endforeach; ?>
    </div>
    <?php if (count($capturas) > 1): ?>
        <p class="galeria__pista">Desliza para ver más →</p>
    <?php endif; ?>
<?php elseif ($mostrar_huecos): ?>
    <div class="hueco">📷 <?= e($hueco) ?></div>
<?php endif; ?>
