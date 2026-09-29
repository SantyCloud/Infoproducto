<?php
declare(strict_types=1);

/*
 * Ventas: lo que pasa cuando registras en el panel una venta cobrada por WhatsApp.
 *
 * En una sola transacción se crea (o actualiza) el comprador, la venta, el acceso y el enlace
 * de primer acceso. Después se envían el email y el evento Purchase a Meta. Registrar dos veces
 * el mismo formulario o el mismo código NO duplica nada.
 */

/** Valida el formulario de venta. Devuelve [datos limpios, errores por campo]. */
function venta_validar(array $entrada): array
{
    $datos = [
        'nombre' => limpiar($entrada['nombre'] ?? '', 100),
        'email' => strtolower(limpiar($entrada['email'] ?? '', 190)),
        'whatsapp' => (string) preg_replace('/\D/', '', texto_de($entrada['whatsapp'] ?? null)),
        'monto' => str_replace(',', '.', limpiar($entrada['monto'] ?? '', 20)),
        'metodo_pago' => limpiar($entrada['metodo_pago'] ?? '', 60),
        'referencia_pago' => limpiar($entrada['referencia_pago'] ?? '', 120),
        'codigo' => normalizar_codigo(texto_de($entrada['codigo'] ?? null)),
        'moneda' => strtoupper(limpiar($entrada['moneda'] ?? '', 10)),
        'enviar_email' => !empty($entrada['enviar_email']),
        'clave_formulario' => preg_match('/^[A-Za-z0-9_-]{20,64}$/', texto_de($entrada['clave_formulario'] ?? null))
            ? (string) $entrada['clave_formulario'] : null,
        'lead' => null,
    ];
    $errores = [];
    if ($datos['nombre'] === '') {
        $errores['nombre'] = 'Escribe el nombre del comprador.';
    }
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Revisa el email: no parece válido.';
    }
    if ($datos['whatsapp'] !== '' && (strlen($datos['whatsapp']) < 8 || strlen($datos['whatsapp']) > 15)) {
        $errores['whatsapp'] = 'El WhatsApp debe tener entre 8 y 15 dígitos, con código de país (ej. 593991234567).';
    }
    if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $datos['monto'])) {
        $errores['monto'] = 'Escribe el monto cobrado, por ejemplo 10 o 200.';
    }
    if ($datos['codigo'] !== '') {
        $datos['lead'] = db_fila('SELECT * FROM leads WHERE codigo = ?', [$datos['codigo']]);
        $ventaDelLead = $datos['lead'] ? db_valor('SELECT id FROM ventas WHERE lead_id = ?', [$datos['lead']['id']]) : null;
        if ($datos['lead'] === null) {
            $errores['codigo'] = "No hay ningún clic con el código {$datos['codigo']}. Revísalo o déjalo vacío.";
        } elseif ($ventaDelLead !== null && !venta_por_clave($datos['clave_formulario'])) {
            $errores['codigo'] = "El código {$datos['codigo']} ya tiene una venta registrada (#$ventaDelLead).";
        }
    }
    // Moneda: la que elijas o, en automático, la de la página por la que llegó el cliente
    if ($datos['moneda'] === '' || $datos['moneda'] === 'AUTO') {
        $datos['moneda'] = (string) negocio_de_pais($datos['lead']['pais'] ?? null)['moneda'];
    } elseif (!in_array($datos['moneda'], monedas(), true)) {
        $errores['moneda'] = 'Elige la moneda en que te pagó.';
    }
    return [$datos, $errores];
}

function venta_por_clave(?string $clave): ?array
{
    return $clave === null ? null : db_fila('SELECT * FROM ventas WHERE clave_formulario = ?', [$clave]);
}

/**
 * Registra la venta (datos ya validados) y devuelve:
 * ['repetida' => bool, 'venta', 'comprador', 'enlace' (null si repetida), 'email' => resultado|null, 'meta' => bool|null]
 */
