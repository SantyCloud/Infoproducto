<?php
/*
 * ESTRUCTURA DEL CURSO (EJEMPLO)
 *
 * Esta carpeta es un EJEMPLO público. Tu curso real va en storage/curso/ del servidor
 * (misma estructura), porque este repositorio es público y cualquiera podría leerlo aquí.
 * Si haces el repositorio privado, puedes editar directamente esta carpeta.
 *
 * - slug: identificador de la lección en la URL (minúsculas, números y guiones). Su texto va en
 *   lecciones/{slug}.md
 * - video: enlace de YouTube (oculto), Google Drive ("Cualquier persona con el enlace") o Vimeo.
 *   Déjalo vacío ('') si la lección no tiene video.
 * - descargas: ids de la lista 'descargables' de abajo.
 */

return [
    // Los módulos siguen lo que promete la landing ("Todo lo que recibes"): el método, el curso de
    // cómo usar el sistema y el de cómo crear anuncios. Pon cada video tuyo en su módulo.
    'modulos' => [
        [
            'titulo' => 'Bienvenida',
            'lecciones' => [
                ['slug' => 'bienvenida', 'titulo' => 'Bienvenida: cómo aprovechar el curso', 'duracion' => '3 min', 'video' => ''],
            ],
        ],
        [
            'titulo' => 'El método',
            'lecciones' => [
                ['slug' => 'que-es-smm', 'titulo' => 'Qué son los servicios SMM y quién los compra', 'duracion' => '', 'video' => ''],
                ['slug' => 'calcular-precios', 'titulo' => 'Cuánto cobrar: precios que dejan ganancia', 'duracion' => '', 'video' => '', 'descargas' => ['lista-de-precios']],
                ['slug' => 'primeros-clientes', 'titulo' => 'Dónde encontrar tus primeros clientes', 'duracion' => '', 'video' => ''],
                ['slug' => 'cerrar-por-whatsapp', 'titulo' => 'Atender, cobrar y dar seguimiento por WhatsApp', 'duracion' => '', 'video' => ''],
            ],
        ],
        [
            'titulo' => 'Curso: cómo usar el sistema',
            'lecciones' => [
                ['slug' => 'crear-cuenta-panel', 'titulo' => 'Crea tu cuenta en smmclixy y haz tu primer pedido', 'duracion' => '', 'video' => ''],
            ],
        ],
        [
            'titulo' => 'Curso: cómo crear anuncios',
            'lecciones' => [
                ['slug' => 'crear-anuncios', 'titulo' => 'Tu primer anuncio para conseguir clientes', 'duracion' => '', 'video' => ''],
            ],
        ],
    ],

    // Archivos para descargar: 'id' => ['archivo' => nombre dentro de descargables/, 'nombre' => texto visible]
    'descargables' => [
        'lista-de-precios' => ['archivo' => 'lista-de-precios.csv', 'nombre' => 'Plantilla de lista de precios (Excel/CSV)'],
    ],
];
