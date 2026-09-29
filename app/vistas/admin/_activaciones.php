<?php
/**
 * Pagos que el cliente todavía no activa, con sus acciones: enlace nuevo o anular.
 * @var array $activaciones  ver activaciones_pendientes()
 */
$ahora = ahora_bd();
?>
<section class="tarjeta">
    <h2>Por activar <small class="suave chico">(pagaron y falta que activen su acceso)</small></h2>
    <ul class="lista-pagos">
        <?php foreach ($activaciones as $activacion): $id = (int) $activacion['id']; ?>
            <li>
                <div>
                    <p>
                        <strong><?= e(formatear_centavos($activacion['monto_centavos'], $activacion['moneda'])) ?></strong>
                        <span class="suave">· Pago #<?= $id ?><?= $activacion['metodo_pago'] ? ' · ' . e($activacion['metodo_pago']) : '' ?></span>
                    </p>
                    <p class="suave chico">
                        Pagó el <?= e(fecha_local($activacion['creado_en'])) ?>
                        <?php if ($activacion['codigo_clic']): ?> · Clic <span class="codigo"><?= e($activacion['codigo_clic']) ?></span><?php endif; ?>
                        <?php if ($activacion['whatsapp']): ?> · WhatsApp <?= e($activacion['whatsapp']) ?><?php endif; ?>
                    </p>
                    <?php if ($activacion['expira_en'] <= $ahora): ?>
                        <span class="estado estado--error">Enlace vencido</span>
                    <?php else: ?>
                        <span class="estado estado--alerta">Enlace vence el <?= e(fecha_local($activacion['expira_en'], 'd/m')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="acciones">
                    <form method="post" action="/admin/activaciones/<?= $id ?>/enlace">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="version" value="<?= e(version_enlace_activacion($activacion)) ?>">
                        <button class="boton boton--chico" type="submit"><?= icono('enlace') ?> Enlace nuevo</button>
                    </form>
                    <form method="post" action="/admin/activaciones/<?= $id ?>/anular">
                        <?= csrf_campo() ?>
                        <button class="boton boton--chico boton--secundario" type="submit" data-confirmar="¿Anular el pago #<?= $id ?>? Su enlace dejará de servir y no contará en tus ingresos.">Anular</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="suave chico separado">Ya cuentan en tus ventas e ingresos. Si un cliente perdió su enlace o se le venció, crea uno nuevo y reenvíaselo.</p>
</section>
