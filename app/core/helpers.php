<?php
// in file: app/core/helpers.php

/**
 * Sanitizes user input to prevent XSS.
 *
 * @param string $data The raw input data.
 * @return string The sanitized data.
 */
function sanitize_input($data, $type = 'string') {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    if ($type === 'email') {
        $data = filter_var($data, FILTER_SANITIZE_EMAIL);
    }
    // Add more types as needed, e.g., 'int', 'float', 'url'

    return $data;
}

/**
 * Creates a notification for a specific user.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $user_id The ID of the user to notify.
 * @param string $message The notification message.
 * @param int|null $request_id The ID of the related request, if any.
 */
function create_notification(PDO $pdo, int $user_id, string $message, ?int $request_id = null) {
    // Avoid notifying a user about their own action if a session exists
    if (isset($_SESSION['user_id']) && $user_id == $_SESSION['user_id']) {
        return;
    }
    
    $sql = "INSERT INTO notifications (user_id, message, request_id) VALUES (?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $message, $request_id]);
    } catch (PDOException $e) {
        // In a real application, you should log this error instead of ignoring it.
        error_log("Failed to create notification: " . $e->getMessage());
    }
}

/**
 * Gets the Bootstrap badge class based on the request status.
 *
 * @param string $status The status of the request.
 * @return string The corresponding CSS class.
 */
function getStatusBadgeClass($status) {
    $map = [
        'pending_manager' => 'bg-warning text-dark',
        'pending_hr' => 'bg-info text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'cancelled' => 'bg-secondary',
        'pending_cancellation_hr' => 'bg-warning text-dark',
    ];
    return $map[$status] ?? 'bg-light text-dark';
}

/**
 * Gets a human-readable text for a request status.
 *
 * @param string $status The status from the database.
 * @return string The display-friendly text.
 */
function getStatusText($status) {
    return ucwords(str_replace('_', ' ', $status));
}

/**
 * Logs an audit action to the audit_logs table.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $action The action performed (e.g., 'login', 'update_user').
 * @param string|null $details Optional details about the action (JSON or text).
 */
function log_audit_action(PDO $pdo, string $action, ?string $details = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    // User Agent and IP Address logging removed
    $sql = "INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $action, $details]);
    } catch (PDOException $e) {
        error_log("Failed to log audit action: " . $e->getMessage());
    }
}