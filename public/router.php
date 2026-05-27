<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/doc/([a-z0-9-]+)$#', $path, $matches)) {
    $_GET['doc'] = $matches[1];
    require __DIR__ . '/view.php';
    return true;
}

return false;
