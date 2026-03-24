<?php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);
    $filename = array_pop($parts);
    $dirs = array_map('strtolower', $parts);
    $path = count($dirs) ? implode('/', $dirs) . '/' : '';
    $file = $base_dir . $path . $filename . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
