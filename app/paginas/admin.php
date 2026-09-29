<?php
declare(strict_types=1);

/*
 * Panel de administración (/admin), solo para el dueño.
 * Usuario y contraseña en .env (se configuran con php bin/crear-admin.php).
 */

// Hash de una contraseña aleatoria: se compara contra él cuando el usuario no existe, para que
// la respuesta tarde lo mismo y no delate si el usuario es correcto.
const HASH_FALSO = '$2y$12$Mj7N5YtirIMEC66Bw9bf5e9eTjWZrY8bouGXeQ/pU.NfHHLXCTaxu';
const LEADS_POR_PAGINA = 50;
const COOKIE_DISPOSITIVO_ADMIN = 'admin_dispositivo';

// Pagos confirmados: las ventas más los pagos que el cliente todavía no activa (sin los anulados).
// Para las métricas: el dinero ya lo recibiste, aunque la venta se cree al activar.
const SQL_PAGOS = 'WITH pagos AS (
    SELECT lead_id, moneda, monto_centavos, creado_en FROM ventas
    UNION ALL
    SELECT lead_id, moneda, monto_centavos, creado_en FROM activaciones WHERE usado_en IS NULL AND anulado_en IS NULL
) ';

// Clics con su estado: venta_id (vendido) o activacion_id (pagó y falta que active su acceso)
const SQL_LEADS_CON_ESTADO = 'SELECT l.*, v.id AS venta_id, a.id AS activacion_id FROM leads l
    LEFT JOIN ventas v ON v.lead_id = l.id
    LEFT JOIN activaciones a ON a.lead_id = l.id AND a.usado_en IS NULL AND a.anulado_en IS NULL';

function admin_configurado(): bool
{
    return config('admin.usuario') !== '' && str_starts_with(config('admin.clave_hash'), '$');
}

function admin_sesion(): ?array
{
    return sesion_de_token('admin', $_COOKIE[COOKIE_ADMIN] ?? null);
}

/** Si no hay sesión de admin devuelve la redirección al acceso; si la hay, null. */
function admin_requerido(): ?array
{
    return admin_sesion() === null ? redireccion('/admin/entrar') : null;
}

function admin_vista(string $plantilla, array $datos, string $seccion = ''): array
{
    $datos['titulo'] = ($datos['titulo'] ?? 'Panel') . ' · Panel';
    return privada(html(vista('admin/' . $plantilla, $datos + ['seccion' => $seccion], 'layout_admin')));
}

/** Id numérico de la ruta, o null si no lo es. */
function id_de_ruta(string $id): ?int
{
    return ctype_digit($id) && strlen($id) < 10 ? (int) $id : null;
}

/* ---------- Entrar y salir ---------- */

function admin_entrar_formulario(): array
{
    if (admin_sesion() !== null) {
        return redireccion('/admin');
    }
    return admin_formulario_acceso();
}

function admin_formulario_acceso(string $error = '', string $usuario = '', int $estado = 200): array
{
    return privada(html(vista('admin/entrar', [
        'titulo' => 'Entrar al panel',
        'error' => $error,
        'usuario' => $usuario,
        'configurado' => admin_configurado(),
    ]), $estado));
}

