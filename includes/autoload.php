<?php
/**
 * Premium Classifieds - Autoloader
 * PSR-4 like simple loader for plugin classes
 */

spl_autoload_register(function ($class) {

    // Only autoload classes from our namespace
    $prefix = 'PremiumClassifieds\\';
    $base_dir = __DIR__ . '/';

    // Does the class use our namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Remove namespace prefix
    $relative_class = substr($class, $len);

    // Convert namespace separators to directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If file exists — require it
    if (file_exists($file)) {
        require_once $file;
    }
});
