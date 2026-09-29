<?php
/**
 * Enlace de activación listo para enviárselo al cliente por WhatsApp (o copiarlo).
 * @var string $codigo      K7Q2M8XPRT
 * @var array  $activacion
 */
$mensaje = mensaje_activacion($codigo);
$numero = preg_replace('/\D/', '', (string) ($activacion['whatsapp'] ?? ''));
?>
<div class="tarjeta">
    <h2><?= icono('enlace') ?> Envíale su enlace de activación</h2>
    <p class="codigo-activacion"><span class="suave chico">Código</span> <span class="codigo"><?= e(formatear_codigo_activacion($codigo)) ?></span></p>
    <div class="campo">
        <label for="mensaje-activacion">Mensaje para el cliente</label>
        <textarea id="mensaje-activacion" class="mensaje-activacion" readonly><?= e($mensaje) ?></textarea>
    </div>
    <div class="acciones separado">
        <a class="boton boton--wa" href="https://wa.me/<?= e($numero) ?>?text=<?= e(rawurlencode($mensaje)) ?>" target="_blank" rel="noopener">
            <?= icono_whatsapp() ?> Enviar por WhatsApp
        </a>
        <button class="boton boton--secundario" type="button" data-copiar="#mensaje-activacion"><?= icono('copiar') ?> Copiar mensaje</button>
    </div>
    <p class="suave chico separado">
        <?= $numero === '' ? 'WhatsApp te pedirá elegir el chat del cliente. ' : '' ?>
        Sirve una sola vez y vence el <?= e(fecha_local($activacion['expira_en'], 'd/m/Y')) ?>. Cuando lo active se crea la venta,
        le llega el email de bienvenida y se avisa a Meta. Solo se muestra ahora: si se pierde, crea uno nuevo en Ventas → Por activar.
    </p>
</div>
