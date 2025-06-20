<?php
// in file: admin/devices.php
// UPDATED to include a live device status check.

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

require_role(['admin', 'hr_manager']);

$devices = $pdo->query("SELECT * FROM devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

/**
 * A simple factory to get the correct device driver instance.
 * @param string|null $device_brand The brand of the device ('ZKTeco', 'Fingertec').
 * @return DeviceDriverInterface|null The driver instance or null if not supported.
 */
function get_device_driver(?string $device_brand): ?DeviceDriverInterface
{
    if (!$device_brand) return null;
    $brand = strtolower($device_brand);
    if ($brand === 'fingertec') return new FingertecDriver();
    if ($brand === 'zkteco') return new ZKTecoDriver();
    return null;
}

// --- Check Device Status ---
// Note: This can make the page load slower if devices are offline due to connection timeouts.
// For many devices, a more advanced solution would use AJAX to check statuses in the background.
foreach ($devices as &$device) { // Use reference '&' to modify the array directly
    $driver = get_device_driver($device['device_brand']);
    if ($driver && $driver->connect($device['ip_address'], (int)$device['port'], $device['communication_key'] ?? '0')) {
        $device['status'] = 'Online';
        $driver->disconnect();
    } else {
        $device['status'] = 'Offline';
    }
}
unset($device); // Unset the reference to avoid potential side effects

$page_title = 'Manage Devices';
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Manage Devices</h1>
    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deviceModal">
        <i class="bi bi-plus-circle me-2"></i>Add New Device
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Connection</th>
                        <th>Type</th>
                        <th>Status</th> <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($devices)): ?>
                        <tr><td colspan="5" class="text-center">No devices found. Add one to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($device['name']) ?></strong><br>
                                    <small class="text-muted">ID: <?= htmlspecialchars($device['id']) ?></small>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($device['ip_address']) ?>:<?= htmlspecialchars($device['port']) ?></code>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($device['device_brand'] ?? 'N/A') ?></span></td>
                                <td>
                                    <?php if ($device['status'] === 'Online'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Online</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="device_users.php?id=<?= $device['id'] ?>" class="btn btn-info btn-sm" title="Manage Users"><i class="bi bi-people-fill"></i> Users</a>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>