<?php
/*
 * TEXTOS DE LA LANDING (la página de venta), en orden de arriba abajo.
 *
 * - Variables que se reemplazan solas con los datos de negocio.php:
 *   {producto} {precio} {precio_normal} {ahorro} {descuento} {promo_nombre} {promo_fin} {garantia_dias} {metodos_pago}
 * - Para resaltar palabras usa **dos asteriscos**.
 * - Lo que está [entre corchetes] es un ejemplo: cámbialo por tu información real.
 * - Si dejas una lista vacía ([]), esa parte no se muestra. Así la página queda corta y directa;
 *   para volver a mostrar una sección, llena su lista.
 * - No nombres aquí la web de proveedor: se revela solo dentro del curso (emails y área de miembros).
 * - Las capturas se toman solas de contenido/capturas/ según cómo empieza su nombre:
 *   mensajes-1.jpg, ingresos-1.jpg, testimonio-1.jpg… (por país: ingresos-ec-1.jpg, ingresos-mx-1.jpg…)
 *
 * Al editar: respeta las comillas '…' y la coma al final de cada línea.
 */

return [
    // Título y descripción que ven Google y WhatsApp al compartir el enlace
    'seo' => [
        'titulo' => '{producto}: tu negocio de servicios para redes sociales',
        'descripcion' => 'El método paso a paso para vender servicios para redes sociales desde tu celular. {promo_nombre}: {precio}.',
    ],

    // Franja amarilla de arriba (solo se muestra mientras la promo esté vigente)
    'promo' => [
        'barra' => '🔥 **{precio}** (antes {precio_normal})',
        'despues' => 'Después vuelve a {precio_normal}.',
        // Etiqueta amarilla junto al precio. {ahorro} = cuánto ahorra ($5) · {descuento} = porcentaje (33%).
        // Ejemplos: 'Ahorra {ahorro}' · '−{descuento}' · 'Ahorra {ahorro} ({descuento})'
        'etiqueta' => 'Ahorra {ahorro}',
    ],

    'hero' => [
        // Lo de arriba (título, precio, botón y lo que recibe) debe verse SIN BAJAR en el celular:
        // mantén estos textos cortos. La etiqueta sobre el título está vacía para ganar espacio.
        'etiqueta' => '',
        'titulo' => 'Crea tu **negocio de servicios para redes** desde tu celular',
        'subtitulo' => 'El método exacto que uso para vender seguidores, likes y vistas.',
        'boton' => 'Quiero el método por {precio}',
        'nota_boton' => 'Pago único · Te atiendo por WhatsApp',
        // Lo que recibe, en corto y en dos columnas (el detalle va más abajo, en "Todo lo que recibes")
        'ventajas' => ['Método completo', 'Acceso a la web', 'Curso del sistema', 'Curso de anuncios'],
        // Texto bajo la captura del celular (cuando ya subiste capturas "mensajes-…")
        'pie_captura' => 'Así me llegan los pedidos por WhatsApp',
        // Mensajes del celular dibujado (se usan mientras no subas capturas "mensajes-…")
        'mensajes_ejemplo' => [
            'Hola 👋 ¿tienes seguidores para mi tienda?',
            '¿Cuánto cuestan 1.000 likes?',
            'Necesito vistas para mi video de TikTok 🙏',
            'Buenas, ¿atiendes pedidos hoy?',
        ],
    ],

    // Solo aparece si subes más de una captura "mensajes-…" (la primera ya va en el celular de arriba)
    'demanda' => [
        'titulo' => 'Así llegan los pedidos a mi WhatsApp',
        'texto' => 'Tiendas, emprendedores y creadores de contenido necesitan crecer en redes **todos los días**. La pregunta es quién los atiende.',
        'capturas' => 'mensajes',
    ],

    // Oculta (lista vacía)
    'problema' => [
        'titulo' => '¿Te suena alguna de estas?',
        'puntos' => [],
        'cierre' => '',
    ],

    'historia' => [
        'titulo' => 'Cómo empecé',
        'parrafos' => [
            'Empecé hace 3 años gracias a un amigo de Argentina que me mostró este negocio. Desde entonces trabajo desde mi celular y hubo épocas en las que **me despertaba con miles de mensajes** pidiendo servicios.',
            'Hoy disfruto de mi **libertad y mi comodidad**, y en este curso te enseño el mismo método que uso yo.',
        ],
        'firma' => '', // vacío: la historia va sin firma (decisión del dueño)
    ],

    // Oculta (lista vacía)
    'como_funciona' => [
        'titulo' => 'Cómo funciona el negocio',
        'texto' => 'Compras los servicios a precio de proveedor, los vendes a tu precio y **la diferencia es tu ganancia**.',
        'pasos' => [],
    ],

    // Ocultas (listas vacías). Cuando termines de grabar los videos puedes poner aquí los módulos.
    'modulos' => [
        'titulo' => 'Qué incluye el método',
        'texto' => 'Videos cortos y al grano, pensados para verlos desde el celular.',
        'lista' => [],
    ],
    'bonos' => [
        'titulo' => 'Y además te llevas',
        'lista' => [],
    ],

    'resultados' => [
        'titulo' => 'Resultados de mi negocio',
        'texto' => 'Transferencias reales de mis clientes. No te prometo lo mismo: depende de tu constancia y del tiempo que le dediques.',
        'capturas' => 'ingresos',
        'aviso' => 'Resultados personales del autor. No representan ingresos típicos ni garantizados.',
    ],

    // Oculta (listas vacías)
    'para_quien' => [
        'titulo' => '¿Es para ti?',
        'si_titulo' => 'Es para ti si…',
        'si' => [],
        'no_titulo' => 'No es para ti si…',
        'no' => [],
    ],

    'testimonios' => [
        'titulo' => 'Lo que dicen quienes ya empezaron',
        'capturas' => 'testimonio',
        // Testimonios en texto, SOLO reales. Ejemplo:
        // ['nombre' => 'María, Quito', 'texto' => 'Hice mi primera venta la primera semana.'],
        'lista' => [],
    ],

    'oferta' => [
        'titulo' => 'Todo lo que recibes',
        'incluye' => [
            '**El método completo:** qué vender, cuánto cobrar y cómo conseguir clientes.',
            '**Acceso a la web** donde compras los servicios a precio de proveedor.',
            '**Curso: cómo usar el sistema**, paso a paso y desde el celular.',
            '**Curso: cómo crear anuncios** para conseguir clientes.',
        ],
        'boton' => 'Quiero mi acceso por {precio}',
        'nota_pago' => 'Pagas por WhatsApp. Aceptamos:',
    ],

    // Solo se muestra si pones días de garantía en negocio.php
    'garantia' => [
        'titulo' => 'Garantía de {garantia_dias} días',
        'texto' => 'Entra, mira el curso y aplícalo. Si en los primeros {garantia_dias} días sientes que no es para ti, escríbeme y **te devuelvo tu dinero**. Sin preguntas incómodas.',
    ],

    'faq' => [
        'titulo' => 'Preguntas frecuentes',
        'lista' => [
            ['pregunta' => '¿Necesito experiencia?', 'respuesta' => 'No. Empiezas desde cero y todo se hace desde el celular.'],
            ['pregunta' => '¿Tengo que invertir algo más?', 'respuesta' => 'Solo el saldo para los pedidos de tus clientes, que recargas a medida que vendes. Puedes empezar con poco dinero.'],
            ['pregunta' => '¿Cómo pago y cuándo recibo el acceso?', 'respuesta' => 'Por WhatsApp, con {metodos_pago}. Apenas confirmo tu pago, te envío por WhatsApp tu enlace para activar tu acceso.'],
            ['pregunta' => '¿Cuánto voy a ganar?', 'respuesta' => 'Depende de ti: de cuántos clientes consigas y del margen que pongas. No hay ingresos garantizados.'],
            ['pregunta' => '¿Sirve para mi país?', 'respuesta' => 'Sí. Tus clientes pueden estar en cualquier país y todo funciona igual desde donde estés.'],
            // Solo aparece si hay garantía (garantia_dias mayor que 0 en negocio.php)
            ['pregunta' => '¿Y si no me gusta?', 'respuesta' => 'Tienes {garantia_dias} días de garantía: si no es para ti, te devuelvo tu dinero.'],
        ],
    ],

    'cierre' => [
        'titulo' => 'Tu negocio puede empezar hoy',
        'texto' => 'Por {precio} accedes al método, a la web y a los cursos. Escríbeme y empezamos.',
        'boton' => 'Escribirme por WhatsApp',
    ],

    // Barra fija de abajo que aparece al bajar por la página
    'barra_fija' => [
        'boton' => 'Quiero el método',
    ],

    'pie' => [
        'aviso' => 'Los resultados mostrados son personales y no garantizan ingresos. Este sitio no forma parte de Facebook, Instagram ni Meta Platforms, Inc., ni está respaldado por ellos.',
    ],
];
