<?php
declare(strict_types=1);

/*
 * Tareas programadas: limpieza de datos y respaldos.
 */

prueba('la limpieza borra IP y navegador de los clics viejos pero conserva los UTM', function () {
    bd_de_prueba();
    $viejo = lead_registrar(visita_de_prueba());
    $reciente = lead_registrar(visita_de_prueba(['visitante_id' => str_repeat('e', 32)]));
    db_ejecutar('UPDATE leads SET creado_en = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 91 * 86400), $viejo['id']]);
    $resultado = mantenimiento_limpiar();
    afirmar_igual(1, $resultado['clics']);
    $fila = db_fila('SELECT * FROM leads WHERE id = ?', [$viejo['id']]);
    afirmar_igual([null, null, null, null], [$fila['ip'], $fila['user_agent'], $fila['fbclid'], $fila['fbp']]);
    afirmar_igual('lanzamiento', $fila['utm_campaign'], 'Los UTM se conservan para las estadísticas.');
    afirmar_igual('190.1.2.3', db_valor('SELECT ip FROM leads WHERE id = ?', [$reciente['id']]));
});

prueba('la limpieza borra sesiones vencidas y límites viejos', function () {
    bd_de_prueba();
    $vigente = sesion_crear('admin', null);
    $vencida = sesion_crear('admin', null);
    db_ejecutar('UPDATE sesiones SET expira_en = ? WHERE token_hash = ?', [gmdate('Y-m-d H:i:s', time() - 60), hash_token($vencida)]);
    limite_permitir('prueba:viejo', 5, 60);
    db_ejecutar('UPDATE limites SET ventana_inicio = ?', [time() - 2 * 86400]);
    $resultado = mantenimiento_limpiar();
    afirmar_igual(1, $resultado['sesiones']);
    afirmar_igual(1, $resultado['limites']);
    afirmar(sesion_de_token('admin', $vigente) !== null);
});

prueba('el respaldo diario se hace una vez por día y conserva los últimos 14', function () {
    bd_de_prueba();
    db_insertar('compradores', fila_comprador('respaldo@x.com'));
    $carpeta = sys_get_temp_dir() . '/respaldos-prueba-' . getmypid();
    @mkdir($carpeta, 0775, true);
    for ($i = 1; $i <= 15; $i++) {
        touch(sprintf('%s/base-2020-01-%02d.sqlite', $carpeta, $i));
    }
    $archivo = respaldo_diario($carpeta);
    afirmar($archivo !== null && is_file($archivo));
    afirmar_igual(1, (int) db_conectar($archivo)->query('SELECT COUNT(*) FROM compradores')->fetchColumn(), 'La copia tiene los datos.');
    afirmar_igual(null, respaldo_diario($carpeta), 'El mismo día no se repite.');
    afirmar_igual(RESPALDOS_A_CONSERVAR, count(glob("$carpeta/base-*.sqlite")));
    array_map('unlink', glob("$carpeta/*") ?: []);
    rmdir($carpeta);
});
