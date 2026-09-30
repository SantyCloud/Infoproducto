# Publicar la web en Hostinger (plan Premium)

Guía paso a paso. Tiempo estimado: 45–60 minutos la primera vez.
Donde veas `tudominio.com` o `uXXXXXXXXX`, pon tus datos reales.

---

## 0. Antes de empezar

Necesitas:

- Tu plan Hostinger Premium. Puede ser el mismo donde ya tienes otros sitios: esta web va como un sitio más (paso 1).
- Acceso a este repositorio de GitHub.
- (Para los emails) una cuenta gratis en [resend.com](https://resend.com).
- (Para Meta) acceso a tu Administrador de eventos de Meta.

> **Repositorio público:** mientras sea público, cualquiera puede leer el código (no tiene claves, eso está bien).
> Pero **no subas a GitHub el contenido pagado del curso**: va en `storage/curso/` del servidor (paso 9).
> Lo más cómodo es hacerlo privado: GitHub → tu repo → **Settings → General → Change visibility → Private**.

---

## 1. Preparar el sitio en hPanel

0. **Agregar la web al plan:** hPanel → **Sitios web → Agregar sitio web** → elige tu plan Premium → sitio vacío → **usar un dominio que ya tengo** → escribe `tudominio.com`.
   - **Si el dominio está en otra cuenta de Hostinger**, hPanel avisa que está registrado en otra cuenta. Tienes dos caminos:
     - **Moverlo a esta cuenta (recomendado: todo en un solo lugar).** Es gratis, no pide código y la web no se cae. Solo se puede **96 horas después de registrarlo**: antes, el botón no aparece.
       1. En la cuenta donde está el dominio: **Dominios → Transferencias → Mover dominio a otra cuenta de Hostinger** (o dentro del dominio: **Vista general → Transferencia**, al final de la columna derecha) → elige el dominio → **Iniciar movimiento de dominio** → escribe el email de esta cuenta. No uses la casilla "Transferir" de esa página: es para traer dominios de otras empresas, y se paga.
       2. En esta cuenta: **Dominios → Aceptar → Confirmar** → completa tus datos de contacto **reales** (el registro de dominios los exige y sirven para demostrar que el dominio es tuyo; con la protección de privacidad nadie los ve) → **Finalizar registro**. Después revisa que la **renovación automática** siga activada en esta cuenta.
       3. Confirma los emails de verificación que llegan a los dos correos.
       - Si el botón no aparece pasadas las 96 horas, pídeselo al chat de soporte de Hostinger: lo hacen ellos.
     - **Dejarlo donde está.** Copia el valor **TXT** que muestra hPanel. En la otra cuenta, ve a **Dominios → tudominio.com → DNS** y crea un registro **TXT** con nombre `@`, ese valor y TTL `900`. Espera hasta 24 horas y vuelve a agregar el sitio. Los registros DNS de después (los de Resend, paso 6) se crean en esa otra cuenta.
   - **Dominio nuevo:** confirma el email de verificación que manda Hostinger (desde `@hostinger-domains.com`) antes de 15 días. Si no, **suspenden el dominio**. Moverlo de cuenta vuelve a pedir esa verificación.
   - **Si el plan ya tiene otros sitios:** en los pasos siguientes elige siempre **este** sitio (arriba, en el selector de sitios web), así no cambias la configuración de los otros.
1. **SSL (https):** en hPanel → **Sitios web → tu sitio → Seguridad → SSL**, comprueba que el certificado esté **activo**. Hostinger lo instala gratis; puede tardar unos minutos tras conectar el dominio.
2. **Versión de PHP (de este sitio):** **Avanzado → Configuración de PHP** → elige **PHP 8.3** (sirve 8.2 o superior). En la pestaña de extensiones, deja activadas `pdo_sqlite`, `sqlite3`, `curl`, `gd`, `mbstring` y `fileinfo` (vienen activas por defecto).
3. **SSH:** **Avanzado → Acceso SSH → Habilitar**. Anota la **IP**, el **puerto (65002)** y el **usuario** (`uXXXXXXXXX`). La contraseña es la de tu cuenta FTP (Archivos → Cuentas FTP).

---

## 2. Conectarte por SSH

Desde la terminal de tu computadora (en Windows: PowerShell):

```bash
ssh -p 65002 uXXXXXXXXX@IP-DE-TU-SERVIDOR
```

Averigua la ruta del PHP correcto (los comandos y el cron deben usarlo):

```bash
ls /opt/alt/ | grep php          # verás php82, php83…
/opt/alt/php83/usr/bin/php -v    # debe decir PHP 8.3.x
```

Para no escribir la ruta larga en cada comando de esta sesión:

```bash
export PATH=/opt/alt/php83/usr/bin:$PATH
php -v
```

---

## 3. Descargar el proyecto

El proyecto va **en la carpeta del dominio**, no dentro de `public_html`: así la carpeta `public_html/` del
proyecto pasa a ser la web y el código, el `.env` y la base de datos quedan fuera del alcance de internet.
Si el plan tiene otros sitios, cada uno tiene su carpeta en `~/domains/`: trabaja solo en la de este dominio.

```bash
cd ~/domains/tudominio.com
mv public_html public_html_anterior      # guarda lo que puso Hostinger (puedes borrarlo después)
git init -q
git remote add origin https://github.com/SantyCloud/Infoproducto.git
git fetch origin
git checkout -t origin/claude/zealous-galileo-7m4972
```

> Cuando el código esté en la rama `main`, usa `origin/main` en la última línea.
>
> **Si el repositorio es privado**, el servidor necesita una "deploy key" (llave de solo lectura) para descargarlo.
> Crea la llave:
>
> ```bash
> mkdir -p ~/.ssh && chmod 700 ~/.ssh
> ssh-keygen -t ed25519 -f ~/.ssh/github-infoproducto -N ""
> cat ~/.ssh/github-infoproducto.pub
> ```
>
> Copia lo que muestra en GitHub → tu repo → **Settings → Deploy keys → Add deploy key**, sin marcar "Allow write access".
> Luego, en lugar de la línea `git remote add` de arriba:
>
> ```bash
> ssh-keyscan github.com >> ~/.ssh/known_hosts
> git remote add origin git@github.com:SantyCloud/Infoproducto.git
> git config core.sshCommand "ssh -i ~/.ssh/github-infoproducto -o IdentitiesOnly=yes"
> ```
>
> Así `git pull` funciona siempre, también el del botón de publicar desde GitHub (paso 11).

---

## 4. Instalar y configurar

```bash
php bin/instalar.php      # crea .env con una clave secreta, las carpetas y la base de datos
nano .env                 # edita (Ctrl+O para guardar, Ctrl+X para salir)
```

En el `.env` cambia como mínimo la dirección de tu web (`ENTORNO` ya viene en `produccion`):

```ini
URL_SITIO=https://tudominio.com
```

Crea tu usuario del panel (la contraseña no se ve mientras la escribes):

```bash
php bin/crear-admin.php
```

Abre `https://tudominio.com` → debe verse la landing. Y `https://tudominio.com/admin` → entra con tu usuario.

---

## 5. Tus datos y textos

Edítalos en tu computadora (o en GitHub) y súbelos; después, en el servidor, `git pull` (paso 11):

- `contenido/negocio.php`: **número de WhatsApp**, precio, **fecha real de fin de la promo**, días de garantía, enlace de registro de smmclixy (y código de bono), datos legales.
- `contenido/landing.php`: tu historia (lo que está `[entre corchetes]`), módulos, bonos, preguntas.
- `contenido/capturas/`: tus capturas **ya difuminadas** (`mensajes-1.jpg`, `ingresos-1.jpg`, `testimonio-1.jpg`…).
- `contenido/legal/`: revisa las plantillas legales (idealmente con un abogado).

El panel (`/admin`) muestra en **Pendientes** lo que todavía falta.

---

## 6. Emails con Resend

1. En [resend.com](https://resend.com) → **Domains → Add Domain** → escribe `tudominio.com`.
2. Resend te mostrará unos registros DNS (normalmente un **MX** y un **TXT** para `send`, y un **TXT** para `resend._domainkey`).
3. En hPanel (en la cuenta donde está el dominio) → **Dominios → tudominio.com → DNS / Nameservers → Administrar registros DNS**, crea cada registro **copiando exactamente** tipo, nombre y valor. Recomendado además: un TXT con nombre `_dmarc` y valor `v=DMARC1; p=none;`.
4. Vuelve a Resend y pulsa **Verify**. Puede tardar desde minutos hasta unas horas.
5. En Resend → **API Keys → Create API Key** (permiso *Sending access*). Copia la clave en el `.env`:

```ini
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxx
EMAIL_REMITENTE="Método Revendedor SMM <acceso@tudominio.com>"
EMAIL_RESPONDER_A=tu-correo@gmail.com
```

Prueba: en el panel → **Dar acceso** → pon tu propio email → deberías recibir el email en segundos.

---

## 7. Pixel de Meta y API de Conversiones

1. En el **Administrador de eventos** de Meta → **Conectar orígenes de datos → Web** → crea el conjunto de datos (Pixel). Copia el **ID del Pixel**.
2. En ese Pixel → **Configuración → API de Conversiones → Generar token de acceso**. Copia el token.
3. En el `.env`:

```ini
META_PIXEL_ID=123456789012345
META_CAPI_TOKEN=EAAB...
```

4. **Probar:** en el Administrador de eventos → **Probar eventos** → copia el código (ej. `TEST12345`) y ponlo en `META_TEST_EVENT_CODE`. Abre tu web, toca el botón de WhatsApp, registra una venta de prueba y actívala con su enlace: deberían aparecer **PageView**, **Contact** (navegador y servidor, deduplicados) y **Purchase** (servidor, al activar).
5. Cuando termines de probar, **borra** `META_TEST_EVENT_CODE` del `.env` (el panel te lo recuerda).

**En tus anuncios**, pon esto en **Seguimiento → Parámetros de URL** para que el panel te diga qué anuncio vende:

```
utm_source=facebook&utm_medium=paid&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&utm_term={{adset.name}}
```

---

## 8. Tareas automáticas (cron)

hPanel → **Avanzado → Cron Jobs** → tipo **Personalizado**, cada 5 minutos (`*/5 * * * *`). Crea uno **nuevo** (si el plan tiene
otros sitios, no cambies sus tareas). Comando:

```
/opt/alt/php83/usr/bin/php /home/uXXXXXXXXX/domains/tudominio.com/bin/tareas.php
```

Hace tres cosas: reintenta los eventos de Meta que fallaron, borra datos que ya no hacen falta (IP y navegador
de los clics de hace más de 90 días, sesiones vencidas…) y guarda una **copia diaria de la base de datos** en
`storage/respaldos/` (conserva las últimas 14). Descarga una copia de vez en cuando desde el Administrador de archivos.

---

## 9. El curso real (privado)

El curso de ejemplo está en `contenido/curso/`. Tu curso real va en `storage/curso/`, que **nunca** se sube a GitHub:

```bash
cp -r contenido/curso storage/curso
nano storage/curso/curso.php        # módulos, lecciones y enlaces de YouTube/Drive
```

- Texto de cada lección: `storage/curso/lecciones/SLUG.md`.
- Archivos para descargar (PDF, Excel…): súbelos a `storage/curso/descargables/` con el **Administrador de archivos** de hPanel y agrégalos a la lista `descargables` de `curso.php`.
- Videos: YouTube en modo **oculto** o Google Drive con "Cualquier persona con el enlace". Pega el enlace en `'video' => '…'`.

---

## 10. Comprobaciones finales

- [ ] `https://tudominio.com` carga con candado (https) y rápido en el celular.
- [ ] `https://tudominio.com/.env` y `https://tudominio.com/storage/base.sqlite` **no** muestran nada (403 o 404).
- [ ] El botón de WhatsApp abre tu chat con el mensaje y el código.
- [ ] `https://tudominio.com/ec` y `https://tudominio.com/mx` cargan con su precio (en México, en pesos: "$200 MXN"). Son las direcciones que pones en los anuncios de cada país.
- [ ] En el panel ves el clic; registras una venta de prueba con ese código ("Con un enlace de activación"); tocas "Enviar por WhatsApp", te mandas el mensaje a ti mismo, abres el enlace, escribes tu nombre y tu email y entras al curso; te llega el email de bienvenida.
- [ ] En Meta ves Contact y Purchase (con el código de prueba).
- [ ] Revocas la venta de prueba (ficha del comprador → Revocar acceso).
- [ ] El cron aparece como ejecutado en hPanel y existe `storage/respaldos/base-FECHA.sqlite`.

---

## 11. Actualizar la web después

Cada vez que cambies algo en GitHub:

```bash
ssh -p 65002 uXXXXXXXXX@IP-DE-TU-SERVIDOR
cd ~/domains/tudominio.com
git pull
/opt/alt/php83/usr/bin/php bin/instalar.php     # aplica cambios de base de datos y optimiza capturas nuevas
```

### Opcional: publicar con un botón desde GitHub

En vez de entrar por SSH, puedes publicar desde GitHub (también desde la app del celular):

1. Crea una clave SSH para GitHub en tu computadora: `ssh-keygen -t ed25519 -f hostinger-github -N ""`.
2. En hPanel → **Avanzado → Acceso SSH → Claves SSH**, agrega el contenido de `hostinger-github.pub`.
3. En GitHub → tu repo → **Settings → Secrets and variables → Actions**, crea estos secrets:
   `HOSTINGER_HOST` (IP), `HOSTINGER_PORT` (`65002`), `HOSTINGER_USER` (`uXXXXXXXXX`),
   `HOSTINGER_SSH_KEY` (contenido del archivo `hostinger-github`, la clave privada),
   `HOSTINGER_RUTA` (`/home/uXXXXXXXXX/domains/tudominio.com`), `HOSTINGER_PHP` (`/opt/alt/php83/usr/bin/php`) y
   `HOSTINGER_KNOWN_HOSTS`: la huella de tu servidor, para que GitHub no se conecte a un impostor. Obtenla en tu
   computadora con `ssh-keyscan -p 65002 IP-DE-TU-SERVIDOR` y pega todo lo que muestre.
4. Para publicar: pestaña **Actions → Desplegar en Hostinger → Run workflow**. Primero corre las pruebas;
   si alguna falla, no publica nada.

Además, en cada cambio que subas, GitHub ejecuta las pruebas automáticamente (pestaña **Actions → Pruebas**).

## Si algo falla

- **Página "Algo salió mal":** mira `storage/logs/errores-AAAA-MM.log`.
- **Emails:** `storage/logs/emails-AAAA-MM.log` y la ficha del comprador en el panel.
- **Meta:** `storage/logs/meta-AAAA-MM.log`.
- **Olvidaste la contraseña del panel:** `php bin/crear-admin.php` de nuevo.
- **El panel dice "Demasiados intentos":** espera 15 minutos o ejecuta `php bin/desbloquear-panel.php`.
  En el celular o la computadora donde ya entraste antes no te afecta: queda recordado.
