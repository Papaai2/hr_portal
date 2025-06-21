<?php
// in file: admin/device_users.php
// REFINED AND CLEANED-UP VERSION
require_once __DIR__ . '/../app/bootstrap.php';
require_once '../app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/../app/core/drivers/ZKTecoDriver.php';
require_role(['admin', 'hr_manager']);
$device_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$device_id) {
    header('Location: /admin/devices.php');
    exit();
}
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$device) {
    header('Location: /admin/devices.php?error=Device+not+found');
    exit();
}
function get_device_driver(?string $device_brand): ?DeviceDriverInterface {
    if (!$device_brand) return null;
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
    $error_message = 'Unsupported device brand: ' . htmlspecialchars($device['device_brand']);
} else {
    if ($driver->connect($device['ip_address'], (int)$device['port'], $device['communication_key'] ?? '0')) {
        $is_connected = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $redirect_url = "device_users.php?id={$device_id}";
            try {
                if ($action === 'add_user') {
                    $employee_code = trim($_POST['employee_code']);
                    $userData = [
                        'name' => trim($_POST['name']),
                        'password' => trim($_POST['password']),
                        'role' => trim($_POST['role']),
                        'privilege' => trim($_POST['role']) === 'Admin' ? 14 : 0, // ZKTeco privilege levels
                    ];
                    
                    if (empty($employee_code) || empty($userData['name'])) {
                         throw new Exception("Employee Code and Name are required.");
                    }
                    
                    // FIXED: Pass both userId (employee_code) and userData
                    if ($driver->addUser($employee_code, $userData)) {
                        $success_message = "User '{$userData['name']}' added to device successfully.";
                    } else {
                        throw new Exception("Failed to add user to the device.");
                    }
                } elseif ($action === 'delete_user') {
                    $employee_code = trim($_POST['employee_code']);
                    
                    if (empty($employee_code)) {
                        throw new Exception("Employee Code is required for deletion.");
                    }
                    
                    // FIXED: Pass employee_code as userId parameter
                    if ($driver->deleteUser($employee_code)) {
                        $success_message = "User #{$employee_code} deleted successfully.";
                    } else {
                       throw new Exception("Failed to delete user from the device.");
                    }
                }
                 header("Location: {$redirect_url}&success=" . urlencode($success_message));
                 exit();
            } catch (Exception $e) {
                 // To show the error, we don't redirect. We fall through to render the page.
                 $error_message = $e->getMessage();
            }
        }
        
        $users_on_device = $driver->getUsers();
        $driver->disconnect();
    } else {
         $error_message = 'Could not connect to the device. Please check if it is online and accessible.';
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
<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title mb-0">Users on Device</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>Employee Code</th><th>Name</th><th>Role</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$is_connected): ?>
                                <tr><td colspan="4" class="text-center p-4">Could not retrieve user list. Device is offline.</td></tr>
                            <?php elseif (empty($users_on_device)): ?>
                                <tr><td colspan="4" class="text-center p-4">No users found on the device.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users_on_device as $user): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($user['user_id'] ?? $user['employee_code'] ?? 'N/A') ?></code></td>
                                        <td><?= htmlspecialchars($user['name'] ?? 'Unknown') ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars(($user['privilege'] ?? 0) > 0 ? 'Admin' : 'User') ?></span></td>
                                        <td class="text-end">
                                            <form action="device_users.php?id=<?= $device_id ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this user from the device?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="employee_code" value="<?= htmlspecialchars($user['user_id'] ?? $user['employee_code'] ?? '') ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete User" <?= !$is_connected ? 'disabled' : '' ?>><i class="bi bi-trash-fill"></i></button>
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
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title mb-0">Add User to Device</h5></div>
            <div class="card-body">
                <?php if (!$is_connected): ?>
                    <div class="alert alert-warning">Device must be online to add users.</div>
                <?php endif; ?>
                <form action="device_users.php?id=<?= $device_id ?>" method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label for="employee_code" class="form-label">Employee Code</label>
                        <input type="text" class="form-control" id="employee_code" name="employee_code" required <?= !$is_connected ? 'disabled' : '' ?>>
                        <small class="form-text text-muted">Unique identifier for the user on the device</small>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" required <?= !$is_connected ? 'disabled' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password (optional)</label>
                        <input type="text" class="form-control" id="password" name="password" <?= !$is_connected ? 'disabled' : '' ?>>
                        <small class="form-text text-muted">Leave blank if not using password authentication</small>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role on Device</label>
                        <select class="form-select" id="role" name="role" required <?= !$is_connected ? 'disabled' : '' ?>>
                            <option value="User">User</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" <?= !$is_connected ? 'disabled' : '' ?>><i class="bi bi-person-plus-fill me-2"></i>Add User to Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../app/templates/footer.php'; ?>