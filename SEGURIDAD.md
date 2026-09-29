# Revisión de seguridad

Revisión previa a producción de las cuatro áreas pedidas: **peticiones falsificadas, acceso sin pagar,
fuga de contenido y datos expuestos**. Cada punto indica cómo está protegido y qué prueba automática lo
demuestra (`php tests/run.php`; también se ejecutan en GitHub en cada cambio).

Fecha: 28-09-2026 (actualizada el 29-09-2026 con los enlaces de activación).

---

## 1. Peticiones falsificadas

No hay pasarela de pago ni webhooks: **ninguna petición externa puede confirmar una venta**. Las ventas solo
se registran desde el panel, con sesión de administrador.

| Riesgo | Protección | Prueba |
|---|---|---|
| Formularios enviados desde otra web (CSRF) | Token de doble envío (cookie + campo oculto) en **todos** los formularios POST, comprobación del `Origin` (también se rechaza `Origin: null`) y cookies `SameSite` | `ventas`: "los formularios sin token CSRF válido se rechazan"; `seguridad`: Origin "null"; `http`: recorrido completo (403 sin token) |
| Adivinar la contraseña del panel | Hash bcrypt en `.env` (mínimo 10 caracteres); 5 intentos cada 15 minutos por IP (en IPv6, por red /64); la contraseña se comprueba siempre contra el hash configurado, así la respuesta tarda lo mismo sea cual sea el usuario | `ventas`: "entrar al panel…se bloquea tras 5 intentos"; `seguridad`: "con usuario incorrecto también se comprueba la contraseña" |
| Dejar al dueño fuera del panel fallando a propósito | No hay contador global: cada IP tiene el suyo, y el celular o la computadora donde ya entraste queda recordado (marca firmada con `CLAVE_APP`) con un contador propio. Si hiciera falta: `php bin/desbloquear-panel.php` | `seguridad`: "nadie puede bloquear al dueño…", "en un dispositivo donde ya entró…" |
| Inflar las estadísticas con clics falsos a `/wa` | Máximo 30 leads nuevos por hora por IP (en IPv6, por red /64), se ignoran robots, un visitante conserva su código 7 días; la persona igual llega a WhatsApp, aunque mande parámetros raros | `leads`: límite de intentos y robots; `seguridad`: "/wa con parámetros o cookies raros…" |
| Falsear la IP con la cabecera `X-Forwarded-For` | Se ignora, salvo con `CONFIAR_PROXY=true` (solo si usas Cloudflare u otro proxy); entonces se toma la **última** IP, la que agrega el proxy | `seguridad`: "con proxy de confianza…" |
| Publicar desde GitHub hacia un servidor impostor | La huella del servidor se guarda como secret (`HOSTINGER_KNOWN_HOSTS`) y `ssh` exige que coincida | revisión de código |
| Redirecciones abiertas | `/wa` solo redirige a `wa.me` con tu número y un mensaje codificado; el resto de redirecciones son rutas internas fijas | revisión de código |

## 2. Acceso sin pagar

