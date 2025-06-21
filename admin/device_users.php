<?php
require_once __DIR__ . '/../app/bootstrap.php';
// Correctly including all necessary driver files
require_once __DIR__ . '/../app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../app/core/drivers/EnhancedDriverFramework.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

require_role(['admin', 'hr_manager']);

$device_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$device_id) {
    header("Location: devices.php");
    exit();
}

/**
 * Gets the appropriate driver for a given device brand.
 *
 * @param string|null $brand The device brand.
 * @return EnhancedBaseDriver|null The driver instance or null on failure.
 */
function get_driver(?string $brand): ?EnhancedBaseDriver {
    if (!$brand) return null;
    $brand_lower = strtolower($brand);
    try {
        if ($brand_lower === 'fingertec') {
            return new FingertecDriver();
        }
        if ($brand_lower === 'zkteco') {
            return new ZKTecoDriver();
        }
    } catch (Exception $e) {
        // Log the driver creation failure for debugging.
        error_log("Driver creation failed for brand '{$brand}': " . $e->getMessage());
    }
    return null;
}

$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header("Location: devices.php?error=" . urlencode("Device not found."));
    exit();
}

$error_message = '';
$success_message = '';
$device_users = [];
$is_online = false;
$driver = get_driver($device['device_brand']);

if (!$driver) {
    $error_message = "Could not initialize driver for device brand '{$device['device_brand']}'. The brand may be unsupported or misconfigured.";
} else {
    // Use the quick ping method to check for basic network reachability first.
    $is_online = $driver->ping($device['ip_address'], (int)$device['port']);
    
    if (!$is_online) {
        $error_message = "Device is offline. Please check its network connection and power.";
    } else {
        // Set a reasonable timeout for UI operations to prevent page hangs.
        $driver->setConfig(['timeout' => 15, 'retry_attempts' => 1]);

        try {
            // Attempt to establish a full connection.
            if ($driver->connect($device['ip_address'], (int)$device['port'])) {
                // Handle the user synchronization POST request.
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_users') {
                    $device_users = $driver->getUsers(); // Get users right before sync.
                    if (!empty($device_users)) {
                        $stmt_find = $pdo->prepare("SELECT 1 FROM users WHERE employee_code = ?");
                        $stmt_insert = $pdo->prepare("INSERT INTO users (employee_code, username, full_name, password, email, role, shift_id) VALUES (?, ?, ?, ?, ?, 'employee', 1)");
                        
                        $new_users_count = 0;
                        foreach ($device_users as $device_user) {
                            $device_user_id = $device_user['user_id'] ?? ($device_user['employee_code'] ?? null);
                            if (empty($device_user_id)) continue;

                            $stmt_find->execute([$device_user_id]);
                            if ($stmt_find->fetchColumn() === false) {
                                $device_user_name = !empty($device_user['name']) ? $device_user['name'] : 'Unnamed User';
                                $default_username = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($device_user_name)) . '_' . $device_user_id;
                                $default_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
                                $default_email = "{$default_username}@imported-device.local";
                                
                                $stmt_insert->execute([$device_user_id, $default_username, $device_user_name, $default_password, $default_email]);
                                $new_users_count++;
                            }
                        }
                        $success_message = "Sync complete. {$new_users_count} new users were imported.";
                    } else {
                        $error_message = "Sync failed: Could not retrieve users from the device, or the device has no users registered.";
                    }
                }

                // Always fetch users for display after any potential POST action.
                $device_users = $driver->getUsers();

            } else {
                // This block now correctly catches connection failures after a successful ping.
                $error_message = "Device is online but failed to establish a connection. It may be busy or require a specific communication key. Error: " . ($driver->getLastError() ?: 'Unknown error');
            }
        } catch (Exception $e) {
            // This will catch any exceptions from the driver during connection or data fetching.
            $error_message = "An error occurred while communicating with the device: " . $e->getMessage();
            error_log("Device communication error for device ID {$device_id}: " . $e->getMessage());
        } finally {
            // Always ensure the connection is closed.
            if ($driver->isConnected()) {
                $driver->disconnect();
            }
        }
    }
}

$page_title = 'Device Users';
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2">Manage Users for <?= htmlspecialchars($device['name']) ?></h1>
        <a href="devices.php" class="text-decoration-none"><i class="bi bi-arrow-left-circle"></i> Back to Devices List</a>
    </div>
    <?php if ($is_online && !empty($device_users)): ?>
    <form action="device_users.php?id=<?= $device_id ?>" method="POST" onsubmit="return confirm('This will import new users from the device into the system. Existing users will not be affected. Continue?');">
        <input type="hidden" name="action" value="sync_users">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-arrow-repeat me-2"></i>Sync Users to System
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Users on Device</h2>
        <?php if ($is_online && empty($error_message)): ?>
            <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> Device Online</span>
        <?php else: ?>
            <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i> Device Offline</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Device User ID</th>
                        <th>Name</th>
                        <th>Privilege</th>
                        <th>Card ID</th>
                        <th>Group ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($error_message) && empty($device_users)): ?>
                        <tr><td colspan="5" class="text-center p-4">Could not retrieve users. Please check the error message above.</td></tr>
                    <?php elseif (empty($device_users)): ?>
                        <tr><td colspan="5" class="text-center p-4">No users found on this device or the device is offline.</td></tr>
                    <?php else: ?>
                        <?php foreach ($device_users as $user): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($user['user_id'] ?? ($user['employee_code'] ?? 'N/A')) ?></strong></td>
                                <td><?= htmlspecialchars($user['name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['privilege'] ?? ($user['role'] ?? 'N/A')) ?></td>
                                <td><?= htmlspecialchars($user['card_id'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['group_id'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>