function admin_entrar(): array
{
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    $usuario = limpiar($_POST['usuario'] ?? '', 100);
    $clave = texto_de($_POST['clave'] ?? null);
    $ip = ip_cliente();

    // Sin dispositivo conocido: 5 intentos por IP cada 15 minutos. En un dispositivo donde el dueño
    // ya entró antes, un contador propio: nadie puede bloquearlo equivocándose desde otras IP.
    $dispositivo = texto_de($_COOKIE[COOKIE_DISPOSITIVO_ADMIN] ?? null);
    $conocido = dispositivo_admin_conocido($dispositivo);
    $claveLimite = $conocido
        ? 'admin-entrar-dispositivo:' . substr(hash('sha256', $dispositivo), 0, 24)
        : 'admin-entrar:' . ip_para_limites($ip);
    if (!limite_permitir($claveLimite, $conocido ? 10 : 5, 900)) {
        registrar('seguridad', 'Demasiados intentos de acceso al panel', ['ip' => $ip]);
        return admin_formulario_acceso('Demasiados intentos. Espera 15 minutos y vuelve a probar.', $usuario, 429);
    }

    // La contraseña se comprueba siempre contra el hash configurado: la respuesta tarda lo mismo
    // sea correcto o no el usuario, así no se puede adivinar cuál es.
    $claveCorrecta = password_verify($clave, admin_configurado() ? config('admin.clave_hash') : HASH_FALSO);
    $usuarioCorrecto = admin_configurado() && hash_equals(config('admin.usuario'), $usuario);
    if (!$claveCorrecta || !$usuarioCorrecto) {
        registrar('seguridad', 'Intento fallido de acceso al panel', ['ip' => $ip]);
        return admin_formulario_acceso('Usuario o contraseña incorrectos.', $usuario, 401);
    }
    limite_reiniciar($claveLimite);
    registrar('seguridad', 'Acceso al panel', ['ip' => $ip]);
    $respuesta = con_cookie(redireccion('/admin'), COOKIE_ADMIN, sesion_crear('admin', null), DURACION_SESION_ADMIN, true, 'Strict', '/admin');
    return $conocido ? $respuesta
        : con_cookie($respuesta, COOKIE_DISPOSITIVO_ADMIN, dispositivo_admin_nuevo(), 365 * 86400, true, 'Strict', '/admin');
}

/** Marca firmada (con CLAVE_APP) que recuerda un dispositivo donde el dueño ya entró. */
function dispositivo_admin_nuevo(): string
{
    $id = token_aleatorio();
    return $id . '.' . substr(hash_hmac('sha256', 'dispositivo-admin:' . $id, config('app.clave')), 0, 32);
}

function dispositivo_admin_conocido(string $valor): bool
{
    if (!preg_match('/^([A-Za-z0-9_-]{43})\.([a-f0-9]{32})$/', $valor, $m)) {
        return false;
    }
    return hash_equals(substr(hash_hmac('sha256', 'dispositivo-admin:' . $m[1], config('app.clave')), 0, 32), $m[2]);
}

function admin_salir(): array
{
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    sesion_cerrar($_COOKIE[COOKIE_ADMIN] ?? null);
    return sin_cookie(redireccion('/admin/entrar'), COOKIE_ADMIN, '/admin');
}

/* ---------- Inicio ---------- */

function admin_inicio(): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    $hoy = (new DateTimeImmutable('today', new DateTimeZone(config('app.zona_horaria'))))
        ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $hace30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
    $contar = fn (string $sql, array $p = []) => (int) db_valor($sql, $p);

    $leads30 = $contar('SELECT COUNT(*) FROM leads WHERE creado_en >= ?', [$hace30]);
    $ventasConLead30 = $contar(SQL_PAGOS . 'SELECT COUNT(*) FROM pagos WHERE lead_id IS NOT NULL AND creado_en >= ?', [$hace30]);
    $metricas = [
        ['Clics a WhatsApp hoy', $contar('SELECT COUNT(*) FROM leads WHERE creado_en >= ?', [$hoy])],
        ['Ventas hoy', $contar(SQL_PAGOS . 'SELECT COUNT(*) FROM pagos WHERE creado_en >= ?', [$hoy])],
        ['Ingresos 30 días', formatear_montos(db_valor(SQL_PAGOS . "SELECT GROUP_CONCAT(moneda || ':' || monto_centavos) FROM pagos WHERE creado_en >= ?", [$hace30]))],
        ['Cierre 30 días', $leads30 > 0 ? round($ventasConLead30 / $leads30 * 100) . '%' : '—'],
    ];

    return admin_vista('inicio', [
        'titulo' => 'Inicio',
        'metricas' => $metricas,
        'leads30' => $leads30,
        'ventas30' => $contar(SQL_PAGOS . 'SELECT COUNT(*) FROM pagos WHERE creado_en >= ?', [$hace30]),
        'recientes' => db_filas(SQL_LEADS_CON_ESTADO . ' ORDER BY l.id DESC LIMIT 12'),
        'anuncios' => db_filas(
            SQL_PAGOS . "SELECT COALESCE(NULLIF(l.utm_campaign, ''), '(sin campaña)') AS campana, COALESCE(l.utm_content, '') AS anuncio,
                    COUNT(*) AS leads, COUNT(p.lead_id) AS ventas, GROUP_CONCAT(p.moneda || ':' || p.monto_centavos) AS ingresos
             FROM leads l LEFT JOIN pagos p ON p.lead_id = l.id
             WHERE l.creado_en >= ? GROUP BY 1, 2 ORDER BY ventas DESC, leads DESC LIMIT 10",
            [$hace30]
        ),
        'por_activar' => activaciones_pendientes(),
        'pendientes' => lista_de_pendientes(),
    ], 'inicio');
}

