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
    'modulos' => [
        [
            'titulo' => 'Bienvenida',
            'lecciones' => [
                ['slug' => 'bienvenida', 'titulo' => 'Bienvenida: cómo aprovechar el curso', 'duracion' => '3 min', 'video' => ''],
            ],
        ],
        [
            'titulo' => 'El negocio SMM por dentro',
            'lecciones' => [
                ['slug' => 'que-es-smm', 'titulo' => 'Qué son los servicios SMM y quién los compra', 'duracion' => '', 'video' => ''],
            ],
        ],
        [
            'titulo' => 'Tu panel de proveedor',
            'lecciones' => [
                ['slug' => 'crear-cuenta-panel', 'titulo' => 'Crea tu cuenta en smmclixy y haz tu primer pedido', 'duracion' => '', 'video' => ''],
            ],
        ],
        [
            'titulo' => 'Precios que dejan ganancia',
            'lecciones' => [
                ['slug' => 'calcular-precios', 'titulo' => 'Cómo calcular tus precios', 'duracion' => '', 'video' => '', 'descargas' => ['lista-de-precios']],
            ],
        ],
        [
            'titulo' => 'Tus primeros clientes',
            'lecciones' => [
                ['slug' => 'primeros-clientes', 'titulo' => 'Dónde encontrar tus primeros clientes', 'duracion' => '', 'video' => ''],
            ],
        ],
        [
            'titulo' => 'Atender y cerrar por WhatsApp',
            'lecciones' => [
                ['slug' => 'cerrar-por-whatsapp', 'titulo' => 'Responder, cobrar y dar seguimiento', 'duracion' => '', 'video' => ''],
            ],
        ],
    ],

    // Archivos para descargar: 'id' => ['archivo' => nombre dentro de descargables/, 'nombre' => texto visible]
    'descargables' => [
        'lista-de-precios' => ['archivo' => 'lista-de-precios.csv', 'nombre' => 'Plantilla de lista de precios (Excel/CSV)'],
    ],
];
