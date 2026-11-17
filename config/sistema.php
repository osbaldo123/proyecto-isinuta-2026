<?php

return [
    /*
    | Acceso directo al sistema para secretaria / presidente.
    | Con correo + contraseña se inicia sesión como ese usuario.
    */
    'acceso' => [
        'secretaria' => [
            'nombre' => 'secretaria',
            'password' => env('SISTEMA_ACCESO_SECRETARIA_PASSWORD', 'lorena1234'),
            'email' => env('SISTEMA_ACCESO_SECRETARIA_EMAIL', 'lorenaMamani@gmail.com'),
            'rol' => 'secretaria',
            'etiqueta' => 'Secretaria',
        ],
        'presidente' => [
            'nombre' => 'presidente',
            'password' => env('SISTEMA_ACCESO_PRESIDENTE_PASSWORD', 'nina1234'),
            'email' => env('SISTEMA_ACCESO_PRESIDENTE_EMAIL', 'sergioNina@gmail.com'),
            'rol' => 'presidente',
            'etiqueta' => 'Presidente',
        ],
    ],

    /*
    | Usuarios de consulta para afiliados (ingreso con su CI).
    | Desactivado por ahora: el sistema solo lo usan la secretaria y el presidente.
    | Para habilitarlo más adelante, agregue USUARIOS_AFILIADOS=true en el archivo .env
    | (y ejecute php artisan config:clear).
    */
    'usuarios_afiliados' => env('USUARIOS_AFILIADOS', false),
];

