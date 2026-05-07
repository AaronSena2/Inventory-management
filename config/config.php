<?php
// Database configuration
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'inventory_db');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application
define('APP_NAME', 'IT Inventory Manager');
define('APP_URL',  getenv('APP_URL') ?: 'http://localhost:8000');
define('APP_ENV',  getenv('APP_ENV') ?: 'development');

// Session
define('SESSION_NAME',     'inv_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// Agent token length (bytes before bin2hex — result will be 2x this length)
define('AGENT_TOKEN_LENGTH', 32);

// Online threshold in seconds (if last_checkin > this age, mark offline)
define('ONLINE_THRESHOLD', 300); // 5 minutes
