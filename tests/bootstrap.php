<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

(static function (): void {
    $root = dirname(__DIR__);

    require $root . '/vendor/autoload.php';

    if (class_exists(Dotenv::class) === false) {
        throw new RuntimeException('Please install symfony/dotenv: composer require --dev symfony/dotenv.');
    }

    $dotenv = new Dotenv();
    $dotenv->usePutenv();
    $dotenv->loadEnv($root . '/.env');
})();
