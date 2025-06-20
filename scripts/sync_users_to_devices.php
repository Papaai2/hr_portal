<?php
// in file: scripts/sync_users_to_devices.php
// A script to synchronize users from the central database to all devices.

// Set a longer execution time for script, as this could take a while
set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure this script is run from the command line, not a browser
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

echo "===============================================\n";
echo "  STARTING USER SYNCHRONIZATION PROCESS\n";
echo "===============================================\n\n";

// 1. Get all users from the central database that should be on devices
$stmt_users = $pdo->query("SELECT employee_code, full_name, password, role FROM users WHERE is_active = 1 AND employee_code IS NOT NULL AND employee_code != ''");
$portal_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

if (empty($portal_users)) {
    echo "No active users with employee codes found in the portal database. Exiting.\n";
    exit;
}
echo "Found " . count($portal_users) . " active users to sync.\n\n";

// 2. Get all configured devices
$stmt_devices = $pdo->query("SELECT * FROM devices");
$devices = $stmt_devices->fetchAll(PDO::FETCH_ASSOC);

function get_driver(?string $brand): ?DeviceDriverInterface {
    if (!$brand) return null;
    $brand = strtolower($brand);
    if ($brand === 'fingertec') return new FingertecDriver();
    if ($brand === 'zkteco') return new ZKTecoDriver();
    return null;
}

// 3. Loop through each device and sync users
foreach ($devices as $device) {
    echo "--- Processing device: {$device['name']} ({$device['ip_address']}) ---\n";
    $driver = get_driver($device['device_brand']);

    if (!$driver) {
        echo "[SKIP] Unsupported device brand: {$device['device_brand']}\n\n";
        continue;
    }

    if ($driver->connect($device['ip_address'], (int)$device['port'], $device['communication_key'] ?? '0')) {
        echo "[OK] Successfully connected to device.\n";
        
        $success_count = 0;
        $fail_count = 0;

        foreach ($portal_users as $user_to_sync) {
            echo "  -> Syncing user '{$user_to_sync['full_name']}' (Code: {$user_to_sync['employee_code']})... ";
            
            // Map portal roles to device roles
            $device_role = (in_array($user_to_sync['role'], ['admin', 'hr_manager'])) ? 'Admin' : 'User';

            $userData = [
                'employee_code' => $user_to_sync['employee_code'],
                'name' => $user_to_sync['full_name'],
                'password' => $user_to_sync['password'] ?? '', // Note: This is the hashed password, real devices may need plaintext
                'role' => $device_role
            ];

            // In a real implementation, you would check if the user exists and call
            // updateUser if needed. For this push-based sync, addUser is sufficient.
            if ($driver->addUser($userData)) {
                echo "SUCCESS\n";
                $success_count++;
            } else {
                echo "FAILED\n";
                $fail_count++;
            }
        }
        
        echo "[DONE] Sync complete for this device. Success: {$success_count}, Failed: {$fail_count}\n";
        $driver->disconnect();
    } else {
        echo "[FAIL] Could not connect to device. Skipping.\n";
    }
    echo "--------------------------------------------------\n\n";
}

echo "Synchronization process finished.\n";