/** Lo que falta configurar o revisar, para mostrarlo en el inicio del panel. */
function lista_de_pendientes(): array
{
    $negocio = contenido('negocio');
    $pendientes = [];
    if (($negocio['whatsapp']['numero'] ?? '') === '593900000000') {
        $pendientes[] = 'Pon tu número de WhatsApp real en contenido/negocio.php.';
    }
    $landing = (string) json_encode(contenido('landing'), JSON_UNESCAPED_UNICODE);
    if (str_contains($landing, '[Tu nombre]') || str_contains($landing, '[X años]')) {
        $pendientes[] = 'Completa tu historia en contenido/landing.php (lo que está [entre corchetes]).';
    }
    if (str_contains((string) json_encode($negocio['legal'] ?? [], JSON_UNESCAPED_UNICODE), '[')) {
        $pendientes[] = 'Completa tus datos legales (titular, RUC, ciudad) en contenido/negocio.php.';
    }
    if (!capturas('mensajes') && !capturas('ingresos')) {
        $pendientes[] = 'Sube tus capturas (mensajes-1.jpg, ingresos-1.jpg…) a contenido/capturas/ y ejecuta php bin/instalar.php.';
    }
    if (config('email.resend_api_key') === '') {
        $pendientes[] = 'Los emails se están simulando: configura RESEND_API_KEY en .env para enviarlos de verdad.';
    }
    if (meta_pixel_id() === null) {
        $pendientes[] = 'Configura META_PIXEL_ID en .env para activar el Pixel de Meta.';
    } elseif (!meta_capi_activa()) {
        $pendientes[] = 'Configura META_CAPI_TOKEN en .env para enviar las ventas a Meta (API de Conversiones).';
    }
    if (config('meta.test_event_code') !== '') {
        $pendientes[] = 'META_TEST_EVENT_CODE está activo: quítalo del .env cuando termines de probar.';
    }
    if (!promo_vigente($negocio) && !empty($negocio['promo']['activa'])) {
        $pendientes[] = 'La promoción ya terminó: la web muestra el precio normal. Si quieres otra, cambia la fecha en negocio.php.';
    }
    $errores = (int) db_valor("SELECT COUNT(*) FROM emails WHERE estado = 'error' AND creado_en >= ?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]);
    if ($errores > 0) {
        $pendientes[] = "$errores email(s) no se pudieron enviar en los últimos 7 días (revisa la ficha de cada comprador).";
    }
    $fallidos = (int) db_valor("SELECT COUNT(*) FROM eventos_meta WHERE estado IN ('error', 'descartado') AND creado_en >= ?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]);
    if ($fallidos > 0) {
        $pendientes[] = "$fallidos evento(s) de Meta fallaron en los últimos 7 días (ver storage/logs/meta-*.log).";
    }
    return $pendientes;
}

/* ---------- Leads ---------- */

function admin_leads(): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    $buscar = limpiar($_GET['q'] ?? '', 60);
    $estado = in_array($_GET['estado'] ?? '', ['sin-venta', 'con-venta'], true) ? $_GET['estado'] : '';
    $pagina = max(1, min(1000, (int) ($_GET['p'] ?? 1)));
    [$where, $parametros] = filtros_leads($buscar, $estado);
    $leads = db_filas(
        SQL_LEADS_CON_ESTADO . " $where ORDER BY l.id DESC LIMIT ? OFFSET ?",
        [...$parametros, LEADS_POR_PAGINA + 1, ($pagina - 1) * LEADS_POR_PAGINA]
    );
    return admin_vista('leads', [
        'titulo' => 'Clics a WhatsApp',
        'leads' => array_slice($leads, 0, LEADS_POR_PAGINA),
        'hay_mas' => count($leads) > LEADS_POR_PAGINA,
        'pagina' => $pagina,
        'buscar' => $buscar,
        'estado' => $estado,
    ], 'leads');
}

function filtros_leads(string $buscar, string $estado): array
{
    $condiciones = [];
    $parametros = [];
    if ($buscar !== '') {
        $condiciones[] = '(l.codigo = ? OR l.utm_campaign LIKE ? OR l.utm_content LIKE ? OR l.utm_source LIKE ?)';
        $like = '%' . $buscar . '%';
        array_push($parametros, normalizar_codigo($buscar), $like, $like, $like);
    }
    if ($estado === 'sin-venta') {
        $condiciones[] = 'v.id IS NULL AND a.id IS NULL';
    } elseif ($estado === 'con-venta') {
        $condiciones[] = '(v.id IS NOT NULL OR a.id IS NOT NULL)'; // incluye los pagos por activar
    }
    return [$condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '', $parametros];
}

/* ---------- Ventas ---------- */

function admin_ventas(string $mensaje = ''): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    $pagina = max(1, min(1000, (int) ($_GET['p'] ?? 1)));
    $ventas = db_filas(
        'SELECT v.*, c.nombre, c.email, l.codigo, l.utm_campaign, l.utm_content, e.estado AS meta_estado
         FROM ventas v JOIN compradores c ON c.id = v.comprador_id
         LEFT JOIN leads l ON l.id = v.lead_id
         LEFT JOIN eventos_meta e ON e.venta_id = v.id AND e.evento = \'Purchase\'
         ORDER BY v.id DESC LIMIT ? OFFSET ?',
        [LEADS_POR_PAGINA + 1, ($pagina - 1) * LEADS_POR_PAGINA]
    );
    return admin_vista('ventas', [
        'titulo' => 'Ventas',
        'ventas' => array_slice($ventas, 0, LEADS_POR_PAGINA),
        'hay_mas' => count($ventas) > LEADS_POR_PAGINA,
        'pagina' => $pagina,
        'por_activar' => $pagina === 1 ? activaciones_pendientes() : [],
        'mensaje' => $mensaje,
    ], 'ventas');
}

