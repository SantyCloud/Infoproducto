<?php
/**
 * Inicio del panel: números clave, qué anuncios traen ventas y últimos clics.
 * @var array $metricas   [[nombre, valor], ...]
 * @var int   $leads30
 * @var int   $ventas30
 * @var array $recientes
 * @var array $anuncios
 * @var array $por_activar  pagos que el cliente todavía no activa
 * @var array $pendientes
 */
?>
<div class="cabecera-tarjeta">
    <h1>Inicio</h1>
    <a class="boton" href="/admin/ventas/nueva"><?= icono('mas') ?> Registrar venta</a>
</div>

<?php if ($pendientes): ?>
    <div class="aviso aviso--alerta">
        <strong>Pendientes:</strong>
        <ul>
            <?php foreach ($pendientes as $pendiente): ?><li><?= e($pendiente) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="metricas">
    <?php foreach ($metricas as [$nombre, $valor]): ?>
        <div class="metrica">
            <?php $partes = explode(' · ', (string) $valor); // varias monedas: una por línea ?>
            <span class="metrica__valor<?= count($partes) > 1 ? ' metrica__valor--varias' : '' ?>"><?= implode('<br>', array_map('e', $partes)) ?></span>
            <span class="metrica__nombre"><?= e($nombre) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($por_activar): ?>
    <?= plantilla('admin/_activaciones', ['activaciones' => $por_activar]) ?>
<?php endif; ?>

<section class="tarjeta">
    <div class="cabecera-tarjeta">
        <h2>¿Qué anuncios venden? <small class="suave chico">(últimos 30 días)</small></h2>
        <span class="suave chico"><?= $leads30 ?> clics · <?= $ventas30 ?> ventas</span>
    </div>
    <?php if ($anuncios): ?>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>Campaña</th><th>Anuncio</th><th>Clics</th><th>Ventas</th><th>Cierre</th><th>Ingresos</th></tr></thead>
                <tbody>
                <?php foreach ($anuncios as $fila): ?>
                    <tr>
                        <td class="envolver"><?= e($fila['campana']) ?></td>
                        <td class="envolver"><?= e($fila['anuncio'] ?: '—') ?></td>
                        <td><?= (int) $fila['leads'] ?></td>
                        <td><strong><?= (int) $fila['ventas'] ?></strong></td>
                        <td><?= $fila['leads'] > 0 ? round($fila['ventas'] / $fila['leads'] * 100) . '%' : '—' ?></td>
                        <td><?= e(formatear_montos($fila['ingresos'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="suave chico">La campaña y el anuncio salen de los parámetros <strong>utm_campaign</strong> y <strong>utm_content</strong> de los enlaces de tus anuncios.</p>
    <?php else: ?>
        <p class="vacio">Todavía no hay clics. Aparecerán aquí cuando alguien toque el botón de WhatsApp.</p>
    <?php endif; ?>
</section>

<section class="tarjeta">
    <div class="cabecera-tarjeta">
        <h2>Últimos clics a WhatsApp</h2>
        <a href="/admin/leads">Ver todos →</a>
    </div>
    <?= plantilla('admin/_tabla_leads', ['leads' => $recientes]) ?>
</section>
