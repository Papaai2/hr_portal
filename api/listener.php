<?php
// in file: api/listener.php

/**
 * ADMS PUSH Protocol Listener with Security
 */

// --- Shared Secret Key ---
// This key must be set on your device's configuration page.
// Using the simplified key for testing.
define('SHARED_SECRET_KEY', 'TestKey9876');

// --- Security Check ---
// The key is passed as a URL parameter, e.g., ?SN=XYZ&key=...
$provided_key = $_GET['key'] ?? null;
if ($provided_key !== SHARED_SECRET_KEY) {
    http_response_code(401); // Unauthorized
    echo "ERROR: Invalid or missing security key.";
    // You might also want to log this unauthorized attempt.
    exit();
}


// --- Bootstrap the Application ---
require_once __DIR__ . '/../app/bootstrap.php';

// Instantiate the Attendance Service
$attendanceService = new AttendanceService();

// --- Get Device Serial Number from Request URI ---
$device_sn = $_GET['SN'] ?? null;
if (!$device_sn) {
    http_response_code(400); // Bad Request
    echo "ERROR: Device Serial Number (SN) not provided in request.";
    exit();
}

// --- Find the Device ID from the Serial Number ---
$stmt_find_device = $pdo->prepare("SELECT id FROM devices WHERE serial_number = ? AND is_active = 1");
$stmt_find_device->execute([$device_sn]);
$device = $stmt_find_device->fetch();

if (!$device) {
    http_response_code(404); // Not Found
    echo "ERROR: Device with SN '{$device_sn}' is not registered or is inactive.";
    exit();
}
$device_id = $device['id'];

// --- Process the incoming data based on the command in the URI ---
$command = basename($_SERVER['REQUEST_URI']);
$raw_post_data = file_get_contents('php://input');

$standardized_logs = [];

// Check if the request contains attendance records ('attlog')
if (strpos($command, 'attlog.cgi') !== false) {
    $lines = explode("\n", trim($raw_post_data));
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = explode("\t", $line);
        $standardized_logs[] = [
            'employee_code' => trim($parts[0]),
            'punch_time'    => date('Y-m-d H:i:s', strtotime(trim($parts[1]))),
            'punch_state'   => (int)trim($parts[2]),
        ];
    }

    if (!empty($standardized_logs)) {
        $result = $attendanceService->saveStandardizedLogs($standardized_logs, $device_id);
    }
}

// --- Respond to the Device ---
header("Content-Type: text/plain");
echo "OK";

exit();