<?php
// A script to be run automatically by a scheduler (e.g., cron job or Windows Task Scheduler)
// It fetches new attendance logs from all active devices configured in the database.

echo "=================================================\n";
echo "HR Portal - Automatic Attendance Pull Service\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n";

// --- Bootstrap the application ---
require_once __DIR__ . '/../app/bootstrap.php';

// --- Include Device Drivers ---
require_once APP_PATH . '/core/drivers/FingertecDriver.php';
require_once APP_PATH . '/core/drivers/ZKTecoDriver.php';


try {
    global $pdo; // Use the global PDO connection from bootstrap.php

    // --- Get all active devices from the database ---
    $stmt_get_devices = $pdo->query("SELECT * FROM devices WHERE is_active = 1");
    $devices = $stmt_get_devices->fetchAll(PDO::FETCH_ASSOC);

    if (empty($devices)) {
        echo "[INFO] No active devices found in the database. Exiting.\n";
        exit(0);
    }

    echo "[INFO] Found " . count($devices) . " active device(s) to process.\n";

    $attendanceService = new AttendanceService();

    // --- Loop through each device and fetch logs ---
    foreach ($devices as $device) {
        $deviceId = $device['id'];
        $deviceName = $device['name'];
        $ip = $device['ip_address'];
        $port = $device['port'];
        $brand = $device['device_brand'];
        $key = $device['communication_key'];

        echo "\n--- Processing device: '{$deviceName}' (IP: {$ip}, Brand: {$brand}) ---\n";

        // --- The Driver Factory ---
        $driver = null;
        switch (strtolower($brand)) {
            case 'fingertec':
                $driver = new FingertecDriver();
                break;
            case 'zkteco':
                $driver = new ZKTecoDriver();
                break;
            default:
                echo "[WARNING] Skipped device '{$deviceName}': Unknown brand '{$brand}'.\n";
                continue 2; // continue the outer foreach loop
        }

        // 1. Connect
        if (!$driver->connect($ip, (int)$port, $key)) {
            echo "[ERROR] Connection failed. Skipping device.\n";
            continue;
        }
        echo "[OK] Connected successfully.\n";

        // 2. Fetch logs
        echo "[INFO] Fetching attendance logs...\n";
        $logs = $driver->getAttendanceLogs();
        if (empty($logs)) {
            echo "[INFO] No new logs to fetch.\n";
            $driver->disconnect();
            continue;
        }
        echo "[OK] Fetched " . count($logs) . " raw log(s).\n";

        // 3. Save logs using the service
        echo "[INFO] Saving logs to database...\n";
        // Pass the device ID to the service
        $result = $attendanceService->saveStandardizedLogs($logs, $deviceId);
        echo "[OK] Save complete. New: {$result['success']}, Duplicates: {$result['duplicates']}, Failed: {$result['failed']}.\n";

        // 4. Update the last_sync_timestamp for the device
        $stmt_update_sync = $pdo->prepare("UPDATE devices SET last_sync_timestamp = NOW() WHERE id = ?");
        $stmt_update_sync->execute([$deviceId]);

        // 5. Disconnect
        $driver->disconnect();
        echo "[INFO] Disconnected.\n";
    }

    echo "\n=================================================\n";
    echo "Service run completed.\n";
    echo "=================================================\n";

} catch (Exception $e) {
    echo "[FATAL ERROR] An unexpected error occurred during the service run: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);