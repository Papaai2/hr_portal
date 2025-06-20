<?php
// in file: admin/device_users.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';

// Check for admin role
if (!is_admin()) {
    header('Location: /');
    exit();
}

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
function get_device_driver(string $device_type): ?DeviceDriverInterface {
    // FIX: Use strcasecmp for a case-insensitive comparison.
    if (strcasecmp($device_type, 'Fingertec') === 0) {
        return new FingertecDriver();
    }
    if (strcasecmp($device_type, 'ZKTeco') === 0) {
        return new ZKTecoDriver();
    }
    return null;
}

$driver = get_device_driver($device['device_brand']);
$page_title = 'Manage Users on ' . htmlspecialchars($device['name']);
$users_on_device = [];
$error_message = '';
$success_message = $_GET['success'] ?? '';

// --- Connection status flag ---
$is_connected = false;

if (!$driver) {
    $error_message = 'Unsupported device type: ' . htmlspecialchars($device['device_brand']);
} else {
    // --- Connect to the device ONCE ---
    if ($driver->connect($device['ip_address'], (int)$device['port'], $device['communication_key'])) {
        $is_connected = true; // Set the flag

        // Handle POST actions (add/delete user)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'add_user') {
                    $userData = [
                        'employee_code' => sanitize_input($_POST['employee_code']),
                        'name' => sanitize_input($_POST['name']),
                        'password' => sanitize_input($_POST['password']),
                        'role' => sanitize_input($_POST['role']),
                    ];
                    if ($driver->addUser($userData)) {
                        log_audit_action($pdo, 'add_device_user', json_encode(['device_id' => $device_id, 'user' => $userData]), get_current_user_id());
                        $success_message = "User added to device successfully.";
                    } else {
                        $error_message = "Failed to add user to the device.";
                    }
                } elseif ($action === 'delete_user') {
                    $employee_code = sanitize_input($_POST['employee_code']);
                    if ($driver->deleteUser($employee_code)) {
                         log_audit_action($pdo, 'delete_device_user', json_encode(['device_id' => $device_id, 'employee_code' => $employee_code]), get_current_user_id());
                        $success_message = "User deleted from device successfully.";
                    } else {
                        $error_message = "Failed to delete user from the device.";
                    }
                }
                // Redirect after POST to prevent form resubmission
                $redirect_url = "device_users.php?id={$device_id}";
                if ($success_message) {
                    $redirect_url .= "&success=" . urlencode($success_message);
                }
                header("Location: " . $redirect_url);
                exit();
            } catch (Exception $e) {
                $error_message = "An error occurred: " . $e->getMessage();
            }
        }

        // Fetch users from the device for display (for GET requests)
        $users_on_device = $driver->getUsers();
        // Disconnect after all operations are done
        $driver->disconnect(); 
    } else {
         $error_message = 'Could not connect to the device. Please check if it is online and accessible.';
    }
}

include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?= $page_title ?></h1>
    <a href="devices.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Devices
    </a>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="card-title mb-0">Users on Device</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$is_connected): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-danger">Could not retrieve user list. Device is offline.</td>
                                </tr>
                            <?php elseif (empty($users_on_device)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No users found on the device.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users_on_device as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['employee_code']) ?></td>
                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($user['role']) ?></span></td>
                                        <td>
                                            <form action="device_users.php?id=<?= $device_id ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="employee_code" value="<?= htmlspecialchars($user['employee_code']) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user from the device?');" title="Delete User">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="card-title mb-0">Add New User to Device</h5>
            </div>
            <div class="card-body">
                <form action="device_users.php?id=<?= $device_id ?>" method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label for="employee_code" class="form-label">Employee Code</label>
                        <input type="text" class="form-control" id="employee_code" name="employee_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password (optional)</label>
                        <input type="text" class="form-control" id="password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="User">User</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" <?= !$is_connected ? 'disabled' : '' ?>>
                           <i class="bi bi-person-plus-fill me-2"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>