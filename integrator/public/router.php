<?php

declare(strict_types=1);

/**
 * Development server router: serve real files (CSS, images) directly and send
 * everything else to the front controller.
 *
 *   php -S 127.0.0.1:3000 -t public public/router.php
 *
 * In production use nginx/apache with a rewrite to public/index.php instead.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path !== '/' && is_file(__DIR__.$path)) {
    return false;
}

require __DIR__.'/index.php';
