<?php
declare(strict_types=1);

prueba('e() escapa el HTML (protege contra inyección de código)', function () {
    afirmar_igual('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
    afirmar_igual('&#039;', e("'"));
    afirmar_igual('', e(null));
});

prueba('la landing escapa los textos y tacha el precio normal solo si hay promo', function () {
    $negocio = ['producto' => 'Sistema <b>Prueba</b>', 'garantia_dias' => 0] + contenido('negocio');
    $conPromo = vista('landing', datos_landing($negocio, contenido('landing')), 'layout_landing');
    afirmar_contiene('Sistema &lt;b&gt;Prueba&lt;/b&gt;', $conPromo);
    afirmar_contiene('<span class="precio-antes">$15</span>', $conPromo);
    afirmar(!str_contains($conPromo, 'id="garantia"'), 'Con garantia_dias = 0 no se muestra la garantía.');

    $sinPromo = ['promo' => ['activa' => false] + $negocio['promo']] + $negocio;
    $html = vista('landing', datos_landing($sinPromo, contenido('landing')), 'layout_landing');
    afirmar(!str_contains($html, 'class="precio-antes'), 'Sin promo no debe haber precio tachado.');
    afirmar(!str_contains($html, 'class="barra-promo"'), 'Sin promo no hay franja amarilla.');
    afirmar_contiene('$15', $html);
});

prueba('no acepta nombres de plantilla con rutas raras', function () {
    afirmar_falla(fn () => vista('../../.env'));
});
