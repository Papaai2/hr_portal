<?php

// A simple script to be run from the command line (CLI)
// Example usage: php scripts/import_users_from_device.php --ip=192.168.1.201 --brand=zkteco

echo "-------------------------------------------\n";
echo "HR Portal - Device User Import Script\n";
echo "-------------------------------------------\n";

// --- Bootstrap the application ---
// This ensures we have access to our database, drivers, and services.
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

// --- Helper function for parsing command line arguments ---
function get_cli_arg($arg_name) {
    global $argv;
    foreach ($argv as $arg) {
        if (strpos($arg, '--' . $arg_name . '=') === 0) {
            return substr($arg, strlen($arg_name) + 3);
        }
    }
    return null;
}

// --- Get arguments from command line ---
$ip = get_cli_arg('ip');
$brand = get_cli_arg('brand');
$port = get_cli_arg('port') ?? 4370; // Default port

if (!$ip || !$brand) {
    echo "[ERROR] Missing required arguments. Usage: \n";
    echo "php scripts/import_users_from_device.php --ip=<device_ip> --brand=<fingertec_or_zkteco>\n";
    exit(1);
}

echo "[INFO] Attempting to import from device at IP: {$ip} (Brand: {$brand})\n";

// --- The Driver Factory ---
// This block dynamically selects and instantiates the correct driver.
$driver = null;
switch (strtolower($brand)) {
    case 'fingertec':
        $driver = new FingertecDriver();
        break;
    case 'zkteco':
        $driver = new ZKTecoDriver();
        break;
    default:
        echo "[ERROR] Invalid brand '{$brand}'. Supported brands are 'fingertec' and 'zkteco'.\n";
        exit(1);
}

// --- Main Logic ---
try {
    // 1. Connect to the device
    echo "[INFO] Connecting to device...\n";
    if (!$driver->connect($ip, (int)$port, '0')) {
        echo "[ERROR] Connection failed. Please check the IP address and network connection.\n";
        exit(1);
    }
    echo "[SUCCESS] Connected to device: " . $driver->getDeviceName() . "\n";

    // 2. Get users from the device
    echo "[INFO] Fetching user list from device. This may take a moment...\n";
    $deviceUsers = $driver->getUsers();
    if (empty($deviceUsers)) {
        echo "[WARNING] No users found on the device or failed to retrieve user list.\n";
        $driver->disconnect();
        exit(0);
    }
    echo "[SUCCESS] Found " . count($deviceUsers) . " users on the device.\n";

    // 3. Process and insert users into the database
    echo "[INFO] Starting database import process...\n";
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE employee_code = ?");
    $stmt_insert = $pdo->prepare("INSERT INTO users (employee_code, name, password, role) VALUES (?, ?, ?, ?)");

    $importedCount = 0;
    $skippedCount = 0;
    
    // Default password for new users. They should be prompted to change it on first login.
    $defaultPassword = password_hash('welcome123', PASSWORD_DEFAULT);

    foreach ($deviceUsers as $user) {
        $employeeCode = $user['employee_code'];
        $name = $user['name'];
        $role = ($user['role'] === 'Admin') ? 'admin' : 'employee';

        // Check if user already exists
        $stmt_check->execute([$employeeCode]);
        if ($stmt_check->fetch()) {
            echo "  - Skipping user '{$name}' (Code: {$employeeCode}): Already exists in database.\n";
            $skippedCount++;
            continue;
        }

        // Insert new user
        if ($stmt_insert->execute([$employeeCode, $name, $defaultPassword, $role])) {
            echo "  - Imported user '{$name}' (Code: {$employeeCode}).\n";
            $importedCount++;
        } else {
            echo "  - FAILED to import user '{$name}' (Code: {$employeeCode}).\n";
        }
    }
    
    echo "[INFO] Import complete.\n";
    echo "-------------------------------------------\n";
    echo "Summary:\n";
    echo " - Successfully Imported: {$importedCount}\n";
    echo " - Skipped (Duplicates):  {$skippedCount}\n";
    echo "-------------------------------------------\n";


} catch (Exception $e) {
    echo "[FATAL ERROR] An unexpected error occurred: " . $e->getMessage() . "\n";
} finally {
    // 4. Always disconnect
    if ($driver) {
        $driver->disconnect();
        echo "[INFO] Disconnected from device.\n";
    }
}

exit(0);

