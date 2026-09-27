<?php

declare(strict_types=1);

/**
 * Autoloader + helpers for the standalone integrator.
 *
 * No Composer on purpose: the tool must run on a plain PHP installation beside
 * Neo Feeder, where installing dependencies is often not an option.
 */

require_once __DIR__.'/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Integrator\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__.'/'.$relative.'.php';

    if (is_file($path)) {
        require_once $path;
    }
});
