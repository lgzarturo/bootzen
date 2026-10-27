<?php

/**
 * Base de datos. Se lee con config('database.clave').
 *
 * SQLite por defecto: un archivo en database/, sin servidor ni administración.
 * Para MySQL, cambia DB_CONNECTION=mysql y completa DB_HOST, DB_DATABASE, etc. en .env.
 */

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            // Ruta relativa a la raíz del proyecto, o :memory: (los tests la usan así)
            'database' => env('DB_DATABASE', 'database/app.sqlite'),
            'foreign_keys' => true,
            // WAL permite leer mientras otra petición escribe
            'journal_mode' => 'WAL',
            'busy_timeout' => 5000,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'app'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
    ],
];
