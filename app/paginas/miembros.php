<?php
declare(strict_types=1);

/*
 * Área de miembros: entrar con enlace mágico, ver el curso y descargar materiales.
 * Sin una sesión válida (y un acceso vigente) no se ve nada.
 */

// Tope diario de emails de /entrar: deja margen en el plan gratis de Resend (100/día) para los de compra
const MAXIMO_EMAILS_LOGIN_DIA = 60;

function miembro_sesion(): ?array
{
    return sesion_de_token('miembro', $_COOKIE[COOKIE_MIEMBRO] ?? null);
}

/** El comprador con sesión iniciada, o null. */
function miembro_actual(): ?array
{
    $sesion = miembro_sesion();
    return $sesion === null ? null : comprador_por_id((int) $sesion['comprador_id']);
}

function miembro_vista(string $plantilla, array $datos, array $comprador): array
{
    return privada(html(vista('miembros/' . $plantilla, $datos + ['comprador' => $comprador], 'layout_miembros')));
}

/* ---------- Entrar ---------- */

function miembro_entrar_formulario(): array
{
    if (miembro_actual() !== null) {
        return redireccion('/miembros');
    }
    return privada(html(vista('miembros/entrar', ['titulo' => 'Entrar al curso', 'enviado' => false, 'error' => '', 'email' => ''])));
}

/**
 * Pide un enlace para entrar. La respuesta es SIEMPRE la misma, exista o no el email:
 * así nadie puede averiguar quién compró.
 */
function miembro_pedir_enlace(): array
{
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    $email = strtolower(limpiar($_POST['email'] ?? '', 190));
    $datos = ['titulo' => 'Entrar al curso', 'enviado' => false, 'error' => '', 'email' => $email];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $datos['error'] = 'Escribe un email válido.';
        return privada(html(vista('miembros/entrar', $datos), 422));
    }
    if (!limite_permitir('entrar-ip:' . ip_para_limites(ip_cliente()), 10, 900)
        || !limite_permitir('entrar-email:' . $email, 3, 900)
        || !limite_permitir('entrar-email-dia:' . $email, 6, 86400)) {
        $datos['error'] = 'Ya pediste varios enlaces. Espera un rato o revisa tu correo (también spam).';
        return privada(html(vista('miembros/entrar', $datos), 429));
    }
    $comprador = comprador_por_email($email);
    if ($comprador !== null && acceso_vigente((int) $comprador['id'])) {
        if (limite_permitir('emails-login-dia', MAXIMO_EMAILS_LOGIN_DIA, 86400)) {
            // Se envía después de responder: así la respuesta tarda lo mismo exista o no el email
            despues_de_responder(fn () => email_login($comprador, enlace_acceso_crear((int) $comprador['id'], 'login')));
        } else {
            registrar('seguridad', 'Se alcanzó el tope diario de emails de /entrar');
        }
    }
    $datos['enviado'] = true;
    return privada(html(vista('miembros/entrar', $datos)));
}

/** Abrir el enlace mágico solo muestra un botón: no lo consume (los antivirus del correo lo abren solos). */
function miembro_acceso_confirmar(string $token): array
{
    $enlace = enlace_acceso_valido($token);
    $respuesta = html(vista('miembros/acceso', [
        'titulo' => 'Entrar al curso',
        'valido' => $enlace !== null,
        'nombre' => $enlace !== null ? primer_nombre((string) $enlace['nombre']) : '',
        'token' => $token,
    ]), $enlace !== null ? 200 : 410);
    // El token no sale hacia otros sitios en el "Referer". No usar 'no-referrer': con esa política el
    // navegador envía el botón con "Origin: null" y envio_legitimo() lo rechaza.
    $respuesta['cabeceras']['Referrer-Policy'] = 'same-origin';
    return privada($respuesta);
}

