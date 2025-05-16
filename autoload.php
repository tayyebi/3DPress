<?php
// 3DPress PSR-4 compatible autoloader
spl_autoload_register(function (string $class_name): void {
    $namespace = 'THREEDPRESS\\';
    if (strpos($class_name, $namespace) === 0) {
        $relative_class = substr($class_name, strlen($namespace));
        $class_file = __DIR__ . '/includes/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
        if (file_exists($class_file)) {
            require_once $class_file;
        }
    }
});
