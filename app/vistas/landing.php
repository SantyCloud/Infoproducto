<?php
/**
 * Landing de venta. Todos los textos vienen de contenido/landing.php (ya con las variables reemplazadas).
 * @var array       $l               textos de la landing
 * @var array       $negocio         contenido/negocio.php
 * @var bool        $promo           ¿promo vigente?
 * @var string|null $pais            página de un país (mx, ec…) o null en la general
 * @var string      $precio          precio actual con su moneda ("$10", "$200 MXN")
 * @var string      $precio_normal   "$15", "$300 MXN"
 * @var string      $precio_html     precio actual para mostrarlo en grande (la moneda va en pequeño)
 * @var string      $precio_normal_corto  precio normal sin la moneda ("$300"), para tacharlo al lado del actual
 * @var string|null $tiempo_promo    "Quedan 3 días", "Hasta el 31 de octubre"…
 * @var int         $descuento       % de descuento de la promo (0 si no hay)
 * @var bool        $garantia        ¿hay garantía?
 * @var array       $capturas        ['demanda' => [...], 'resultados' => [...], 'testimonios' => [...]]
 * @var array|null  $adelanto        video "adelanto del curso" (ver adelanto()), o null si no hay
 * @var bool        $mostrar_huecos
 */

$boton = fn (string $texto, string $id, string $clase = ''): string =>
    plantilla('parciales/boton_wa', ['texto' => $texto, 'id' => $id, 'clase' => $clase, 'pais' => $pais]);
$galeria = fn (array $lista, string $alt, string $hueco): string =>
    plantilla('parciales/galeria', ['capturas' => $lista, 'alt' => $alt, 'hueco' => $hueco, 'mostrar_huecos' => $mostrar_huecos]);
$horas = ['07:02', '07:04', '07:09', '07:15'];
?>
<?php if ($promo): ?>
    <div class="barra-promo">
        <span><?= formato($l['promo']['barra']) ?></span>
        <?php if ($tiempo_promo): ?><span class="barra-promo__tiempo"><?= icono('reloj') ?> <?= e($tiempo_promo) ?></span><?php endif; ?>
    </div>
<?php endif; ?>

