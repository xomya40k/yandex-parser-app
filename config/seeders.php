<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default User
    |--------------------------------------------------------------------------
    |
    | This is the default user that will be seeded into the database.
    |
    */

    'default_user' => [
        'email' => env('SEED_USER_EMAIL', 'test@example.com'),
        'password' => env('SEED_USER_PASSWORD', 'password'),
    ],

];
