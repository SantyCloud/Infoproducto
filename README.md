# Web de venta — Sistema de Reventa SMM

Landing de venta con cierre por WhatsApp, panel de administración y área de miembros.
PHP + SQLite, sin librerías externas, pensada para Hostinger.

- **Landing** (`/`): textos editables, precio con promoción real, capturas, botón de WhatsApp con código.
- **Botón de WhatsApp** (`/wa`): registra de qué anuncio viene cada persona y abre el chat con un código corto.
- **Panel** (`/admin`): registras la venta con ese código → se crea el acceso, se envía el email y se avisa a Meta.
- **Área de miembros** (`/miembros`): se entra con un enlace mágico (sin contraseña); lecciones y descargables protegidos.

## Publicarla

Sigue **[DESPLIEGUE.md](DESPLIEGUE.md)** (Hostinger, paso a paso).

## Probarla en tu computadora

Necesitas PHP 8.2 o superior (`php -v`).

```bash
php bin/instalar.php
php bin/crear-admin.php
php -S localhost:8000 -t public_html public_html/index.php
```

Abre http://localhost:8000 (landing) y http://localhost:8000/admin (panel). Sin Resend configurado,
los emails se "simulan": el panel te muestra el enlace de acceso para probarlo. Para ver el detalle
de los errores mientras pruebas, pon `ENTORNO=local` en tu `.env`.

## Pruebas automáticas

```bash
php tests/run.php
```

## Dónde se editan los textos

Todo lo que puedes cambiar sin tocar código está en `contenido/`:

- `negocio.php`: nombre, precios, promoción, garantía, WhatsApp, métodos de pago, smmclixy y datos legales.
- `landing.php`: todos los textos de la página de venta.
- `emails.php`: textos de los emails.
- `legal/`: términos, privacidad y reembolsos.
- `capturas/`: tus capturas (ya difuminadas).
- `curso/`: curso de ejemplo; el real va en `storage/curso/` del servidor.

Decisiones técnicas y convenciones en [CLAUDE.md](CLAUDE.md). Revisión de seguridad en [SEGURIDAD.md](SEGURIDAD.md).
