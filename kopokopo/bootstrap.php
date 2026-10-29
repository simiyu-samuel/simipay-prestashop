<?php
/** Module-local autoloading avoids collisions with the shop's Composer dependencies. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'SimiyuSamuel\\Kopokopo\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
