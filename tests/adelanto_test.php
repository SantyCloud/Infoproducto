<?php
declare(strict_types=1);

/*
 * Adelanto del curso: el video corto del dueño en la landing (app/lib/adelanto.php, bin/optimizar-video.php).
 */

/** La landing con un adelanto de ejemplo (sin archivos: solo lo que ve la plantilla), sin su CSS en línea. */
function landing_con_adelanto(?array $adelanto, bool $huecos = false): string
{
    $datos = datos_landing(contenido('negocio'), contenido('landing'));
    $datos['adelanto'] = $adelanto;
    $datos['mostrar_huecos'] = $huecos;
    return (string) preg_replace('#<style[^>]*>.*?</style>#s', '', vista('landing', $datos, 'layout_landing'));
}

/** Carpeta temporal vacía para las pruebas con archivos. */
function carpeta_de_prueba(string $nombre): string
{
    $carpeta = sys_get_temp_dir() . "/$nombre-" . getmypid();
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0775, true);
    }
    array_map('unlink', [...(glob("$carpeta/*") ?: []), ...(glob("$carpeta/.nuevo-*") ?: [])]);
    return $carpeta;
}

prueba('sin video no se muestra el adelanto; en local se ve dónde irá', function () {
    $html = landing_con_adelanto(null);
    afirmar(!str_contains($html, 'adelanto'), 'Sin video, la landing no lo menciona.');
    afirmar_contiene('id="historia"', $html, 'La historia sigue igual.');
    afirmar_contiene('adelanto-hueco', landing_con_adelanto(null, huecos: true), 'En local, un hueco muestra dónde irá.');
});

prueba('con video: portada liviana, vista previa, enlace al video completo y su duración', function () {
    $adelanto = adelanto_desde_indice(['ancho' => 720, 'alto' => 1280, 'duracion' => 45.4, 'version' => 'abc123']);
    $html = landing_con_adelanto($adelanto);
    afirmar_contiene('href="/assets/video/adelanto.mp4?v=abc123"', $html, 'Sin JavaScript, el enlace abre el video.');
    afirmar_contiene('data-previa="/assets/video/adelanto-previa.mp4?v=abc123"', $html);
    afirmar_contiene('src="/assets/video/adelanto.webp?v=abc123" width="720"', $html);
    afirmar_contiene('height="1280" alt="" loading="lazy"', $html, 'La portada se carga cuando hace falta.');
    afirmar(!str_contains($html, '<video'), 'Nada del video se descarga al abrir la página (lo agrega landing.js).');
    afirmar_contiene('Mira un adelanto · 0:45', $html);
    afirmar_contiene('Dale play para escucharme', $html);
    afirmar_contiene('aria-label="Ver el adelanto del curso con sonido (0:45)"', $html);
    afirmar_contiene('adelanto--vertical', $html);
    afirmar_contiene('historia--al-lado', $html, 'Si es vertical, en la computadora va al lado de la historia.');
    afirmar(strpos($html, 'id="historia"') < strpos($html, 'class="adelanto'), 'Va con la historia, lejos de la primera pantalla.');

    $html = landing_con_adelanto(adelanto_desde_indice(['ancho' => 1280, 'alto' => 720, 'duracion' => 30, 'version' => 'x']));
    afirmar(!str_contains($html, 'adelanto--vertical') && !str_contains($html, 'historia--al-lado'), 'Horizontal: debajo de la historia.');
});

prueba('la duración y la versión del adelanto se leen bien, y un índice roto no lo muestra', function () {
    afirmar_igual(['0:00', '0:09', '0:45', '1:15', '10:00'], array_map('duracion_legible', [0.2, 9.4, 45.4, 75.0, 599.6]));
    afirmar_igual('/assets/video/adelanto.mp4?v=abc', adelanto_desde_indice(['ancho' => 1, 'alto' => 2, 'version' => 'a/b"c'])['video']);
    afirmar_igual('/assets/video/adelanto.mp4?v=0', adelanto_desde_indice(['ancho' => 1, 'alto' => 2])['video']);
    afirmar_igual(null, adelanto_desde_indice(['ancho' => 0, 'alto' => 720]));

    $carpeta = carpeta_de_prueba('adelanto-indice');
    file_put_contents("$carpeta/adelanto.json", '{"ancho":720,"alto":1280,"duracion":20,"version":"v1"}');
    afirmar_igual(null, adelanto($carpeta), 'Sin sus archivos (subida a medias) no se muestra.');
    foreach (ADELANTO_ARCHIVOS as $archivo) {
        file_put_contents("$carpeta/$archivo", 'x');
    }
    afirmar_igual('0:20', adelanto($carpeta)['duracion'] ?? null);
    file_put_contents("$carpeta/adelanto.json", '{roto');
    afirmar_igual(null, adelanto($carpeta));
    quitar_adelanto($carpeta);
    afirmar_igual([], glob("$carpeta/*") ?: [], 'Quitarlo borra sus archivos.');
    rmdir($carpeta);
});

