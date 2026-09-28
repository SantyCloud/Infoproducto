<?php
/**
 * Pedir un enlace para entrar al curso.
 * @var bool   $enviado
 * @var string $error
 * @var string $email
 */
$whatsapp = preg_replace('/\D/', '', (string) contenido('negocio')['whatsapp']['numero']);
?>
<main class="acceso">
    <div class="tarjeta">
        <div class="acceso__icono"><?= icono('email') ?></div>
        <?php if ($enviado): ?>
            <h1 class="tarjeta--centro">Revisa tu correo</h1>
            <p>Si <strong><?= e($email) ?></strong> tiene acceso al curso, te acabamos de enviar un enlace para entrar. Vence en 30 minutos.</p>
            <p class="suave chico">¿No llega? Revisa las carpetas de spam y promociones. Si compraste con otro email, pruébalo con ese.</p>
            <p><a class="boton boton--secundario boton--ancho" href="/entrar">Probar con otro email</a></p>
        <?php else: ?>
            <h1 class="tarjeta--centro">Entrar al curso</h1>
            <p class="suave">Escribe el email con el que compraste y te enviaremos un enlace para entrar, sin contraseñas.</p>
            <?php if ($error): ?><div class="aviso aviso--error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form class="formulario" method="post" action="/entrar">
                <?= csrf_campo() ?>
                <div class="campo">
                    <label for="email">Tu email</label>
                    <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" inputmode="email" required autofocus>
                </div>
                <button class="boton boton--ancho boton--grande" type="submit">Enviarme el enlace</button>
            </form>
        <?php endif; ?>
        <p class="suave chico separado">¿Problemas para entrar? <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">Escríbeme por WhatsApp</a>.</p>
    </div>
</main>
