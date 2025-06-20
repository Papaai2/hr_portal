<?php
// app/bootstrap.php
// This file is the single entry point for loading all core application files.

// Start the session on every page load.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Define Core Paths ---
// This makes including files easier and more consistent.
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

// --- Include Core Files in the Correct Order ---
// The order is important to ensure dependencies are met.
require_once APP_PATH . '/core/config.php';
require_once APP_PATH . '/core/database.php';
require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/auth.php';

// --- Include Service Classes ---
// This makes services like AttendanceService globally available.
require_once APP_PATH . '/core/services/AttendanceService.php';

// You can add any other global initializations here in the future.