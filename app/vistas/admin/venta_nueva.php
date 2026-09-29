<?php
/**
 * Formulario para registrar una venta cobrada por WhatsApp.
 * @var array      $valores
 * @var array      $errores
 * @var array|null $lead
 * @var array      $metodos
 * @var array      $monedas
 * @var string     $clave_formulario
 */
$valor = fn (string $campo) => e((string) ($valores[$campo] ?? ''));
$error = fn (string $campo) => isset($errores[$campo]) ? '<small class="error-campo">' . e($errores[$campo]) . '</small>' : '';
?>
<h1>Registrar venta</h1>
<p class="suave">Cuando confirmes el pago en WhatsApp, registra aquí la venta: se crea el acceso, se envía el email y se avisa a Meta.</p>

<?php if ($errores): ?><div class="aviso aviso--error" role="alert">Revisa los campos marcados.</div><?php endif; ?>

<?php if ($lead): ?>
    <div class="tarjeta">
        <h2>Clic <span class="codigo"><?= e($lead['codigo']) ?></span></h2>
        <dl class="lista-datos">
            <dt>Fecha</dt><dd><?= e(fecha_local($lead['creado_en'])) ?></dd>
            <dt>Página</dt><dd><?= e(!empty($lead['pais']) ? (paises()[$lead['pais']]['nombre'] ?? strtoupper($lead['pais'])) : 'General') ?></dd>
            <dt>Campaña</dt><dd><?= e($lead['utm_campaign'] ?: ($lead['utm_source'] ?: 'Directo (sin anuncio)')) ?></dd>
            <?php if ($lead['utm_content']): ?><dt>Anuncio</dt><dd><?= e($lead['utm_content']) ?></dd><?php endif; ?>
            <dt>Botón</dt><dd><?= e($lead['boton'] ?: '—') ?> · <?= (int) $lead['clics'] ?> clic(s)</dd>
        </dl>
    </div>
<?php endif; ?>

<form class="tarjeta formulario" method="post" action="/admin/ventas">
    <?= csrf_campo() ?>
    <input type="hidden" name="clave_formulario" value="<?= e($clave_formulario) ?>">

    <div class="campo">
        <label for="codigo">Código del mensaje de WhatsApp</label>
        <input id="codigo" name="codigo" type="text" value="<?= $valor('codigo') ?>" placeholder="Ej. K7Q2" autocomplete="off" autocapitalize="characters">
        <small>Viene al final del primer mensaje del cliente ("Mi código: K7Q2"). Si no lo tiene, déjalo vacío.</small>
        <?= $error('codigo') ?>
    </div>

    <div class="fila-campos">
        <div class="campo">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" value="<?= $valor('nombre') ?>" required autocomplete="off">
            <?= $error('nombre') ?>
        </div>
        <div class="campo">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= $valor('email') ?>" required autocomplete="off" inputmode="email">
            <small>Revísalo bien: ahí le llega el acceso.</small>
            <?= $error('email') ?>
        </div>
    </div>

    <div class="fila-campos">
        <div class="campo">
            <label for="whatsapp">WhatsApp</label>
            <input id="whatsapp" name="whatsapp" type="tel" value="<?= $valor('whatsapp') ?>" placeholder="593991234567" inputmode="tel">
            <small>Con código de país. Ayuda a Meta a reconocer la compra.</small>
            <?= $error('whatsapp') ?>
        </div>
        <div class="campo">
            <label for="monto">Monto cobrado</label>
            <input id="monto" name="monto" type="text" value="<?= $valor('monto') ?>" placeholder="Ej. 10 o 200" required inputmode="decimal">
            <?= $error('monto') ?>
        </div>
    </div>

    <div class="fila-campos">
        <div class="campo">
            <label for="moneda">Moneda</label>
            <select id="moneda" name="moneda">
                <option value="AUTO"<?= ($valores['moneda'] ?? 'AUTO') === 'AUTO' ? ' selected' : '' ?>>Según la página del clic</option>
                <?php foreach ($monedas as $moneda): ?>
                    <option value="<?= e($moneda) ?>"<?= ($valores['moneda'] ?? '') === $moneda ? ' selected' : '' ?>><?= e(nombre_moneda($moneda)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php
            $otras = [];
            foreach (paises() as $pais) {
                if (($pais['moneda'] ?? 'USD') !== 'USD') {
                    $otras[] = $pais['nombre'] . ': ' . $pais['moneda'];
                }
            }
            ?>
            <small>En automático, la de la página por la que llegó el cliente<?= $otras ? ' (' . e(implode(', ', $otras)) . '; las demás: USD)' : '' ?>.</small>
            <?= $error('moneda') ?>
        </div>
        <div class="campo">
            <label for="metodo_pago">Método de pago</label>
            <select id="metodo_pago" name="metodo_pago">
                <?php foreach ($metodos as $metodo): ?>
                    <option<?= ($valores['metodo_pago'] ?? '') === $metodo ? ' selected' : '' ?>><?= e($metodo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="referencia_pago">Referencia del pago (opcional)</label>
            <input id="referencia_pago" name="referencia_pago" type="text" value="<?= $valor('referencia_pago') ?>" placeholder="Nº de transferencia, id de PayPal…">
        </div>
    </div>

    <label class="casilla">
        <input type="checkbox" name="enviar_email" value="1"<?= !empty($valores['enviar_email']) ? ' checked' : '' ?>>
        <span>Enviarle el email con su acceso</span>
    </label>

    <button class="boton boton--grande" type="submit"><?= icono('check') ?> Registrar venta y dar acceso</button>
</form>
