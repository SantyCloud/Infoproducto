<?php
/**
 * Una lección: video, texto, descargables y navegación.
 * @var array      $leccion
 * @var array|null $video      ['url', 'origen']
 * @var string     $html       texto de la lección ya convertido
 * @var array      $descargas  [id => ['nombre', 'archivo']]
 */
?>
<p><a class="volver" href="/miembros"><?= icono('atras') ?> Volver al curso</a></p>
<p class="suave chico">Lección <?= (int) $leccion['numero'] ?> · <?= e($leccion['modulo']) ?></p>
<h1><?= e($leccion['titulo']) ?></h1>

<?php if ($video): ?>
    <div class="video">
        <iframe src="<?= e($video['url']) ?>" title="<?= e($leccion['titulo']) ?>" loading="lazy"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
<?php endif; ?>

<?php if ($html !== ''): ?>
    <article class="tarjeta contenido-leccion"><?= $html ?></article>
<?php endif; ?>

<?php if ($descargas): ?>
    <section class="tarjeta">
        <h2>Materiales de esta lección</h2>
        <ul class="descargas">
            <?php foreach ($descargas as $id => $descarga): ?>
                <li><a href="/miembros/descargar/<?= e((string) $id) ?>"><?= icono('descarga') ?> <?= e($descarga['nombre']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<nav class="navegacion-lecciones">
    <?php if ($leccion['anterior']): ?>
        <a class="boton boton--secundario" href="/miembros/leccion/<?= e($leccion['anterior']['slug']) ?>"><?= icono('atras') ?> Anterior</a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($leccion['siguiente']): ?>
        <a class="boton" href="/miembros/leccion/<?= e($leccion['siguiente']['slug']) ?>">Siguiente: <?= e($leccion['siguiente']['titulo']) ?> <?= icono('flecha') ?></a>
    <?php else: ?>
        <a class="boton" href="/miembros">Terminaste 🎉 Volver al inicio</a>
    <?php endif; ?>
</nav>
