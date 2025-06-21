<?php
// scripts/sync_fake_users.php
// This script "hires" the fake employees by adding them to the database,
// allowing their attendance logs to be saved. Run this once.

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../app/core/drivers/EnhancedDriverFramework.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

echo "=================================================\n";
echo " Starting Fake User Sync Script\n";
echo "=================================================\n";

$stmt = $pdo->query("SELECT * FROM devices WHERE is_active = 1");
$devices = $stmt->fetchAll();

if (empty($devices)) {
    echo "[ERROR] No active devices found. Cannot sync users.\n";
    exit;
}

$find_user_stmt = $pdo->prepare("SELECT id FROM users WHERE employee_code = ?");
// FIXED: Removed the non-existent 'username' column from the INSERT statement.
$insert_user_stmt = $pdo->prepare(
    "INSERT INTO users (employee_code, full_name, password, email, role, shift_id) 
     VALUES (?, ?, ?, ?, 'employee', 1)" // Assuming default shift_id=1
);

$total_synced = 0;

foreach ($devices as $device) {
    echo "\n[INFO] Checking device: {$device['name']} ({$device['device_brand']})\n";
    $driver_class = ucfirst(strtolower($device['device_brand'])) . 'Driver';
    if (!class_exists($driver_class)) {
        echo "[WARN] Driver class '{$driver_class}' not found. Skipping.\n";
        continue;
    }

    $driver = new $driver_class();
    if ($driver->connect($device['ip_address'], (int)$device['port'])) {
        $device_users = $driver->getUsers();
        $driver->disconnect();

        if (empty($device_users)) {
            echo "[INFO] No users found on device to sync.\n";
            continue;
        }

        echo "[INFO] Found " . count($device_users) . " users on device. Checking against database...\n";

        foreach ($device_users as $user) {
            $employee_code = $user['user_id'];
            $find_user_stmt->execute([$employee_code]);
            if ($find_user_stmt->fetch()) {
                echo "  - User {$employee_code} ('{$user['name']}') already exists in database. Skipping.\n";
            } else {
                $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                // Generate a unique email based on name and employee code
                $email_user_part = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $user['name']));
                $email = "{$email_user_part}{$employee_code}@example.com";

                // FIXED: The execute call now matches the corrected INSERT statement.
                $insert_user_stmt->execute([
                    $employee_code,
                    $user['name'],
                    $password,
                    $email
                ]);
                echo "  - SUCCESS: Added user {$employee_code} ('{$user['name']}') to database.\n";
                $total_synced++;
            }
        }
    } else {
        echo "[ERROR] Could not connect to device. Skipping sync for this device.\n";
    }
}

echo "\n=================================================\n";
echo " Sync Finished. Total new users added: {$total_synced}\n";
echo "=================================================\n";