function admin_venta_formulario(): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    $codigo = normalizar_codigo(texto_de($_GET['codigo'] ?? null));
    $lead = $codigo !== '' ? db_fila('SELECT * FROM leads WHERE codigo = ?', [$codigo]) : null;
    // Desde un clic: el precio y la moneda de su página. Sin clic, el monto queda vacío a propósito
    // (con la moneda en automático, un "10" de otro país se registraría mal).
    $negocio = negocio_de_pais($lead['pais'] ?? null);
    return admin_vista_formulario_venta([
        'codigo' => $codigo,
        'monto' => $lead ? rtrim(rtrim(number_format(precio_actual($negocio), 2, '.', ''), '0'), '.') : '',
        'moneda' => $lead ? $negocio['moneda'] : 'AUTO',
        'entrega' => 'activacion',
        'enviar_email' => true,
    ], []);
}

function admin_vista_formulario_venta(array $valores, array $errores): array
{
    $lead = !empty($valores['codigo']) ? db_fila('SELECT * FROM leads WHERE codigo = ?', [$valores['codigo']]) : null;
    return admin_vista('venta_nueva', [
        'titulo' => 'Registrar venta',
        'valores' => $valores,
        'errores' => $errores,
        'lead' => $lead,
        'metodos' => metodos_de_pago(),
        'monedas' => monedas(),
        'clave_formulario' => $valores['clave_formulario'] ?? token_aleatorio(),
    ], 'ventas');
}

