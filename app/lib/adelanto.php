<?php
declare(strict_types=1);

/*
 * Adelanto del curso: un video corto del dueño en la landing, debajo de "Cómo empecé".
 *
 * El video original (el del celular, del tamaño que sea) se prepara con bin/optimizar-video.php, que usa
 * ffmpeg. Hostinger no lo tiene: se ejecuta en una computadora y lo que crea se sube a Git, en
 * public_html/assets/video/:
 *   adelanto.mp4         completo y con sonido (lado corto de 720 px: nítido en el celular y liviano)
 *   adelanto-previa.mp4  unos segundos sin sonido, que se repiten al pasar el mouse o al verlo en pantalla
 *   adelanto.webp        la portada, antes de reproducir
 *   adelanto.json        medidas, duración y versión (la versión va en la URL: la caché nunca sirve uno viejo)
 * Mientras no exista adelanto.json, la landing no muestra esta parte.
 */

const ADELANTO_LADO_VIDEO = 720;
const ADELANTO_LADO_PREVIA = 480;
const ADELANTO_LADO_PORTADA = 540;
const ADELANTO_SEGUNDOS_PREVIA = 6;
const ADELANTO_ARCHIVOS = ['video' => 'adelanto.mp4', 'previa' => 'adelanto-previa.mp4', 'portada' => 'adelanto.webp'];

function carpeta_adelanto(): string
{
    return RAIZ . '/public_html/assets/video';
}

/**
 * El adelanto para la landing, o null si no hay:
 * ['video', 'previa', 'portada' (URLs), 'ancho', 'alto', 'duracion' ("0:45"), 'vertical' (bool)].
 */
function adelanto(?string $carpeta = null): ?array
{
    $carpeta ??= carpeta_adelanto();
    foreach ([...ADELANTO_ARCHIVOS, 'adelanto.json'] as $archivo) {
        if (!is_file("$carpeta/$archivo")) {
            return null; // sin video, o subido a medias: mejor no mostrar nada que un video roto
        }
    }
    $indice = json_decode((string) file_get_contents("$carpeta/adelanto.json"), true);
    return is_array($indice) ? adelanto_desde_indice($indice) : null;
}

/** Lo que necesita la landing a partir de adelanto.json (separado para probarlo sin archivos). */
function adelanto_desde_indice(array $indice): ?array
{
    $ancho = (int) ($indice['ancho'] ?? 0);
    $alto = (int) ($indice['alto'] ?? 0);
    if ($ancho <= 0 || $alto <= 0) {
        return null;
    }
    $version = (string) preg_replace('/[^a-z0-9]/i', '', (string) ($indice['version'] ?? '')) ?: '0';
    $url = fn (string $archivo): string => "/assets/video/$archivo?v=$version";
    return [
        'video' => $url(ADELANTO_ARCHIVOS['video']),
        'previa' => $url(ADELANTO_ARCHIVOS['previa']),
        'portada' => $url(ADELANTO_ARCHIVOS['portada']),
        'ancho' => $ancho,
        'alto' => $alto,
        'duracion' => duracion_legible((float) ($indice['duracion'] ?? 0)),
        'vertical' => $alto > $ancho,
    ];
}

/** 45.3 → "0:45" · 75 → "1:15" */
function duracion_legible(float $segundos): string
{
    $total = max(0, (int) round($segundos));
    return intdiv($total, 60) . ':' . str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT);
}

/**
 * Prepara el adelanto a partir del video original y devuelve lo que hizo (para la consola).
 * $desde: segundo en que empiezan la vista previa sin sonido y la portada (un momento que dé curiosidad).
 * Lanza RuntimeException si falta ffmpeg o no puede leer el video; en ese caso el adelanto anterior queda igual.
 */
