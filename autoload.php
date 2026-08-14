<?php

declare(strict_types=1);

/**
 * Autoloader du phong khi chua chay `composer install`.
 * Neu da co Composer, dung vendor/autoload.php thay cho file nay.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Gems\\Mime\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
