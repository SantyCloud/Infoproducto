# CLAUDE.md

Guía del proyecto para retomarlo en futuras sesiones. Todo en **español**: código, comentarios, commits y explicaciones al dueño.

## Qué es

Web de venta y entrega de un infoproducto: un **sistema/curso de reventa SMM** que enseña a montar un negocio revendiendo servicios SMM (seguidores, likes, vistas…) con **smmclixy.com** (el panel del dueño) como proveedor.

- Doble objetivo: vender el curso y que cada comprador se registre en smmclixy.com.
- Tráfico: anuncios de Meta (Instagram/Facebook), casi todo desde el celular, en Latinoamérica.
- Precio: $15 normal, $10 en promoción con fecha de fin real.

## Flujo de venta (no hay pasarela de pago)

1. Anuncio → **landing**, cuyo único trabajo es convencer. Se guardan los UTM y el fbclid.
2. El visitante toca el botón → `/wa` crea un **lead** con un código corto (ej. `K7Q2`) y lo manda a WhatsApp con un mensaje ya escrito que incluye ese código.
3. El dueño cierra y cobra **por WhatsApp** (transferencia bancaria, PayPal, Binance/USDT).
4. En el **panel admin** registra la venta (código + nombre + email + WhatsApp + monto) → se crea el acceso, se envía el email (Resend) y el evento **Purchase** a Meta (API de Conversiones) con los datos del clic original.
5. El comprador entra al **área de miembros** con un enlace mágico, sin contraseña.

## Decisiones y motivos

| Tema | Decisión | Motivo |
|---|---|---|
| Pago | Sin pasarela: cierre manual por WhatsApp | Decisión del dueño. No hay webhooks de pago: el acceso solo lo crea el admin y ninguna URL pública lo otorga. |
| Hosting | Hostinger **Premium** (compartido) | Plan del dueño: PHP, SSH, Git y cron, pero no Node.js. |
| Stack | PHP 8.2+ sin framework **ni dependencias** (sin Composer) | Nada que instalar ni actualizar en el servidor; lo mantiene una sola persona. |
| Base de datos | **SQLite** (`storage/base.sqlite`) | Sin configuración, igual en local y en producción, poco volumen. Copia diaria por cron (fase 7). |
| Emails | **Resend** (API HTTP con curl) | Llega mejor que el SMTP compartido. Gratis: 100/día y 3.000/mes. |
| Videos | **YouTube (oculto) o Google Drive** | El dueño quiere que sus usuarios de smmclixy también los vean, así que los videos NO son exclusivos. La protección se centra en el área de miembros y los descargables. Preferir YouTube: Drive corta la reproducción de los archivos muy vistos. |
| Acceso de miembros | Enlace mágico por email, de un solo uso y confirmado con un botón (POST); sesión de 90 días; máximo 3 dispositivos | Sin contraseñas que olvidar. El botón evita que los antivirus del correo "gasten" el enlace al escanearlo. El admin puede copiar el enlace y mandarlo también por WhatsApp. |
| Precio | $15 tachado → $10 con fecha de fin **real** (`contenido/negocio.php`) | Un precio anterior ficticio es publicidad engañosa (Ley Orgánica de Defensa del Consumidor, art. 7). Al vencer la fecha, la web muestra $15 sola. Nada de contadores falsos. |
| Medición | Pixel (PageView, Contact) + API de Conversiones (Contact con el mismo `event_id`; Purchase al registrar la venta) | Meta aprende de las ventas reales, aunque se cierren por WhatsApp. |

## Estructura

```
app/             código PHP (no accesible desde la web)
  bootstrap.php  arranque común (web, comandos y pruebas)
  rutas.php      tabla de rutas: [método, ruta, función]
  lib/           funciones por tema: env, config, db, migraciones, http, vista, contenido, negocio, registro
  paginas/       funciones que atienden cada ruta y devuelven una respuesta
  vistas/        plantillas HTML (layout.php + una por página)
  migraciones/   cambios de la base de datos (NNN_nombre.sql; se aplican en orden y una sola vez)
bin/             comandos: instalar.php
contenido/       lo que edita el dueño: negocio.php (precios, WhatsApp…); luego landing, curso, emails y legales
public_html/     raíz web: index.php (único punto de entrada), .htaccess, robots.txt, assets/
storage/         fuera de Git: base.sqlite, logs/, respaldos/, descargables/
tests/           pruebas: php tests/run.php
```