| Riesgo | Protección | Prueba |
|---|---|---|
| Crear un acceso sin ser el dueño | Solo `/admin` (con sesión) crea accesos; todas sus páginas redirigen al login sin sesión | `ventas`: "el panel exige iniciar sesión en todas sus páginas" |
| Adivinar un enlace mágico | 256 bits aleatorios; en la base solo se guarda su hash (HMAC con `CLAVE_APP`) | `miembros`: "se guarda el hash del enlace, nunca el enlace" |
| Adivinar un código de activación (`/activar`) | 10 caracteres sin letras confusas (31^10, unos 50 bits); en la base solo su hash; 10 intentos cada 15 minutos por IP (en IPv6, por red /64); vence a los 30 días. Aun con 65.000 redes IPv6 (las que da gratis un túnel IPv6) probando sin parar, harían falta unos 7 años para tener un 1 % de probabilidad de acertar alguno de 50 pagos pendientes. **Sin tope global** a propósito: con él, cualquiera podría frenar la activación de todos los compradores equivocándose desde varias IP | `activaciones`: "probar códigos al azar tiene límite por IP, y los errores de otros no frenan…", "solo se guarda el hash del código" |
| Usar dos veces el mismo código | Se marca como usado en la misma transacción que crea la venta: solo una petición puede hacerlo. Un enlace nuevo anula el anterior; un pago anulado ya no se activa | `activaciones`: "sirve una sola vez", "un enlace nuevo anula el anterior…" |
| Entrar a una cuenta ajena escribiendo su email al activar | Si el email ya es de un comprador, no se abre sesión ni se cambia su nombre: la compra se le suma y el enlace para entrar llega a **ese** correo | `activaciones`: "si el email ya es de un comprador…" |
| Ocupar de antemano el email de otra persona (activar tu pago con su email) y seguir dentro cuando ella compre | Toda compra con un email que ya existía (panel, enlace de activación o acceso manual) cierra las sesiones abiertas de esa cuenta; su dueño entra con el enlace que le llega a su correo | `activaciones`: "quien ocupó el email de otra persona…" |
| Registrar dos pagos del mismo clic (dos pestañas del panel a la vez) | El clic se vuelve a revisar dentro de la transacción que guarda el pago; la otra pestaña muestra el aviso | `activaciones`: "dos formularios del mismo clic enviados a la vez…" |
| Reutilizar o compartir el enlace | Un solo uso (consumo atómico en la base, ni con dos clics simultáneos), vence (7 días / 30 minutos) y el nuevo anula los anteriores | `miembros`: "sirve una sola vez", "vence…"; `http`: el enlace usado da 410 |
| Antivirus del correo que "gastan" el enlace | Abrir el enlace solo muestra un botón; se consume al pulsarlo (POST) | `http`: abrirlo dos veces no lo gasta |
| Compartir la cuenta | Máximo 3 dispositivos; el cuarto cierra la sesión más antigua | `miembros`: "como máximo 3 dispositivos" |
| Seguir entrando tras un reembolso | "Revocar acceso" cierra sus sesiones y anula sus enlaces al instante | `ventas` y `http`: revocar |
| Usar una sesión de alumno como de admin (o al revés) | Sesiones con tipo distinto y cookies distintas | `miembros`: "una sesión de miembro no sirve como sesión de admin" |

## 3. Fuga de contenido

| Riesgo | Protección | Prueba |
|---|---|---|
| Ver lecciones o descargables sin sesión | Todas las rutas de `/miembros` exigen sesión y acceso vigente | `miembros`: "sin sesión…redirigen a /entrar" |
| URLs de descarga adivinables o salir de la carpeta | La URL lleva un identificador de la lista de `curso.php`, nunca un nombre de archivo; `basename` y `realpath` impiden salir de `descargables/` | `miembros`: "los descargables no permiten salir de su carpeta" |
| El contenido del curso en GitHub | El curso real va en `storage/curso/`, fuera de Git; el de `contenido/curso/` es solo un ejemplo | — |
| Páginas privadas en cachés | `Cache-Control: no-store` y `noindex` en panel y área de miembros | revisión de código |
| Videos | YouTube oculto o Drive: **no son privados** (decisión del dueño, para que los usuarios de smmclixy también los vean). Lo protegido son las páginas del curso y los descargables | — |

## 4. Datos expuestos

