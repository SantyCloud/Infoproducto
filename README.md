# Web de venta — Sistema de Reventa SMM

Landing de venta con cierre por WhatsApp, panel de administración y área de miembros.
PHP + SQLite, sin librerías externas, pensada para Hostinger.

## Probarla en tu computadora

Necesitas PHP 8.2 o superior (`php -v` para comprobarlo).

```bash
php bin/instalar.php
php -S localhost:8000 -t public_html public_html/index.php
```

Abre http://localhost:8000 en el navegador.

## Pruebas automáticas

```bash
php tests/run.php
```

## Dónde se editan los textos

Todo lo que puedes cambiar sin tocar código está en `contenido/`:

- `negocio.php`: nombre del producto, precios, promoción, WhatsApp, métodos de pago y enlace de smmclixy.
- Fase 2: `landing.php`, con todos los textos de la página de venta.

## Publicarla en Hostinger

Los pasos detallados llegan en la fase 7.

Más detalles técnicos y decisiones del proyecto en [CLAUDE.md](CLAUDE.md).
