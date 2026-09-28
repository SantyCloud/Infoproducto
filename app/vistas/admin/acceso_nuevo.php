<?php
/**
 * Dar acceso sin venta (regalo, socio, prueba). No se avisa a Meta.
 * @var array $valores
 * @var array $errores
 */
$valor = fn (string $campo) => e((string) ($valores[$campo] ?? ''));
$error = fn (string $campo) => isset($errores[$campo]) ? '<small class="error-campo">' . e($errores[$campo]) . '</small>' : '';
?>
<h1>Dar acceso manual</h1>
<p class="suave">Para regalos, socios o pruebas. Si fue una venta, mejor usa <a href="/admin/ventas/nueva">Registrar venta</a> (así se cuenta en las estadísticas y en Meta).</p>
<form class="tarjeta formulario" method="post" action="/admin/accesos">
    <?= csrf_campo() ?>
    <div class="fila-campos">
        <div class="campo"><label for="nombre">Nombre</label><input id="nombre" name="nombre" type="text" value="<?= $valor('nombre') ?>" required><?= $error('nombre') ?></div>
        <div class="campo"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= $valor('email') ?>" required><?= $error('email') ?></div>
    </div>
    <div class="campo"><label for="whatsapp">WhatsApp (opcional)</label><input id="whatsapp" name="whatsapp" type="tel" value="<?= $valor('whatsapp') ?>" placeholder="593991234567"></div>
    <div class="campo"><label for="notas">Notas (opcional)</label><textarea id="notas" name="notas" placeholder="Ej. regalo para un socio"><?= $valor('notas') ?></textarea></div>
    <label class="casilla"><input type="checkbox" name="enviar_email" value="1"<?= !empty($valores['enviar_email']) ? ' checked' : '' ?>><span>Enviarle el email con su acceso</span></label>
    <div><button class="boton boton--grande" type="submit"><?= icono('check') ?> Dar acceso</button></div>
</form>
