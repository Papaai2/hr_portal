<?php
// in file: app/core/error_handler.php

/**
 * Custom error handler to log errors and display user-friendly messages in production.
 */
function custom_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return false;
    }

    $log_message = sprintf(
        "[%s] PHP Error: %s in %s on line %d (Severity: %d)\nStack Trace:\n%s",
        date('Y-m-d H:i:s'),
        $message,
        $file,
        $line,
        $severity,
        (new Exception)->getTraceAsString()
    );

    error_log($log_message);

    if (ENVIRONMENT === 'production') {
        // For production, display a generic error message to the user
        // and prevent further script execution for critical errors.
        if ($severity === E_ERROR || $severity === E_PARSE || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
            http_response_code(500);
            echo "<h1>An unexpected error occurred.</h1><p>Please try again later or contact support.</p>";
            exit();
        }
    } else {
        // In development, let PHP display errors as configured
        return false; // Let the default PHP error handler take over
    }
    return true; // Error handled
}

/**
 * Custom exception handler to log uncaught exceptions.
 */
function custom_exception_handler(Throwable $exception) {
    $log_message = sprintf(
        "[%s] Uncaught Exception: %s in %s on line %d\nStack Trace:\n%s",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    error_log($log_message);

    if (ENVIRONMENT === 'production') {
        http_response_code(500);
        echo "<h1>An unexpected error occurred.</h1><p>Please try again later or contact support.</p>";
    } else {
        // In development, re-throw the exception to let default handler show it
        throw $exception;
    }
    exit(); // Ensure script terminates after an uncaught exception
}

// Set the custom error and exception handlers
set_error_handler("custom_error_handler");
set_exception_handler("custom_exception_handler");

// Ensure all errors are caught by our handler, even fatal ones
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        custom_error_handler($error['type'], $error['message'], $error['file'], $error['line']);
    }
});