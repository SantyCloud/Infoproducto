<?php
/**
 * Activar el acceso con el código que el dueño envía por WhatsApp después del pago.
 * @var string $pantalla    formulario | ya_activado | revisa_tu_correo | no_sirve | limitado
 * @var string $codigo      K7Q2M-8XPRT
 * @var bool   $con_enlace  el código vino en el enlace: no hace falta escribirlo
 * @var string $nombre
 * @var string $email
 * @var array  $errores     [campo => mensaje]
 */
$negocio = contenido('negocio');
$whatsapp = preg_replace('/\D/', '', (string) $negocio['whatsapp']['numero']);
$error = fn (string $campo) => isset($errores[$campo]) ? '<small class="error-campo">' . e($errores[$campo]) . '</small>' : '';
$iconos = ['formulario' => 'regalo', 'ya_activado' => 'check', 'revisa_tu_correo' => 'email', 'no_sirve' => 'reloj', 'limitado' => 'escudo'];
?>
<main class="acceso">
    <div class="tarjeta">
        <div class="acceso__icono"><?= icono($iconos[$pantalla] ?? 'regalo') ?></div>
        <?php if ($pantalla === 'formulario'): ?>
            <h1 class="tarjeta--centro">Activa tu acceso</h1>
            <p class="suave">¡Gracias por tu compra! Escribe tu nombre y tu email para entrar a <strong><?= e($negocio['producto']) ?></strong>.</p>
            <?php if ($errores): ?><div class="aviso aviso--error" role="alert">Revisa los datos marcados.</div><?php endif; ?>
            <form class="formulario" method="post" action="/activar">
                <?= csrf_campo() ?>
                <?php if ($con_enlace): ?>
                    <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                    <input type="hidden" name="con_enlace" value="1">
                <?php else: ?>
                    <div class="campo">
                        <label for="codigo">Código de activación</label>
                        <input id="codigo" name="codigo" type="text" value="<?= e($codigo) ?>" placeholder="Ej. K7Q2M-8XPRT" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                        <small>Te lo envié por WhatsApp junto con el enlace.</small>
                        <?= $error('codigo') ?>
                    </div>
                <?php endif; ?>
                <div class="campo">
                    <label for="nombre">Tu nombre</label>
                    <input id="nombre" name="nombre" type="text" value="<?= e($nombre) ?>" autocomplete="name" required>
                    <?= $error('nombre') ?>
                </div>
                <div class="campo">
                    <label for="email">Tu email</label>
                    <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" inputmode="email" required>
                    <small>Con este email vuelves a entrar cuando quieras: revísalo bien.</small>
                    <?= $error('email') ?>
                </div>
                <button class="boton boton--ancho boton--grande" type="submit">Activar mi acceso <?= icono('flecha') ?></button>
            </form>
        <?php elseif ($pantalla === 'ya_activado'): ?>
            <h1 class="tarjeta--centro">Tu acceso ya está activado</h1>
            <p class="suave">Ya activaste este código con <strong><?= e($email) ?></strong>. Entra al curso desde aquí; si te pide tu email, escribe ese mismo.</p>
            <a class="boton boton--ancho boton--grande" href="/miembros">Entrar al curso <?= icono('flecha') ?></a>
        <?php elseif ($pantalla === 'revisa_tu_correo'): ?>
            <h1 class="tarjeta--centro">Revisa tu correo</h1>
            <p>Tu compra quedó registrada. Como <strong><?= e($email) ?></strong> ya tenía una cuenta, te enviamos a ese correo un enlace para entrar.</p>
            <p class="suave chico">¿No llega? Revisa las carpetas de spam y promociones, o pide otro enlace.</p>
            <a class="boton boton--secundario boton--ancho" href="/entrar">Pedir otro enlace</a>
        <?php elseif ($pantalla === 'limitado'): ?>
            <h1 class="tarjeta--centro">Demasiados intentos</h1>
            <p class="suave">Espera unos minutos y vuelve a probar. Si sigue sin funcionar, escríbeme por WhatsApp y lo resolvemos.</p>
        <?php else: ?>
            <h1 class="tarjeta--centro">Este enlace ya no sirve</h1>
            <p class="suave">El código no es válido, ya se usó o venció. Si ya activaste tu acceso, entra con tu email.</p>
            <a class="boton boton--ancho boton--grande" href="/miembros">Entrar al curso</a>
        <?php endif; ?>
        <p class="suave chico separado">¿Problemas? <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">Escríbeme por WhatsApp</a>.</p>
    </div>
</main>