| Riesgo | Protección | Prueba |
|---|---|---|
| Descargar `.env`, la base de datos o el código | Todo vive **fuera** de `public_html`; además `.htaccess` bloquea archivos ocultos y `storage/.htaccess` lo bloquea todo | `http`: "no se puede descargar nada privado" (incluye rutas con `../`) |
| Errores que muestran rutas del servidor | En producción, página genérica, errores solo en `storage/logs/` y `display_errors` apagado. `.env.example` trae `ENTORNO=produccion`: si olvidas cambiarlo, la web queda protegida igual | revisión de código |
| Secretos o datos en los logs | En producción, los emails simulados (sin Resend) se guardan **sin** el enlace de acceso; los intentos fallidos del panel guardan la IP, no lo que se escribió como usuario; la ruta de cada error se recorta | `seguridad`: "en producción, los emails simulados no dejan enlaces…" |
| Inyección de código en las páginas (XSS) | Todo dato se escapa (`e()`, `formato()`, `markdown()` escapa antes de dar formato; solo enlaces `http(s)`, `mailto` o rutas propias); CSP estricta con `nonce` y sin `unsafe-inline` | `vista`, `landing`: escape y enlaces peligrosos |
| Inyección SQL | Todas las consultas con parámetros; los nombres de columnas se validan | `db`: "rechaza nombres de columna sospechosos" |
| Saber quién compró (enumeración de emails) | `/entrar` responde igual, y en el mismo tiempo, exista o no el email (el email se envía después de responder); límite de pedidos por email e IP | `miembros`: "misma respuesta exista o no el email" |
| Llenar de emails a un comprador o gastar el cupo de Resend | Por email: 3 enlaces cada 15 minutos y 6 al día. En total: 60 emails de `/entrar` al día, para que siempre quede cupo (100/día en el plan gratis) para los de compra | `seguridad`: "/entrar: tope diario por email y tope global…" |
| Fórmulas maliciosas al abrir el CSV en Excel | Todo va entre comillas y se neutraliza con un apóstrofo cualquier `= + - @` al inicio del texto **o después de una coma o un punto y coma** (por si Excel separa las columnas con otro carácter) | `ventas`: "exportar CSV…" |
| Datos personales enviados a Meta | Email, teléfono y nombre van en hash SHA-256; IP y navegador de los clics se borran a los 90 días (cron) | `meta`, `mantenimiento` |
| Cookies robables | Sesiones `HttpOnly`, `Secure` con https, `SameSite` (`Strict` en el panel) | `ventas`: cookie del panel |
| El enlace mágico (o el código de activación) en el "Referer" | Sus páginas usan `Referrer-Policy: same-origin`: el enlace nunca se envía a otros sitios. (No `no-referrer`: con esa política el navegador envía el botón con `Origin: null` y nadie podría entrar) | `seguridad`: Origin "null" |
| Web incrustada en otra (clickjacking) | `frame-ancestors 'none'` y `X-Frame-Options: DENY` | `http`: cabeceras |

## Revisión independiente (28-09-2026)

Un segundo revisor atacó la web sin ver este documento: leyó todo el código y probó cada idea contra un
servidor de prueba. **Conclusión: nadie puede conseguir acceso al curso sin que el dueño lo cree.** Encontró
1 problema de gravedad media, 3 bajos y 6 informativos. Todos están corregidos, con una prueba automática
(archivo `tests/seguridad_test.php` y otros), salvo lo que se indica.

| # | Hallazgo | Gravedad | Estado |
|---|---|---|---|
| 1 | Cualquiera podía bloquear el panel al dueño equivocándose a propósito desde varias IP (había un contador global de 30 intentos) | Media | Corregido: sin contador global; contador por IP y por dispositivo conocido; `bin/desbloquear-panel.php` |
| 2 | El tiempo de respuesta delataba si el usuario del panel era correcto (el hash falso y el real tenían distinto costo) | Baja | Corregido: la contraseña se comprueba siempre contra el hash configurado |
| 3 | Se podían pedir enlaces de `/entrar` sin parar para un email: molestia al comprador y cupo de Resend agotado | Baja | Corregido: 6 por email al día y 60 en total al día |
| 4 | Con `CONFIAR_PROXY=true`, la IP se podía falsear con `X-Forwarded-For` | Baja | Corregido: se usa la última IP; IPv6 por red /64 (y las IPv4 escritas como IPv6 cuentan como IPv4) |
| 5 | `/wa?b[]=1` (parámetros como lista) daba error 500 y llenaba el log | Info | Corregido: `texto_de()` en todos los datos del navegador; la ruta del error se recorta |
| 6 | Se podían crear muchos leads falsos (y eventos de clic hacia Meta) desde una IP | Info | Mitigado: 30 leads nuevos por hora por IP o red /64. Quien use muchas IP puede seguir inflando números; no da acceso a nada |
| 7 | En el CSV, una fórmula después de una coma podía ejecutarse si Excel separa columnas con coma | Info | Corregido: se neutraliza también después de `,` y `;` |
| 8 | Enlaces de acceso (emails simulados) y lo escrito como usuario del panel quedaban en los logs | Info | Corregido |
| 9 | `.env.example` traía `ENTORNO=local` (errores con detalle si se olvidaba cambiarlo) | Info | Corregido: ahora trae `produccion` |
| 10 | Defensa extra: se aceptaba `Origin: null`; el despliegue confiaba en la huella SSH que recibiera; las cookies no usan los prefijos `__Host-` | Info | `Origin: null` y la huella SSH, corregidos. Prefijos `__Host-`: **riesgo aceptado** (solo importaría si otra persona controlara un subdominio de tu dominio) |

