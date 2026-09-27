<?php
declare(strict_types=1);

/*
 * Acceso a la carpeta contenido/: los textos y datos que edita el dueño.
 */

/** Devuelve el array que define contenido/{$nombre}.php. Ej.: contenido('negocio'). */
function contenido(string $nombre): array
{
    static $cargados = [];
    if (!preg_match('/^[a-z_]+$/', $nombre)) {
        throw new InvalidArgumentException("Nombre de contenido no válido: $nombre");
    }
    return $cargados[$nombre] ??= require RAIZ . '/contenido/' . $nombre . '.php';
}
