<?php
declare(strict_types=1);

/*
 * Pruebas automáticas, sin librerías:
 *
 *     php tests/run.php
 *
 * Cada archivo tests/*_test.php registra pruebas con prueba('nombre', function () { … }).
 */

// Configuración propia de las pruebas: no depende del .env de tu computadora.
$bdPruebas = sys_get_temp_dir() . '/infoproducto-pruebas-' . getmypid() . '.sqlite';
putenv('ENTORNO=pruebas');
putenv('URL_SITIO=http://localhost');
putenv('CLAVE_APP=clave-de-pruebas');
putenv('ZONA_HORARIA=America/Guayaquil');
putenv("RUTA_BD=$bdPruebas");
$logsPruebas = sys_get_temp_dir() . '/infoproducto-logs-' . getmypid();
putenv("RUTA_LOGS=$logsPruebas");

require dirname(__DIR__) . '/app/bootstrap.php';

// Las pruebas ignoran el .env de tu computadora: solo usan lo definido arriba
env_valores([]);
config_todo(true);

$GLOBALS['pruebas'] = [];

function prueba(string $nombre, callable $funcion): void
{
    $GLOBALS['pruebas'][] = [basename($GLOBALS['archivo_de_pruebas'], '_test.php'), $nombre, $funcion];
}

function afirmar(bool $condicion, string $mensaje = 'La condición no se cumple'): void
{
    if (!$condicion) {
        throw new AssertionError($mensaje);
    }
}

function afirmar_igual(mixed $esperado, mixed $real, string $mensaje = ''): void
{
    if ($esperado !== $real) {
        throw new AssertionError(trim(
            $mensaje . ' Esperado: ' . var_export($esperado, true) . ' · Obtenido: ' . var_export($real, true)
        ));
    }
}

function afirmar_contiene(string $aguja, string $pajar, string $mensaje = ''): void
{
    if (!str_contains($pajar, $aguja)) {
        throw new AssertionError(trim("$mensaje No aparece: " . var_export($aguja, true)));
    }
}

/** Comprueba que $funcion lanza un error y lo devuelve. */
function afirmar_falla(callable $funcion, string $mensaje = 'Se esperaba un error y no lo hubo'): Throwable
{
    try {
        $funcion();
    } catch (Throwable $error) {
        return $error;
    }
    throw new AssertionError($mensaje);
}

/** Ejecuta $funcion con variables de configuración extra (ej. META_PIXEL_ID) y luego las quita. */
function con_config(array $variables, callable $funcion): mixed
{
    $anteriores = [];
    foreach ($variables as $clave => $valor) {
        $anteriores[$clave] = getenv($clave);
        putenv("$clave=$valor");
    }
    config_todo(true);
    try {
        return $funcion();
    } finally {
        foreach ($anteriores as $clave => $anterior) {
            putenv($anterior === false ? $clave : "$clave=$anterior");
        }
        config_todo(true);
    }
}

/** Prepara las variables de una petición simulada ($_POST, $_COOKIE, $_SERVER, $_GET). */
function simular_peticion(array $post = [], array $cookies = [], array $servidor = [], array $get = []): void
{
    $_POST = $post;
    $_COOKIE = $cookies;
    $_GET = $get;
    $_SERVER = $servidor + [
        'REMOTE_ADDR' => '10.0.0.' . random_int(1, 250),
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (pruebas)',
        'HTTP_HOST' => 'localhost',
        'REQUEST_METHOD' => $post ? 'POST' : 'GET',
        'REQUEST_URI' => '/',
    ];
}

/** Formulario legítimo: el token CSRF del campo coincide con el de la cookie. */
function post_legitimo(array $post, array $cookies = []): void
{
    simular_peticion($post + ['_csrf' => 'token-de-prueba-csrf'], $cookies + ['csrf' => 'token-de-prueba-csrf']);
}

/** Base de datos nueva en memoria, con todas las tablas, para una prueba. */
function bd_de_prueba(): PDO
{
    // Las tareas de fondo que dejó una prueba anterior no deben ejecutarse sobre la base nueva
    $tareas = &tareas_de_fondo();
    $tareas = [];
    $pdo = db_conectar(':memory:');
    migrar($pdo);
    return db($pdo);
}

foreach (glob(__DIR__ . '/*_test.php') as $archivo) {
    $GLOBALS['archivo_de_pruebas'] = $archivo;
    require $archivo;
}

$fallos = 0;
$grupoActual = null;
foreach ($GLOBALS['pruebas'] as [$grupo, $nombre, $funcion]) {
    if ($grupo !== $grupoActual) {
        echo "\n$grupo\n";
        $grupoActual = $grupo;
    }
    try {
        $funcion();
        echo "  ✓ $nombre\n";
    } catch (Throwable $error) {
        $fallos++;
        echo "  ✗ $nombre\n    → " . $error->getMessage() . "\n";
    }
}

foreach (['', '-wal', '-shm'] as $sufijo) {
    if (is_file($bdPruebas . $sufijo)) {
        unlink($bdPruebas . $sufijo);
    }
}
array_map('unlink', glob("$logsPruebas/*") ?: []);
if (is_dir($logsPruebas)) {
    rmdir($logsPruebas);
}

$total = count($GLOBALS['pruebas']);
echo "\n" . ($fallos === 0 ? "✓ $total pruebas correctas" : "✗ Fallaron $fallos de $total pruebas") . "\n";
exit($fallos === 0 ? 0 : 1);
