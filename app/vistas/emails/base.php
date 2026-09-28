<?php
/**
 * Plantilla HTML de los emails. Estilos en línea y tablas: es lo que entienden todos los
 * programas de correo (Gmail, Outlook, el de iPhone…).
 * @var string     $asunto
 * @var string     $titulo
 * @var array      $parrafos
 * @var string     $boton
 * @var string     $url
 * @var array|null $caja       ['titulo', 'texto', 'boton', 'url']
 * @var string     $despedida
 */
$producto = contenido('negocio')['producto'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($asunto) ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#0f1424;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;">
    <tr><td style="background:#0b1020;padding:18px 24px;color:#ffffff;font-size:16px;font-weight:bold;"><?= e($producto) ?></td></tr>
    <tr><td style="padding:28px 24px 8px;">
        <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#0f1424;"><?= formato($titulo) ?></h1>
        <?php foreach ($parrafos as $parrafo): ?>
            <p style="margin:0 0 14px;font-size:16px;line-height:1.55;color:#374061;"><?= formato($parrafo) ?></p>
        <?php endforeach; ?>
    </td></tr>
    <tr><td align="center" style="padding:6px 24px 10px;">
        <a href="<?= e($url) ?>" style="display:inline-block;background:#25d366;color:#04210f;font-size:17px;font-weight:bold;text-decoration:none;padding:15px 28px;border-radius:12px;"><?= e($boton) ?></a>
    </td></tr>
    <tr><td style="padding:6px 24px 20px;">
        <p style="margin:0;font-size:13px;line-height:1.5;color:#7a82a0;">Si el botón no funciona, copia este enlace en tu navegador:<br><a href="<?= e($url) ?>" style="color:#6445f0;word-break:break-all;"><?= e($url) ?></a></p>
    </td></tr>
    <?php if ($caja): ?>
    <tr><td style="padding:0 24px 22px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1edff;border-radius:12px;">
            <tr><td style="padding:18px;">
                <p style="margin:0 0 8px;font-size:15px;line-height:1.5;font-weight:bold;color:#0f1424;"><?= formato($caja['titulo']) ?></p>
                <?php if ($caja['texto'] !== ''): ?>
                    <p style="margin:0 0 12px;font-size:15px;line-height:1.5;color:#374061;"><?= formato($caja['texto']) ?></p>
                <?php endif; ?>
                <a href="<?= e($caja['url']) ?>" style="display:inline-block;background:#7c5cff;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;padding:11px 18px;border-radius:10px;"><?= e($caja['boton']) ?></a>
            </td></tr>
        </table>
    </td></tr>
    <?php endif; ?>
    <tr><td style="padding:0 24px 26px;">
        <p style="margin:0;font-size:15px;line-height:1.55;color:#374061;"><?= formato($despedida) ?></p>
    </td></tr>
</table>
<p style="margin:16px 0 0;font-size:12px;color:#9aa1b8;"><?= e($producto) ?> · <?= e(config('app.url')) ?></p>
</td></tr>
</table>
</body>
</html>
