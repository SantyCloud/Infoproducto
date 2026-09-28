<?php
declare(strict_types=1);

/*
 * Área de miembros: entrar con enlace mágico, ver el curso y descargar materiales.
 * Sin una sesión válida (y un acceso vigente) no se ve nada.
 */

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
    $ip = ip_cliente();
    if (!limite_permitir('entrar-ip:' . $ip, 10, 900) || !limite_permitir('entrar-email:' . $email, 3, 900)) {
        $datos['error'] = 'Ya pediste varios enlaces. Espera 15 minutos o revisa tu correo (también spam).';
        return privada(html(vista('miembros/entrar', $datos), 429));
    }
    $comprador = comprador_por_email($email);
    if ($comprador !== null && acceso_vigente((int) $comprador['id'])) {
        email_login($comprador, enlace_acceso_crear((int) $comprador['id'], 'login'));
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
    $respuesta['cabeceras']['Referrer-Policy'] = 'no-referrer'; // el token no sale en el "Referer"
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
    $sesion = sesion_crear('miembro', $compradorId);
    return con_cookie(redireccion('/miembros'), COOKIE_MIEMBRO, $sesion, DURACION_SESION_MIEMBRO);
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
