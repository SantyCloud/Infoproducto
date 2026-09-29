<?php
declare(strict_types=1);

/*
 * Textos: variables como {producto} o {precio}, **negritas**, Markdown sencillo y fechas en español.
 */

/** Valores de las variables {…} que se pueden usar en los textos de contenido/. */
function variables_texto(?array $negocio = null): array
{
    $negocio ??= contenido('negocio');
    $promo = $negocio['promo'] ?? [];
    $legal = $negocio['legal'] ?? [];
    $moneda = (string) ($negocio['moneda'] ?? 'USD');
    return [
        '{producto}' => (string) $negocio['producto'],
        '{precio}' => formatear_precio(precio_actual($negocio), $moneda),
        '{precio_normal}' => formatear_precio($negocio['precio_normal'], $moneda),
        '{promo_nombre}' => (string) ($promo['nombre'] ?? ''),
        '{promo_fin}' => empty($promo['termina']) ? '' : fecha_larga($promo['termina']),
        '{ahorro}' => formatear_precio(ahorro_promo($negocio), $moneda),
        '{descuento}' => porcentaje_descuento($negocio) . '%',
        '{garantia_dias}' => (string) ($negocio['garantia_dias'] ?? 0),
        '{metodos_pago}' => implode(', ', $negocio['metodos_pago'] ?? []),
        '{email_soporte}' => (string) ($negocio['soporte']['email'] ?? ''),
        '{whatsapp_soporte}' => '+' . preg_replace('/\D/', '', (string) ($negocio['whatsapp']['numero'] ?? '')),
        '{titular}' => (string) ($legal['titular'] ?? ''),
        '{identificacion}' => (string) ($legal['identificacion'] ?? ''),
        '{ciudad}' => (string) ($legal['ciudad'] ?? ''),
        '{pais}' => (string) ($legal['pais'] ?? ''),
        '{fecha_actualizacion}' => (string) ($legal['fecha_actualizacion'] ?? ''),
        '{sitio}' => config('app.url'),
        '{anio}' => gmdate('Y'),
    ];
}

/** Reemplaza las variables {…} de un texto. */
function texto(string $texto, ?array $variables = null): string
{
    return strtr($texto, $variables ?? variables_texto());
}

/** Aplica texto() a todos los textos de un array (por ejemplo, todo contenido/landing.php). */
function textos(array $datos, ?array $variables = null): array
{
    $variables ??= variables_texto();
    array_walk_recursive($datos, function (mixed &$valor) use ($variables): void {
        if (is_string($valor)) {
            $valor = strtr($valor, $variables);
        }
    });
    return $datos;
}

/** Escapa el texto y convierte **así** en <strong>así</strong>. */
function formato(string $texto): string
{
    return (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($texto));
}

/**
 * Convierte Markdown sencillo en HTML seguro (todo el texto se escapa primero).
 * Admite: # títulos, párrafos, listas (- y 1.), > citas, **negrita**, *cursiva*, `código` y [enlaces](https://…).
 */
