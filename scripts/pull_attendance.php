<?php
/**
 * This script is intended to be run from the command line or a cron job
 * to pull attendance logs from all active devices.
 *
 * Example: php /path/to/your/project/scripts/pull_attendance.php
 */

// --- Bootstrap The Application ---
// This ensures we have access to our database, services, and core functions.
require_once __DIR__ . '/../app/bootstrap.php';

// --- Include All Necessary Device Drivers ---
// By including them here, we avoid repeatedly including them inside the loop.
require_once APP_PATH . '/core/drivers/DeviceDriverInterface.php';
require_once APP_PATH . '/core/drivers/EnhancedDriverFramework.php';
require_once APP_PATH . '/core/drivers/FingertecDriver.php';
require_once APP_PATH . '/core/drivers/ZKTecoDriver.php';

echo "=================================================\n";
echo " Starting Attendance Pull Script - " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n";

// --- Fetch all active devices from the database ---
$stmt = $pdo->query("SELECT * FROM devices WHERE is_active = 1");
$devices = $stmt->fetchAll();

if (empty($devices)) {
    echo "[INFO] No active devices found in the database. Exiting.\n";
    exit;
}

echo "[INFO] Found " . count($devices) . " active device(s) to process.\n";

// The AttendanceService is loaded globally via bootstrap.php
if (!class_exists('AttendanceService')) {
    echo "[FATAL] AttendanceService class not found. Check bootstrap.php. Exiting.\n";
    exit(1);
}
$service = new AttendanceService($pdo);

// --- Process Each Device ---
foreach ($devices as $device) {
    echo "\n-------------------------------------------------\n";
    echo "[INFO] Processing device: {$device['name']} ({$device['ip_address']}:{$device['port']})\n";

    // --- Dynamically determine the driver class name ---
    $driver_class = ucfirst(strtolower($device['device_brand'])) . 'Driver';
    
    if (!class_exists($driver_class)) {
        echo "[ERROR] Driver class '{$driver_class}' not found. Please check the device brand name or driver file. Skipping.\n";
        continue;
    }

    // --- Create Driver Instance and Connect ---
    /** @var EnhancedBaseDriver $driver */
    $driver = new $driver_class();

    if (!$driver->connect($device['ip_address'], (int)$device['port'], $device['communication_key'] ?? null)) {
        echo "[ERROR] Could not connect to the device. Error: " . $driver->getLastError() . ". Skipping.\n";
        continue;
    }
    
    echo "[SUCCESS] Connected to device: " . $driver->getDeviceName() . "\n";
    
    try {
        $logs = $driver->getAttendanceLogs();
        
        if (empty($logs)) {
            echo "[INFO] No new attendance logs found on the device.\n";
            $driver->disconnect();
            continue;
        }

        echo "[INFO] Found " . count($logs) . " new log(s). Saving to database...\n";

        // Pass the device ID to the service to correctly associate the logs.
        $saved_count = $service->saveStandardizedLogs($logs, $device['id']);

        echo "[SUCCESS] Successfully saved {$saved_count} new log(s) to the database.\n";

    } catch (Exception $e) {
        echo "[ERROR] An error occurred while fetching logs: " . $e->getMessage() . "\n";
    } finally {
        $driver->disconnect();
        echo "[INFO] Disconnected from device.\n";
    }
}

echo "-------------------------------------------------\n";
echo "[INFO] Script finished at " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n";