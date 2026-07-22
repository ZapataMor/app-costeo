<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña inicial de los usuarios sembrados
    |--------------------------------------------------------------------------
    |
    | La usa UsuarioSeeder al CREAR usuarios nuevos. Nunca se aplica a usuarios
    | que ya existen: reejecutar el seeder no reemplaza la contraseña de nadie.
    |
    | Fuera de producción el valor por defecto es «password», que es lo cómodo
    | para desarrollo y para las demos. En producción no hay valor por defecto:
    | si no defines SEED_USER_PASSWORD, el seeder genera una contraseña
    | aleatoria distinta por usuario y la imprime una sola vez en la consola.
    |
    | Si de verdad quieres «password» también en el servidor de producción,
    | ponlo explícitamente en el .env de ese servidor:
    |
    |     SEED_USER_PASSWORD=password
    |
    */

    'user_password' => env(
        'SEED_USER_PASSWORD',
        env('APP_ENV') === 'production' ? null : 'password',
    ),

];
