<?php
/**
 * Application entry point / front controller.
 */
define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/app/core/Database.php';
require_once BASE_PATH . '/app/core/Auth.php';
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/App.php';

// Autoload models and controllers
spl_autoload_register(function (string $class): void {
    $paths = [
        BASE_PATH . '/app/models/'          . $class . '.php',
        BASE_PATH . '/app/controllers/'     . $class . '.php',
        BASE_PATH . '/app/controllers/api/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);

session_name(SESSION_NAME);
session_start();

$app = new App();
$app->run();
