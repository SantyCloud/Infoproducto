<?php
/**
 * Estructura de la landing: CSS en línea (carga más rápida desde el anuncio) y Pixel de Meta opcional.
 * @var string      $cuerpo
 * @var string      $titulo
 * @var string      $descripcion
 * @var string      $url
 * @var string|null $pixel_id
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titulo) ?></title>
    <meta name="description" content="<?= e($descripcion) ?>">
    <meta name="theme-color" content="#0b1020">
    <link rel="canonical" href="<?= e($url) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_LA">
    <meta property="og:title" content="<?= e($titulo) ?>">
    <meta property="og:description" content="<?= e($descripcion) ?>">
    <meta property="og:url" content="<?= e($url) ?>">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style nonce="<?= e(csp_nonce()) ?>"><?= css_en_linea('landing.css') ?></style>
    <?php if ($pixel_id): ?>
    <script nonce="<?= e(csp_nonce()) ?>">
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?= e($pixel_id) ?>');
        fbq('track', 'PageView');
    </script>
    <?php endif; ?>
</head>
<body>
<?= $cuerpo ?>
<script src="<?= e(asset('js/landing.js')) ?>" defer></script>
</body>
</html>
