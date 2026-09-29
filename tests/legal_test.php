<?php
declare(strict_types=1);

/*
 * Páginas legales: la de reembolsos cambia según haya o no garantía.
 */

prueba('sin garantía, la página de reembolsos cubre solo las devoluciones que exige la ley', function () {
    afirmar_igual('reembolsos-sin-garantia', documento_reembolsos(['garantia_dias' => 0]));
    afirmar_igual('reembolsos-sin-garantia', documento_reembolsos([]));
    afirmar_igual('reembolsos', documento_reembolsos(['garantia_dias' => 7]));

    $pagina = pagina_legal('reembolsos-sin-garantia');
    afirmar_igual(200, $pagina['estado']);
    afirmar_contiene('no ofrecemos garantía de satisfacción', $pagina['cuerpo']);
    afirmar_contiene('Todavía no has entrado al curso', $pagina['cuerpo']);
    afirmar_contiene('15 días', $pagina['cuerpo']);
});

prueba('los textos legales que se muestran siempre no dependen de los días de garantía', function () {
    foreach (['terminos', 'privacidad', 'reembolsos-sin-garantia'] as $documento) {
        $texto = (string) file_get_contents(RAIZ . "/contenido/legal/$documento.md");
        afirmar(!str_contains($texto, '{garantia_dias}'), "$documento.md no debe decir \"garantía de 0 días\".");
    }
});
