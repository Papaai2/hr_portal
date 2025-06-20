<?php
// in file: htdocs/download.php

require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/database.php';

require_login();

$attachment_id = $_GET['id'] ?? null;
if (!$attachment_id) {
    http_response_code(400);
    exit('Invalid request.');
}

$user_id = get_current_user_id();
$user_role = get_current_user_role();

// Fetch attachment and request details
$stmt = $pdo->prepare("
    SELECT ra.file_name, ra.stored_name, vr.user_id, vr.manager_id 
    FROM request_attachments ra
    JOIN vacation_requests vr ON ra.request_id = vr.id
    WHERE ra.id = ?
");
$stmt->execute([$attachment_id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

// --- ACCESS CONTROL ---
// Check if the current user has permission to download this file.
// Permission is granted if:
// 1. They are the user who submitted the request.
// 2. They are the manager assigned to the request.
// 3. They have an 'hr' or 'admin' role.
$is_owner = ($user_id == $file['user_id']);
$is_manager = ($user_id == $file['manager_id']);
$is_hr_or_admin = in_array($user_role, ['hr', 'admin']);

if (!$is_owner && !$is_manager && !$is_hr_or_admin) {
    http_response_code(403);
    exit('Access Denied.');
}

$file_path = __DIR__ . '/uploads/' . $file['stored_name'];

if (!file_exists($file_path)) {
    http_response_code(404);
    exit('File not found on server.');
}

// Serve the file for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream'); // Generic type for all files
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
flush(); // Flush system output buffer
readfile($file_path);
exit();