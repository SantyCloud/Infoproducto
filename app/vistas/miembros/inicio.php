<?php
/**
 * Inicio del área de miembros: bienvenida, paso 1 (cuenta en smmclixy) y módulos.
 * @var array      $comprador
 * @var array      $curso
 * @var array      $vistas     [slug => true]
 * @var array|null $siguiente  primera lección sin ver
 * @var array      $panel      contenido/negocio.php → smmclixy
 */
$total = count($curso['lista']);
$porcentaje = $total > 0 ? (int) round(count($vistas) / $total * 100) : 0;
?>
<section class="bienvenida">
    <h1>Hola, <?= e(primer_nombre((string) $comprador['nombre'])) ?> 👋</h1>
    <p><?= $porcentaje === 0 ? 'Empieza por la primera lección. Videos cortos y al grano.' : "Llevas el $porcentaje% del curso. ¡Sigue así!" ?></p>
    <?php if ($siguiente): ?>
        <a class="boton boton--wa" href="/miembros/leccion/<?= e($siguiente['slug']) ?>"><?= icono('play') ?> <?= $porcentaje === 0 ? 'Empezar' : 'Continuar' ?>: <?= e($siguiente['titulo']) ?></a>
    <?php endif; ?>
    <progress class="progreso" value="<?= $porcentaje ?>" max="100" aria-label="Progreso del curso"><?= $porcentaje ?>%</progress>
</section>

<section class="tarjeta paso-panel separado">
    <h2><?= icono('rayo') ?> Paso 1: crea tu cuenta de proveedor</h2>
    <p>Regístrate gratis en <strong>smmclixy.com</strong>, el panel que usamos en el curso para hacer los pedidos de tus clientes.</p>
    <?php if (!empty($panel['codigo_bono'])): ?>
        <p>Usa el código <span class="bono-codigo"><?= e($panel['codigo_bono']) ?></span> para tu bono de bienvenida.</p>
    <?php endif; ?>
    <a class="boton" href="<?= e(url_registro_panel('miembros')) ?>" target="_blank" rel="noopener">Crear mi cuenta en smmclixy <?= icono('flecha') ?></a>
</section>

<?php foreach ($curso['modulos'] as $i => $modulo): ?>
    <section class="tarjeta">
        <h2>Módulo <?= $i + 1 ?>: <?= e($modulo['titulo']) ?></h2>
        <ul class="modulo-lista">
            <?php foreach ($modulo['lecciones'] as $leccion): $vista = isset($vistas[$leccion['slug']]); ?>
                <li>
                    <a href="/miembros/leccion/<?= e($leccion['slug']) ?>"<?= $vista ? ' class="vista"' : '' ?>>
                        <?= icono($vista ? 'check' : 'play') ?>
                        <span><?= e($leccion['titulo']) ?></span>
                        <?php if ($leccion['duracion']): ?><small><?= e($leccion['duracion']) ?></small><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>
