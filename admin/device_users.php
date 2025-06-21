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
$success_message = $_GET['success'] ?? ''; // For redirect messages
$device_users = [];
$is_online = false;
$driver = get_driver($device['device_brand']);

if (!$driver) {
    $error_message = "Could not initialize driver for device brand '{$device['device_brand']}'.";
} else {
    $is_online = $driver->ping($device['ip_address'], (int)$device['port']);
    
    if (!$is_online) {
        $error_message = "Device is offline. Please check its network connection and power.";
    } else {
        $driver->setConfig(['timeout' => 15, 'retry_attempts' => 1]);

        try {
            if ($driver->connect($device['ip_address'], (int)$device['port'])) {
                $device_users = $driver->getUsers();
            } else {
                $error_message = "Device is online but failed to establish a connection. It may be busy or require a specific communication key. Error: " . ($driver->getLastError() ?: 'Unknown error');
            }
        } catch (Exception $e) {
            $error_message = "An error occurred while communicating with the device: " . $e->getMessage();
            error_log("Device communication error for device ID {$device_id}: " . $e->getMessage());
        } finally {
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
</div>

<?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
<?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

<div class="card shadow-sm mb-4">
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
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Privilege</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($error_message) && empty($device_users)): ?>
                        <tr><td colspan="3" class="text-center p-4">Could not retrieve users. Please check the error message above.</td></tr>
                    <?php elseif (empty($device_users)): ?>
                        <tr><td colspan="3" class="text-center p-4">No users found on this device or the device is offline.</td></tr>
                    <?php else: ?>
                        <?php foreach ($device_users as $user): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($user['user_id'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($user['name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['privilege'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>