<?php
/**
 * Página del enlace mágico: un botón que confirma la entrada (el enlace se consume al pulsarlo).
 * @var bool   $valido
 * @var string $nombre
 * @var string $token
 */
?>
<main class="acceso">
    <div class="tarjeta tarjeta--centro">
        <div class="acceso__icono"><?= icono($valido ? 'candado' : 'reloj') ?></div>
        <?php if ($valido): ?>
            <h1>¡Hola, <?= e($nombre) ?>!</h1>
            <p class="suave">Toca el botón para entrar a tu curso en este dispositivo.</p>
            <form method="post" action="/acceso/<?= e($token) ?>">
                <?= csrf_campo() ?>
                <button class="boton boton--ancho boton--grande" type="submit">Entrar al curso <?= icono('flecha') ?></button>
            </form>
        <?php else: ?>
            <h1>Este enlace ya no sirve</h1>
            <p class="suave">Los enlaces de acceso sirven una sola vez y vencen con el tiempo. Pide uno nuevo con tu email: llega en segundos.</p>
            <a class="boton boton--ancho boton--grande" href="/entrar">Pedir un enlace nuevo</a>
        <?php endif; ?>
    </div>
</main>
