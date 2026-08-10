<?php
// Database config - PHASE 1: MySQL ONLY (XAMPP)
// SQLite fallback disabled as per request "mysql use korun"
return [
    // 'mysql' forces MySQL - error will show clearly if MySQL not running
    'connection' => getenv('DB_CONNECTION') ?: 'mysql', // mysql

    'mysql' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'file_server',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '', // XAMPP default is empty. If you set password in phpMyAdmin, put it here
        'charset' => 'utf8mb4',
    ],

    'sqlite' => [
        'path' => __DIR__ . '/../database/file_server.sqlite',
    ],
];
