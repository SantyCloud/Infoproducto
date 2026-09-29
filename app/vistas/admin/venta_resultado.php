<?php
/**
 * Resultado de registrar una venta.
 * @var array $resultado  ver venta_registrar()
 */
$venta = $resultado['venta'];
$comprador = $resultado['comprador'];
$email = $resultado['email'];
?>
<?php if ($resultado['repetida']): ?>
    <div class="aviso aviso--alerta">Esta venta ya estaba registrada (#<?= (int) $venta['id'] ?>): no se duplicó nada.</div>
<?php else: ?>
    <div class="aviso aviso--ok"><?= icono('check') ?> Venta #<?= (int) $venta['id'] ?> registrada: <?= e(formatear_centavos($venta['monto_centavos'], $venta['moneda'])) ?>. <?= e($comprador['nombre']) ?> ya tiene acceso.</div>
<?php endif; ?>

<div class="tarjeta">
    <h2>Resumen</h2>
    <dl class="lista-datos">
        <dt>Comprador</dt><dd><a href="/admin/compradores/<?= (int) $comprador['id'] ?>"><?= e($comprador['nombre']) ?></a> · <?= e($comprador['email']) ?></dd>
        <dt>Email de acceso</dt>
        <dd>
            <?php if ($email === null): ?>
                <span class="estado">No enviado</span>
            <?php elseif (!$email['ok']): ?>
                <span class="estado estado--error">Error</span> <?= e($email['error']) ?>
            <?php elseif ($email['simulado']): ?>
                <span class="estado estado--alerta">Simulado</span> Falta configurar Resend: envíale el enlace de abajo por WhatsApp.
            <?php else: ?>
                <span class="estado estado--ok">Enviado</span>
            <?php endif; ?>
        </dd>
        <dt>Meta (compra)</dt>
        <dd>
            <?php if ($resultado['meta'] === null): ?>
                <span class="estado">No configurado</span>
            <?php elseif ($resultado['meta']): ?>
                <span class="estado estado--ok">Enviado</span>
            <?php else: ?>
                <span class="estado estado--alerta">Pendiente</span> Se reintentará automáticamente.
            <?php endif; ?>
        </dd>
    </dl>
</div>

<?php if ($resultado['enlace']): ?>
    <?= plantilla('parciales/enlace_acceso', ['enlace' => $resultado['enlace'], 'comprador' => $comprador]) ?>
<?php endif; ?>

<div class="acciones">
    <a class="boton" href="/admin/ventas/nueva"><?= icono('mas') ?> Registrar otra venta</a>
    <a class="boton boton--secundario" href="/admin">Volver al inicio</a>
</div>
