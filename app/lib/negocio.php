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

/** % de descuento de la promo vigente (15 → 10 = 33). 0 si no hay promo. */
function porcentaje_descuento(?array $negocio = null, ?DateTimeImmutable $momento = null): int
{
    $negocio ??= contenido('negocio');
    $normal = (float) $negocio['precio_normal'];
    if ($normal <= 0 || !promo_vigente($negocio, $momento)) {
        return 0;
    }
    return max(0, (int) round(($normal - precio_actual($negocio, $momento)) / $normal * 100));
}

/** Cuánto se ahorra con la promo vigente (15 → 10 = 5). 0 si no hay promo. */
function ahorro_promo(?array $negocio = null, ?DateTimeImmutable $momento = null): float
{
    $negocio ??= contenido('negocio');
    return max(0.0, round((float) $negocio['precio_normal'] - precio_actual($negocio, $momento), 2));
}

/** 10 → "$10" · 12.5 → "$12.50" */
function formatear_precio(float|int $monto): string
{
    $decimales = floor($monto) == $monto ? 0 : 2;
    return '$' . number_format($monto, $decimales, '.', ',');
}

/**
 * Texto de urgencia REAL, calculado con la fecha de fin de la promo:
 * "Termina hoy", "Termina mañana", "Quedan 5 días" (última semana) o "Hasta el 31 de octubre".
 */
function texto_tiempo_promo(?array $negocio = null, ?DateTimeImmutable $momento = null): ?string
{
    $negocio ??= contenido('negocio');
    if (!promo_vigente($negocio, $momento) || empty($negocio['promo']['termina'])) {
        return null;
    }
    $zona = new DateTimeZone(config('app.zona_horaria'));
    $fin = new DateTimeImmutable($negocio['promo']['termina'], $zona);
    $hoy = ($momento ?? ahora())->setTimezone($zona);
    $dias = (int) $hoy->setTime(0, 0)->diff($fin->setTime(0, 0))->days;
    return match (true) {
        $dias === 0 => 'Termina hoy',
        $dias === 1 => 'Termina mañana',
        $dias <= 7 => "Quedan $dias días",
        default => 'Hasta el ' . fecha_larga($fin),
    };
}

/** Si no hay garantía (garantia_dias = 0), quita de la landing las frases que la mencionan. */
function sin_menciones_de_garantia(array $landing): array
{
    $menciona = fn (mixed $texto): bool => is_string($texto) && str_contains($texto, '{garantia_dias}');
    $landing['hero']['ventajas'] = array_values(array_filter($landing['hero']['ventajas'] ?? [], fn ($v) => !$menciona($v)));
    $landing['faq']['lista'] = array_values(array_filter(
        $landing['faq']['lista'] ?? [],
        fn (array $p) => !$menciona($p['pregunta']) && !$menciona($p['respuesta'])
    ));
    return $landing;
}