function miembro_acceso_usar(string $token): array
{
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    $compradorId = enlace_acceso_usar($token);
    if ($compradorId === null) {
        return miembro_acceso_confirmar($token); // muestra "este enlace ya no sirve"
    }
    comprador_marcar_ingreso($compradorId);
    $sesion = sesion_crear('miembro', $compradorId);
    return con_cookie(redireccion('/miembros'), COOKIE_MIEMBRO, $sesion, DURACION_SESION_MIEMBRO);
}

/* ---------- Activar (pago confirmado por WhatsApp) ---------- */

/**
 * Página del enlace de activación (/activar/K7Q2-M8XP): si el código sirve, pide nombre y email.
 * Sin código (/activar), el formulario incluye el campo para escribirlo.
 */
function miembro_activar_formulario(string $codigo = ''): array
{
    if ($codigo === '') {
        return miembro_activar_vista([]);
    }
    if ($limite = miembro_activar_limitado()) {
        return $limite;
    }
    $normalizado = normalizar_codigo_activacion($codigo);
    if ($normalizado === null || activacion_vigente($normalizado) === null) {
        miembro_activar_contar_fallo();
        // Quien ya activó y vuelve a tocar el enlace de WhatsApp para entrar, va directo al curso
        return miembro_actual() !== null ? redireccion('/miembros') : miembro_activar_vista(['pantalla' => 'no_sirve'], 404);
    }
    return miembro_activar_vista(['codigo' => formatear_codigo_activacion($normalizado), 'con_enlace' => true]);
}

function miembro_activar(): array
{
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    $datos = [
        'codigo' => limpiar($_POST['codigo'] ?? '', 40),
        'nombre' => limpiar($_POST['nombre'] ?? '', 100),
        'email' => strtolower(limpiar($_POST['email'] ?? '', 190)),
        'con_enlace' => !empty($_POST['con_enlace']),
    ];
    $codigo = normalizar_codigo_activacion($datos['codigo']);
    $errores = [];
    if ($codigo === null) {
        $errores['codigo'] = 'El código tiene 8 letras y números, por ejemplo K7Q2-M8XP.';
        $datos['con_enlace'] = false;
    }
    if ($datos['nombre'] === '') {
        $errores['nombre'] = 'Escribe tu nombre.';
    }
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Revisa tu email: no parece válido.';
    }
    if ($errores) {
        return miembro_activar_vista($datos + ['errores' => $errores], 422);
    }
    if ($limite = miembro_activar_limitado()) {
        return $limite;
    }
    $resultado = activacion_canjear((string) $codigo, $datos['nombre'], $datos['email']);
    if ($resultado === null && activacion_recien_usada((string) $codigo, $datos['email'])) {
        return miembro_activar_vista(['pantalla' => 'ya_activado', 'email' => $datos['email']]);
    }
    if ($resultado === null) {
        miembro_activar_contar_fallo();
        $datos['con_enlace'] = false;
        return miembro_activar_vista($datos + ['errores' => [
            'codigo' => 'Ese código no sirve: revisa que esté bien escrito. Si ya lo usaste, entra con tu email en /entrar.',
        ]], 422);
    }
    $comprador = $resultado['comprador'];
    registrar('ventas', 'Acceso activado por el cliente', ['venta' => $resultado['venta']['id'], 'comprador' => $comprador['id']]);
    if ($resultado['cuenta_existente']) {
        // No se abre una cuenta que ya existía: el enlace para entrar le llega a ese email
        return miembro_activar_vista(['pantalla' => 'revisa_tu_correo', 'email' => $comprador['email']]);
    }
    comprador_marcar_ingreso((int) $comprador['id']);
    $sesion = sesion_crear('miembro', (int) $comprador['id']);
    return con_cookie(redireccion('/miembros?bienvenida=1'), COOKIE_MIEMBRO, $sesion, DURACION_SESION_MIEMBRO);
}

/**
 * Freno a quien pruebe códigos al azar: 10 intentos por IP cada 15 minutos y, entre todos,
 * 100 códigos equivocados por hora. Devuelve la página de "demasiados intentos" o null.
 */