function admin_venta_registrar(): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    [$datos, $errores] = venta_validar($_POST);
    if ($errores) {
        return admin_vista_formulario_venta($datos, $errores);
    }
    // El mismo formulario enviado otra vez (doble toque, recargar, volver atrás y cambiar la opción)
    // no registra nada nuevo: se muestra lo que ya se había registrado con él.
    $activacion = activacion_por_clave($datos['clave_formulario']);
    if ($activacion === null && $datos['entrega'] === 'activacion' && venta_por_clave($datos['clave_formulario']) === null) {
        $resultado = activacion_crear($datos);
        if (isset($resultado['error'])) {
            return admin_vista_formulario_venta($datos, ['codigo' => $resultado['error']]);
        }
        registrar('ventas', $resultado['repetida'] ? 'Pago repetido (no se duplicó)' : 'Pago registrado: falta que el cliente lo active', [
            'activacion' => $resultado['activacion']['id'],
        ]);
        return admin_vista_activacion($resultado);
    }
    if ($activacion !== null) {
        return admin_vista_activacion(activacion_ya_creada($activacion));
    }
    $resultado = venta_registrar($datos);
    if (isset($resultado['error'])) {
        return admin_vista_formulario_venta($datos, ['codigo' => $resultado['error']]);
    }
    registrar('ventas', $resultado['repetida'] ? 'Venta repetida (no se duplicó)' : 'Venta registrada', [
        'venta' => $resultado['venta']['id'],
        'email' => $resultado['comprador']['email'],
    ]);
    return admin_vista('venta_resultado', ['titulo' => 'Venta registrada', 'resultado' => $resultado], 'ventas');
}

function admin_vista_activacion(array $resultado, bool $enlaceNuevo = false, string $aviso = ''): array
{
    $activacion = $resultado['activacion'];
    $venta = $activacion['venta_id'] !== null ? db_fila('SELECT * FROM ventas WHERE id = ?', [$activacion['venta_id']]) : null;
    return admin_vista('activacion_resultado', [
        'titulo' => $enlaceNuevo ? 'Enlace nuevo' : 'Pago registrado',
        'resultado' => $resultado,
        'enlace_nuevo' => $enlaceNuevo,
        'aviso' => $aviso,
        'comprador' => $venta !== null ? comprador_por_id((int) $venta['comprador_id']) : null,
        'clic' => $activacion['lead_id'] !== null ? db_fila('SELECT * FROM leads WHERE id = ?', [$activacion['lead_id']]) : null,
    ], 'ventas');
}

/* ---------- Pagos por activar ---------- */

/** Nuevo enlace (el cliente perdió el suyo o se le venció) o anular el pago (devolución, error). */
function admin_activacion_accion(string $id, string $accion): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    $activacion = ($numero = id_de_ruta($id)) !== null ? activacion_por_id($numero) : null;
    if ($activacion === null) {
        return pagina_error(404, 'No encontrado', 'Ese pago no existe.');
    }
    $aid = (int) $activacion['id'];
    if ($accion === 'enlace') {
        // Solo si el enlace sigue siendo el que se veía al tocar el botón: recargar la página no anula el que ya enviaste
        $resultado = activacion_nuevo_codigo($aid, texto_de($_POST['version'] ?? null));
        if ($resultado === null) {
            $actual = activacion_por_id($aid);
            if ($actual !== null && $actual['usado_en'] === null && $actual['anulado_en'] === null) {
                return admin_vista_activacion(activacion_ya_creada($actual), false, 'Ya se había creado un enlace nuevo para este pago'
                    . ' (quizá recargaste la página): el último que enviaste sigue sirviendo. Si no lo copiaste, crea otro.');
            }
            return admin_ventas("El pago #$aid ya se activó o se anuló: no se le puede crear otro enlace.");
        }
        registrar('ventas', 'Nuevo enlace de activación', ['activacion' => $aid]);
        return admin_vista_activacion($resultado, true);
    }
    if (!activacion_anular($aid)) {
        return admin_ventas("El pago #$aid ya se había activado o anulado.");
    }
    registrar('ventas', 'Pago por activar anulado', ['activacion' => $aid]);
    return admin_ventas("Pago #$aid anulado: su enlace ya no sirve y no cuenta en tus ingresos.");
}