prueba('el optimizador prepara el video: 720 px, 30 cuadros, vista previa sin sonido y sin la ubicación del celular', function () {
    $carpeta = carpeta_de_prueba('adelanto-video');
    if (ejecutar_programa(['ffmpeg', '-version'])[0] !== 0) {
        // Sin ffmpeg (Hostinger, algunas computadoras): lo dice claro, sin tocar nada
        $error = afirmar_falla(fn () => optimizar_adelanto(__FILE__, 1, $carpeta));
        afirmar_contiene('Falta ffmpeg', $error->getMessage());
        afirmar_igual(null, adelanto($carpeta));
        rmdir($carpeta);
        return;
    }
    // Un video "de celular": vertical, 60 cuadros por segundo, con sonido y con la ubicación en los metadatos
    $original = "$carpeta/original.mp4";
    [$codigo, , $errores] = ejecutar_programa(['ffmpeg', '-y', '-nostdin', '-v', 'error',
        '-f', 'lavfi', '-i', 'testsrc2=size=1080x1920:rate=60', '-f', 'lavfi', '-i', 'sine=frequency=440',
        '-t', '2', '-c:v', 'libx264', '-preset', 'ultrafast', '-pix_fmt', 'yuv420p', '-c:a', 'aac',
        '-metadata', 'location=-0.1807-078.4678/', $original]);
    afirmar_igual(0, $codigo, $errores);

    $mensajes = optimizar_adelanto($original, 0.5, $carpeta);
    afirmar_contiene('720x1280', $mensajes[0]);
    $adelanto = adelanto($carpeta);
    afirmar(($adelanto['vertical'] ?? false) && $adelanto['duracion'] === '0:02', json_encode($adelanto));
    $video = medidas_de_video("$carpeta/adelanto.mp4");
    afirmar_igual([720, 1280, true], [$video['ancho'], $video['alto'], $video['audio']]);
    $previa = medidas_de_video("$carpeta/adelanto-previa.mp4");
    afirmar_igual([480, 854, false], [$previa['ancho'], $previa['alto'], $previa['audio']], 'La vista previa va sin sonido.');
    [, $cuadros] = ejecutar_programa(['ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=r_frame_rate',
        '-of', 'csv=p=0', "$carpeta/adelanto.mp4"]);
    afirmar_igual('30/1', trim($cuadros), 'Máximo 30 cuadros por segundo.');
    [, $etiquetas] = ejecutar_programa(['ffprobe', '-v', 'error', '-show_entries', 'format_tags', '-of', 'json', "$carpeta/adelanto.mp4"]);
    afirmar(!str_contains($etiquetas, 'location') && !str_contains($etiquetas, '078.4678'), 'Sin la ubicación GPS del celular.');

    // Si lo que llega no sirve (un archivo cualquiera, o una nota de voz sin imagen), el adelanto que ya estaba
    // queda igual y no quedan archivos a medias
    $antes = array_map(fn (string $archivo): string => (string) md5_file("$carpeta/$archivo"), [...ADELANTO_ARCHIVOS, 'adelanto.json']);
    file_put_contents("$carpeta/roto.mp4", 'esto no es un video');
    afirmar_contiene('No pude leer el video', afirmar_falla(fn () => optimizar_adelanto("$carpeta/roto.mp4", 1, $carpeta))->getMessage());
    ejecutar_programa(['ffmpeg', '-y', '-nostdin', '-v', 'error', '-f', 'lavfi', '-i', 'sine', '-t', '1', "$carpeta/nota-de-voz.m4a"]);
    afirmar_contiene('ffmpeg no pudo', afirmar_falla(fn () => optimizar_adelanto("$carpeta/nota-de-voz.m4a", 0, $carpeta))->getMessage());
    afirmar_igual($antes, array_map(fn (string $archivo): string => (string) md5_file("$carpeta/$archivo"), [...ADELANTO_ARCHIVOS, 'adelanto.json']));
    afirmar_igual([], glob("$carpeta/.nuevo-*") ?: [], 'No quedan archivos temporales.');

    array_map('unlink', glob("$carpeta/*") ?: []);
    rmdir($carpeta);
});
