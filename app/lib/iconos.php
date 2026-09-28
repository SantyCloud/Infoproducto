<?php
declare(strict_types=1);

/*
 * Iconos SVG en línea (sin archivos ni librerías externas).
 */

function icono(string $nombre, string $clase = 'icono'): string
{
    $trazos = [
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'escudo' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'regalo' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
        'play' => '<circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4z"/>',
        'reloj' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'flecha' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
        'atras' => '<path d="M19 12H5M11 19l-7-7 7-7"/>',
        'candado' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'descarga' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'usuario' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'salir' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'copiar' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
        'enlace' => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        'grafico' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-6"/>',
        'libro' => '<path d="M4 19V5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2zm0 0a2 2 0 0 0 2 2h14"/>',
        'rayo' => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
        'mas' => '<path d="M12 5v14M5 12h14"/>',
        'buscar' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    ];
    if (!isset($trazos[$nombre])) {
        throw new InvalidArgumentException("Icono desconocido: $nombre");
    }
    return '<svg class="' . e($clase) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $trazos[$nombre] . '</svg>';
}

/** Logo de WhatsApp (relleno). */
function icono_whatsapp(string $clase = 'icono'): string
{
    return '<svg class="' . e($clase) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">'
        . '<path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.41-.08-.13-.27-.2-.57-.35zM12.05 21.8h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88a9.83 9.83 0 0 1 6.99 2.9 9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88zm8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41z"/>'
        . '</svg>';
}
