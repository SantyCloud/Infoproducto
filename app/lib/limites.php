<?php
declare(strict_types=1);

/*
 * Límite de intentos: frena abusos (fuerza bruta en los accesos, clics automáticos…).
 * Cada "clave" (ej. "login:1.2.3.4") admite $maximo intentos por ventana de $segundos.
 */

/** Cuenta un intento y dice si está permitido. */
function limite_permitir(string $clave, int $maximo, int $segundos): bool
{
    $ahora = time();
    return db_transaccion(function () use ($clave, $maximo, $segundos, $ahora): bool {
        $fila = db_fila('SELECT contador, ventana_inicio FROM limites WHERE clave = ?', [$clave]);
        if ($fila === null || (int) $fila['ventana_inicio'] <= $ahora - $segundos) {
            db_ejecutar(
                'INSERT INTO limites (clave, contador, ventana_inicio) VALUES (?, 1, ?)
                 ON CONFLICT (clave) DO UPDATE SET contador = 1, ventana_inicio = excluded.ventana_inicio',
                [$clave, $ahora]
            );
            return true;
        }
        if ((int) $fila['contador'] >= $maximo) {
            return false;
        }
        db_ejecutar('UPDATE limites SET contador = contador + 1 WHERE clave = ?', [$clave]);
        return true;
    });
}

/** ¿La clave ya llegó al máximo en su ventana? Solo mira: no cuenta un intento nuevo. */
function limite_alcanzado(string $clave, int $maximo, int $segundos): bool
{
    $fila = db_fila('SELECT contador, ventana_inicio FROM limites WHERE clave = ?', [$clave]);
    return $fila !== null && (int) $fila['ventana_inicio'] > time() - $segundos && (int) $fila['contador'] >= $maximo;
}

/** Olvida los intentos de una clave (por ejemplo, tras un inicio de sesión correcto). */
function limite_reiniciar(string $clave): void
{
    db_ejecutar('DELETE FROM limites WHERE clave = ?', [$clave]);
}
