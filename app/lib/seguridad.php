<?php
declare(strict_types=1);

/*
 * Seguridad compartida por el panel admin y el área de miembros:
 * tokens aleatorios, sesiones en la base de datos y protección de formularios (CSRF).
 *
 * - Nunca se guarda un token tal cual: solo su hash (HMAC con CLAVE_APP).
 * - Las cookies de sesión son HttpOnly (JavaScript no puede leerlas) y Secure con https.
 */

const COOKIE_MIEMBRO = 'sesion';
const COOKIE_ADMIN = 'admin';
const DURACION_SESION_MIEMBRO = 90 * 86400;
const DURACION_SESION_ADMIN = 12 * 3600;
const MAXIMO_DISPOSITIVOS = 3;

/** Token aleatorio de 256 bits, apto para URLs. */
function token_aleatorio(): string
{
    return base64url(random_bytes(32));
}

function hash_token(string $token): string
{
    return hash_hmac('sha256', $token, config('app.clave'));
}

function token_con_formato_valido(mixed $token): bool
{
    return is_string($token) && (bool) preg_match('/^[A-Za-z0-9_-]{40,64}$/', $token);
}

/* ---------- Sesiones ---------- */

/**
 * Abre una sesión y devuelve el token que va en la cookie.
 * Para miembros, si ya hay 3 dispositivos abiertos, cierra el más antiguo.
 */
function sesion_crear(string $tipo, ?int $compradorId): string
{
    $token = token_aleatorio();
    $ahora = ahora_bd();
    $duracion = $tipo === 'admin' ? DURACION_SESION_ADMIN : DURACION_SESION_MIEMBRO;
    db_insertar('sesiones', [
        'token_hash' => hash_token($token),
        'tipo' => $tipo,
        'comprador_id' => $compradorId,
        'ip' => ip_cliente() ?: null,
        'user_agent' => agente_usuario() ?: null,
        'creado_en' => $ahora,
        'ultimo_uso_en' => $ahora,
        'expira_en' => gmdate('Y-m-d H:i:s', time() + $duracion),
    ]);
    if ($tipo === 'miembro') {
        db_ejecutar(
            'DELETE FROM sesiones WHERE tipo = ? AND comprador_id = ? AND id NOT IN (
                SELECT id FROM sesiones WHERE tipo = ? AND comprador_id = ? ORDER BY id DESC LIMIT ?)',
            ['miembro', $compradorId, 'miembro', $compradorId, MAXIMO_DISPOSITIVOS]
        );
    }
    return $token;
}

/** Sesión válida para el token dado (o null). Para miembros exige además un acceso vigente. */
function sesion_de_token(string $tipo, mixed $token): ?array
{
    if (!token_con_formato_valido($token)) {
        return null;
    }
    $sesion = db_fila(
        'SELECT * FROM sesiones WHERE token_hash = ? AND tipo = ? AND expira_en > ?',
        [hash_token($token), $tipo, ahora_bd()]
    );
    if ($sesion === null) {
        return null;
    }
    if ($tipo === 'miembro' && !acceso_vigente((int) $sesion['comprador_id'])) {
        return null;
    }
    // Registra el uso como mucho una vez por hora (evita escribir en cada página)
    if ($sesion['ultimo_uso_en'] < gmdate('Y-m-d H:i:s', time() - 3600)) {
        db_ejecutar('UPDATE sesiones SET ultimo_uso_en = ? WHERE id = ?', [ahora_bd(), $sesion['id']]);
    }
    return $sesion;
}

function sesion_cerrar(mixed $token): void
{
    if (token_con_formato_valido($token)) {
        db_ejecutar('DELETE FROM sesiones WHERE token_hash = ?', [hash_token($token)]);
    }
}

/* ---------- Formularios: protección CSRF ---------- */

/**
 * Token anti-falsificación del formulario (patrón "doble envío"): el mismo valor va en una
 * cookie y en un campo oculto. Una web ajena no puede leer la cookie, así que no puede
 * enviar formularios en nombre del visitante.
 */
function csrf_token(): string
{
    static $token = null;
    if ($token === null) {
        $existente = $_COOKIE['csrf'] ?? null;
        $token = is_string($existente) && preg_match('/^[A-Za-z0-9_-]{43}$/', $existente) ? $existente : token_aleatorio();
    }
    return $token;
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Añade la cookie del token CSRF a la respuesta si la página generó uno nuevo. */
function con_cookie_csrf(array $respuesta): array
{
    if (($_COOKIE['csrf'] ?? null) !== csrf_token()) {
        $respuesta = con_cookie($respuesta, 'csrf', csrf_token(), 0, true, 'Lax');
    }
    return $respuesta;
}

/**
 * ¿El envío del formulario es legítimo? Comprueba el token CSRF y, si el navegador indica
 * el origen, que venga de esta misma web.
 */
function envio_legitimo(array $post, array $cookies, array $servidor): bool
{
    $token = $post['_csrf'] ?? '';
    $cookie = $cookies['csrf'] ?? '';
    if (!is_string($token) || !is_string($cookie) || $cookie === '' || !hash_equals($cookie, $token)) {
        return false;
    }
    $origen = (string) ($servidor['HTTP_ORIGIN'] ?? '');
    if ($origen !== '' && $origen !== 'null') {
        $host = parse_url($origen, PHP_URL_HOST) . (parse_url($origen, PHP_URL_PORT) ? ':' . parse_url($origen, PHP_URL_PORT) : '');
        return strcasecmp($host, (string) ($servidor['HTTP_HOST'] ?? '')) === 0;
    }
    return true;
}

/** Respuesta de error si el formulario no es legítimo; null si todo está bien. */
function rechazar_si_no_legitimo(): ?array
{
    if (envio_legitimo($_POST, $_COOKIE, $_SERVER)) {
        return null;
    }
    return pagina_error(403, 'Formulario vencido', 'Vuelve atrás, recarga la página e inténtalo de nuevo.');
}

/** Encabezados para páginas privadas: que nadie las guarde en caché ni las indexe. */
function privada(array $respuesta): array
{
    $respuesta['cabeceras']['Cache-Control'] = 'no-store';
    $respuesta['cabeceras']['X-Robots-Tag'] = 'noindex, nofollow';
    return con_cookie_csrf($respuesta);
}
