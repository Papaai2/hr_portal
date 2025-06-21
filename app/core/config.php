<?php
// in file: app/core/config.php

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // WARNING: Blank password. Use environment variables or a secure method for production!
define('DB_NAME', 'hr_portal');

// --- Site Configuration ---
define('SITE_NAME', 'HR Portal');
define('BASE_URL', 'http://localhost'); // Corrected for root directory
define('TIMEZONE', 'Africa/Cairo');

// --- Error Reporting & Timezone ---
// Set to 'development' for debugging, 'production' for live site
define('ENVIRONMENT', 'development');

// Apply the timezone setting for all date/time functions
date_default_timezone_set(TIMEZONE);

if (ENVIRONMENT == 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL); // Still report all errors internally
    ini_set('log_errors', 1); // Enable error logging
    ini_set('error_log', __DIR__ . '/../../logs/php_error.log'); // Set error log file path
}