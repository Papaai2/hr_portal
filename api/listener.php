<?php
/**
 * This is the endpoint for devices that use the PUSH protocol (e.g., ADMS).
 * The device's ADMS settings must be configured to point to this URL.
 * Example: http://yourdomain.com/api/listener.php?SN=DEVICE_SERIAL_NUMBER
 *
 * This script listens for real-time attendance punches sent from the device.
 */

// Bootstrap the application to get access to the database and services.
require_once __DIR__ . '/../app/bootstrap.php';

// The project does not use formal namespacing, so the 'use' statement was causing an error.
// It has been removed. The AttendanceService class is loaded via bootstrap.php.

header("Content-Type: text/plain");

// --- Device Identification ---
$serial_number = $_GET['SN'] ?? null;
if (!$serial_number) {
    http_response_code(400);
    error_log("Listener Error: Device Serial Number (SN) not provided in request.");
    echo "ERROR: SN_NOT_PROVIDED";
    exit;
}

// Find the device in the database by its serial number.
// The device must be marked as active to accept punches.
$stmt = $pdo->prepare("SELECT id, name, serial_number FROM devices WHERE serial_number = ? AND is_active = 1");
$stmt->execute([$serial_number]);
$device = $stmt->fetch();

if (!$device) {
    http_response_code(404);
    error_log("Listener Security: Failed connection attempt from unknown or inactive device with SN: " . $serial_number);
    echo "ERROR: DEVICE_NOT_FOUND_OR_INACTIVE";
    exit;
}

// --- Data Processing ---
$raw_data = file_get_contents('php://input');

// The device may perform a simple "ping" by sending an empty request.
// It expects an "OK" response to confirm the server is reachable.
if (empty($raw_data)) {
    echo "OK";
    exit;
}

// Process the incoming attendance data payload.
$lines = explode("\n", trim($raw_data));
$standardized_logs = [];

foreach ($lines as $line) {
    // Expected format from many ZKTeco/Fingertec devices is tab-separated:
    // UserID\tTimestamp\tStatus\tVerificationMode...
    // Example: 1\t2025-06-21 16:03:00\t0\t1\t0\t0
    $parts = preg_split('/\s+/', trim($line)); // Split by any whitespace
    
    // We need at least the UserID and a full timestamp.
    if (count($parts) >= 3) {
        $employee_code = $parts[0];
        $punch_time_str = $parts[1] . ' ' . $parts[2];
        
        // Validate that the parsed data is logical before adding it.
        if (is_numeric($employee_code) && strtotime($punch_time_str) !== false) {
             $standardized_logs[] = [
                'employee_code' => $employee_code,
                'punch_time'    => $punch_time_str
             ];
        } else {
            error_log("Listener Warning: Discarding malformed log line from SN {$serial_number}: '{$line}'");
        }
    }
}

// If we have valid logs, process them using the AttendanceService.
if (!empty($standardized_logs)) {
    // Ensure the service class is available before using it.
    if (class_exists('AttendanceService')) {
        $service = new AttendanceService($pdo);
        // Pass the device ID to the service so logs are associated correctly.
        $saved_count = $service->saveStandardizedLogs($standardized_logs, $device['id']);
        error_log("Listener Info: Processed {$saved_count} of " . count($standardized_logs) . " records from device '{$device['name']}' (SN: {$serial_number})");
    } else {
        http_response_code(500);
        error_log("Listener CRITICAL: AttendanceService class not found!");
        echo "ERROR: SERVICE_UNAVAILABLE";
        exit;
    }
}

// Finally, send the "OK" response to the device to acknowledge receipt.
// This is crucial for the device to know the data was sent successfully.
echo "OK";