<?php
declare(strict_types=1);

function fila_comprador(string $email): array
{
    return ['nombre' => 'Ana', 'email' => $email, 'creado_en' => ahora_bd(), 'actualizado_en' => ahora_bd()];
}

function fila_lead(string $codigo): array
{
    return ['codigo' => $codigo, 'event_id' => "ev-$codigo", 'creado_en' => ahora_bd(), 'ultimo_clic_en' => ahora_bd()];
}

function fila_venta(int $compradorId, ?int $leadId = null, ?string $claveFormulario = null): array
{
    return [
        'comprador_id' => $compradorId,
        'lead_id' => $leadId,
        'monto_centavos' => 1000,
        'clave_formulario' => $claveFormulario,
        'creado_en' => ahora_bd(),
    ];
}

prueba('las migraciones crean todas las tablas y no se aplican dos veces', function () {
    $pdo = db_conectar(':memory:');
    afirmar_igual(['001_inicial.sql'], migrar($pdo));
    afirmar_igual([], migrar($pdo), 'La segunda vez no debe aplicar nada.');

    $tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['leads', 'compradores', 'ventas', 'accesos', 'tokens_login', 'sesiones', 'eventos_meta', 'emails', 'limites', 'migraciones'] as $tabla) {
        afirmar(in_array($tabla, $tablas, true), "Falta la tabla $tabla.");
    }
});

prueba('el email del comprador es único aunque cambien mayúsculas', function () {
    bd_de_prueba();
    db_insertar('compradores', fila_comprador('ana@correo.com'));
    afirmar_falla(fn () => db_insertar('compradores', fila_comprador('ANA@Correo.com')));
});

prueba('un lead solo puede convertirse en una venta', function () {
    bd_de_prueba();
    $comprador = db_insertar('compradores', fila_comprador('ana@correo.com'));
    $lead = db_insertar('leads', fila_lead('K7Q2'));
    db_insertar('ventas', fila_venta($comprador, $lead));
    afirmar_falla(fn () => db_insertar('ventas', fila_venta($comprador, $lead)));
});

prueba('el mismo formulario no puede registrar dos ventas (doble clic)', function () {
    bd_de_prueba();
    $comprador = db_insertar('compradores', fila_comprador('ana@correo.com'));
    db_insertar('ventas', fila_venta($comprador, null, 'form-123'));
    afirmar_falla(fn () => db_insertar('ventas', fila_venta($comprador, null, 'form-123')));
    db_insertar('ventas', fila_venta($comprador)); // sin lead ni clave: se permiten varias
    afirmar_igual(2, (int) db_valor('SELECT COUNT(*) FROM ventas'));
});

prueba('un comprador tiene un solo acceso por producto', function () {
    bd_de_prueba();
    $comprador = db_insertar('compradores', fila_comprador('ana@correo.com'));
    db_insertar('accesos', ['comprador_id' => $comprador, 'otorgado_en' => ahora_bd()]);
    afirmar_falla(fn () => db_insertar('accesos', ['comprador_id' => $comprador, 'otorgado_en' => ahora_bd()]));
});

prueba('no se pueden crear ventas de compradores que no existen', function () {
    bd_de_prueba();
    afirmar_falla(fn () => db_insertar('ventas', fila_venta(999)));
});

prueba('una sesión de miembro necesita comprador y una de admin no lo lleva', function () {
    bd_de_prueba();
    $comprador = db_insertar('compradores', fila_comprador('ana@correo.com'));
    $sesion = fn (string $tipo, ?int $compradorId, string $token) => db_insertar('sesiones', [
        'token_hash' => $token, 'tipo' => $tipo, 'comprador_id' => $compradorId,
        'creado_en' => ahora_bd(), 'ultimo_uso_en' => ahora_bd(), 'expira_en' => ahora_bd(),
    ]);
    $sesion('miembro', $comprador, 'a');
    $sesion('admin', null, 'b');
    afirmar_falla(fn () => $sesion('miembro', null, 'c'));
    afirmar_falla(fn () => $sesion('admin', $comprador, 'd'));
});

prueba('una transacción que falla no deja nada a medias', function () {
    bd_de_prueba();
    afirmar_falla(fn () => db_transaccion(function () {
        db_insertar('compradores', fila_comprador('ana@correo.com'));
        throw new RuntimeException('fallo a mitad');
    }));
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM compradores'));
});

prueba('db_insertar rechaza nombres de columna sospechosos', function () {
    bd_de_prueba();
    afirmar_falla(fn () => db_insertar('compradores', ['nombre) VALUES (1); DROP TABLE ventas; --' => 'x']));
});