<header class="hero">
    <div class="contenedor hero__rejilla">
        <div class="hero__texto">
            <?php if (!empty($l['hero']['etiqueta'])): ?><p class="etiqueta"><?= formato($l['hero']['etiqueta']) ?></p><?php endif; ?>
            <h1><?= formato($l['hero']['titulo']) ?></h1>
            <p class="hero__sub"><?= formato($l['hero']['subtitulo']) ?></p>

            <div class="precio-linea">
                <?php if ($promo): ?><span class="precio-antes"><?= e($precio_normal_corto) ?></span><?php endif; ?>
                <span class="precio-ahora"><?= $precio_html ?></span>
                <?php if ($promo && $descuento > 0): ?><span class="chip-promo"><?= formato($l['promo']['etiqueta'] ?? '−' . $descuento . '%') ?></span><?php endif; ?>
            </div>

            <?= $boton($l['hero']['boton'], 'hero', 'boton-wa--pulso') ?>
            <p class="nota-boton"><?= formato($l['hero']['nota_boton']) ?></p>
            <?php if ($l['hero']['ventajas']): ?>
                <ul class="ventajas">
                    <?php foreach ($l['hero']['ventajas'] as $ventaja): ?>
                        <li><?= icono('check') ?><?= formato($ventaja) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="hero__visual">
            <div class="telefono<?= $capturas['demanda'] ? ' telefono--captura' : '' ?>">
                <?php if ($capturas['demanda']): ?>
                    <img src="<?= e($capturas['demanda'][0]['src']) ?>" width="<?= $capturas['demanda'][0]['ancho'] ?>"
                         height="<?= $capturas['demanda'][0]['alto'] ?>" alt="Mensajes de clientes en WhatsApp" fetchpriority="high">
                <?php else: ?>
                    <div class="chat" aria-hidden="true">
                        <div class="chat__barra">
                            <span class="chat__avatar"><?= icono('usuario') ?></span>
                            <span><strong>Clientes</strong><small>en línea</small></span>
                        </div>
                        <?php foreach ($l['hero']['mensajes_ejemplo'] as $i => $mensaje): ?>
                            <p class="burbuja"><?= e($mensaje) ?><small><?= e($horas[$i % count($horas)]) ?></small></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!$capturas['demanda']): ?>
                <p class="ilustrativo">Ejemplo ilustrativo</p>
            <?php elseif (!empty($l['hero']['pie_captura'])): ?>
                <p class="ilustrativo"><?= formato($l['hero']['pie_captura']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
    <?php $masMensajes = array_slice($capturas['demanda'], 1); // la primera ya se ve en el celular de la portada ?>
    <?php if ($masMensajes || (!$capturas['demanda'] && $mostrar_huecos)): ?>
        <section class="seccion" id="demanda">
            <div class="contenedor">
                <div class="seccion__cabecera">
                    <h2><?= formato($l['demanda']['titulo']) ?></h2>
                    <p><?= formato($l['demanda']['texto']) ?></p>
                </div>
                <?= $galeria($masMensajes, 'Mensajes de clientes pidiendo servicios', 'Aquí van tus capturas de mensajes: súbelas como mensajes-1.jpg, mensajes-2.jpg…') ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($l['problema']['puntos']): ?>
        <section class="seccion seccion--alt" id="problema">
            <div class="contenedor">
                <div class="seccion__cabecera"><h2><?= formato($l['problema']['titulo']) ?></h2></div>
                <ul class="lista-problemas">
                    <?php foreach ($l['problema']['puntos'] as $punto): ?>
                        <li><?= icono('x') ?><span><?= formato($punto) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <p class="cierre-problema"><?= formato($l['problema']['cierre']) ?></p>
            </div>
        </section>
    <?php endif; ?>

    <?php $hayAdelanto = $adelanto !== null || $mostrar_huecos; ?>
    <?php if ($l['historia']['parrafos'] || $hayAdelanto): ?>
        <section class="seccion" id="historia">
            <div class="contenedor historia<?= $hayAdelanto ? ' historia--con-adelanto' : '' ?><?= ($adelanto['vertical'] ?? true) ? ' historia--al-lado' : '' ?>">
                <?php if ($l['historia']['parrafos']): ?>
                    <div class="historia__texto">
                        <h2><?= formato($l['historia']['titulo']) ?></h2>
                        <?php foreach ($l['historia']['parrafos'] as $parrafo): ?>
                            <p><?= formato($parrafo) ?></p>
                        <?php endforeach; ?>
                        <?php if ($l['historia']['firma']): ?><p class="historia__firma">— <?= formato($l['historia']['firma']) ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($hayAdelanto): ?>
                    <?= plantilla('parciales/adelanto', ['adelanto' => $adelanto, 'textos' => $l['adelanto'] ?? [], 'mostrar_huecos' => $mostrar_huecos]) ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($l['como_funciona']['pasos'])): ?>
    <section class="seccion seccion--alt" id="como-funciona">
        <div class="contenedor">
            <div class="seccion__cabecera">
                <h2><?= formato($l['como_funciona']['titulo']) ?></h2>
                <p><?= formato($l['como_funciona']['texto']) ?></p>
            </div>
            <ol class="pasos">
                <?php foreach ($l['como_funciona']['pasos'] as $paso): ?>
                    <li class="paso">
                        <h3><?= formato($paso['titulo']) ?></h3>
                        <p><?= formato($paso['texto']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($l['modulos']['lista']) || !empty($l['bonos']['lista'])): ?>
    <section class="seccion" id="modulos">
        <div class="contenedor">
            <div class="seccion__cabecera">
                <h2><?= formato($l['modulos']['titulo']) ?></h2>
                <p><?= formato($l['modulos']['texto']) ?></p>
            </div>
            <?php if (!empty($l['modulos']['lista'])): ?>
            <ol class="modulos">
                <?php foreach ($l['modulos']['lista'] as $i => $modulo): ?>
                    <li class="modulo">
                        <span class="modulo__num"><?= sprintf('%02d', $i + 1) ?></span>
                        <div>
                            <h3><?= formato($modulo['titulo']) ?></h3>
                            <p><?= formato($modulo['texto']) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>

            <?php if (!empty($l['bonos']['lista'])): ?>
                <h3 class="bonos__titulo"><?= icono('regalo') ?> <?= formato($l['bonos']['titulo']) ?></h3>
                <ul class="bonos">
                    <?php foreach ($l['bonos']['lista'] as $bono): ?>
                        <li class="bono">
                            <strong><?= formato($bono['titulo']) ?></strong>
                            <span><?= formato($bono['texto']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($capturas['resultados'] || $mostrar_huecos): ?>
        <section class="seccion seccion--alt" id="resultados">
            <div class="contenedor">
                <div class="seccion__cabecera">
                    <h2><?= formato($l['resultados']['titulo']) ?></h2>
                    <p><?= formato($l['resultados']['texto']) ?></p>
                </div>
                <?= $galeria($capturas['resultados'], 'Captura de resultados del negocio del autor', 'Aquí van tus capturas de ingresos: ' . ($pais ? "ingresos-$pais-1.jpg, ingresos-$pais-2.jpg…" : 'ingresos-1.jpg, ingresos-2.jpg…') . ' (sin datos de clientes)') ?>
                <p class="aviso"><?= formato($l['resultados']['aviso']) ?></p>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($l['para_quien']['si']) || !empty($l['para_quien']['no'])): ?>
    <section class="seccion" id="para-quien">
        <div class="contenedor">
            <div class="seccion__cabecera"><h2><?= formato($l['para_quien']['titulo']) ?></h2></div>
            <div class="columnas">
                <div class="tarjeta">
                    <h3><?= formato($l['para_quien']['si_titulo']) ?></h3>
                    <ul class="lista-iconos lista-iconos--si">
                        <?php foreach ($l['para_quien']['si'] as $item): ?>
                            <li><?= icono('check') ?><span><?= formato($item) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tarjeta">
                    <h3><?= formato($l['para_quien']['no_titulo']) ?></h3>
                    <ul class="lista-iconos lista-iconos--no">
                        <?php foreach ($l['para_quien']['no'] as $item): ?>
                            <li><?= icono('x') ?><span><?= formato($item) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($capturas['testimonios'] || $l['testimonios']['lista']): ?>
        <section class="seccion seccion--alt" id="testimonios">
            <div class="contenedor">
                <div class="seccion__cabecera"><h2><?= formato($l['testimonios']['titulo']) ?></h2></div>
                <?= $galeria($capturas['testimonios'], 'Testimonio de un cliente', '') ?>
                <?php if ($l['testimonios']['lista']): ?>
                    <div class="testimonios">
                        <?php foreach ($l['testimonios']['lista'] as $testimonio): ?>
                            <figure class="testimonio">
                                <blockquote><?= formato($testimonio['texto']) ?></blockquote>
                                <figcaption><?= formato($testimonio['nombre']) ?></figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="seccion oferta" id="oferta">
        <div class="contenedor">
            <div class="tarjeta-precio">
                <h2><?= formato($l['oferta']['titulo']) ?></h2>
                <ul class="lista-iconos lista-iconos--si">
                    <?php foreach ($l['oferta']['incluye'] as $item): ?>
                        <li><?= icono('check') ?><span><?= formato($item) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($promo): ?>
                    <p class="precio-antes precio-antes--grande">Precio normal <s><?= e($precio_normal) ?></s></p>
                <?php endif; ?>
                <p class="precio-grande"><?= $precio_html ?><small>Pago único · Sin mensualidades</small></p>
                <?php if ($promo): ?>
                    <p class="tiempo-promo">
                        <?= e($negocio['promo']['nombre']) ?><?= $tiempo_promo ? ' · ' . e($tiempo_promo) : '' ?>.
                        <?= formato($l['promo']['despues']) ?>
                    </p>
                <?php endif; ?>
                <?= $boton($l['oferta']['boton'], 'oferta') ?>
                <p class="nota-pago"><?= formato($l['oferta']['nota_pago']) ?></p>
                <?php if (!empty($negocio['metodos_pago'])): ?>
                    <ul class="metodos">
                        <?php foreach ($negocio['metodos_pago'] as $metodo): ?><li><?= e($metodo) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($garantia): ?>
        <section class="seccion" id="garantia">
            <div class="contenedor">
                <div class="garantia">
                    <?= icono('escudo') ?>
                    <div>
                        <h2><?= formato($l['garantia']['titulo']) ?></h2>
                        <p><?= formato($l['garantia']['texto']) ?></p>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($l['faq']['lista']): ?>
        <section class="seccion seccion--alt" id="faq">
            <div class="contenedor">
                <div class="seccion__cabecera"><h2><?= formato($l['faq']['titulo']) ?></h2></div>
                <div class="faq">
                    <?php foreach ($l['faq']['lista'] as $pregunta): ?>
                        <details>
                            <summary><?= formato($pregunta['pregunta']) ?><?= icono('chevron') ?></summary>
                            <p><?= formato($pregunta['respuesta']) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="seccion seccion--oscura cierre" id="cierre">
        <div class="contenedor">
            <h2><?= formato($l['cierre']['titulo']) ?></h2>
            <p><?= formato($l['cierre']['texto']) ?></p>
            <?= $boton($l['cierre']['boton'], 'cierre') ?>
        </div>
    </section>
</main>

<footer class="pie">
    <div class="contenedor">
        <nav aria-label="Páginas legales">
            <a href="/terminos">Términos y condiciones</a>
            <a href="/privacidad">Política de privacidad</a>
            <a href="/reembolsos">Reembolsos</a>
            <a href="/entrar">Acceso alumnos</a>
        </nav>
        <p><?= formato($l['pie']['aviso']) ?></p>
        <p>Usamos cookies para medir nuestros anuncios. Más información en la <a href="/privacidad">política de privacidad</a>.</p>
        <p>© <?= gmdate('Y') ?> <?= e($negocio['producto']) ?></p>
    </div>
</footer>

<div class="barra-fija">
    <p class="barra-fija__precio">
        <?php if ($promo): ?><s><?= e($precio_normal_corto) ?></s><?php endif; ?>
        <?= $precio_html ?>
    </p>
    <?= $boton($l['barra_fija']['boton'], 'barra') ?>
</div>

<dialog class="visor" aria-label="Captura ampliada">
    <img src="data:," alt="">
    <button type="button" class="visor__cerrar" aria-label="Cerrar"><?= icono('x') ?></button>
</dialog>
