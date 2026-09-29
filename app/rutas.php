<?php
declare(strict_types=1);

/*
 * Todas las direcciones de la web: [método, ruta, función que la atiende].
 * {nombre} captura un trozo de la ruta (letras, números, - y _) y llega a la función como parámetro.
 */

return [
    // Públicas
    ['GET', '/', 'pagina_inicio'],
    ['GET', '/wa', 'pagina_whatsapp'],
    ['GET', '/terminos', 'pagina_terminos'],
    ['GET', '/privacidad', 'pagina_privacidad'],
    ['GET', '/reembolsos', 'pagina_reembolsos'],

    // Área de miembros
    ['GET', '/entrar', 'miembro_entrar_formulario'],
    ['POST', '/entrar', 'miembro_pedir_enlace'],
    ['GET', '/acceso/{token}', 'miembro_acceso_confirmar'],
    ['POST', '/acceso/{token}', 'miembro_acceso_usar'],
    ['GET', '/activar', 'miembro_activar_formulario'],
    ['GET', '/activar/{codigo}', 'miembro_activar_formulario'],
    ['POST', '/activar', 'miembro_activar'],
    ['GET', '/miembros', 'miembro_inicio'],
    ['GET', '/miembros/leccion/{slug}', 'miembro_leccion'],
    ['GET', '/miembros/descargar/{id}', 'miembro_descargar'],
    ['POST', '/miembros/salir', 'miembro_salir'],

    // Panel de administración
    ['GET', '/admin/entrar', 'admin_entrar_formulario'],
    ['POST', '/admin/entrar', 'admin_entrar'],
    ['POST', '/admin/salir', 'admin_salir'],
    ['GET', '/admin', 'admin_inicio'],
    ['GET', '/admin/leads', 'admin_leads'],
    ['GET', '/admin/ventas', 'admin_ventas'],
    ['GET', '/admin/ventas/nueva', 'admin_venta_formulario'],
    ['POST', '/admin/ventas', 'admin_venta_registrar'],
    ['POST', '/admin/activaciones/{id}/enlace', 'admin_activacion_enlace'],
    ['POST', '/admin/activaciones/{id}/anular', 'admin_activacion_anular'],
    ['GET', '/admin/compradores', 'admin_compradores'],
    ['GET', '/admin/compradores/{id}', 'admin_comprador'],
    ['POST', '/admin/compradores/{id}/editar', 'admin_comprador_editar'],
    ['POST', '/admin/compradores/{id}/enlace', 'admin_comprador_enlace'],
    ['POST', '/admin/compradores/{id}/reenviar', 'admin_comprador_reenviar'],
    ['POST', '/admin/compradores/{id}/revocar', 'admin_comprador_revocar'],
    ['POST', '/admin/compradores/{id}/restaurar', 'admin_comprador_restaurar'],
    ['POST', '/admin/compradores/{id}/cerrar-sesiones', 'admin_comprador_cerrar_sesiones'],
    ['GET', '/admin/accesos/nuevo', 'admin_acceso_formulario'],
    ['POST', '/admin/accesos', 'admin_acceso_crear'],
    ['GET', '/admin/exportar/{tipo}', 'admin_exportar'],

    // Páginas por país (/ec, /mx…, según contenido/negocio.php). Va al final a propósito.
    ['GET', '/{pais}', 'pagina_pais'],
];
