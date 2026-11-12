<?php
// Uso local: php -S 127.0.0.1:8080 -t public public/router.php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/') return false;
$file = realpath(__DIR__ . $path);
if ($file && str_starts_with($file, __DIR__) && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
require __DIR__ . '/index.php';
