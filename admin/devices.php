<?php
// in file: admin/devices.php

require_once __DIR__ . '/../app/bootstrap.php';

// Check for admin role
if (!is_admin()) {
    // This redirect is fine because it happens before any output
    header('Location: /');
    exit();
}

$success_message = $_GET['success'] ?? '';
$error_message = '';

// --- LOGIC MOVED TO TOP ---
// All form processing and redirects must happen BEFORE any HTML is sent.

// Handle form submissions for adding/editing devices
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_id = filter_input(INPUT_POST, 'device_id', FILTER_VALIDATE_INT);
    $name = sanitize_input($_POST['name']);
    $ip_address = sanitize_input($_POST['ip_address']);
    $port = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
    $device_brand = sanitize_input($_POST['device_brand']);

    // Basic validation
    if (empty($name) || empty($ip_address) || empty($port) || empty($device_brand)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
        $error_message = 'Invalid IP address format.';
    } else {
        try {
            if ($device_id) {
                // Update existing device
                $stmt = $pdo->prepare("UPDATE devices SET name = ?, ip_address = ?, port = ?, device_brand = ? WHERE id = ?");
                $stmt->execute([$name, $ip_address, $port, $device_brand, $device_id]);
                $success_message = 'Device updated successfully.';
            } else {
                // Add new device
                $stmt = $pdo->prepare("INSERT INTO devices (name, ip_address, port, device_brand) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $ip_address, $port, $device_brand]);
                $success_message = 'Device added successfully.';
            }
            log_audit_action($pdo, $device_id ? 'update_device' : 'add_device', json_encode($_POST), get_current_user_id());
            // This header call is now safe
            header("Location: devices.php?success=" . urlencode($success_message));
            exit();
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Handle device deletion
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $device_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($device_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
            $stmt->execute([$device_id]);
            log_audit_action($pdo, 'delete_device', json_encode(['device_id' => $device_id]), get_current_user_id());
            // This header call is now safe
            header("Location: devices.php?success=Device deleted successfully.");
            exit();
        } catch (PDOException $e) {
            $error_message = "Error deleting device: " . $e->getMessage();
        }
    }
}

// Fetch all devices from the database for display
$stmt = $pdo->query("SELECT * FROM devices ORDER BY name ASC");
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --- PRESENTATION STARTS HERE ---
$page_title = 'Manage Devices';
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Manage Devices</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deviceModal">
        <i class="bi bi-plus-circle me-2"></i>Add New Device
    </button>
</div>

<?php if ($success_message && !$error_message): // Show success only if there are no new errors ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>IP Address</th>
                        <th>Port</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($devices)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No devices found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td><?= htmlspecialchars($device['id']) ?></td>
                                <td><?= htmlspecialchars($device['name']) ?></td>
                                <td><?= htmlspecialchars($device['ip_address']) ?></td>
                                <td><?= htmlspecialchars($device['port']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($device['device_brand'] ?? 'N/A') ?></span></td>
                                <td>
                                    <a href="device_users.php?id=<?= $device['id'] ?>" class="btn btn-info btn-sm" title="Manage Users">
                                        <i class="bi bi-people-fill"></i> Users
                                    </a>
                                    <button class="btn btn-warning btn-sm edit-device-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deviceModal"
                                            data-id="<?= $device['id'] ?>"
                                            data-name="<?= htmlspecialchars($device['name']) ?>"
                                            data-ip="<?= htmlspecialchars($device['ip_address']) ?>"
                                            data-port="<?= htmlspecialchars($device['port']) ?>"
                                            data-type="<?= htmlspecialchars($device['device_brand'] ?? '') ?>"
                                            title="Edit Device">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <a href="devices.php?action=delete&id=<?= $device['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this device?');" title="Delete Device">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
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
        <div class="modal-content">
            <form action="devices.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="deviceModalLabel">Add/Edit Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="device_id" id="device_id">
                    <div class="mb-3">
                        <label for="name" class="form-label">Device Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="ip_address" class="form-label">IP Address</label>
                        <input type="text" class="form-control" id="ip_address" name="ip_address" required>
                    </div>
                    <div class="mb-3">
                        <label for="port" class="form-label">Port</label>
                        <input type="number" class="form-control" id="port" name="port" value="4370" required>
                    </div>
                    <div class="mb-3">
                        <label for="device_brand" class="form-label">Device Type</label>
                        <select class="form-select" id="device_brand" name="device_brand" required>
                            <option value="Fingertec">Fingertec</option>
                            <option value="ZKTeco">ZKTeco</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deviceModal = document.getElementById('deviceModal');
    deviceModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const modalTitle = deviceModal.querySelector('.modal-title');
        const form = deviceModal.querySelector('form');

        // Reset form for "Add New"
        modalTitle.textContent = 'Add New Device';
        form.reset();
        document.getElementById('device_id').value = '';
        document.getElementById('device_brand').value = 'ZKTeco'; 

        // If button is an edit button, populate form
        if (button.classList.contains('edit-device-btn')) {
            modalTitle.textContent = 'Edit Device';
            document.getElementById('device_id').value = button.dataset.id;
            document.getElementById('name').value = button.dataset.name;
            document.getElementById('ip_address').value = button.dataset.ip;
            document.getElementById('port').value = button.dataset.port;
            document.getElementById('device_brand').value = button.dataset.type;
        }
    });
});
</script>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>