function miembro_activar_limitado(): ?array
{
    $ip = ip_cliente();
    if (limite_permitir('activar-ip:' . ip_para_limites($ip), 10, 900) && !limite_alcanzado('activar-fallos', 100, 3600)) {
        return null;
    }
    registrar('seguridad', 'Demasiados intentos de activación', ['ip' => $ip]);
    return miembro_activar_vista(['pantalla' => 'limitado'], 429);
}

function miembro_activar_contar_fallo(): void
{
    limite_permitir('activar-fallos', 100, 3600);
}

function miembro_activar_vista(array $datos, int $estado = 200): array
{
    $respuesta = html(vista('miembros/activar', $datos + [
        'titulo' => 'Activa tu acceso',
        'pantalla' => 'formulario',
        'codigo' => '',
        'con_enlace' => false,
        'nombre' => '',
        'email' => '',
        'errores' => [],
    ]), $estado);
    // El código no sale hacia otros sitios en el "Referer" (con 'no-referrer' el formulario se rechazaría)
    $respuesta['cabeceras']['Referrer-Policy'] = 'same-origin';
    return privada($respuesta);
}

function miembro_salir(): array
{
    if ($rechazo = rechazar_si_no_legitimo()) {
        return $rechazo;
    }
    sesion_cerrar($_COOKIE[COOKIE_MIEMBRO] ?? null);
    return sin_cookie(redireccion('/entrar'), COOKIE_MIEMBRO);
}

/* ---------- Curso ---------- */

function miembro_inicio(): array
{
    $comprador = miembro_actual();
    if ($comprador === null) {
        return redireccion('/entrar');
    }
    $curso = curso();
    $vistas = lecciones_vistas((int) $comprador['id']);
    $siguiente = null;
    foreach ($curso['lista'] as $leccion) {
        if (!isset($vistas[$leccion['slug']])) {
            $siguiente = $leccion;
            break;
        }
    }
    return miembro_vista('inicio', [
        'titulo' => contenido('negocio')['producto'],
        'curso' => $curso,
        'vistas' => $vistas,
        'siguiente' => $siguiente,
        'panel' => contenido('negocio')['smmclixy'],
        'bienvenida' => isset($_GET['bienvenida']), // recién activó su acceso
    ], $comprador);
}

function miembro_leccion(string $slug): array
{
    $comprador = miembro_actual();
    if ($comprador === null) {
        return redireccion('/entrar');
    }
    $leccion = leccion($slug);
    if ($leccion === null) {
        return pagina_error(404, 'Lección no encontrada', 'Esa lección no existe. Vuelve al inicio del curso.');
    }
    marcar_leccion_vista((int) $comprador['id'], $slug);
    $video = video_incrustado((string) $leccion['video']);
    if ($video !== null) {
        csp_permitir('frame-src', $video['origen']);
    }
    return miembro_vista('leccion', [
        'titulo' => $leccion['titulo'],
        'leccion' => $leccion,
        'video' => $video,
        'html' => leccion_html($slug),
        'descargas' => descargas_de($leccion),
    ], $comprador);
}

/** Entrega un descargable solo a miembros con sesión; el archivo vive fuera de public_html. */
function miembro_descargar(string $id): array
{
    if (miembro_actual() === null) {
        return redireccion('/entrar');
    }
    $ruta = descargable_ruta($id);
    if ($ruta === null) {
        return pagina_error(404, 'Archivo no encontrado', 'Ese archivo no existe.');
    }
    $nombre = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', basename($ruta));
    $tipo = function_exists('mime_content_type') ? (mime_content_type($ruta) ?: 'application/octet-stream') : 'application/octet-stream';
    return privada(respuesta((string) file_get_contents($ruta), 200, [
        'Content-Type' => $tipo,
        'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        'Content-Length' => (string) filesize($ruta),
    ]));
}
