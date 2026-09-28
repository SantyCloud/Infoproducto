<?php
/**
 * Lista de compradores.
 * @var array  $compradores
 * @var bool   $hay_mas
 * @var int    $pagina
 * @var string $buscar
 */
$url = fn (int $p) => '/admin/compradores?' . http_build_query(array_filter(['q' => $buscar, 'p' => $p > 1 ? $p : null]));
?>
<div class="cabecera-tarjeta">
    <h1>Compradores</h1>
    <div class="acciones">
        <a class="boton boton--secundario" href="/admin/exportar/compradores"><?= icono('descarga') ?> Exportar</a>
        <a class="boton" href="/admin/accesos/nuevo"><?= icono('mas') ?> Dar acceso</a>
    </div>
</div>
<form class="filtros" method="get" action="/admin/compradores">
    <input type="search" name="q" value="<?= e($buscar) ?>" placeholder="Nombre, email o WhatsApp">
    <button class="boton boton--secundario" type="submit"><?= icono('buscar') ?> Buscar</button>
</form>
<?php if ($compradores): ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Email</th><th>WhatsApp</th><th>Desde</th><th>Pagado</th><th>Acceso</th></tr></thead>
            <tbody>
            <?php foreach ($compradores as $c): ?>
                <tr>
                    <td class="envolver"><a href="/admin/compradores/<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></a></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e($c['whatsapp'] ?: '—') ?></td>
                    <td><?= e(fecha_local($c['creado_en'], 'd/m/Y')) ?></td>
                    <td><?= e(formatear_centavos($c['pagado'])) ?></td>
                    <td>
                        <?php if ($c['acceso_id'] && !$c['revocado_en']): ?><span class="estado estado--ok">Activo</span>
                        <?php else: ?><span class="estado estado--error">Sin acceso</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <nav class="paginacion">
        <?php if ($pagina > 1): ?><a class="boton boton--secundario" href="<?= e($url($pagina - 1)) ?>">← Anteriores</a><?php else: ?><span></span><?php endif; ?>
        <?php if ($hay_mas): ?><a class="boton boton--secundario" href="<?= e($url($pagina + 1)) ?>">Siguientes →</a><?php endif; ?>
    </nav>
<?php else: ?>
    <p class="vacio tarjeta"><?= $buscar !== '' ? 'Nadie coincide con la búsqueda.' : 'Todavía no hay compradores.' ?></p>
<?php endif; ?>
