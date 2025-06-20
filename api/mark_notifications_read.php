<?php
// in file: htdocs/api/mark_notifications_read.php

header('Content-Type: application/json');

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$user_id = get_current_user_id();

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);

    echo json_encode(['success' => true, 'message' => 'Notifications marked as read.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}