<?php
return [
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'database' => $_ENV['DB_NAME'] ?? 'clinic_db',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? 'secret',
    'charset' => 'utf8mb4',
];