function optimizar_adelanto(string $original, float $desde = 1.0, ?string $destino = null): array
{
    $destino ??= carpeta_adelanto();
    if (!is_file($original)) {
        throw new RuntimeException("No encuentro el video: $original");
    }
    foreach (['ffmpeg', 'ffprobe'] as $programa) {
        if (ejecutar_programa([$programa, '-version'])[0] !== 0) {
            throw new RuntimeException("Falta $programa (ffmpeg.org). Instálalo, o pídele a Claude que prepare el video.");
        }
    }
    $duracion = medidas_de_video($original)['duracion'] ?? 0.0;
    if ($duracion <= 0) {
        throw new RuntimeException('No pude leer el video: ¿es un archivo de video?');
    }
    $desde = max(0.0, min($desde, $duracion - ADELANTO_SEGUNDOS_PREVIA));
    if (!is_dir($destino)) {
        mkdir($destino, 0775, true);
    }

    // Primero en archivos temporales (con punto: la web no los sirve). Si algo falla, el adelanto anterior sigue igual.
    $nuevos = [];
    foreach (ADELANTO_ARCHIVOS as $clave => $archivo) {
        $nuevos[$clave] = "$destino/.nuevo-$archivo";
    }
    // Sin metadatos: los videos del celular guardan la ubicación GPS y el modelo del teléfono
    $sinDatos = ['-map_metadata', '-1', '-map_chapters', '-1'];
    $h264 = ['-c:v', 'libx264', '-preset', 'slow', '-pix_fmt', 'yuv420p', '-fpsmax', '30', '-movflags', '+faststart'];
    try {
        ejecutar_ffmpeg(['-i', $original, '-map', '0:v:0', '-map', '0:a:0?', '-vf', escala_lado_corto(ADELANTO_LADO_VIDEO),
            ...$h264, '-crf', '27', '-maxrate', '2M', '-bufsize', '4M', '-c:a', 'aac', '-b:a', '96k', '-ac', '2',
            ...$sinDatos, $nuevos['video']]);
        ejecutar_ffmpeg(['-ss', (string) $desde, '-t', (string) ADELANTO_SEGUNDOS_PREVIA, '-i', $original, '-map', '0:v:0', '-an',
            '-vf', escala_lado_corto(ADELANTO_LADO_PREVIA), ...$h264, '-crf', '30', '-maxrate', '1M', '-bufsize', '2M',
            ...$sinDatos, $nuevos['previa']]);
        ejecutar_ffmpeg(['-ss', (string) $desde, '-i', $original, '-frames:v', '1', '-vf', escala_lado_corto(ADELANTO_LADO_PORTADA),
            '-c:v', 'libwebp', '-quality', '80', ...$sinDatos, $nuevos['portada']]);

        $medidas = medidas_de_video($nuevos['video']); // ya girado como se ve en el celular
        if (($medidas['ancho'] ?? 0) <= 0) {
            throw new RuntimeException('ffmpeg no generó un video válido.');
        }
        $segundosPrevia = (int) round(medidas_de_video($nuevos['previa'])['duracion'] ?? 0);
        $huellas = array_map(fn (string $archivo): string => (string) md5_file($archivo), $nuevos);
        foreach ($nuevos as $clave => $archivo) {
            rename($archivo, "$destino/" . ADELANTO_ARCHIVOS[$clave]);
        }
    } finally {
        foreach ($nuevos as $archivo) {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }
    }
    // El índice va al final: la landing solo muestra el adelanto cuando todo está listo
    file_put_contents("$destino/adelanto.json", json_encode([
        'ancho' => $medidas['ancho'],
        'alto' => $medidas['alto'],
        'duracion' => round($medidas['duracion'], 2),
        'desde' => $desde,
        'version' => substr(md5(implode('', $huellas)), 0, 10),
    ], JSON_PRETTY_PRINT) . "\n");

    $pesos = array_map(fn (string $archivo): int => (int) filesize("$destino/$archivo"), ADELANTO_ARCHIVOS);
    $mensajes = [
        sprintf('✓ Video completo: %s (%dx%d, %s, %s)', ADELANTO_ARCHIVOS['video'], $medidas['ancho'], $medidas['alto'],
            duracion_legible($medidas['duracion']), tamano_legible($pesos['video'])),
        sprintf('✓ Vista previa sin sonido: %s (%d s desde el segundo %s, %s)', ADELANTO_ARCHIVOS['previa'],
            $segundosPrevia, rtrim(rtrim(number_format($desde, 1, '.', ''), '0'), '.'), tamano_legible($pesos['previa'])),
        sprintf('✓ Portada: %s (%s)', ADELANTO_ARCHIVOS['portada'], tamano_legible($pesos['portada'])),
    ];
    if ($pesos['video'] > 15 * 1024 * 1024) {
        $mensajes[] = '⚠ El video pesa mucho para verlo con datos del celular: mejor uno más corto (de 30 a 60 segundos).';
    }
    return $mensajes;
}

