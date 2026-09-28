<?php
/*
 * TEXTOS DE LA LANDING (la página de venta), en orden de arriba abajo.
 *
 * - Variables que se reemplazan solas con los datos de negocio.php:
 *   {producto} {precio} {precio_normal} {promo_nombre} {promo_fin} {garantia_dias} {metodos_pago}
 * - Para resaltar palabras usa **dos asteriscos**.
 * - Lo que está [entre corchetes] es un ejemplo: cámbialo por tu información real.
 * - Si dejas una lista vacía ([]), esa parte no se muestra.
 * - Las capturas se toman solas de contenido/capturas/ según cómo empieza su nombre:
 *   mensajes-1.jpg, ingresos-1.jpg, testimonio-1.jpg…
 *
 * Al editar: respeta las comillas '…' y la coma al final de cada línea.
 */

return [
    // Título y descripción que ven Google y WhatsApp al compartir el enlace
    'seo' => [
        'titulo' => '{producto}: tu negocio de servicios para redes sociales',
        'descripcion' => 'El sistema paso a paso para revender servicios SMM desde tu celular, sin inventario. {promo_nombre}: {precio}.',
    ],

    // Franja amarilla de arriba (solo se muestra mientras la promo esté vigente)
    'promo' => [
        'barra' => '🔥 {promo_nombre}: **{precio}** (antes {precio_normal})',
        'despues' => 'Después vuelve a {precio_normal}.',
    ],

    'hero' => [
        'etiqueta' => 'Negocio digital desde tu celular',
        'titulo' => 'Crea tu propio **negocio de servicios para redes sociales** desde tu celular',
        'subtitulo' => 'El sistema exacto que uso para vender servicios SMM (seguidores, likes y vistas): dónde conseguirlos a precio de mayorista, cuánto cobrar y cómo encontrar tus primeros clientes.',
        'boton' => 'Quiero el sistema por {precio}',
        'nota_boton' => 'Te atiendo personalmente por WhatsApp',
        'ventajas' => ['Pago único', 'Sin mensualidades', 'Garantía de {garantia_dias} días'],
        // Mensajes del celular dibujado (se usan mientras no subas capturas "mensajes-…")
        'mensajes_ejemplo' => [
            'Hola 👋 ¿tienes seguidores para mi tienda?',
            '¿Cuánto cuestan 1.000 likes?',
            'Necesito vistas para mi video de TikTok 🙏',
            'Buenas, ¿atiendes pedidos hoy?',
        ],
    ],

    'demanda' => [
        'titulo' => 'Así amanece mi WhatsApp',
        'texto' => 'Tiendas, emprendedores, artistas y creadores de contenido necesitan crecer en redes **todos los días**. Esa demanda ya existe. La pregunta es quién la atiende.',
        'capturas' => 'mensajes',
    ],

    'problema' => [
        'titulo' => '¿Te suena alguna de estas?',
        'puntos' => [
            'Quieres un ingreso extra, pero no tienes capital para comprar mercadería.',
            'Probaste vender productos y te quedaste con inventario sin salida.',
            'Pasas horas en redes sociales y no te dejan ni un dólar.',
            'Todos los "negocios digitales" que ves piden cursos caros o saber programar.',
        ],
        'cierre' => 'Con este sistema no necesitas inventario, local ni conocimientos técnicos: **solo tu celular y ganas de atender clientes.**',
    ],

    'historia' => [
        'titulo' => 'Cómo empecé',
        'parrafos' => [
            'Hace [X años] yo [cuenta tu situación de entonces: trabajo, estudios, qué buscabas]. Empecé a revender servicios para redes sociales desde mi celular, con muy poco dinero y un par de clientes conocidos.',
            '[Cuenta cómo te fue al principio: tus primeras ventas, los errores que cometiste, lo que aprendiste.]',
            'Con el tiempo armé un sistema: dónde comprar, cuánto cobrar y qué responder a cada cliente. Hubo épocas en las que **me despertaba con miles de mensajes** de personas pidiendo servicios.',
            'Hoy tengo mi propio panel, smmclixy.com, y en este curso te enseño paso a paso el mismo sistema que uso yo.',
        ],
        'firma' => '[Tu nombre]',
    ],

    'como_funciona' => [
        'titulo' => 'Cómo funciona el negocio',
        'texto' => 'Paneles mayoristas como **smmclixy.com** venden servicios para redes sociales a precio de proveedor. Tú los ofreces a tus clientes a tu precio y **la diferencia es tu ganancia**.',
        'pasos' => [
            ['titulo' => 'Creas tu cuenta de revendedor', 'texto' => 'Te registras gratis en smmclixy.com, el panel que uso como proveedor, y recargas saldo solo cuando tienes pedidos.'],
            ['titulo' => 'Consigues clientes', 'texto' => 'Te muestro dónde están, qué decirles y cómo usar tus propias redes para vender.'],
            ['titulo' => 'Haces el pedido y cobras', 'texto' => 'Cargas el pedido en el panel, se entrega automáticamente y te quedas con la diferencia.'],
        ],
    ],

    'modulos' => [
        'titulo' => 'Qué incluye el sistema',
        'texto' => 'Videos cortos y al grano, pensados para verlos desde el celular.',
        'lista' => [
            ['titulo' => 'El negocio SMM por dentro', 'texto' => 'Qué son los servicios SMM, quién los compra y por qué es un mercado que no para de crecer.'],
            ['titulo' => 'Tu panel de proveedor', 'texto' => 'Cómo crear tu cuenta en smmclixy, recargar saldo y hacer tu primer pedido.'],
            ['titulo' => 'Precios que dejan ganancia', 'texto' => 'Cómo calcular tus precios, armar paquetes y no competir por ser el más barato.'],
            ['titulo' => 'Tus primeros clientes', 'texto' => 'Dónde encontrarlos, qué mensajes enviar y cómo usar tus redes para vender.'],
            ['titulo' => 'Atender y cerrar por WhatsApp', 'texto' => 'Respuestas listas para las preguntas más comunes, cómo cobrar y cómo dar seguimiento.'],
            ['titulo' => 'Crecer y automatizar', 'texto' => 'Clientes que vuelven, cómo organizar tu tiempo y cómo hacer crecer el negocio.'],
        ],
    ],

    'bonos' => [
        'titulo' => 'Y además te llevas',
        'lista' => [
            ['titulo' => 'Plantillas de mensajes', 'texto' => 'Respuestas para WhatsApp listas para copiar y pegar.'],
            ['titulo' => 'Lista de precios editable', 'texto' => 'Tu tabla de servicios y precios, lista para enviar a tus clientes.'],
            ['titulo' => 'Soporte por WhatsApp', 'texto' => 'Me escribes tus dudas mientras arrancas.'],
        ],
    ],

    'resultados' => [
        'titulo' => 'Resultados de mi negocio',
        'texto' => 'Capturas reales de mi propio negocio. No te prometo lo mismo: depende de tu constancia, tus clientes y el tiempo que le dediques. Lo que sí te doy es el sistema exacto que uso.',
        'capturas' => 'ingresos',
        'aviso' => 'Resultados personales del autor. No representan ingresos típicos ni garantizados.',
    ],

    'para_quien' => [
        'titulo' => '¿Es para ti?',
        'si_titulo' => 'Es para ti si…',
        'si' => [
            'Quieres un ingreso extra desde tu celular.',
            'Puedes dedicarle al menos una hora al día.',
            'Te gustan las redes sociales y hablar con gente.',
            'Quieres empezar con poco dinero.',
        ],
        'no_titulo' => 'No es para ti si…',
        'no' => [
            'Buscas dinero fácil sin trabajar.',
            'No quieres atender clientes.',
            'Esperas resultados sin aplicar lo que aprendes.',
        ],
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
            'Curso completo en video, paso a paso',
            'Plantillas de mensajes y lista de precios editable',
            'Acceso a tu área de miembros',
            'Soporte por WhatsApp',
            'Guía para crear tu cuenta de proveedor en smmclixy.com',
        ],
        'boton' => 'Quiero mi acceso por {precio}',
        'nota_pago' => 'Pagas por WhatsApp. Aceptamos:',
    ],

    'garantia' => [
        'titulo' => 'Garantía de {garantia_dias} días',
        'texto' => 'Entra, mira el curso y aplícalo. Si en los primeros {garantia_dias} días sientes que no es para ti, escríbeme y **te devuelvo tu dinero**. Sin preguntas incómodas.',
    ],

    'faq' => [
        'titulo' => 'Preguntas frecuentes',
        'lista' => [
            ['pregunta' => '¿Necesito experiencia o conocimientos técnicos?', 'respuesta' => 'No. El curso empieza desde cero y todo se hace desde el celular.'],
            ['pregunta' => '¿Cuánto dinero necesito para empezar?', 'respuesta' => 'Además del curso, solo el saldo para tus primeros pedidos. Puedes empezar con pocos dólares y recargar a medida que vendes.'],
            ['pregunta' => '¿Cómo pago?', 'respuesta' => 'Por WhatsApp: te paso los datos para pagar con {metodos_pago}. Cuando confirmo tu pago, te llega el acceso.'],
            ['pregunta' => '¿Cuándo recibo el acceso?', 'respuesta' => 'Apenas confirmo tu pago te llega un email con tu enlace de acceso, y también te lo envío por WhatsApp.'],
            ['pregunta' => '¿Cuánto voy a ganar?', 'respuesta' => 'Depende de ti: de cuántos clientes consigas y del margen que pongas. No hay ingresos garantizados. Te enseño el sistema; el trabajo lo pones tú.'],
            ['pregunta' => '¿Sirve si no vivo en Ecuador?', 'respuesta' => 'Sí. El negocio funciona en cualquier país y los pagos se manejan en dólares.'],
            ['pregunta' => '¿Tengo que pagar el panel smmclixy?', 'respuesta' => 'Registrarte es gratis. Solo recargas saldo cuando tienes pedidos de tus clientes.'],
            ['pregunta' => '¿Y si no me gusta?', 'respuesta' => 'Tienes {garantia_dias} días de garantía: si no es para ti, te devuelvo tu dinero.'],
        ],
    ],

    'cierre' => [
        'titulo' => 'Tu negocio puede empezar hoy',
        'texto' => 'Por {precio} tienes el sistema completo, las plantillas y mi ayuda por WhatsApp. Escríbeme y empezamos.',
        'boton' => 'Escribirme por WhatsApp',
    ],

    // Barra fija de abajo que aparece al bajar por la página
    'barra_fija' => [
        'boton' => 'Quiero el sistema',
    ],

    'pie' => [
        'aviso' => 'Los resultados mostrados son personales y no garantizan ingresos. Este sitio no forma parte de Facebook, Instagram ni Meta Platforms, Inc., ni está respaldado por ellos.',
    ],
];
