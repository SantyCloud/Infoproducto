<?php
declare(strict_types=1);

/*
 * Compradores, accesos y enlaces mágicos.
 *
 * Un enlace mágico es de UN SOLO USO: al abrirlo se muestra un botón "Entrar" y recién al
 * pulsarlo (POST) se consume. Así los antivirus del correo, que abren los enlaces para
 * revisarlos, no lo gastan.
 */

const VALIDEZ_PRIMER_ACCESO = 7 * 86400; // enlace del email de compra (o el que mandas por WhatsApp)
const VALIDEZ_LOGIN = 30 * 60;          // enlace que se pide desde /entrar

/** Busca un comprador por email (sin importar mayúsculas). */
function comprador_por_email(string $email): ?array
{
    return db_fila('SELECT * FROM compradores WHERE email = ?', [strtolower(trim($email))]);
}

function comprador_por_id(int $id): ?array
{
    return db_fila('SELECT * FROM compradores WHERE id = ?', [$id]);
}

/** Crea el comprador o actualiza su nombre/WhatsApp si ya existía. Devuelve la fila. */
function comprador_guardar(string $nombre, string $email, ?string $whatsapp, ?string $notas = null): array
{
    $email = strtolower(trim($email));
    $existente = comprador_por_email($email);
    if ($existente !== null) {
        db_ejecutar(
            'UPDATE compradores SET nombre = ?, whatsapp = COALESCE(?, whatsapp), notas = COALESCE(?, notas), actualizado_en = ? WHERE id = ?',
            [$nombre, $whatsapp, $notas, ahora_bd(), $existente['id']]
        );
        return comprador_por_id((int) $existente['id']);
    }
    $id = db_insertar('compradores', [
        'nombre' => $nombre,
        'email' => $email,
        'whatsapp' => $whatsapp,
        'notas' => $notas,
        'creado_en' => ahora_bd(),
        'actualizado_en' => ahora_bd(),
    ]);
    return comprador_por_id($id);
}

/** Da acceso al curso (o lo reactiva si estaba revocado). */
function acceso_otorgar(int $compradorId, ?int $ventaId): void
{
    $existente = db_fila('SELECT id FROM accesos WHERE comprador_id = ? AND producto = ?', [$compradorId, 'curso']);
    if ($existente === null) {
        db_insertar('accesos', ['comprador_id' => $compradorId, 'producto' => 'curso', 'venta_id' => $ventaId, 'otorgado_en' => ahora_bd()]);
    } else {
        db_ejecutar('UPDATE accesos SET revocado_en = NULL, venta_id = COALESCE(venta_id, ?) WHERE id = ?', [$ventaId, $existente['id']]);
    }
}

function acceso_vigente(int $compradorId): bool
{
    return (bool) db_valor(
        'SELECT 1 FROM accesos WHERE comprador_id = ? AND producto = ? AND revocado_en IS NULL',
        [$compradorId, 'curso']
    );
}

/** Quita el acceso y cierra todas sus sesiones al instante. */
function acceso_revocar(int $compradorId): void
{
    db_transaccion(function () use ($compradorId): void {
        db_ejecutar('UPDATE accesos SET revocado_en = ? WHERE comprador_id = ? AND revocado_en IS NULL', [ahora_bd(), $compradorId]);
        db_ejecutar("DELETE FROM sesiones WHERE tipo = 'miembro' AND comprador_id = ?", [$compradorId]);
        db_ejecutar('UPDATE tokens_login SET usado_en = ? WHERE comprador_id = ? AND usado_en IS NULL', [ahora_bd(), $compradorId]);
    });
}

/**
 * Crea un enlace mágico y devuelve la URL completa. Los enlaces anteriores del mismo tipo
 * que no se usaron quedan anulados (solo sirve el último).
 */
function enlace_acceso_crear(int $compradorId, string $proposito = 'primer_acceso'): string
{
    $token = token_aleatorio();
    $validez = $proposito === 'login' ? VALIDEZ_LOGIN : VALIDEZ_PRIMER_ACCESO;
    db_ejecutar(
        'UPDATE tokens_login SET usado_en = ? WHERE comprador_id = ? AND proposito = ? AND usado_en IS NULL',
        [ahora_bd(), $compradorId, $proposito]
    );
    db_insertar('tokens_login', [
        'comprador_id' => $compradorId,
        'token_hash' => hash_token($token),
        'proposito' => $proposito,
        'creado_en' => ahora_bd(),
        'expira_en' => gmdate('Y-m-d H:i:s', time() + $validez),
    ]);
    return config('app.url') . '/acceso/' . $token;
}

/** Datos del enlace si todavía sirve (sin consumirlo), o null. */
function enlace_acceso_valido(mixed $token): ?array
{
    if (!token_con_formato_valido($token)) {
        return null;
    }
    $fila = db_fila(
        'SELECT t.*, c.nombre, c.email FROM tokens_login t JOIN compradores c ON c.id = t.comprador_id
         WHERE t.token_hash = ? AND t.usado_en IS NULL AND t.expira_en > ?',
        [hash_token($token), ahora_bd()]
    );
    if ($fila === null || !acceso_vigente((int) $fila['comprador_id'])) {
        return null;
    }
    return $fila;
}

/**
 * Consume el enlace (una sola vez, aunque lleguen dos peticiones a la vez) y devuelve
 * el id del comprador, o null si ya no servía.
 */
function enlace_acceso_usar(mixed $token): ?int
{
    if (!token_con_formato_valido($token)) {
        return null;
    }
    // Una sola operación atómica: solo UNA petición puede marcarlo como usado
    $marcado = db_ejecutar(
        'UPDATE tokens_login SET usado_en = ? WHERE token_hash = ? AND usado_en IS NULL AND expira_en > ?',
        [ahora_bd(), hash_token($token), ahora_bd()]
    );
    if ($marcado !== 1) {
        return null;
    }
    $compradorId = (int) db_valor('SELECT comprador_id FROM tokens_login WHERE token_hash = ?', [hash_token($token)]);
    return acceso_vigente($compradorId) ? $compradorId : null;
}

/** Guarda la primera vez que el comprador entra al curso (la política de reembolsos depende de eso). */
function comprador_marcar_ingreso(int $compradorId): void
{
    db_ejecutar('UPDATE compradores SET primer_ingreso_en = COALESCE(primer_ingreso_en, ?) WHERE id = ?', [ahora_bd(), $compradorId]);
}