/** Quita el adelanto de la landing (borra sus archivos). */
function quitar_adelanto(?string $carpeta = null): void
{
    $carpeta ??= carpeta_adelanto();
    foreach ([...ADELANTO_ARCHIVOS, 'adelanto.json'] as $archivo) {
        if (is_file("$carpeta/$archivo")) {
            unlink("$carpeta/$archivo");
        }
    }
}

/** Filtro de ffmpeg: el lado corto a $lado píxeles (sin agrandar videos más chicos) y medidas pares. */
function escala_lado_corto(int $lado): string
{
    return "scale='if(gt(iw,ih),-2,min($lado,trunc(iw/2)*2))':'if(gt(iw,ih),min($lado,trunc(ih/2)*2),-2)',setsar=1";
}

/** ['ancho', 'alto', 'duracion', 'audio' (bool)] de un video, o [] si ffprobe no lo pudo leer. */
function medidas_de_video(string $archivo): array
{
    [$codigo, $salida] = ejecutar_programa(['ffprobe', '-v', 'error', '-show_entries',
        'stream=codec_type,width,height:format=duration', '-of', 'json', $archivo]);
    $datos = $codigo === 0 ? json_decode($salida, true) : null;
    if (!is_array($datos)) {
        return [];
    }
    $video = null;
    $audio = false;
    foreach ($datos['streams'] ?? [] as $pista) {
        $video ??= ($pista['codec_type'] ?? '') === 'video' ? $pista : null;
        $audio = $audio || ($pista['codec_type'] ?? '') === 'audio';
    }
    return [
        'ancho' => (int) ($video['width'] ?? 0),
        'alto' => (int) ($video['height'] ?? 0),
        'duracion' => (float) ($datos['format']['duration'] ?? 0),
        'audio' => $audio,
    ];
}

function ejecutar_ffmpeg(array $argumentos): void
{
    [$codigo, , $errores] = ejecutar_programa(['ffmpeg', '-y', '-nostdin', '-v', 'error', ...$argumentos]);
    if ($codigo !== 0) {
        throw new RuntimeException('ffmpeg no pudo preparar el video: ' . trim(substr($errores, -600)));
    }
}

/** Ejecuta un programa sin pasar por la consola (no hay nada que escapar). Devuelve [código, salida, errores]. */
function ejecutar_programa(array $comando): array
{
    $proceso = @proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
    if (!is_resource($proceso)) {
        return [127, '', 'No se pudo ejecutar ' . $comando[0]];
    }
    $salida = (string) stream_get_contents($tubos[1]);
    $errores = (string) stream_get_contents($tubos[2]);
    fclose($tubos[1]);
    fclose($tubos[2]);
    return [proc_close($proceso), $salida, $errores];
}

/** 6500000 → "6,2 MB" · 38000 → "37 KB" */
function tamano_legible(int $bytes): string
{
    return $bytes >= 1024 * 1024
        ? number_format($bytes / 1024 / 1024, 1, ',', '.') . ' MB'
        : max(1, (int) round($bytes / 1024)) . ' KB';
}
