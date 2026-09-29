<?php
/**
 * Resultado de registrar un pago por activar, o de crearle un enlace nuevo.
 * @var array      $resultado     ver activacion_crear()
 * @var bool       $enlace_nuevo  se creó otro enlace para un pago ya registrado
 * @var array|null $comprador     quien lo activó (si ya lo activó)
 * @var array|null $clic          el clic a WhatsApp del pago, si tiene
 */
$activacion = $resultado['activacion'];
$id = (int) $activacion['id'];
$monto = formatear_centavos($activacion['monto_centavos'], $activacion['moneda']);
?>
<?php if ($resultado['codigo'] !== null): ?>
    <div class="aviso aviso--ok">
        <?= icono('check') ?>
        <?= $enlace_nuevo
            ? 'Enlace nuevo para el pago #' . $id . ' (' . e($monto) . '): el anterior ya no sirve.'
            : 'Pago #' . $id . ' registrado: ' . e($monto) . '. Ahora envíale su enlace para que active su acceso.' ?>
    </div>
    <?= plantilla('parciales/enlace_activacion', ['codigo' => $resultado['codigo'], 'activacion' => $activacion]) ?>
<?php else: ?>
    <div class="aviso aviso--alerta">Este pago ya estaba registrado (#<?= $id ?>, <?= e($monto) ?>): no se duplicó nada.</div>
    <div class="tarjeta">
        <?php if ($comprador !== null): ?>
            <p>Ya lo activó <a href="/admin/compradores/<?= (int) $comprador['id'] ?>"><?= e($comprador['nombre']) ?></a>
                (<?= e($comprador['email']) ?>) el <?= e(fecha_local($activacion['usado_en'])) ?>: es la venta #<?= (int) $activacion['venta_id'] ?>.</p>
        <?php elseif ($activacion['anulado_en'] !== null): ?>
            <p>Lo anulaste el <?= e(fecha_local($activacion['anulado_en'])) ?>.</p>
        <?php else: ?>
            <p>Todavía no lo activa. El enlace solo se muestra al crearlo: si no lo copiaste, crea uno nuevo (el anterior deja de servir).</p>
            <form method="post" action="/admin/activaciones/<?= $id ?>/enlace">
                <?= csrf_campo() ?>
                <button class="boton" type="submit"><?= icono('enlace') ?> Crear un enlace nuevo</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($clic !== null): ?>
    <p class="suave chico">Clic <span class="codigo"><?= e($clic['codigo']) ?></span> · <?= e($clic['utm_campaign'] ?: ($clic['utm_source'] ?: 'Directo (sin anuncio)')) ?></p>
<?php endif; ?>

<div class="acciones">
    <a class="boton" href="/admin/ventas/nueva"><?= icono('mas') ?> Registrar otra venta</a>
    <a class="boton boton--secundario" href="/admin/ventas">Ver ventas</a>
</div>
