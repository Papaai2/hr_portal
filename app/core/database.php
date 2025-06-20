<?php
// in file: htdocs/app/core/database.php

// This will be included by other files, so it needs to find the config file
// The path assumes that this file is in app/core/ and config.php is in app/core/
require_once 'config.php';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Set the session timezone for the database connection to a fixed offset for Cairo (UTC+3)
    // This avoids the "Unknown or incorrect time zone" error
    $pdo->exec("SET time_zone = '+03:00'");
} catch (\PDOException $e) {
    // For a real application, you might want to log this error instead of displaying it
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}