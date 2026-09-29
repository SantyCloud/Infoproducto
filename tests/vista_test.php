<?php
declare(strict_types=1);

prueba('e() escapa el HTML (protege contra inyección de código)', function () {
    afirmar_igual('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
    afirmar_igual('&#039;', e("'"));
    afirmar_igual('', e(null));
});

prueba('la landing escapa los textos y tacha el precio normal solo si hay promo', function () {
    // Promo propia de la prueba (sin fecha de fin): la de contenido/negocio.php vence
    $negocio = ['producto' => 'Sistema <b>Prueba</b>', 'garantia_dias' => 0, 'precio_normal' => 15,
        'promo' => ['activa' => true, 'precio' => 10, 'nombre' => 'Promo de prueba', 'termina' => null]] + contenido('negocio');
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

prueba('la etiqueta junto al precio se escribe en contenido/landing.php (ahorro o porcentaje)', function () {
    $negocio = ['precio_normal' => 15, 'promo' => ['activa' => true, 'precio' => 10, 'nombre' => 'Promo', 'termina' => null]] + contenido('negocio');
    $etiqueta = function (?string $texto) use ($negocio): string {
        $landing = contenido('landing');
        if ($texto === null) {
            unset($landing['promo']['etiqueta']);
        } else {
            $landing['promo']['etiqueta'] = $texto;
        }
        $html = vista('landing', datos_landing($negocio, $landing), 'layout_landing');
        return preg_match('#<span class="chip-promo">(.*?)</span>#', $html, $m) ? $m[1] : '';
    };
    afirmar_igual('Ahorra $5', $etiqueta('Ahorra {ahorro}'));
    afirmar_igual('−33%', $etiqueta('−{descuento}'));
    afirmar_igual('−33%', $etiqueta(null), 'Sin etiqueta en el contenido, se muestra el porcentaje.');
});

prueba('no acepta nombres de plantilla con rutas raras', function () {
    afirmar_falla(fn () => vista('../../.env'));
});
