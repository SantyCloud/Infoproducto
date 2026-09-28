<?php
declare(strict_types=1);

/*
 * Plantillas HTML (app/vistas/).
 */

/** Escapa un texto para imprimirlo en HTML sin riesgo. Úsalo SIEMPRE: <?= e($dato) ?> */
function e(mixed $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Renderiza app/vistas/{$plantilla}.php dentro del layout y devuelve el HTML.
 * Cada clave de $datos llega a la plantilla como variable ($titulo, $negocio…).
 */
function vista(string $plantilla, array $datos = [], ?string $layout = 'layout'): string
{
    $cuerpo = plantilla($plantilla, $datos);
    return $layout === null ? $cuerpo : plantilla($layout, ['cuerpo' => $cuerpo] + $datos);
}

/** Renderiza una sola plantilla y devuelve el HTML. */
function plantilla(string $__plantilla, array $__datos): string
{
    if (!preg_match('#^[a-z0-9_/-]+$#', $__plantilla)) {
        throw new InvalidArgumentException("Nombre de plantilla no válido: $__plantilla");
    }
    extract($__datos, EXTR_SKIP);
    ob_start();
    try {
        require RAIZ . '/app/vistas/' . $__plantilla . '.php';
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
    return (string) ob_get_clean();
}

/**
 * URL de un archivo de public_html/assets/ con su versión (?v=…): el navegador lo guarda
 * en caché y, cuando el archivo cambia, descarga el nuevo.
 */
function asset(string $ruta): string
{
    $ruta = ltrim($ruta, '/');
    $archivo = RAIZ . '/public_html/assets/' . $ruta;
    $version = is_file($archivo) ? substr(md5((string) filemtime($archivo)), 0, 8) : '0';
    return '/assets/' . $ruta . '?v=' . $version;
}

/**
 * Contenido de un CSS de public_html/assets/css/ listo para incrustar en <style>:
 * sin comentarios ni espacios de más (así la página carga con una petición menos).
 */
function css_en_linea(string $archivo): string
{
    static $cache = [];
    if (!isset($cache[$archivo])) {
        if (!preg_match('/^[a-z0-9_-]+\.css$/', $archivo)) {
            throw new InvalidArgumentException("Nombre de CSS no válido: $archivo");
        }
        $css = (string) file_get_contents(RAIZ . '/public_html/assets/css/' . $archivo);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $css = (string) preg_replace('/\s+/', ' ', $css);
        $css = (string) preg_replace('/\s*([{};,])\s*/', '$1', $css);
        $cache[$archivo] = str_replace('</', '<\\/', trim($css)); // nunca cerrar la etiqueta <style> por accidente
    }
    return $cache[$archivo];
}
