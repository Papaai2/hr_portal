<?php
// in file: admin/device_users.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

require_role(['admin', 'hr_manager']);

$device_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$device_id) {
    header('Location: /admin/devices.php');
    exit();
}

// Fetch device details from DB
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header('Location: /admin/devices.php?error=Device+not+found');
    exit();
}

// --- Driver Factory ---
function get_device_driver(string $device_brand): ?DeviceDriverInterface {
    $brand = strtolower($device_brand);
    if ($brand === 'fingertec') return new FingertecDriver();
    if ($brand === 'zkteco') return new ZKTecoDriver();
    return null;
}

$driver = get_device_driver($device['device_brand']);
$page_title = 'Manage Users on ' . htmlspecialchars($device['name']);
$users_on_device = [];
$error_message = '';
$success_message = $_GET['success'] ?? '';
$is_connected = false;

if (!$driver) {
    $error_message = 'Unsupported device type: ' . htmlspecialchars($device['device_brand']);
} else {
    // --- Connect to the device to check status and fetch data ---
    if ($driver->connect($device['ip_address'], (int)$device['port'], $device['communication_key'])) {
        $is_connected = true;

        // POST actions are not included in this rebuild as they are complex (add/delete users)
        // and a stub is sufficient for this simulation.

        // Fetch users for display
        $users_on_device = $driver->getUsers();
        $driver->disconnect();
    } else {
         $error_message = 'Could not connect to the device. Please check its IP address and ensure it is online and accessible from the server.';
    }
}

include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><?= $page_title ?></h1>
        <?php if ($is_connected): ?>
            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Connected</span>
        <?php else: ?>
            <span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> Disconnected</span>
        <?php endif; ?>
    </div>
    <a href="devices.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Devices</a>
</div>

<?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header"><h5 class="card-title mb-0">Users on Device</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr><th>Employee Code</th><th>Name</th><th>Role</th></tr>
                </thead>
                <tbody>
                    <?php if (!$is_connected): ?>
                        <tr><td colspan="3" class="text-center text-danger">Could not retrieve user list. Device is offline.</td></tr>
                    <?php elseif (empty($users_on_device)): ?>
                        <tr><td colspan="3" class="text-center">No users found on the device.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users_on_device as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['employee_code']) ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($user['role']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>