function admin_activacion_enlace(string $id): array
{
    return admin_activacion_accion($id, 'enlace');
}

function admin_activacion_anular(string $id): array
{
    return admin_activacion_accion($id, 'anular');
}

/* ---------- Compradores ---------- */

function admin_compradores(): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    $buscar = limpiar($_GET['q'] ?? '', 100);
    $pagina = max(1, min(1000, (int) ($_GET['p'] ?? 1)));
    $where = '';
    $parametros = [];
    if ($buscar !== '') {
        $where = 'WHERE c.nombre LIKE ? OR c.email LIKE ? OR c.whatsapp LIKE ?';
        $like = '%' . $buscar . '%';
        $parametros = [$like, $like, '%' . preg_replace('/\D/', '', $buscar) . '%'];
        if (preg_replace('/\D/', '', $buscar) === '') {
            $parametros[2] = $like;
        }
    }
    $compradores = db_filas(
        "SELECT c.*, a.revocado_en, a.id AS acceso_id,
                (SELECT GROUP_CONCAT(moneda || ':' || monto_centavos) FROM ventas WHERE comprador_id = c.id) AS pagado
         FROM compradores c LEFT JOIN accesos a ON a.comprador_id = c.id AND a.producto = 'curso'
         $where ORDER BY c.id DESC LIMIT ? OFFSET ?",
        [...$parametros, LEADS_POR_PAGINA + 1, ($pagina - 1) * LEADS_POR_PAGINA]
    );
    return admin_vista('compradores', [
        'titulo' => 'Compradores',
        'compradores' => array_slice($compradores, 0, LEADS_POR_PAGINA),
        'hay_mas' => count($compradores) > LEADS_POR_PAGINA,
        'pagina' => $pagina,
        'buscar' => $buscar,
    ], 'compradores');
}

function admin_comprador(string $id, array $extra = []): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    $comprador = ($numero = id_de_ruta($id)) !== null ? comprador_por_id($numero) : null;
    if ($comprador === null) {
        return pagina_error(404, 'No encontrado', 'Ese comprador no existe.');
    }
    $cid = (int) $comprador['id'];
    return admin_vista('comprador', $extra + [
        'titulo' => $comprador['nombre'],
        'comprador' => $comprador,
        'acceso' => db_fila("SELECT * FROM accesos WHERE comprador_id = ? AND producto = 'curso'", [$cid]),
        'ventas' => db_filas(
            'SELECT v.*, l.codigo, l.utm_campaign, l.utm_content, e.estado AS meta_estado FROM ventas v
             LEFT JOIN leads l ON l.id = v.lead_id LEFT JOIN eventos_meta e ON e.venta_id = v.id AND e.evento = \'Purchase\'
             WHERE v.comprador_id = ? ORDER BY v.id DESC',
            [$cid]
        ),
        'emails' => db_filas('SELECT * FROM emails WHERE comprador_id = ? ORDER BY id DESC LIMIT 10', [$cid]),
        'sesiones' => (int) db_valor("SELECT COUNT(*) FROM sesiones WHERE tipo = 'miembro' AND comprador_id = ? AND expira_en > ?", [$cid, ahora_bd()]),
        'vistas' => count(lecciones_vistas($cid)),
        'total_lecciones' => count(curso()['lista']),
        'mensaje' => $extra['mensaje'] ?? '',
        'enlace' => $extra['enlace'] ?? null,
    ], 'compradores');
}

