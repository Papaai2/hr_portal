<?php
// This is the endpoint for devices that use the PUSH protocol (ADMS).
// The device must be configured to point to this URL.
// e.g., http://yourdomain.com/api/listener.php?SN=DEVICE_SERIAL_NUMBER

require_once __DIR__ . '/../app/bootstrap.php';
use App\Core\Services\AttendanceService;

// Basic security check (optional but recommended)
// define('SHARED_SECRET_KEY', 'YourSecretKey123');
// $provided_key = $_GET['key'] ?? '';
// if ($provided_key !== SHARED_SECRET_KEY) {
//     http_response_code(401);
//     echo "Unauthorized";
//     exit;
// }

header("Content-Type: text/plain");

$serial_number = $_GET['SN'] ?? null;
if (!$serial_number) {
    http_response_code(400);
    echo "ERROR: Device Serial Number (SN) not provided.";
    exit;
}

// Find the device in the database
$stmt = $pdo->prepare("SELECT * FROM devices WHERE serial_number = ? AND is_active = 1");
$stmt->execute([$serial_number]);
$device = $stmt->fetch();

if (!$device) {
    http_response_code(404);
    echo "ERROR: Device with SN {$serial_number} not found or is not active.";
    // Log this attempt for security purposes
    error_log("Failed ADMS connection attempt from unknown device with SN: " . $serial_number);
    exit;
}


$raw_data = file_get_contents('php://input');
if (empty($raw_data)) {
    echo "OK"; // Device expects "OK" response to confirm connection
    exit;
}

// Process the attendance data
$lines = explode("\n", trim($raw_data));
$standardized_logs = [];

foreach ($lines as $line) {
    // Expected format: 1	2025-06-20 10:00:00	0	1	0	0
    $parts = preg_split('/\s+/', trim($line));
    if (count($parts) >= 3) {
        $employee_code = $parts[0];
        $punch_time = $parts[1] . ' ' . $parts[2];
        
        // Basic validation
        if (is_numeric($employee_code) && strtotime($punch_time) !== false) {
             $standardized_logs[] = [
                'employee_code' => $employee_code,
                'punch_time'    => $punch_time
             ];
        }
    }
}

if (!empty($standardized_logs)) {
    $service = new AttendanceService($pdo);
    // **FIXED**: Pass the device ID to the service
    $saved_count = $service->saveStandardizedLogs($standardized_logs, $device['id']);
    error_log("Processed {$saved_count} records from device {$device['name']} (SN: {$serial_number})");
}


// Acknowledge receipt to the device
echo "OK";