<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/drivers/EnhancedDriverFramework.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

require_role(['admin', 'hr_manager']);

$device_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$device_id) {
    header("Location: devices.php");
    exit();
}

// --- Helper function to get the correct driver ---
function get_driver(?string $brand): ?EnhancedBaseDriver {
    if (!$brand) return null;
    $brand_lower = strtolower($brand);
    try {
        if ($brand_lower === 'fingertec') return new FingertecDriver();
        if ($brand_lower === 'zkteco') return new ZKTecoDriver();
    } catch (Exception $e) {
        error_log("Failed to create driver for brand {$brand}: " . $e->getMessage());
    }
    return null;
}

// --- Fetch device details from database ---
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header("Location: devices.php");
    exit();
}

$error_message = '';
$success_message = '';
$device_users = [];
$is_online = false;

// --- Initialize driver ---
$driver = get_driver($device['device_brand']);

if (!$driver) {
    $error_message = "Could not initialize the driver for this device brand ({$device['device_brand']}).";
} else {
    // --- First, do a quick ping to see if device is online without hanging the page ---
    $is_online = $driver->ping($device['ip_address'], (int)$device['port']);
    
    if (!$is_online) {
        $error_message = "The device is offline or unreachable. Please check the connection and try again.";
    } else {
        // --- Handle form submissions (like syncing users) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_users') {
            if ($driver->connect($device['ip_address'], (int)$device['port'])) {
                $users_from_device = $driver->getUsers();
                $driver->disconnect();

                if (!empty($users_from_device)) {
                    $stmt_find = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
                    $stmt_insert = $pdo->prepare("INSERT INTO users (username, full_name, password, email, role) VALUES (?, ?, ?, ?, 'employee')");
                    
                    $new_users_count = 0;
                    foreach ($users_from_device as $device_user) {
                        $device_user_id = $device_user['user_id'] ?? null;
                        $device_user_name = $device_user['name'] ?? 'N/A';
                        
                        if (empty($device_user_id)) continue;
                        
                        $stmt_find->execute([$device_user_id]);
                        if ($stmt_find->fetchColumn() === false) {
                            // User does not exist, so add them
                            $default_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
                            $default_email = "{$device_user_id}@imported-device.local";
                            $stmt_insert->execute([$device_user_id, $device_user_name, $default_password, $default_email]);
                            $new_users_count++;
                        }
                    }
                    $success_message = "Sync complete. {$new_users_count} new users were imported into the system.";
                } else {
                    $error_message = "Could not retrieve users from the device, or the device has no users.";
                }
            } else {
                $error_message = "Failed to connect to the device for syncing: " . ($driver->getLastError() ?: 'Unknown error');
            }
        }

        // --- Fetch users for display ---
        if ($driver->connect($device['ip_address'], (int)$device['port'])) {
            $device_users = $driver->getUsers();
            $driver->disconnect();
        } else {
             $error_message = "Failed to connect to the device to retrieve users: " . ($driver->getLastError() ?: 'Unknown error');
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
        <?php if ($is_online): ?>
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
                    <?php if (!$is_online): ?>
                        <tr><td colspan="5" class="text-center p-4">Device is offline. Cannot display users.</td></tr>
                    <?php elseif (empty($device_users)): ?>
                        <tr><td colspan="5" class="text-center p-4">No users found on this device.</td></tr>
                    <?php else: ?>
                        <?php foreach ($device_users as $user): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($user['user_id'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($user['name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['privilege'] ?? 'N/A') ?></td>
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