<?php
/**
 * Ficha de un comprador: datos, acceso, ventas, emails y acciones.
 * @var array       $comprador
 * @var array|null  $acceso
 * @var array       $ventas
 * @var array       $emails
 * @var int         $sesiones
 * @var int         $vistas
 * @var int         $total_lecciones
 * @var string      $mensaje
 * @var string|null $enlace
 */
$id = (int) $comprador['id'];
$activo = $acceso !== null && $acceso['revocado_en'] === null;
$estadoEmail = ['enviado' => 'ok', 'simulado' => 'alerta', 'error' => 'error'];
?>
<p><a class="volver" href="/admin/compradores"><?= icono('atras') ?> Compradores</a></p>
<div class="cabecera-tarjeta">
    <h1><?= e($comprador['nombre']) ?></h1>
    <?= $activo ? '<span class="estado estado--ok">Acceso activo</span>' : '<span class="estado estado--error">Sin acceso</span>' ?>
</div>

<?php if ($mensaje): ?><div class="aviso aviso--info" role="status"><?= e($mensaje) ?></div><?php endif; ?>

<?php if ($enlace): ?>
    <?= plantilla('parciales/enlace_acceso', ['enlace' => $enlace, 'comprador' => $comprador]) ?>
<?php endif; ?>

<div class="tarjeta">
    <h2>Acciones</h2>
    <div class="acciones">
        <?php if ($activo): ?>
            <form method="post" action="/admin/compradores/<?= $id ?>/reenviar"><?= csrf_campo() ?>
                <button class="boton" type="submit"><?= icono('email') ?> Reenviar email de acceso</button></form>
            <form method="post" action="/admin/compradores/<?= $id ?>/enlace"><?= csrf_campo() ?>
                <button class="boton boton--secundario" type="submit"><?= icono('enlace') ?> Generar enlace para WhatsApp</button></form>
            <form method="post" action="/admin/compradores/<?= $id ?>/cerrar-sesiones"><?= csrf_campo() ?>
                <button class="boton boton--secundario" type="submit" data-confirmar="¿Cerrar sus sesiones en todos los dispositivos?"><?= icono('salir') ?> Cerrar sesiones (<?= $sesiones ?>)</button></form>
            <form method="post" action="/admin/compradores/<?= $id ?>/revocar"><?= csrf_campo() ?>
                <button class="boton boton--peligro" type="submit" data-confirmar="¿Quitarle el acceso al curso? (por ejemplo, tras un reembolso)"><?= icono('x') ?> Revocar acceso</button></form>
        <?php else: ?>
            <form method="post" action="/admin/compradores/<?= $id ?>/restaurar"><?= csrf_campo() ?>
                <button class="boton" type="submit"><?= icono('check') ?> Restaurar acceso</button></form>
        <?php endif; ?>
    </div>
    <p class="suave chico separado">
        <?php if (!empty($comprador['primer_ingreso_en'])): ?>
            Entró al curso por primera vez el <?= e(fecha_local($comprador['primer_ingreso_en'])) ?>.
            Progreso: <?= $vistas ?> de <?= $total_lecciones ?> lecciones vistas.
        <?php else: ?>
            Todavía no ha entrado al curso. Si pide la devolución dentro de los 15 días desde la compra, corresponde hacerla
            (ver la política de reembolsos).
        <?php endif; ?>
    </p>
</div>

<div class="tarjeta">
    <h2>Datos</h2>
    <form class="formulario" method="post" action="/admin/compradores/<?= $id ?>/editar">
        <?= csrf_campo() ?>
        <div class="fila-campos">
            <div class="campo"><label for="nombre">Nombre</label><input id="nombre" name="nombre" type="text" value="<?= e($comprador['nombre']) ?>" required></div>
            <div class="campo"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= e($comprador['email']) ?>" required></div>
        </div>
        <div class="campo"><label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" type="tel" value="<?= e($comprador['whatsapp'] ?? '') ?>"></div>
        <div class="campo"><label for="notas">Notas</label><textarea id="notas" name="notas"><?= e($comprador['notas'] ?? '') ?></textarea></div>
        <div><button class="boton boton--secundario" type="submit">Guardar cambios</button></div>
    </form>
    <p class="suave chico separado">Cliente desde el <?= e(fecha_local($comprador['creado_en'])) ?>.</p>
</div>

<div class="tarjeta">
    <h2>Ventas</h2>
    <?php if ($ventas): ?>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>#</th><th>Fecha</th><th>Monto</th><th>Método</th><th>Código</th><th>Campaña</th><th>Meta</th></tr></thead>
                <tbody>
                <?php foreach ($ventas as $venta): ?>
                    <tr>
                        <td><?= (int) $venta['id'] ?></td>
                        <td><?= e(fecha_local($venta['creado_en'])) ?></td>
                        <td><?= e(formatear_centavos($venta['monto_centavos'], $venta['moneda'])) ?></td>
                        <td><?= e($venta['metodo_pago'] ?: '—') ?><?= $venta['referencia_pago'] ? '<br><small class="suave">' . e($venta['referencia_pago']) . '</small>' : '' ?></td>
                        <td class="codigo"><?= e($venta['codigo'] ?: '—') ?></td>
                        <td class="envolver"><?= e($venta['utm_campaign'] ?: '—') ?></td>
                        <td><?= e($venta['meta_estado'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="vacio">Sin ventas (acceso dado a mano).</p>
    <?php endif; ?>
</div>

<div class="tarjeta">
    <h2>Emails</h2>
    <?php if ($emails): ?>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Detalle</th></tr></thead>
                <tbody>
                <?php foreach ($emails as $email): ?>
                    <tr>
                        <td><?= e(fecha_local($email['creado_en'])) ?></td>
                        <td><?= e($email['tipo']) ?></td>
                        <td><span class="estado estado--<?= $estadoEmail[$email['estado']] ?? '' ?>"><?= e($email['estado']) ?></span></td>
                        <td class="envolver"><?= e($email['error'] ?: $email['destinatario']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="vacio">Todavía no se le envió ningún email.</p>
    <?php endif; ?>
</div>
