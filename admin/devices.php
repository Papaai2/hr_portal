<?php
// admin/devices.php

// --- Bootstrap The Application ---
// By including this single file, we ensure the entire application
// environment (session, database, helpers, auth) is loaded correctly.
require_once __DIR__ . '/../app/bootstrap.php';

// --- Authentication & Authorization Check ---
// Use require_role to ensure only admins can access this page.
require_role('admin');

// --- Database Connection ---
// The $pdo variable is now globally available from bootstrap.php
global $pdo; 
$message = '';
$message_type = '';

// --- BEGIN BACKEND LOGIC (FORM PROCESSING) ---

// Handle POST requests for Add, Update, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- Add New Device ---
    if (isset($_POST['add_device'])) {
        $name = sanitize_input($_POST['name']);
        $ip_address = sanitize_input($_POST['ip_address']);
        $port = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
        $device_brand = sanitize_input($_POST['device_brand']);

        if ($name && $ip_address && $port && $device_brand) {
            try {
                $stmt = $pdo->prepare("INSERT INTO devices (name, ip_address, port, device_brand) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $ip_address, $port, $device_brand])) {
                    $message = 'Device added successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to add device.';
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = 'Please fill in all fields correctly.';
            $message_type = 'error';
        }
    }

    // --- Update Existing Device ---
    if (isset($_POST['update_device'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = sanitize_input($_POST['name']);
        $ip_address = sanitize_input($_POST['ip_address']);
        $port = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
        $device_brand = sanitize_input($_POST['device_brand']);
        // CORRECTED: Checkbox value handling
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id && $name && $ip_address && $port && $device_brand) {
            try {
                $stmt = $pdo->prepare("UPDATE devices SET name = ?, ip_address = ?, port = ?, device_brand = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$name, $ip_address, $port, $device_brand, $is_active, $id])) {
                    $message = 'Device updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update device.';
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = 'Invalid data submitted.';
            $message_type = 'error';
        }
    }

    // --- Delete Device ---
    if (isset($_POST['delete_device'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                // First, delete related attendance logs to avoid foreign key constraints
                $stmt_delete_logs = $pdo->prepare("DELETE FROM attendance_logs WHERE device_id = ?");
                $stmt_delete_logs->execute([$id]);

                // Then, delete the device itself
                $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $message = 'Device and related logs deleted successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete device.';
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// --- Fetch all devices for display ---
$stmt_get_all = $pdo->query("SELECT * FROM devices ORDER BY name ASC");
$devices = $stmt_get_all->fetchAll(PDO::FETCH_ASSOC);

// --- END BACKEND LOGIC ---


// --- BEGIN TEMPLATE ---
// The header file should handle the HTML head, body tag, and main navigation.
include __DIR__ . '/../app/templates/header.php';
?>

<div class="container mx-auto my-10 px-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manage Attendance Devices</h1>

    <?php if ($message): ?>
        <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <h2 class="card-header h5">Add New Device</h2>
        <div class="card-body">
            <form action="devices.php" method="POST" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="name" class="form-label">Device Name</label>
                    <input type="text" id="name" name="name" required class="form-control" placeholder="e.g., Main Entrance">
                </div>
                <div class="col-md-3">
                    <label for="ip_address" class="form-label">IP Address</label>
                    <input type="text" id="ip_address" name="ip_address" required class="form-control" placeholder="e.g., 192.168.1.201">
                </div>
                <div class="col-md-2">
                    <label for="port" class="form-label">Port</label>
                    <input type="number" id="port" name="port" required value="4370" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="device_brand" class="form-label">Device Brand</label>
                    <select id="device_brand" name="device_brand" required class="form-select">
                        <option value="zkteco">ZKTeco</option>
                        <option value="fingertec">Fingertec</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_device" class="btn btn-primary w-100">
                        Add Device
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <h2 class="card-header h5">Configured Devices</h2>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>IP Address</th>
                            <th>Port</th>
                            <th>Brand</th>
                            <th>Status</th>
                            <th>Last Sync</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($devices)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted p-4">No devices configured yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($devices as $device): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($device['name']); ?></td>
                                    <td><?php echo htmlspecialchars($device['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($device['port']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($device['device_brand'])); ?></td>
                                    <td>
                                        <?php if ($device['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                     <td class="text-muted">
                                        <?php echo $device['last_sync_timestamp'] ? date('Y-m-d H:i:s', strtotime($device['last_sync_timestamp'])) : 'Never'; ?>
                                    </td>
                                    <td class="text-end">
                                        <form action="devices.php" method="POST" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $device['id']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDeviceModal-<?php echo $device['id']; ?>">
                                                <i class="bi bi-pencil-fill"></i> Edit
                                            </button>
                                            <button type="submit" name="delete_device" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this device? This will also remove all associated attendance logs.');">
                                                <i class="bi bi-trash-fill"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editDeviceModal-<?php echo $device['id']; ?>" tabindex="-1" aria-labelledby="editDeviceModalLabel-<?php echo $device['id']; ?>" aria-hidden="true">
                                  <div class="modal-dialog">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="editDeviceModalLabel-<?php echo $device['id']; ?>">Edit Device</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <form action="devices.php" method="POST">
                                          <div class="modal-body">
                                                <input type="hidden" name="id" value="<?php echo $device['id']; ?>">
                                                <div class="mb-3">
                                                    <label for="name-<?php echo $device['id']; ?>" class="form-label">Device Name</label>
                                                    <input type="text" id="name-<?php echo $device['id']; ?>" name="name" required class="form-control" value="<?php echo htmlspecialchars($device['name']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="ip_address-<?php echo $device['id']; ?>" class="form-label">IP Address</label>
                                                    <input type="text" id="ip_address-<?php echo $device['id']; ?>" name="ip_address" required class="form-control" value="<?php echo htmlspecialchars($device['ip_address']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="port-<?php echo $device['id']; ?>" class="form-label">Port</label>
                                                    <input type="number" id="port-<?php echo $device['id']; ?>" name="port" required class="form-control" value="<?php echo htmlspecialchars($device['port']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="device_brand-<?php echo $device['id']; ?>" class="form-label">Device Brand</label>
                                                    <select id="device_brand-<?php echo $device['id']; ?>" name="device_brand" required class="form-select">
                                                        <option value="zkteco" <?php if($device['device_brand'] == 'zkteco') echo 'selected'; ?>>ZKTeco</option>
                                                        <option value="fingertec" <?php if($device['device_brand'] == 'fingertec') echo 'selected'; ?>>Fingertec</option>
                                                    </select>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active-<?php echo $device['id']; ?>" <?php if ($device['is_active']) echo 'checked'; ?>>
                                                    <label class="form-check-label" for="is_active-<?php echo $device['id']; ?>">
                                                        Device is Active
                                                    </label>
                                                </div>
                                          </div>
                                          <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" name="update_device" class="btn btn-primary">Save changes</button>
                                          </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// This should include the closing body and html tags.
include __DIR__ . '/../app/templates/footer.php';
?>