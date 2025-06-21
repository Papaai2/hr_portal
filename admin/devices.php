<?php
// in file: admin/devices.php
// FINAL ENHANCED VERSION with Status Check and fixed Modal logic

require_once __DIR__ . '/../app/bootstrap.php';
require_once '../app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

require_role(['admin', 'hr_manager']);

$error_message = '';
$success_message = $_GET['success'] ?? '';

// --- Form Processing at the Top ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This block handles both add and update actions
    $device_id = filter_input(INPUT_POST, 'device_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $ip_address = trim($_POST['ip_address'] ?? '');
    $port = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
    $device_brand = trim($_POST['device_brand'] ?? '');

    if (empty($name) || empty($ip_address) || empty($port) || empty($device_brand)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
        $error_message = 'The provided IP address is not valid.';
    } else {
        try {
            if ($device_id) { // Update existing device
                $stmt = $pdo->prepare("UPDATE devices SET name = ?, ip_address = ?, port = ?, device_brand = ? WHERE id = ?");
                $stmt->execute([$name, $ip_address, $port, $device_brand, $device_id]);
                $success_message = 'Device updated successfully.';
            } else { // Add new device
                $stmt = $pdo->prepare("INSERT INTO devices (name, ip_address, port, device_brand) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $ip_address, $port, $device_brand]);
                $success_message = 'Device added successfully.';
            }
            header("Location: devices.php?success=" . urlencode($success_message));
            exit();
        } catch (PDOException $e) {
            $error_message = 'Database error: Could not save the device. ' . $e->getMessage();
        }
    }
}

// --- Data Fetching ---
$devices = $pdo->query("SELECT * FROM devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

function get_driver(?string $brand): ?DeviceDriverInterface {
    if (!$brand) return null;
    $brand = strtolower($brand);
    if ($brand === 'fingertec') return new FingertecDriver();
    if ($brand === 'zkteco') return new ZKTecoDriver();
    return null;
}

// Loop through devices to check their live status
foreach ($devices as &$device) {
    $driver = get_driver($device['device_brand']);
    if ($driver && $driver->connect($device['ip_address'], (int)$device['port'], $device['communication_key'] ?? '0')) {
        $device['status'] = 'Online';
        $driver->disconnect();
    } else {
        $device['status'] = 'Offline';
    }
}
unset($device);

$page_title = 'Manage Devices';
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Manage Devices</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deviceModal">
        <i class="bi bi-plus-circle me-2"></i>Add New Device
    </button>
</div>

<?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header"><h2 class="h5 mb-0">Registered Devices</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Connection</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($devices)): ?>
                        <tr><td colspan="5" class="text-center p-4">No devices found. Click "Add New Device" to begin.</td></tr>
                    <?php else: ?>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($device['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($device['ip_address']) ?>:<?= htmlspecialchars($device['port']) ?></code></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($device['device_brand']) ?></span></td>
                                <td>
                                    <?php if ($device['status'] === 'Online'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> Online</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i> Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="device_users.php?id=<?= $device['id'] ?>" class="btn btn-info btn-sm" title="Manage Users"><i class="bi bi-people-fill"></i> Users</a>
                                    <button class="btn btn-warning btn-sm edit-device-btn"
                                            data-bs-toggle="modal" data-bs-target="#deviceModal"
                                            data-id="<?= $device['id'] ?>"
                                            data-name="<?= htmlspecialchars($device['name']) ?>"
                                            data-ip="<?= htmlspecialchars($device['ip_address']) ?>"
                                            data-port="<?= htmlspecialchars($device['port']) ?>"
                                            data-brand="<?= htmlspecialchars($device['device_brand']) ?>"
                                            title="Edit Device"><i class="bi bi-pencil-fill"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="deviceModal" tabindex="-1" aria-labelledby="deviceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="devices.php" method="POST" id="deviceForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deviceModalLabel">Add Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="device_id" id="form_device_id">
                    <div class="mb-3">
                        <label for="form_name" class="form-label">Device Name</label>
                        <input type="text" class="form-control" id="form_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="form_ip_address" class="form-label">IP Address</label>
                        <input type="text" class="form-control" id="form_ip_address" name="ip_address" required>
                    </div>
                    <div class="mb-3">
                        <label for="form_port" class="form-label">Port</label>
                        <input type="number" class="form-control" id="form_port" name="port" required>
                    </div>
                    <div class="mb-3">
                        <label for="form_device_brand" class="form-label">Device Brand</label>
                        <select class="form-select" id="form_device_brand" name="device_brand" required>
                            <option value="ZKTeco">ZKTeco</option>
                            <option value="Fingertec">Fingertec</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Device</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deviceModal = document.getElementById('deviceModal');
    const modalTitle = document.getElementById('deviceModalLabel');
    const deviceForm = document.getElementById('deviceForm');
    const deviceIdInput = document.getElementById('form_device_id');
    const nameInput = document.getElementById('form_name');
    const ipInput = document.getElementById('form_ip_address');
    const portInput = document.getElementById('form_port');
    const brandInput = document.getElementById('form_device_brand');

    deviceModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        
        // Check if the button that triggered the modal is for editing
        if (button && button.classList.contains('edit-device-btn')) {
            // Edit Mode
            modalTitle.textContent = 'Edit Device';
            deviceIdInput.value = button.dataset.id;
            nameInput.value = button.dataset.name;
            ipInput.value = button.dataset.ip;
            portInput.value = button.dataset.port;
            brandInput.value = button.dataset.brand;
        } else {
            // Add Mode
            modalTitle.textContent = 'Add New Device';
            deviceForm.reset();
            deviceIdInput.value = ''; // Ensure ID is cleared
            portInput.value = '4370'; // Default port
        }
    });
});
</script>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>