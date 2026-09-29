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
    $archivos = array_map('basename', glob(RAIZ . '/app/migraciones/*.sql') ?: []);
    sort($archivos);
    afirmar_igual($archivos, migrar($pdo));
    afirmar_igual([], migrar($pdo), 'La segunda vez no debe aplicar nada.');

    $tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['leads', 'compradores', 'ventas', 'accesos', 'tokens_login', 'sesiones', 'eventos_meta', 'emails', 'limites', 'progreso', 'migraciones'] as $tabla) {
        afirmar(in_array($tabla, $tablas, true), "Falta la tabla $tabla.");
    }
});

prueba('la migración 003 recupera cuándo entraron por primera vez los compradores anteriores', function () {
    $carpeta = sys_get_temp_dir() . '/migraciones-' . getmypid();
    if (!is_dir($carpeta)) {
        mkdir($carpeta);
    }
    foreach (['001_inicial.sql', '002_progreso.sql'] as $archivo) {
        copy(RAIZ . "/app/migraciones/$archivo", "$carpeta/$archivo");
    }
    $pdo = db_conectar(':memory:');
    migrar($pdo, $carpeta);
    array_map('unlink', glob("$carpeta/*.sql") ?: []);
    rmdir($carpeta);
    db($pdo);
    $entro = (int) db_insertar('compradores', fila_comprador('entro@correo.com'));
    $nunca = (int) db_insertar('compradores', fila_comprador('nunca@correo.com'));
    db_ejecutar("INSERT INTO progreso (comprador_id, leccion, vista_en) VALUES (?, 'bienvenida', '2026-09-02 10:00:00')", [$entro]);
    db_ejecutar(
        "INSERT INTO tokens_login (comprador_id, token_hash, proposito, creado_en, expira_en, usado_en)
         VALUES (?, 'hash', 'primer_acceso', '2026-09-01 09:00:00', '2026-09-08 09:00:00', '2026-09-01 09:30:00')",
        [$entro]
    );

    afirmar_igual(['003_primer_ingreso.sql'], array_values(array_filter(migrar($pdo), fn ($m) => $m === '003_primer_ingreso.sql')));
    afirmar_igual('2026-09-01 09:30:00', db_valor('SELECT primer_ingreso_en FROM compradores WHERE id = ?', [$entro]), 'Toma la fecha más antigua.');
    afirmar_igual(null, db_valor('SELECT primer_ingreso_en FROM compradores WHERE id = ?', [$nunca]), 'Quien nunca entró queda sin fecha.');
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
