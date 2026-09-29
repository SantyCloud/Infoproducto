<?php
/**
 * Botón que lleva a WhatsApp pasando por /wa (ahí se crea el lead con su código).
 * @var string $texto
 * @var string $id     identifica el botón en las estadísticas (hero, oferta, cierre, barra)
 * @var string $clase
 * @var string|null $pais   página del país (mx, ec…): el mensaje de WhatsApp sale con sus precios
 */
?>
<a class="boton-wa js-wa <?= e($clase ?? '') ?>" href="/wa?b=<?= e($id) ?><?= !empty($pais) ? '&amp;p=' . e($pais) : '' ?>" rel="nofollow">
    <?= icono_whatsapp() ?>
    <span><?= formato($texto) ?></span>
</a>
