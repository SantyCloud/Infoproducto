<?php
/**
 * Lista de ventas.
 * @var array $ventas
 * @var bool  $hay_mas
 * @var int   $pagina
 */
$estadosMeta = ['enviado' => ['Enviado', 'ok'], 'error' => ['Error', 'error'], 'descartado' => ['Descartado', 'error'], 'pendiente' => ['Pendiente', 'alerta'], 'enviando' => ['Enviando', 'alerta']];
?>
<div class="cabecera-tarjeta">
    <h1>Ventas</h1>
    <div class="acciones">
        <a class="boton boton--secundario" href="/admin/exportar/ventas"><?= icono('descarga') ?> Exportar</a>
        <a class="boton" href="/admin/ventas/nueva"><?= icono('mas') ?> Registrar venta</a>
    </div>
</div>
<?php if ($ventas): ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>#</th><th>Fecha</th><th>Comprador</th><th>Monto</th><th>Método</th><th>Código</th><th>Campaña / anuncio</th><th>Meta</th></tr></thead>
            <tbody>
            <?php foreach ($ventas as $venta): ?>
                <tr>
                    <td><?= (int) $venta['id'] ?></td>
                    <td><?= e(fecha_local($venta['creado_en'])) ?></td>
                    <td class="envolver"><a href="/admin/compradores/<?= (int) $venta['comprador_id'] ?>"><?= e($venta['nombre']) ?></a><br><small class="suave"><?= e($venta['email']) ?></small></td>
                    <td><strong><?= e(formatear_centavos($venta['monto_centavos'])) ?></strong></td>
                    <td><?= e($venta['metodo_pago'] ?: '—') ?></td>
                    <td class="codigo"><?= e($venta['codigo'] ?: '—') ?></td>
                    <td class="envolver"><?= e($venta['utm_campaign'] ?: '—') ?><?php if ($venta['utm_content']): ?><br><small class="suave"><?= e($venta['utm_content']) ?></small><?php endif; ?></td>
                    <td>
                        <?php if ($venta['meta_estado'] && isset($estadosMeta[$venta['meta_estado']])): [$texto, $clase] = $estadosMeta[$venta['meta_estado']]; ?>
                            <span class="estado estado--<?= $clase ?>"><?= e($texto) ?></span>
                        <?php else: ?><span class="estado">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <nav class="paginacion">
        <?php if ($pagina > 1): ?><a class="boton boton--secundario" href="/admin/ventas?p=<?= $pagina - 1 ?>">← Anteriores</a><?php else: ?><span></span><?php endif; ?>
        <?php if ($hay_mas): ?><a class="boton boton--secundario" href="/admin/ventas?p=<?= $pagina + 1 ?>">Siguientes →</a><?php endif; ?>
    </nav>
<?php else: ?>
    <p class="vacio tarjeta">Todavía no hay ventas registradas.</p>
<?php endif; ?>