function markdown(string $texto): string
{
    $bloques = [];
    $parrafo = [];
    $cita = [];
    $lista = null; // ['tipo' => 'ul'|'ol', 'items' => [...]]

    $cerrar = function () use (&$bloques, &$parrafo, &$cita, &$lista): void {
        if ($parrafo) {
            $bloques[] = '<p>' . markdown_linea(implode(' ', $parrafo)) . '</p>';
            $parrafo = [];
        }
        if ($cita) {
            $bloques[] = '<blockquote><p>' . markdown_linea(implode(' ', $cita)) . '</p></blockquote>';
            $cita = [];
        }
        if ($lista) {
            $items = array_map(fn (string $item) => '<li>' . markdown_linea($item) . '</li>', $lista['items']);
            $bloques[] = "<{$lista['tipo']}>" . implode('', $items) . "</{$lista['tipo']}>";
            $lista = null;
        }
    };

    foreach (preg_split('/\R/', $texto) as $linea) {
        if (trim($linea) === '') {
            $cerrar();
        } elseif (preg_match('/^(#{1,3})\s+(.+)$/', $linea, $m)) {
            $cerrar();
            $nivel = strlen($m[1]);
            $bloques[] = "<h$nivel>" . markdown_linea(trim($m[2])) . "</h$nivel>";
        } elseif (preg_match('/^\s*(?:([-*])|(\d+)[.)])\s+(.+)$/', $linea, $m)) {
            $tipo = $m[1] !== '' ? 'ul' : 'ol';
            if ($parrafo || $cita || ($lista && $lista['tipo'] !== $tipo)) {
                $cerrar();
            }
            $lista ??= ['tipo' => $tipo, 'items' => []];
            $lista['items'][] = trim($m[3]);
        } elseif (preg_match('/^>\s?(.*)$/', $linea, $m)) {
            if ($parrafo || $lista) {
                $cerrar();
            }
            $cita[] = trim($m[1]);
        } elseif ($lista && preg_match('/^\s+(\S.*)$/', $linea, $m)) {
            $lista['items'][count($lista['items']) - 1] .= ' ' . trim($m[1]); // continuación del punto anterior
        } else {
            if ($lista || $cita) {
                $cerrar();
            }
            $parrafo[] = trim($linea);
        }
    }
    $cerrar();
    return implode("\n", $bloques);
}

/** Formato dentro de una línea de Markdown: escapa y aplica enlaces, `código`, **negrita** y *cursiva*. */
function markdown_linea(string $texto): string
{
    $html = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function (array $m): string {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!preg_match('#^(https?://|mailto:|/[^/])#i', $url)) {
            return $m[0]; // solo enlaces seguros: web, email o rutas propias
        }
        $externo = preg_match('#^https?://#i', $url) ? ' target="_blank" rel="noopener noreferrer"' : '';
        return '<a href="' . e($url) . '"' . $externo . '>' . $m[1] . '</a>';
    }, e($texto));
    $html = preg_replace('/`([^`]+)`/u', '<code>$1</code>', (string) $html);
    $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', (string) $html);
    $html = preg_replace('/(?<![*\w])\*(?![\s*])(.+?)(?<![\s*])\*(?![*\w])/u', '<em>$1</em>', (string) $html);
    return (string) $html;
}

/** "2026-10-31 23:59" → "31 de octubre" (en la zona horaria del negocio). */
function fecha_larga(string|DateTimeInterface $fecha, bool $conAnio = false): string
{
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
        'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $zona = new DateTimeZone(config('app.zona_horaria'));
    $fecha = $fecha instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($fecha)->setTimezone($zona)
        : new DateTimeImmutable($fecha, $zona);
    return $fecha->format('j') . ' de ' . $meses[(int) $fecha->format('n') - 1] . ($conAnio ? ' de ' . $fecha->format('Y') : '');
}

/** Fecha guardada en la BD (UTC) → hora local legible: "28/09/2026 14:05". */
function fecha_local(?string $fechaUtc, string $formato = 'd/m/Y H:i'): string
{
    if ($fechaUtc === null || $fechaUtc === '') {
        return '';
    }
    return (new DateTimeImmutable($fechaUtc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(config('app.zona_horaria')))
        ->format($formato);
}

/** Un dato que viene del navegador, como texto: si llega un array u otra cosa (p. ej. ?b[]=1), cuenta como vacío. */
function texto_de(mixed $valor): string
{
    return is_string($valor) ? $valor : '';
}

/** Limpia un texto que viene de fuera: quita caracteres de control y lo recorta sin romper letras. */
function limpiar(mixed $valor, int $maximo = 200): string
{
    if (!is_string($valor)) {
        return '';
    }
    $valor = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $valor));
    return function_exists('mb_strcut') ? mb_strcut($valor, 0, $maximo, 'UTF-8') : substr($valor, 0, $maximo);
}
