<?php
/**
 * Lista de clics a WhatsApp con búsqueda y filtro.
 * @var array  $leads
 * @var bool   $hay_mas
 * @var int    $pagina
 * @var string $buscar
 * @var string $estado
 */
$url = fn (int $p) => '/admin/leads?' . http_build_query(array_filter(['q' => $buscar, 'estado' => $estado, 'p' => $p > 1 ? $p : null]));
?>
<div class="cabecera-tarjeta">
    <h1>Clics a WhatsApp</h1>
    <a class="boton boton--secundario" href="/admin/exportar/leads"><?= icono('descarga') ?> Exportar</a>
</div>
<form class="filtros" method="get" action="/admin/leads">
    <input type="search" name="q" value="<?= e($buscar) ?>" placeholder="Código, campaña o anuncio">
    <select name="estado">
        <option value="">Todos</option>
        <option value="sin-venta"<?= $estado === 'sin-venta' ? ' selected' : '' ?>>Sin venta</option>
        <option value="con-venta"<?= $estado === 'con-venta' ? ' selected' : '' ?>>Con venta</option>
    </select>
    <button class="boton boton--secundario" type="submit"><?= icono('buscar') ?> Buscar</button>
</form>
<?= plantilla('admin/_tabla_leads', ['leads' => $leads]) ?>
<nav class="paginacion">
    <?php if ($pagina > 1): ?><a class="boton boton--secundario" href="<?= e($url($pagina - 1)) ?>">← Anteriores</a><?php else: ?><span></span><?php endif; ?>
    <?php if ($hay_mas): ?><a class="boton boton--secundario" href="<?= e($url($pagina + 1)) ?>">Siguientes →</a><?php endif; ?>
</nav>
