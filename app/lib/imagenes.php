<?php
declare(strict_types=1);

/*
 * Capturas de la landing. Los originales se suben a contenido/capturas/ y
 * bin/optimizar-capturas.php (lo ejecuta también bin/instalar.php) crea versiones
 * livianas en public_html/assets/img/capturas/, más un índice con sus medidas.
 */

const ANCHOS_CAPTURAS = [480, 960];

function carpeta_capturas_originales(): string
{
    return RAIZ . '/contenido/capturas';
}

function carpeta_capturas_publicas(): string
{
    return RAIZ . '/public_html/assets/img/capturas';
}

/** Índice de capturas optimizadas: [nombre => ['ancho', 'alto', 'archivos' => [ancho => archivo], 'version']]. */
function capturas_indice(bool $recargar = false): array
{
    static $indice = null;
    if ($indice === null || $recargar) {
        $archivo = carpeta_capturas_publicas() . '/indice.json';
        $indice = is_file($archivo) ? (json_decode((string) file_get_contents($archivo), true) ?: []) : [];
    }
    return $indice;
}

/**
 * Capturas cuyo nombre empieza por $prefijo ("mensajes" → mensajes-1, mensajes-2…), en orden natural.
 * Cada una trae 'src' (versión grande), 'srcset', 'ancho', 'alto' y 'nombre'.
 */
function capturas(string $prefijo): array
{
    $lista = [];
    foreach (capturas_indice() as $nombre => $datos) {
        if ($nombre !== $prefijo && !str_starts_with($nombre, $prefijo . '-')) {
            continue;
        }
        $urls = [];
        foreach ($datos['archivos'] as $ancho => $archivo) {
            $urls[(int) $ancho] = '/assets/img/capturas/' . $archivo . '?v=' . $datos['version'];
        }
        ksort($urls);
        $lista[$nombre] = [
            'nombre' => $nombre,
            'ancho' => (int) $datos['ancho'],
            'alto' => (int) $datos['alto'],
            'src' => end($urls),
            'src_chico' => reset($urls),
            'srcset' => implode(', ', array_map(fn (int $ancho, string $url) => "$url {$ancho}w", array_keys($urls), $urls)),
        ];
    }
    uksort($lista, 'strnatcasecmp');
    return array_values($lista);
}

/** ¿Este servidor puede crear imágenes WebP? Si no, se usa JPEG. */
function formato_capturas(): string
{
    return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false) ? 'webp' : 'jpg';
}

/**
 * Crea las versiones optimizadas de las capturas nuevas o modificadas y borra las que ya no existen.
 * Devuelve la lista de cambios (para mostrarla en consola).
 */
function optimizar_capturas(?string $origen = null, ?string $destino = null): array
{
    $origen ??= carpeta_capturas_originales();
    $destino ??= carpeta_capturas_publicas();
    if (!function_exists('imagecreatetruecolor')) {
        return ['⚠ Falta la extensión GD de PHP: no se pueden optimizar las capturas.'];
    }
    if (!is_dir($destino)) {
        mkdir($destino, 0775, true);
    }

    $archivoIndice = "$destino/indice.json";
    $anterior = is_file($archivoIndice) ? (json_decode((string) file_get_contents($archivoIndice), true) ?: []) : [];
    $indice = [];
    $cambios = [];
    $formato = formato_capturas();

    foreach (glob("$origen/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}", GLOB_BRACE) ?: [] as $original) {
        $nombre = strtolower((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($original, PATHINFO_FILENAME)));
        $version = substr(sha1_file($original) ?: '', 0, 10);
        $previo = $anterior[$nombre] ?? null;
        $completo = $previo && $previo['version'] === $version && $previo['formato'] === $formato
            && !array_filter($previo['archivos'], fn (string $archivo) => !is_file("$destino/$archivo"));
        if ($completo) {
            $indice[$nombre] = $previo;
            continue;
        }

        $imagen = imagen_abrir($original);
        if ($imagen === null) {
            $cambios[] = "⚠ No se pudo leer $original (¿formato no válido?)";
            continue;
        }
        $archivos = [];
        foreach (ANCHOS_CAPTURAS as $anchoMaximo) {
            $archivo = "$nombre-$anchoMaximo.$formato";
            [$ancho, $alto] = imagen_guardar($imagen, "$destino/$archivo", $anchoMaximo, $formato);
            $archivos[$anchoMaximo] = $archivo;
        }
        $indice[$nombre] = ['ancho' => $ancho, 'alto' => $alto, 'archivos' => $archivos, 'version' => $version, 'formato' => $formato];
        $cambios[] = "✓ Optimizada $nombre";
    }

    // Borra las versiones de capturas que ya no están en contenido/capturas/
    foreach (array_diff_key($anterior, $indice) as $nombre => $datos) {
        foreach ($datos['archivos'] as $archivo) {
            if (is_file("$destino/$archivo")) {
                unlink("$destino/$archivo");
            }
        }
        $cambios[] = "✓ Eliminada $nombre";
    }

    ksort($indice, SORT_NATURAL);
    if ($indice !== $anterior) {
        file_put_contents($archivoIndice, json_encode($indice, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    capturas_indice(true);
    return $cambios;
}

/** Abre una imagen (JPG, PNG o WebP) y corrige la rotación de las fotos de celular. */
function imagen_abrir(string $archivo): ?GdImage
{
    $tipo = @getimagesize($archivo)[2] ?? null;
    $imagen = match ($tipo) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($archivo),
        IMAGETYPE_PNG => @imagecreatefrompng($archivo),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($archivo) : false,
        default => false,
    };
    if (!$imagen instanceof GdImage) {
        return null;
    }
    if ($tipo === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $orientacion = (int) (@exif_read_data($archivo)['Orientation'] ?? 1);
        $grados = [3 => 180, 6 => -90, 8 => 90][$orientacion] ?? 0;
        if ($grados !== 0) {
            $rotada = imagerotate($imagen, $grados, 0);
            if ($rotada instanceof GdImage) {
                $imagen = $rotada;
            }
        }
    }
    return $imagen;
}

/**
 * Guarda una copia de como máximo $anchoMaximo px de ancho (nunca la agranda).
 * Al volver a codificarla se eliminan los metadatos (ubicación GPS, modelo del celular…).
 */
function imagen_guardar(GdImage $imagen, string $destino, int $anchoMaximo, string $formato): array
{
    $ancho = imagesx($imagen);
    $alto = imagesy($imagen);
    if ($ancho > $anchoMaximo) {
        $alto = (int) round($alto * $anchoMaximo / $ancho);
        $ancho = $anchoMaximo;
    }
    $copia = imagecreatetruecolor($ancho, $alto);
    if ($formato === 'webp') {
        imagealphablending($copia, false);
        imagesavealpha($copia, true);
    } else {
        imagefill($copia, 0, 0, (int) imagecolorallocate($copia, 255, 255, 255)); // JPEG no tiene transparencia
    }
    imagecopyresampled($copia, $imagen, 0, 0, 0, 0, $ancho, $alto, imagesx($imagen), imagesy($imagen));
    $formato === 'webp' ? imagewebp($copia, $destino, 80) : imagejpeg($copia, $destino, 82);
    return [$ancho, $alto];
}
