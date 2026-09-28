<?php
/**
 * Enlace de acceso listo para copiar o enviar por WhatsApp al comprador.
 * @var string $enlace
 * @var array  $comprador
 */
$mensaje = 'Hola ' . primer_nombre((string) $comprador['nombre']) . ' 👋 Este es tu acceso a '
    . contenido('negocio')['producto'] . ': ' . $enlace
    . "\n\nEl enlace es personal y sirve una sola vez. Para volver a entrar después, pide uno nuevo en "
    . config('app.url') . '/entrar';
$numero = preg_replace('/\D/', '', (string) ($comprador['whatsapp'] ?? ''));
?>
<div class="tarjeta">
    <h2><?= icono('enlace') ?> Enlace de acceso</h2>
    <p class="suave chico">Personal, de un solo uso, válido 7 días. Puedes enviárselo también por WhatsApp.</p>
    <div class="copiar">
        <input id="enlace-acceso" type="text" value="<?= e($enlace) ?>" readonly>
        <button class="boton boton--secundario" type="button" data-copiar="#enlace-acceso"><?= icono('copiar') ?> Copiar</button>
    </div>
    <?php if ($numero !== ''): ?>
        <p class="separado">
            <a class="boton boton--wa" href="https://wa.me/<?= e($numero) ?>?text=<?= e(rawurlencode($mensaje)) ?>" target="_blank" rel="noopener">
                <?= icono_whatsapp() ?> Enviar por WhatsApp
            </a>
        </p>
    <?php endif; ?>
</div>
