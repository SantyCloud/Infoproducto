<?php
declare(strict_types=1);

/*
 * Curso del área de miembros: módulos, lecciones, videos y descargables.
 *
 * Se lee de storage/curso/ si existe (contenido privado, fuera de Git) o, si no, de
 * contenido/curso/. Cada carpeta tiene:
 *   curso.php         módulos, lecciones y lista de descargables
 *   lecciones/*.md    texto de cada lección (se llama igual que su "slug")
 *   descargables/     archivos para descargar (PDF, plantillas…)
 */

function carpeta_curso(): string
{
    $privada = RAIZ . '/storage/curso';
    return is_file("$privada/curso.php") ? $privada : RAIZ . '/contenido/curso';
}

/** El curso completo, con cada lección numerada y enlazada con la anterior y la siguiente. */
function curso(bool $recargar = false): array
{
    static $curso = null;
    if ($curso !== null && !$recargar) {
        return $curso;
    }
    $datos = require carpeta_curso() . '/curso.php';
    $lista = [];
    foreach ($datos['modulos'] as $m => $modulo) {
        foreach ($modulo['lecciones'] as $l => $leccion) {
            if (!preg_match('/^[a-z0-9-]+$/', (string) $leccion['slug'])) {
                throw new RuntimeException("Slug de lección no válido en curso.php: {$leccion['slug']} (usa minúsculas, números y guiones).");
            }
            $leccion += ['duracion' => '', 'video' => '', 'descargas' => []];
            $leccion['modulo'] = $modulo['titulo'];
            $leccion['numero'] = count($lista) + 1;
            $datos['modulos'][$m]['lecciones'][$l] = $leccion;
            $lista[] = $leccion;
        }
    }
    $datos['lista'] = $lista;
    $datos['descargables'] ??= [];
    return $curso = $datos;
}

/** Una lección por su slug, con 'anterior' y 'siguiente' (o null). */
function leccion(string $slug): ?array
{
    $lista = curso()['lista'];
    foreach ($lista as $i => $leccion) {
        if ($leccion['slug'] === $slug) {
            return $leccion + ['anterior' => $lista[$i - 1] ?? null, 'siguiente' => $lista[$i + 1] ?? null];
        }
    }
    return null;
}

/** Texto de la lección (lecciones/{slug}.md) convertido a HTML seguro. */
function leccion_html(string $slug): string
{
    $archivo = carpeta_curso() . '/lecciones/' . $slug . '.md';
    return is_file($archivo) ? markdown(texto((string) file_get_contents($archivo))) : '';
}

/**
 * Convierte el enlace de un video (YouTube, Google Drive o Vimeo) en la dirección para
 * incrustarlo. Devuelve ['url' => …, 'origen' => …] o null si no se reconoce.
 */
function video_incrustado(string $enlace): ?array
{
    $enlace = trim($enlace);
    if ($enlace === '') {
        return null;
    }
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $enlace, $m)
        || preg_match('~^([A-Za-z0-9_-]{11})$~', $enlace, $m)) {
        return ['url' => "https://www.youtube-nocookie.com/embed/{$m[1]}?rel=0&modestbranding=1", 'origen' => 'https://www.youtube-nocookie.com'];
    }
    if (preg_match('~drive\.google\.com/(?:file/d/|open\?id=)([A-Za-z0-9_-]{10,})~', $enlace, $m)) {
        return ['url' => "https://drive.google.com/file/d/{$m[1]}/preview", 'origen' => 'https://drive.google.com'];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d{6,12})~', $enlace, $m)) {
        return ['url' => "https://player.vimeo.com/video/{$m[1]}", 'origen' => 'https://player.vimeo.com'];
    }
    return null;
}

/** Descargables de una lección: [id => ['nombre', 'archivo']] (solo los que existen). */
function descargas_de(array $leccion): array
{
    $todos = curso()['descargables'];
    $lista = [];
    foreach ($leccion['descargas'] as $id) {
        if (isset($todos[$id]) && descargable_ruta($id) !== null) {
            $lista[$id] = $todos[$id];
        }
    }
    return $lista;
}

/**
 * Ruta del archivo de un descargable, o null. Solo se entregan archivos que figuran en la
 * lista de curso.php: la URL nunca contiene nombres de archivo ni rutas.
 */
function descargable_ruta(string $id): ?string
{
    $datos = curso()['descargables'][$id] ?? null;
    if ($datos === null) {
        return null;
    }
    $carpeta = realpath(carpeta_curso() . '/descargables');
    $ruta = $carpeta ? realpath($carpeta . '/' . basename((string) $datos['archivo'])) : false;
    return $ruta !== false && str_starts_with($ruta, $carpeta . DIRECTORY_SEPARATOR) && is_file($ruta) ? $ruta : null;
}

/** Lecciones que el comprador ya abrió: [slug => true]. */
function lecciones_vistas(int $compradorId): array
{
    $vistas = db_filas('SELECT leccion FROM progreso WHERE comprador_id = ?', [$compradorId]);
    return array_fill_keys(array_column($vistas, 'leccion'), true);
}

function marcar_leccion_vista(int $compradorId, string $slug): void
{
    db_ejecutar('INSERT OR IGNORE INTO progreso (comprador_id, leccion, vista_en) VALUES (?, ?, ?)', [$compradorId, $slug, ahora_bd()]);
}
