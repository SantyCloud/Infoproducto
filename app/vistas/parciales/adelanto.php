<?php
/**
 * Adelanto del curso: video corto del dueño (ver app/lib/adelanto.php).
 * Sin JavaScript, el enlace abre el video. Con landing.js se ve aquí mismo: unos segundos sin sonido al pasar
 * el mouse (en el celular, al llegar a él) y completo, con sonido, al tocarlo.
 * @var array|null $adelanto        ver adelanto()
 * @var array      $textos          contenido/landing.php → adelanto
 * @var bool       $mostrar_huecos  en local, sin video, muestra dónde irá
 */
$textos += ['etiqueta' => 'Mira un adelanto', 'sonido' => 'Dale play para escucharme', 'texto' => ''];
?>
<?php if ($adelanto !== null): ?>
    <figure class="adelanto<?= $adelanto['vertical'] ? ' adelanto--vertical' : '' ?>">
        <div class="adelanto__marco">
            <img class="adelanto__portada" src="<?= e($adelanto['portada']) ?>" width="<?= $adelanto['ancho'] ?>"
                 height="<?= $adelanto['alto'] ?>" alt="" loading="lazy" decoding="async">
            <a class="adelanto__boton js-adelanto" href="<?= e($adelanto['video']) ?>" data-previa="<?= e($adelanto['previa']) ?>"
               aria-label="Ver el adelanto del curso con sonido (<?= e($adelanto['duracion']) ?>)">
                <span class="adelanto__play"><?= icono('reproducir') ?></span>
                <span class="adelanto__etiqueta"><?= formato($textos['etiqueta']) ?> · <?= e($adelanto['duracion']) ?></span>
                <span class="adelanto__sonido"><?= icono('sin_sonido') ?> <?= formato($textos['sonido']) ?></span>
            </a>
        </div>
        <?php if ($textos['texto'] !== ''): ?><figcaption><?= formato($textos['texto']) ?></figcaption><?php endif; ?>
    </figure>
<?php elseif ($mostrar_huecos): ?>
    <div class="hueco adelanto-hueco">🎬 Aquí va tu adelanto del curso (un video corto): prepáralo con bin/optimizar-video.php o envíaselo a Claude.</div>
<?php endif; ?>