function venta_registrar(array $datos): array
{
    $repetida = venta_por_clave($datos['clave_formulario']);
    if ($repetida !== null) {
        return venta_ya_registrada($repetida);
    }
    try {
        $resultado = db_transaccion(function () use ($datos): array {
            $comprador = comprador_guardar($datos['nombre'], $datos['email'], $datos['whatsapp'] ?: null);
            $ventaId = db_insertar('ventas', [
                'comprador_id' => $comprador['id'],
                'lead_id' => $datos['lead']['id'] ?? null,
                'monto_centavos' => (int) round((float) $datos['monto'] * 100),
                'moneda' => $datos['moneda'] ?? 'USD',
                'metodo_pago' => $datos['metodo_pago'] ?: null,
                'referencia_pago' => $datos['referencia_pago'] ?: null,
                'clave_formulario' => $datos['clave_formulario'],
                'creado_en' => ahora_bd(),
            ]);
            acceso_otorgar((int) $comprador['id'], $ventaId);
            $venta = db_fila('SELECT * FROM ventas WHERE id = ?', [$ventaId]);
            return [
                'repetida' => false,
                'venta' => $venta,
                'comprador' => $comprador,
                'enlace' => enlace_acceso_crear((int) $comprador['id']),
                'evento_meta' => meta_purchase_para_venta($venta, $comprador, $datos['lead']),
            ];
        });
    } catch (PDOException $error) {
        // Dos envíos simultáneos del mismo formulario: el segundo choca con la restricción UNIQUE
        $repetida = venta_por_clave($datos['clave_formulario']);
        if ($repetida !== null) {
            return venta_ya_registrada($repetida);
        }
        throw $error;
    }

    // Llamadas externas, fuera de la transacción
    $resultado['email'] = $datos['enviar_email']
        ? email_acceso($resultado['comprador'], $resultado['enlace'], 'acceso-venta-' . $resultado['venta']['id'])
        : null;
    $resultado['meta'] = $resultado['evento_meta'] !== null ? meta_enviar_evento($resultado['evento_meta']) : null;
    return $resultado;
}

function venta_ya_registrada(array $venta): array
{
    return [
        'repetida' => true,
        'venta' => $venta,
        'comprador' => comprador_por_id((int) $venta['comprador_id']),
        'enlace' => null,
        'email' => null,
        'meta' => null,
    ];
}

/** Acceso sin venta (regalo, socio, prueba): no se avisa a Meta. */
function acceso_manual(string $nombre, string $email, ?string $whatsapp, ?string $notas, bool $enviarEmail): array
{
    $resultado = db_transaccion(function () use ($nombre, $email, $whatsapp, $notas): array {
        $comprador = comprador_guardar($nombre, $email, $whatsapp, $notas);
        acceso_otorgar((int) $comprador['id'], null);
        return ['comprador' => $comprador, 'enlace' => enlace_acceso_crear((int) $comprador['id'])];
    });
    $resultado['email'] = $enviarEmail ? email_acceso($resultado['comprador'], $resultado['enlace']) : null;
    return $resultado;
}

/** Métodos de pago para el formulario: los de negocio.php más "Otro". */
function metodos_de_pago(): array
{
    return [...(contenido('negocio')['metodos_pago'] ?? []), 'Otro'];
}

/** 1000 centavos → "$10" · en pesos: 20000 → "$200 MXN" */
function formatear_centavos(int|string|null $centavos, ?string $moneda = 'USD'): string
{
    return formatear_precio(((int) $centavos) / 100, $moneda ?: 'USD');
}

/**
 * Suma montos de varias monedas sin mezclarlas. Recibe lo que da la consulta
 * GROUP_CONCAT(moneda || ':' || monto_centavos): "USD:1000,MXN:20000,USD:500" → "$15 · $200 MXN".
 */
function formatear_montos(?string $lista): string
{
    $totales = [];
    foreach (array_filter(explode(',', (string) $lista)) as $par) {
        [$moneda, $centavos] = array_pad(explode(':', $par, 2), 2, '0');
        $totales[$moneda] = ($totales[$moneda] ?? 0) + (int) $centavos;
    }
    if (!$totales) {
        return formatear_precio(0);
    }
    uksort($totales, fn ($a, $b) => ($a === 'USD' ? 0 : 1) <=> ($b === 'USD' ? 0 : 1) ?: strcmp($a, $b));
    return implode(' · ', array_map(fn ($moneda, $centavos) => formatear_centavos($centavos, $moneda), array_keys($totales), $totales));
}

/** "MXN" → "Pesos mexicanos (MXN)", para el formulario de venta. */
function nombre_moneda(string $moneda): string
{
    return match ($moneda) {
        'USD' => 'Dólares (USD)',
        'MXN' => 'Pesos mexicanos (MXN)',
        'COP' => 'Pesos colombianos (COP)',
        'PEN' => 'Soles (PEN)',
        default => $moneda,
    };
}