/** Acciones sobre un comprador (formularios POST de su ficha). */
function admin_comprador_accion(string $id, string $accion): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    $comprador = ($numero = id_de_ruta($id)) !== null ? comprador_por_id($numero) : null;
    if ($comprador === null) {
        return pagina_error(404, 'No encontrado', 'Ese comprador no existe.');
    }
    $cid = (int) $comprador['id'];
    switch ($accion) {
        case 'enlace':
            if (!acceso_vigente($cid)) {
                return admin_comprador($id, ['mensaje' => 'Este comprador no tiene acceso activo. Restáuralo primero.']);
            }
            return admin_comprador($id, ['enlace' => enlace_acceso_crear($cid), 'mensaje' => 'Enlace nuevo creado. Los anteriores sin usar ya no sirven.']);
        case 'reenviar':
            if (!acceso_vigente($cid)) {
                return admin_comprador($id, ['mensaje' => 'Este comprador no tiene acceso activo. Restáuralo primero.']);
            }
            $enlace = enlace_acceso_crear($cid);
            $envio = email_acceso($comprador, $enlace);
            $mensaje = $envio['ok']
                ? ($envio['simulado'] ? 'Email simulado (falta configurar Resend). Copia el enlace de abajo.' : 'Email de acceso reenviado a ' . $comprador['email'] . '.')
                : 'No se pudo enviar el email: ' . $envio['error'];
            return admin_comprador($id, ['enlace' => $enlace, 'mensaje' => $mensaje]);
        case 'revocar':
            acceso_revocar($cid);
            registrar('ventas', 'Acceso revocado', ['comprador' => $cid]);
            return admin_comprador($id, ['mensaje' => 'Acceso revocado y sesiones cerradas.']);
        case 'restaurar':
            acceso_otorgar($cid, null);
            return admin_comprador($id, ['mensaje' => 'Acceso restaurado. Genera un enlace nuevo para enviárselo.']);
        case 'cerrar-sesiones':
            db_ejecutar("DELETE FROM sesiones WHERE tipo = 'miembro' AND comprador_id = ?", [$cid]);
            return admin_comprador($id, ['mensaje' => 'Se cerraron todas sus sesiones.']);
        case 'editar':
            $nombre = limpiar($_POST['nombre'] ?? '', 100);
            $email = strtolower(limpiar($_POST['email'] ?? '', 190));
            $whatsapp = (string) preg_replace('/\D/', '', texto_de($_POST['whatsapp'] ?? null));
            $notas = limpiar($_POST['notas'] ?? '', 1000);
            if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return admin_comprador($id, ['mensaje' => 'Revisa el nombre y el email.']);
            }
            $otro = comprador_por_email($email);
            if ($otro !== null && (int) $otro['id'] !== $cid) {
                return admin_comprador($id, ['mensaje' => 'Ese email ya pertenece a otro comprador (#' . $otro['id'] . ').']);
            }
            db_ejecutar(
                'UPDATE compradores SET nombre = ?, email = ?, whatsapp = ?, notas = ?, actualizado_en = ? WHERE id = ?',
                [$nombre, $email, $whatsapp ?: null, $notas ?: null, ahora_bd(), $cid]
            );
            return admin_comprador($id, ['mensaje' => 'Datos guardados.']);
    }
    return pagina_error(404, 'No encontrado', 'Acción desconocida.');
}

function admin_comprador_enlace(string $id): array
{
    return admin_comprador_accion($id, 'enlace');
}

function admin_comprador_reenviar(string $id): array
{
    return admin_comprador_accion($id, 'reenviar');
}

function admin_comprador_revocar(string $id): array
{
    return admin_comprador_accion($id, 'revocar');
}

function admin_comprador_restaurar(string $id): array
{
    return admin_comprador_accion($id, 'restaurar');
}

function admin_comprador_cerrar_sesiones(string $id): array
{
    return admin_comprador_accion($id, 'cerrar-sesiones');
}

function admin_comprador_editar(string $id): array
{
    return admin_comprador_accion($id, 'editar');
}

/* ---------- Acceso manual (sin venta) ---------- */

function admin_acceso_formulario(array $valores = ['enviar_email' => true], array $errores = []): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    return admin_vista('acceso_nuevo', ['titulo' => 'Dar acceso', 'valores' => $valores, 'errores' => $errores], 'acceso');
}

