<?php

use Illuminate\Support\Str;

return [
    'default' => 'mongodb',

    'connections' => [
        'mongodb' => [
            'driver' => 'mongodb',
            'dsn' => env('MONGO_DB_DSN', ''),
            'database' => env('MONGO_DB_DATABASE', 'bulk_campaign'),
        ],

        'mysql_voters' => [
            'driver' => 'mysql',
            'host' => env('MYSQL_DB_HOST', '127.0.0.1'),
            'port' => env('MYSQL_DB_PORT', '3306'),
            'database' => env('MYSQL_DB_DATABASE', 'voters_db'),
            'username' => env('MYSQL_DB_USERNAME', 'root'),
            'password' => env('MYSQL_DB_PASSWORD', ''),
            'unix_socket' => env('MYSQL_DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => env('MYSQL_DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::ATTR_EMULATE_PREPARES => true,
            ]) : [],
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
