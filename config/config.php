<?php

declare(strict_types=1);

return [
    'app' => [
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
        'name' => $_ENV['APP_NAME'] ?? 'My App',
    ],
    'database' => [
        'driver' => $_ENV['DB_DRIVER'] ?? 'sqlite',
        'path' => ($_ENV['DB_PATH'] ?? '') !== '' ? $_ENV['DB_PATH'] : __DIR__ . '/../storage/database.sqlite',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '5432',
        'name' => $_ENV['DB_NAME'] ?? '',
        'user' => $_ENV['DB_USER'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
    'modules' => [
        'auth' => ($_ENV['MODULE_AUTH'] ?? 'true') === 'true',
        'email' => ($_ENV['MODULE_EMAIL'] ?? 'false') === 'true',
        'queue' => ($_ENV['MODULE_QUEUE'] ?? 'false') === 'true',
    ],
    'ses' => [
        'region' => $_ENV['AWS_SES_REGION'] ?? 'us-east-1',
        'access_key' => $_ENV['AWS_SES_ACCESS_KEY'] ?? '',
        'secret_key' => $_ENV['AWS_SES_SECRET_KEY'] ?? '',
        'from_address' => $_ENV['AWS_SES_FROM_ADDRESS'] ?? '',
    ],
];
