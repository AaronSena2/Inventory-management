<?php
// Database configuration – copy this file to config.php and fill in your values
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'inventory_db');
define('DB_USER',    getenv('DB_USER')    ?: 'your_db_user');
define('DB_PASS',    getenv('DB_PASS')    ?: 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// Application
define('APP_NAME', 'IT Inventory Manager');
define('APP_URL',  getenv('APP_URL') ?: 'http://localhost:8000');
define('APP_ENV',  getenv('APP_ENV') ?: 'production');

// Session
define('SESSION_NAME',     'inv_session');
define('SESSION_LIFETIME', 3600);

// Agent token length
define('AGENT_TOKEN_LENGTH', 32);

// Online threshold in seconds
define('ONLINE_THRESHOLD', 300);
