<?php

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(
    static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    },
);

define('PROJECT_ROOT', dirname(__DIR__));

require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';
