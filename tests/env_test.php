<?php
declare(strict_types=1);

prueba('lee claves, comillas y comentarios del .env', function () {
    $valores = env_parsear(<<<'TXT'
        # comentario
        ENTORNO=local
        VACIO=
        CON_ESPACIOS = hola mundo   # comentario al final
        COMILLAS="Curso <acceso@correo.com> # esto no es comentario"
        SIMPLES='valor'
        HASH=$argon2id$v=19$m=65536,t=4,p=1$abc
        linea sin igual
        TXT);

    afirmar_igual('local', $valores['ENTORNO']);
    afirmar_igual('', $valores['VACIO']);
    afirmar_igual('hola mundo', $valores['CON_ESPACIOS']);
    afirmar_igual('Curso <acceso@correo.com> # esto no es comentario', $valores['COMILLAS']);
    afirmar_igual('valor', $valores['SIMPLES']);
    afirmar_igual('$argon2id$v=19$m=65536,t=4,p=1$abc', $valores['HASH']);
    afirmar_igual(6, count($valores));
});

prueba('las variables del sistema mandan sobre el .env y un valor vacío cuenta como no definido', function () {
    $anteriores = env_valores();
    try {
        env_valores(['X_PRUEBA' => 'del-env', 'Y_VACIA' => '']);
        afirmar_igual('del-env', env('X_PRUEBA'));
        putenv('X_PRUEBA=del-sistema');
        afirmar_igual('del-sistema', env('X_PRUEBA'));
        afirmar_igual('por-defecto', env('Y_VACIA', 'por-defecto'));
        afirmar_igual(null, env('NO_EXISTE'));
    } finally {
        putenv('X_PRUEBA');
        env_valores($anteriores);
    }
});
