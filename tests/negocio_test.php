<?php
declare(strict_types=1);

function negocio_de_prueba(array $promo): array
{
    return ['precio_normal' => 15, 'promo' => $promo];
}

prueba('la promo vale hasta su fecha de fin, en hora de Ecuador', function () {
    $negocio = negocio_de_prueba(['activa' => true, 'precio' => 10, 'termina' => '2026-10-31 23:59']);
    $antes = new DateTimeImmutable('2026-11-01 04:58:00', new DateTimeZone('UTC'));   // 23:58 en Ecuador
    $despues = new DateTimeImmutable('2026-11-01 05:00:00', new DateTimeZone('UTC')); // 00:00 en Ecuador

    afirmar(promo_vigente($negocio, $antes));
    afirmar_igual(10.0, precio_actual($negocio, $antes));
    afirmar(!promo_vigente($negocio, $despues));
    afirmar_igual(15.0, precio_actual($negocio, $despues));
});

prueba('sin promo activa se cobra el precio normal', function () {
    afirmar_igual(15.0, precio_actual(negocio_de_prueba(['activa' => false, 'precio' => 10, 'termina' => null])));
    afirmar_igual(15.0, precio_actual(negocio_de_prueba([])));
});

prueba('una promo sin fecha de fin sigue vigente', function () {
    afirmar_igual(10.0, precio_actual(negocio_de_prueba(['activa' => true, 'precio' => 10, 'termina' => null])));
});

prueba('el ahorro y el descuento de la promo se calculan del precio normal y el de promo', function () {
    $conPromo = negocio_de_prueba(['activa' => true, 'precio' => 10, 'termina' => null]);
    afirmar_igual(5.0, ahorro_promo($conPromo));
    afirmar_igual('$5', variables_texto($conPromo + contenido('negocio'))['{ahorro}']);
    afirmar_igual('33%', variables_texto($conPromo + contenido('negocio'))['{descuento}']);
    afirmar_igual(5.01, ahorro_promo(negocio_de_prueba(['activa' => true, 'precio' => 9.99, 'termina' => null])));
    $sinPromo = negocio_de_prueba(['activa' => false, 'precio' => 10, 'termina' => null]);
    afirmar_igual(0.0, ahorro_promo($sinPromo));
    afirmar_igual('0%', variables_texto($sinPromo + contenido('negocio'))['{descuento}']);
});

prueba('formatea los precios en dólares', function () {
    afirmar_igual('$10', formatear_precio(10));
    afirmar_igual('$12.50', formatear_precio(12.5));
    afirmar_igual('$1,500', formatear_precio(1500));
});

prueba('el archivo contenido/negocio.php tiene los datos que usa la web', function () {
    $negocio = contenido('negocio');
    foreach (['producto', 'precio_normal', 'promo', 'garantia_dias', 'whatsapp', 'metodos_pago', 'smmclixy', 'soporte', 'legal'] as $clave) {
        afirmar(array_key_exists($clave, $negocio), "Falta '$clave' en contenido/negocio.php.");
    }
    afirmar_contiene('{codigo}', $negocio['whatsapp']['texto_codigo'], 'El texto del código de WhatsApp debe incluir {codigo}.');
    afirmar(
        (bool) preg_match('/^\d{8,15}$/', $negocio['whatsapp']['numero']),
        'El número de WhatsApp debe tener solo dígitos, con código de país.'
    );
});

prueba('la promo de contenido/negocio.php es coherente', function () {
    $promo = contenido('negocio')['promo'];
    if (empty($promo['activa'])) {
        return;
    }
    afirmar($promo['precio'] < contenido('negocio')['precio_normal'], 'El precio promo debe ser menor que el normal.');
    if (!empty($promo['termina'])) {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $promo['termina']);
        afirmar(
            $fecha !== false && $fecha->format('Y-m-d H:i') === $promo['termina'],
            "La fecha de fin de la promo debe ser válida y con el formato 'AAAA-MM-DD HH:MM'."
        );
    }
});
