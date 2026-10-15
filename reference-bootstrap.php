<?php
spl_autoload_register(function ($class) {
    $prefix = 'ParseAPI\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require $file;
    }
}, true, true);