**Producción:** el repo se clona en `/home/uXXXX/domains/DOMINIO/`. Así la carpeta `public_html/` del repo **es** la raíz web de Hostinger, y el código, el `.env` y la base de datos quedan fuera del alcance de internet. Para actualizar basta con `git pull` + `php bin/instalar.php`. En Hostinger no se puede cambiar la raíz web, por eso la carpeta pública se llama `public_html`.

## Convenciones

- Código, tablas, comentarios y mensajes en **español**. `declare(strict_types=1)` en cada archivo PHP de código (los de `contenido/` son solo datos y se mantienen simples para el dueño).
- Funciones simples agrupadas por tema en `app/lib/`. Nada de clases salvo que aporten algo claro.
- Cada ruta devuelve una respuesta (`html()`, `redireccion()`, `pagina_error()`) y `despachar()` la envía.
- SQL **siempre** con parámetros (`db_fila`, `db_filas`, `db_valor`, `db_ejecutar`, `db_insertar`, `db_transaccion`). Nunca se concatenan datos del usuario.
- En las vistas, todo dato va escapado: `<?= e($dato) ?>`. Hay CSP: los `<script>` y `<style>` en línea necesitan `nonce="<?= csp_nonce() ?>"`.
- Fechas en la BD en **UTC** (`Y-m-d H:i:s`, con `ahora_bd()`); se muestran en `ZONA_HORARIA` (America/Guayaquil). Dinero en **centavos** (INTEGER).
- Los textos editables van solo en `contenido/` y los secretos solo en `.env`, que nunca se sube a Git.
- Compatibilidad con **PHP 8.2** (Hostinger): no usar `json_validate`, `array_find`, property hooks, constantes tipadas ni otras novedades de 8.3/8.4.
- Los avisos de PHP se convierten en excepciones (`app/bootstrap.php`), así que el código no debe generar warnings.
- Cambios de base de datos: un archivo nuevo en `app/migraciones/`. Nunca se edita uno ya aplicado en producción.
- Commits pequeños por fase, en español. Al terminar cada fase: pruebas en verde, capturas del celular y pasos para que el dueño lo pruebe.

## Comandos

```bash
php bin/instalar.php                                        # crea .env, carpetas y base de datos (se puede repetir)
php -S localhost:8000 -t public_html public_html/index.php  # web local → http://localhost:8000
php tests/run.php                                           # pruebas (incluye peticiones reales a un servidor temporal)
```

## Fases

- [x] 1. Estructura, base de datos y pruebas
- [ ] 2. Landing + páginas legales
- [ ] 3. Leads (botón de WhatsApp con código) + panel admin
- [ ] 4. Área de miembros
- [ ] 5. Emails (Resend)
- [ ] 6. Pixel + API de Conversiones
- [ ] 7. Despliegue en Hostinger + revisión de seguridad

## Pendiente del dueño

- Nombre definitivo del producto, dominio y número de WhatsApp.
- Capturas (ingresos y "miles de mensajes") con los datos de los clientes difuminados, en `contenido/capturas/`.
- Su historia (cómo empezó, qué logró) para la sección "Mi historia" de la landing.
- Garantía (¿cuántos días de devolución?), módulos del curso y bonos.
- Enlace de registro o de referido de smmclixy y código de bono (opcional).
- Cuentas: Resend (fase 5), Pixel + token de la API de Conversiones (fase 6), acceso SSH a Hostinger (fase 7).

## Repositorio público

A 27-09-2026 el repo `SantyCloud/Infoproducto` es **público** (se recomendó al dueño hacerlo privado). Mientras lo sea:

- **Nada de contenido pagado en Git**: ni textos de las lecciones ni descargables. Se guardan en `storage/` del servidor.
- Las capturas van en `contenido/capturas/`, siempre ya difuminadas.
- Los secretos, como siempre, solo en `.env`.

## Riesgos conocidos

- **Política de Meta:** prohíbe vender o comprar interacción (likes, seguidores, vistas) y revisa tanto el anuncio como la landing. Puede rechazar anuncios o restringir la cuenta publicitaria.
- **Capturas de ingresos:** Meta rechaza "recompensas económicas poco realistas por poco esfuerzo". Hay que mostrarlas con contexto y el aviso "resultados no garantizados", sin promesas del tipo "gana $X en Y días".
- **Datos personales en las capturas:** difuminar nombres, números y fotos de clientes (Ley Orgánica de Protección de Datos Personales).
