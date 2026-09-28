<?php
declare(strict_types=1);

/*
 * Si el panel dice "Demasiados intentos" y necesitas entrar ya:
 *
 *     php bin/desbloquear-panel.php
 *
 * Borra los contadores de intentos de acceso al panel (no cambia tu contraseña).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$borrados = db_ejecutar("DELETE FROM limites WHERE clave LIKE 'admin-entrar%'");
echo "✓ Panel desbloqueado ($borrados contador(es) borrado(s)).\n";
