<?php
declare(strict_types=1);

/*
 * Reglas del negocio que dependen de contenido/negocio.php (precio, promoción…).
 * Las funciones aceptan $negocio y $momento opcionales para poder probarlas.
 */

function ahora(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

/** ¿La promoción está activa y todavía no llegó su fecha de fin (hora local)? */
function promo_vigente(?array $negocio = null, ?DateTimeImmutable $momento = null): bool
{
    $negocio ??= contenido('negocio');
    $promo = $negocio['promo'] ?? [];
    if (empty($promo['activa']) || !isset($promo['precio'])) {
        return false;
    }
    if (empty($promo['termina'])) {
        return true;
    }
    $fin = new DateTimeImmutable($promo['termina'], new DateTimeZone(config('app.zona_horaria')));
    return ($momento ?? ahora()) < $fin;
}

/** Precio que se cobra hoy: el de la promo si está vigente; si no, el normal. */
function precio_actual(?array $negocio = null, ?DateTimeImmutable $momento = null): float
{
    $negocio ??= contenido('negocio');
    return (float) (promo_vigente($negocio, $momento) ? $negocio['promo']['precio'] : $negocio['precio_normal']);
}

/** 10 → "$10" · 12.5 → "$12.50" */
function formatear_precio(float|int $monto): string
{
    $decimales = floor($monto) == $monto ? 0 : 2;
    return '$' . number_format($monto, $decimales, '.', ',');
}