Al comprobar las correcciones en un navegador real (Chromium) apareció un problema nuevo que las pruebas no
veían: la página del enlace mágico usaba `Referrer-Policy: no-referrer`, y con esa política el navegador envía
el botón "Entrar" con `Origin: null`, que la corrección 10 rechaza. **Ningún comprador habría podido entrar.**
Se cambió a `same-origin` y una prueba impide volver a `no-referrer`. Todos los formularios (panel, venta,
enlace, salir, `/entrar`) se probaron de nuevo en Chromium.

El revisor también confirmó que funcionan bien: enlaces y sesiones (256 bits, guardados como hash, uso atómico:
12 envíos simultáneos del mismo enlace crearon 1 sola sesión), revocar, separación de sesiones de alumno y de
admin, CSRF en las 12 rutas POST, sin redirecciones abiertas, sin salir de carpetas, `.env`/`.git`/`storage`
inaccesibles, errores genéricos en producción, sin XSS, cookies y cabeceras, y SQL con parámetros.

## Segunda revisión independiente (29-09-2026)

Desde esta fecha el acceso se entrega normalmente con un **enlace de activación** que el dueño envía por
WhatsApp (sección 2). Otro revisor atacó esa parte y el estilo nuevo con un servidor de prueba y Chromium.
**Conclusión: nadie consigue acceso sin haber pagado** (sin XSS, SQL con parámetros, CSRF en las rutas nuevas;
6 activaciones simultáneas del mismo código crearon siempre 1 sola venta). Encontró 6 problemas, ninguno
grave, y reprodujo los 6. Todos están corregidos, cada uno con una prueba automática, y sus propios scripts
de ataque se volvieron a ejecutar contra el código corregido.

| # | Hallazgo | Gravedad | Estado |
|---|---|---|---|
| 1 | Cualquiera podía frenar `/activar` para todos: 100 códigos equivocados desde 10 IP agotaban el tope global de la hora, y los compradores con su código bueno veían "Demasiados intentos" | Media | Corregido: código de 10 caracteres y sin tope global; queda el límite por IP |
| 2 | "Enlace nuevo" no aguantaba un reenvío: si el navegador recargaba la página, se creaba otro enlace y dejaba de servir el que el dueño ya había enviado | Media-baja | Corregido: el botón lleva la versión del enlace que se veía; si ya cambió, no se crea otro y el panel lo avisa |
| 3 | Algunos textos chicos de la landing (entre ellos el aviso "no representan ingresos típicos ni garantizados") no llegaban al contraste mínimo de 4,5:1 | Baja | Corregido: texto suave más oscuro (`#8f5070`: 4,8:1 o más sobre todos los fondos claros) |
| 4 | Alguien podía activar su pago con el email de otra persona y seguir dentro de esa cuenta después de que su verdadero dueño comprara | Baja | Corregido: la compra cierra las sesiones abiertas de esa cuenta (sección 2) |
| 5 | Una negrita (`**…**`) en el chip del precio, un botón o el pie habría quedado ilegible (color oscuro sobre fondo oscuro o rosa) | Baja (hoy no pasaba) | Corregido: ahí la negrita toma el color de su texto |
| 6 | Dos pestañas del panel con el mismo clic enviadas a la vez podían registrar dos pagos de ese clic (o dar un error 500) | Muy baja | Corregido: el clic se revisa dentro de la transacción. Con un servidor real, 24 rondas de envíos simultáneos (el choque ocurrió en 7): siempre un solo pago por clic y ningún error |

Además se había subido por error una captura de prueba (`angosto-mx.png`, la landing en un celular angosto,
sin datos privados): se borró y `.gitignore` impide subir imágenes sueltas en la raíz.

## Recomendaciones para el dueño

- Usa una **contraseña larga y única** para el panel (una frase de 4 o 5 palabras) y no la compartas.
- Si el panel dice "Demasiados intentos" y no fuiste tú, alguien está probando contraseñas: no pasa nada
  si la tuya es larga. Desde tu celular o computadora de siempre puedes seguir entrando.
- Mantén el repositorio **privado** o, si es público, nunca subas el curso real ni capturas sin difuminar.
- Descarga de vez en cuando una copia de `storage/respaldos/`.
- Activa la verificación en dos pasos en Hostinger, GitHub, Resend y Meta.
- Revisa las plantillas legales con un abogado, incluida la base legal de las cookies de medición.
