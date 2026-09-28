# Revisión de seguridad

Revisión previa a producción de las cuatro áreas pedidas: **peticiones falsificadas, acceso sin pagar,
fuga de contenido y datos expuestos**. Cada punto indica cómo está protegido y qué prueba automática lo
demuestra (`php tests/run.php`; también se ejecutan en GitHub en cada cambio).

Fecha: 28-09-2026.

---

## 1. Peticiones falsificadas

No hay pasarela de pago ni webhooks: **ninguna petición externa puede confirmar una venta**. Las ventas solo
se registran desde el panel, con sesión de administrador.

| Riesgo | Protección | Prueba |
|---|---|---|
| Formularios enviados desde otra web (CSRF) | Token de doble envío (cookie + campo oculto) en **todos** los formularios POST, comprobación del `Origin` y cookies `SameSite` | `ventas`: "los formularios sin token CSRF válido se rechazan"; `http`: recorrido completo (403 sin token) |
| Adivinar la contraseña del panel | Hash bcrypt en `.env`; máximo 5 intentos por IP y 30 en total cada 15 minutos; misma respuesta y mismo tiempo exista o no el usuario | `ventas`: "entrar al panel…se bloquea tras 5 intentos" |
| Inflar las estadísticas con clics falsos a `/wa` | Límite por IP, se ignoran robots, un visitante conserva su código 7 días; la persona igual llega a WhatsApp | `leads`: límite de intentos y robots |
| Redirecciones abiertas | `/wa` solo redirige a `wa.me` con tu número y un mensaje codificado; el resto de redirecciones son rutas internas fijas | revisión de código |

## 2. Acceso sin pagar

| Riesgo | Protección | Prueba |
|---|---|---|
| Crear un acceso sin ser el dueño | Solo `/admin` (con sesión) crea accesos; todas sus páginas redirigen al login sin sesión | `ventas`: "el panel exige iniciar sesión en todas sus páginas" |
| Adivinar un enlace mágico | 256 bits aleatorios; en la base solo se guarda su hash (HMAC con `CLAVE_APP`) | `miembros`: "se guarda el hash del enlace, nunca el enlace" |
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
| Errores que muestran rutas del servidor | En producción, página genérica, errores solo en `storage/logs/` y `display_errors` apagado | revisión de código |
| Inyección de código en las páginas (XSS) | Todo dato se escapa (`e()`, `formato()`, `markdown()` escapa antes de dar formato; solo enlaces `http(s)`, `mailto` o rutas propias); CSP estricta con `nonce` y sin `unsafe-inline` | `vista`, `landing`: escape y enlaces peligrosos |
| Inyección SQL | Todas las consultas con parámetros; los nombres de columnas se validan | `db`: "rechaza nombres de columna sospechosos" |
| Saber quién compró (enumeración de emails) | `/entrar` responde igual exista o no el email; límite de pedidos por email e IP | `miembros`: "misma respuesta exista o no el email" |
| Fórmulas maliciosas al abrir el CSV en Excel | Las celdas que empiezan por `= + - @` se neutralizan | `ventas`: "exportar CSV…" |
| Datos personales enviados a Meta | Email, teléfono y nombre van en hash SHA-256; IP y navegador de los clics se borran a los 90 días (cron) | `meta`, `mantenimiento` |
| Cookies robables | Sesiones `HttpOnly`, `Secure` con https, `SameSite` (`Strict` en el panel) | `ventas`: cookie del panel |
| Web incrustada en otra (clickjacking) | `frame-ancestors 'none'` y `X-Frame-Options: DENY` | `http`: cabeceras |

## Recomendaciones para el dueño

- Usa una **contraseña larga y única** para el panel (una frase de 4 o 5 palabras) y no la compartas.
- Mantén el repositorio **privado** o, si es público, nunca subas el curso real ni capturas sin difuminar.
- Descarga de vez en cuando una copia de `storage/respaldos/`.
- Activa la verificación en dos pasos en Hostinger, GitHub, Resend y Meta.
- Revisa las plantillas legales con un abogado, incluida la base legal de las cookies de medición.
