<?php
declare(strict_types=1);

prueba('e() escapa el HTML (protege contra inyección de código)', function () {
    afirmar_igual('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
    afirmar_igual('&#039;', e("'"));
    afirmar_igual('', e(null));
});

prueba('la portada muestra el producto escapado y el precio normal tachado', function () {
    $html = vista('inicio', [
        'titulo' => 'Prueba',
        'negocio' => ['producto' => 'Sistema <b>Prueba</b>', 'precio_normal' => 15],
        'promo' => true,
        'precio' => 10.0,
    ]);
    afirmar_contiene('Sistema &lt;b&gt;Prueba&lt;/b&gt;', $html);
    afirmar_contiene('<s>$15</s>', $html);
    afirmar_contiene('<strong>$10</strong>', $html);
    afirmar_contiene('<html lang="es">', $html, 'Debe ir dentro del layout.');
    afirmar_contiene('/assets/css/app.css?v=', $html);
});

prueba('sin promo no se tacha nada', function () {
    $html = vista('inicio', [
        'titulo' => 'Prueba',
        'negocio' => ['producto' => 'X', 'precio_normal' => 15],
        'promo' => false,
        'precio' => 15.0,
    ]);
    afirmar(!str_contains($html, '<s>'), 'No debería haber precio tachado.');
});

prueba('no acepta nombres de plantilla con rutas raras', function () {
    afirmar_falla(fn () => vista('../../.env'));
});
