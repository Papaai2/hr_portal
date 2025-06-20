<?php
// in file: app/core/database.php

require_once 'config.php';

/**
 * Database Class
 *
 * Encapsulates the database connection logic, allowing for a single,
 * consistent way to access the database throughout the application.
 */
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn;

    /**
     * Establishes and returns a PDO database connection.
     *
     * @return PDO The PDO connection object.
     * @throws PDOException if the connection fails.
     */
    public function getConnection() {
        $this->conn = null;

        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // MODIFIED: Dynamically set the MySQL timezone based on the PHP timezone.
            // This correctly handles DST for timezones like 'Africa/Cairo'.
            $offset_string = (new DateTime('now', new DateTimeZone(TIMEZONE)))->format('P');
            $this->conn->exec("SET time_zone = '{$offset_string}'");

        } catch(PDOException $exception) {
            // Re-throw the exception to be handled by the calling code
            throw new PDOException($exception->getMessage(), (int)$exception->getCode());
        }

        return $this->conn;
    }
}

// --- Global PDO instance for backward compatibility ---
// Many existing files directly use the global '$pdo' variable.
// This block ensures that the variable is still available.
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (PDOException $e) {
    // If the database connection fails, it's a fatal error.
    // In a production environment, you would log this error and show a user-friendly error page.
    die("FATAL ERROR: Database connection failed: " . $e->getMessage());
}