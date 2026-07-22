<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña inicial de los usuarios sembrados
    |--------------------------------------------------------------------------
    |
    | La usa UsuarioSeeder al CREAR usuarios nuevos. Si se deja vacía, el
    | seeder genera una contraseña aleatoria distinta por usuario y la imprime
    | una sola vez en la consola.
    |
    | Nunca se aplica a usuarios que ya existen: reejecutar el seeder no
    | reemplaza la contraseña de nadie.
    |
    */

    'user_password' => env('SEED_USER_PASSWORD'),

];
