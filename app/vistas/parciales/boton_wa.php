<?php
/**
 * Botón que lleva a WhatsApp pasando por /wa (ahí se crea el lead con su código).
 * @var string $texto
 * @var string $id     identifica el botón en las estadísticas (hero, oferta, cierre, barra)
 * @var string $clase
 */
?>
<a class="boton-wa js-wa <?= e($clase ?? '') ?>" href="/wa?b=<?= e($id) ?>" rel="nofollow">
    <?= icono_whatsapp() ?>
    <span><?= formato($texto) ?></span>
</a>
