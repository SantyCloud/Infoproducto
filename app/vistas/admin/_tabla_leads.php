<?php
/**
 * Tabla de leads (clics a WhatsApp), reutilizada en el inicio y en la lista completa.
 * @var array $leads
 */
?>
<?php if ($leads): ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Código</th><th>País</th><th>Fecha</th><th>Campaña / anuncio</th><th>Botón</th><th>Clics</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($leads as $lead): ?>
                <tr>
                    <td class="codigo"><?= e($lead['codigo']) ?></td>
                    <td><?= e(strtoupper((string) ($lead['pais'] ?? '')) ?: '—') ?></td>
                    <td><?= e(fecha_local($lead['creado_en'])) ?></td>
                    <td class="envolver">
                        <?= e($lead['utm_campaign'] ?: ($lead['utm_source'] ?: 'Directo')) ?>
                        <?php if ($lead['utm_content']): ?><br><small class="suave"><?= e($lead['utm_content']) ?></small><?php endif; ?>
                    </td>
                    <td><?= e($lead['boton'] ?: '—') ?></td>
                    <td><?= (int) $lead['clics'] ?></td>
                    <td>
                        <?php if ($lead['venta_id']): ?>
                            <span class="estado estado--ok">Vendido</span>
                        <?php else: ?>
                            <a class="boton boton--chico boton--secundario" href="/admin/ventas/nueva?codigo=<?= e(rawurlencode($lead['codigo'])) ?>">Registrar venta</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="vacio">No hay clics que mostrar.</p>
<?php endif; ?>
