<?php
declare(strict_types=1);

/*
 * Área de miembros: enlaces mágicos, sesiones, curso y descargables.
 */

function comprador_con_acceso(string $email = 'ana@correo.com'): array
{
    $comprador = comprador_guardar('Ana López', $email, '593991112233');
    acceso_otorgar((int) $comprador['id'], null);
    return $comprador;
}

prueba('el enlace mágico sirve una sola vez', function () {
    bd_de_prueba();
    $comprador = comprador_con_acceso();
    $token = basename(enlace_acceso_crear((int) $comprador['id']));
    afirmar(enlace_acceso_valido($token) !== null, 'Abrirlo (GET) no lo consume.');
    afirmar(enlace_acceso_valido($token) !== null);
    afirmar_igual((int) $comprador['id'], enlace_acceso_usar($token));
    afirmar_igual(null, enlace_acceso_usar($token), 'La segunda vez ya no sirve.');
});

prueba('el enlace vence, y al crear uno nuevo el anterior deja de servir', function () {
    bd_de_prueba();
    $comprador = comprador_con_acceso();
    $viejo = basename(enlace_acceso_crear((int) $comprador['id']));
    $nuevo = basename(enlace_acceso_crear((int) $comprador['id']));
    afirmar_igual(null, enlace_acceso_valido($viejo), 'Solo sirve el último enlace.');
    db_ejecutar('UPDATE tokens_login SET expira_en = ?', [gmdate('Y-m-d H:i:s', time() - 1)]);
    afirmar_igual(null, enlace_acceso_valido($nuevo), 'Un enlace vencido no sirve.');
});

prueba('se guarda el hash del enlace, nunca el enlace', function () {
    bd_de_prueba();
    $comprador = comprador_con_acceso();
    $token = basename(enlace_acceso_crear((int) $comprador['id']));
    afirmar_igual(0, (int) db_valor('SELECT COUNT(*) FROM tokens_login WHERE token_hash = ?', [$token]));
    afirmar_igual(1, (int) db_valor('SELECT COUNT(*) FROM tokens_login WHERE token_hash = ?', [hash_token($token)]));
});

prueba('como máximo 3 dispositivos: el cuarto cierra la sesión más antigua', function () {
    bd_de_prueba();
    $id = (int) comprador_con_acceso()['id'];
    $sesiones = [];
    for ($i = 0; $i < 4; $i++) {
        $sesiones[] = sesion_crear('miembro', $id);
    }
    afirmar_igual(null, sesion_de_token('miembro', $sesiones[0]), 'La primera se cerró.');
    afirmar(sesion_de_token('miembro', $sesiones[3]) !== null);
    afirmar_igual(3, (int) db_valor("SELECT COUNT(*) FROM sesiones WHERE comprador_id = ?", [$id]));
});

prueba('una sesión de miembro no sirve como sesión de admin (ni al revés)', function () {
    bd_de_prueba();
    $miembro = sesion_crear('miembro', (int) comprador_con_acceso()['id']);
    $admin = sesion_crear('admin', null);
    afirmar_igual(null, sesion_de_token('admin', $miembro));
    afirmar_igual(null, sesion_de_token('miembro', $admin));
    afirmar_igual(null, sesion_de_token('miembro', 'inventado'));
});

prueba('pedir enlace: misma respuesta exista o no el email (no revela quién compró)', function () {
    bd_de_prueba();
    comprador_con_acceso();
    post_legitimo(['email' => 'ana@correo.com']);
    $existe = miembro_pedir_enlace();
    post_legitimo(['email' => 'nadie@correo.com']);
    $noExiste = miembro_pedir_enlace();
    afirmar_igual($existe['estado'], $noExiste['estado']);
    afirmar_igual(
        str_replace('ana@correo.com', 'X', strip_tags($existe['cuerpo'])),
        str_replace('nadie@correo.com', 'X', strip_tags($noExiste['cuerpo']))
    );
    afirmar_igual(1, (int) db_valor("SELECT COUNT(*) FROM emails WHERE tipo = 'login'"), 'Solo se envía email a quien tiene acceso.');
});

prueba('pedir enlace tiene límite por email', function () {
    bd_de_prueba();
    comprador_con_acceso();
    for ($i = 0; $i < 3; $i++) {
        post_legitimo(['email' => 'ana@correo.com']);
        afirmar_igual(200, miembro_pedir_enlace()['estado']);
    }
    post_legitimo(['email' => 'ana@correo.com']);
    afirmar_igual(429, miembro_pedir_enlace()['estado']);
});

prueba('sin sesión, el área de miembros y los descargables redirigen a /entrar', function () {
    bd_de_prueba();
    simular_peticion();
    foreach ([miembro_inicio(), miembro_leccion('bienvenida'), miembro_descargar('lista-de-precios')] as $respuesta) {
        afirmar_igual(302, $respuesta['estado']);
        afirmar_igual('/entrar', $respuesta['cabeceras']['Location']);
    }
});

prueba('con sesión se ve el curso, se marca el progreso y se descarga el material', function () {
    bd_de_prueba();
    $comprador = comprador_con_acceso();
    simular_peticion([], [COOKIE_MIEMBRO => sesion_crear('miembro', (int) $comprador['id'])]);
    $inicio = miembro_inicio();
    afirmar_igual(200, $inicio['estado']);
    afirmar_contiene('Hola, Ana', $inicio['cuerpo']);
    afirmar_contiene('utm_source=curso&amp;utm_medium=miembros', $inicio['cuerpo'], 'El paso 1 lleva a smmclixy marcado.');
    afirmar_igual(200, miembro_leccion('bienvenida')['estado']);
    afirmar_igual(['bienvenida' => true], lecciones_vistas((int) $comprador['id']));
    afirmar_igual(404, miembro_leccion('no-existe')['estado']);
    $descarga = miembro_descargar('lista-de-precios');
    afirmar_igual(200, $descarga['estado']);
    afirmar_contiene('attachment; filename="lista-de-precios.csv"', $descarga['cabeceras']['Content-Disposition']);
    afirmar_igual(404, miembro_descargar('no-existe')['estado']);
});

prueba('los descargables no permiten salir de su carpeta', function () {
    afirmar_igual(null, descargable_ruta('../../.env'));
    afirmar_igual(null, descargable_ruta('inexistente'));
    afirmar(descargable_ruta('lista-de-precios') !== null);
});

prueba('reconoce enlaces de YouTube, Drive y Vimeo para incrustar el video', function () {
    $casos = [
        'https://youtu.be/dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1',
        'https://youtube.com/shorts/dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1',
        'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOp/view?usp=sharing' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOp/preview',
        'https://vimeo.com/123456789' => 'https://player.vimeo.com/video/123456789',
    ];
    foreach ($casos as $enlace => $esperado) {
        afirmar_igual($esperado, video_incrustado($enlace)['url'] ?? null, $enlace);
    }
    afirmar_igual(null, video_incrustado(''));
    afirmar_igual(null, video_incrustado('https://sitio-raro.com/video'));
});

prueba('la lección con video autoriza solo ese origen en la CSP', function () {
    csp_reiniciar();
    afirmar_contiene("frame-src 'none'", politica_csp(), 'Sin video, la CSP no permite ningún marco.');
    csp_permitir('frame-src', 'https://www.youtube-nocookie.com');
    afirmar_contiene('frame-src https://www.youtube-nocookie.com', politica_csp());
    afirmar(!str_contains(politica_csp(), "frame-src 'none'"));
    csp_reiniciar();
});
