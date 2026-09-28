<?php
/**
 * Acceso al panel.
 * @var string $error
 * @var string $usuario
 * @var bool   $configurado
 */
?>
<main class="acceso">
    <div class="tarjeta">
        <div class="acceso__icono"><?= icono('candado') ?></div>
        <h1 class="tarjeta--centro">Panel de administración</h1>
        <?php if (!$configurado): ?>
            <div class="aviso aviso--alerta">
                Todavía no hay usuario de administrador. En el servidor ejecuta
                <strong>php bin/crear-admin.php</strong> y vuelve a esta página.
            </div>
        <?php endif; ?>
        <?php if ($error): ?><div class="aviso aviso--error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form class="formulario" method="post" action="/admin/entrar">
            <?= csrf_campo() ?>
            <div class="campo">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" type="text" autocomplete="username" value="<?= e($usuario) ?>" required autofocus>
            </div>
            <div class="campo">
                <label for="clave">Contraseña</label>
                <input id="clave" name="clave" type="password" autocomplete="current-password" required>
            </div>
            <button class="boton boton--ancho boton--grande" type="submit">Entrar</button>
        </form>
    </div>
</main>