function admin_acceso_crear(): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    $valores = [
        'nombre' => limpiar($_POST['nombre'] ?? '', 100),
        'email' => strtolower(limpiar($_POST['email'] ?? '', 190)),
        'whatsapp' => (string) preg_replace('/\D/', '', texto_de($_POST['whatsapp'] ?? null)),
        'notas' => limpiar($_POST['notas'] ?? '', 1000),
        'enviar_email' => !empty($_POST['enviar_email']),
    ];
    $errores = [];
    if ($valores['nombre'] === '') {
        $errores['nombre'] = 'Escribe el nombre.';
    }
    if (!filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Revisa el email: no parece válido.';
    }
    if ($errores) {
        return admin_acceso_formulario($valores, $errores);
    }
    $resultado = acceso_manual($valores['nombre'], $valores['email'], $valores['whatsapp'] ?: null, $valores['notas'] ?: null, $valores['enviar_email']);
    registrar('ventas', 'Acceso manual', ['comprador' => $resultado['comprador']['id']]);
    return admin_comprador((string) $resultado['comprador']['id'], [
        'enlace' => $resultado['enlace'],
        'mensaje' => 'Acceso creado.' . ($resultado['email'] === null ? '' : ($resultado['email']['ok'] ? ' Email de acceso enviado.' : ' No se pudo enviar el email: ' . $resultado['email']['error'])),
    ]);
}

/* ---------- Exportar CSV ---------- */

/**
 * Evita que Excel ejecute como fórmula un texto que empieza por = + - @. También revisa lo que va después
 * de cada coma o punto y coma: si el Excel separa las columnas con otro carácter, ese trozo quedaría al
 * inicio de una celda. Se neutraliza con un apóstrofo delante.
 */
function celda_csv_segura(mixed $valor): mixed
{
    return is_string($valor) ? (string) preg_replace('/(^|[,;\t\r\n])(\s*)([=+\-@])/', "$1$2'$3", $valor) : $valor;
}

function admin_exportar(string $tipo): array
{
    if ($r = admin_requerido()) {
        return $r;
    }
    $consultas = [
        'ventas' => 'SELECT v.id, v.creado_en AS fecha, c.nombre, c.email, c.whatsapp, v.monto_centavos / 100.0 AS monto, v.moneda,
                        v.metodo_pago, v.referencia_pago, l.codigo, l.pais, l.utm_source, l.utm_campaign, l.utm_content
                    FROM ventas v JOIN compradores c ON c.id = v.comprador_id LEFT JOIN leads l ON l.id = v.lead_id ORDER BY v.id',
        'leads' => 'SELECT l.id, l.creado_en AS fecha, l.codigo, l.pais, l.boton, l.clics, l.utm_source, l.utm_medium, l.utm_campaign,
                        l.utm_content, l.utm_term,
                        CASE WHEN v.id IS NOT NULL THEN \'sí\' WHEN a.id IS NOT NULL THEN \'por activar\' ELSE \'no\' END AS vendido
                    FROM leads l LEFT JOIN ventas v ON v.lead_id = l.id
                    LEFT JOIN activaciones a ON a.lead_id = l.id AND a.usado_en IS NULL AND a.anulado_en IS NULL ORDER BY l.id',
        'compradores' => 'SELECT c.id, c.creado_en AS fecha, c.nombre, c.email, c.whatsapp, c.notas,
                        CASE WHEN a.id IS NULL OR a.revocado_en IS NOT NULL THEN \'no\' ELSE \'sí\' END AS acceso
                    FROM compradores c LEFT JOIN accesos a ON a.comprador_id = c.id AND a.producto = \'curso\' ORDER BY c.id',
    ];
    if (!isset($consultas[$tipo])) {
        return pagina_error(404, 'No encontrado', 'No se puede exportar eso.');
    }
    $filas = db_filas($consultas[$tipo]);
    // Todos los campos van entre comillas, aunque Excel use "," o ";" como separador
    $linea = fn (array $campos): string =>
        implode(';', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $campos)) . "\r\n";
    $csv = "\xEF\xBB\xBF"; // BOM: Excel reconoce las tildes
    if ($filas) {
        $csv .= $linea(array_keys($filas[0]));
    }
    foreach ($filas as $fila) {
        if (isset($fila['fecha'])) {
            $fila['fecha'] = fecha_local($fila['fecha'], 'Y-m-d H:i');
        }
        $csv .= $linea(array_map('celda_csv_segura', $fila));
    }
    return respuesta($csv, 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $tipo . '-' . gmdate('Y-m-d') . '.csv"',
        'Cache-Control' => 'no-store',
    ]);
}
