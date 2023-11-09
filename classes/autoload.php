<?php

spl_autoload_register(static function($class) {
    $file = __DIR__ . '/' . $class . '.php';

    if (file_exists($file)) {
        include $file;
    }
});
