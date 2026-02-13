<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

/** @var array{app: array{env: string, debug: bool, name: string}, database: array{path: string}} $config */
$config = require __DIR__ . '/../config/config.php';

return $config;
