<?php
// This script is intended to be run from the command line or a cron job.
// Example: php /path/to/your/project/scripts/pull_attendance.php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Services\AttendanceService;

echo "Starting attendance pull script...\n";

// Get all active PULL devices from the database
$stmt = $pdo->query("SELECT * FROM devices WHERE is_active = 1");
$devices = $stmt->fetchAll();

if (empty($devices)) {
    echo "No active devices found. Exiting.\n";
    exit;
}

$service = new AttendanceService($pdo);

foreach ($devices as $device) {
    echo "---------------------------------\n";
    echo "Processing device: {$device['name']} ({$device['ip_address']})\n";

    $driver_class = ucfirst($device['device_brand']) . 'Driver';
    $driver_file = __DIR__ . '/../app/core/drivers/' . $driver_class . '.php';

    if (!file_exists($driver_file)) {
        echo "[ERROR] Driver file not found: {$driver_file}\n";
        continue;
    }
    require_once $driver_file;
    
    if (!class_exists($driver_class)) {
        echo "[ERROR] Driver class not found: {$driver_class}\n";
        continue;
    }

    $driver = new $driver_class();

    if (!$driver->connect($device['ip_address'], (int)$device['port'], $device['communication_key'])) {
        echo "[ERROR] Could not connect to the device.\n";
        continue;
    }
    
    echo "[SUCCESS] Connected to device: " . $driver->getDeviceName() . "\n";
    
    $logs = $driver->getAttendanceLogs();
    
    if (empty($logs)) {
        echo "[INFO] No new attendance logs found.\n";
        $driver->disconnect();
        continue;
    }

    echo "[INFO] Found " . count($logs) . " new log(s). Saving to database...\n";

    // **FIXED**: Pass the device ID to the service
    $saved_count = $service->saveStandardizedLogs($logs, $device['id']);

    echo "[SUCCESS] Successfully saved {$saved_count} log(s).\n";

    $driver->disconnect();
}

echo "---------------------------------\n";
echo "Script finished.\n";