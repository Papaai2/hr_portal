<?php
// in file: htdocs/admin/users.php
// REVERTED to remove Sync to Devices button

require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr', 'hr_manager']);

$page_title = 'Manage Users';
include __DIR__ . '/../app/templates/header.php';

$error = '';
$success = '';

// Handle form submissions for Create and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['id'] ?? null;
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $employee_code = trim($_POST['employee_code']);
    $role = $_POST['role'];
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $direct_manager_id = !empty($_POST['direct_manager_id']) ? $_POST['direct_manager_id'] : null;
    $shift_id = !empty($_POST['shift_id']) ? $_POST['shift_id'] : null;
    $password = $_POST['password'];

    // Basic Validation
    if (empty($full_name) || empty($email) || empty($role)) {
        $error = 'Full Name, Email, and Role are required.';
    } else {
        try {
            if ($user_id) {
                // UPDATE USER
                $sql = "UPDATE users SET full_name = ?, email = ?, employee_code = ?, role = ?, department_id = ?, direct_manager_id = ?, shift_id = ? WHERE id = ?";
                $params = [$full_name, $email, $employee_code, $role, $department_id, $direct_manager_id, $shift_id, $user_id];
                $pdo->prepare($sql)->execute($params);

                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?")->execute([$hashed_password, $user_id]);
                }
                $success = 'User updated successfully.';
                log_audit_action($pdo, 'update_user', json_encode(['user_id' => $user_id, 'email' => $email]), get_current_user_id());
            } else {
                // CREATE USER
                if (empty($password)) {
                    throw new Exception("Password is required for new users.");
                }
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (full_name, email, employee_code, password, role, department_id, direct_manager_id, shift_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $params = [$full_name, $email, $employee_code, $hashed_password, $role, $department_id, $direct_manager_id, $shift_id];
                $pdo->prepare($sql)->execute($params);
                $success = 'User created successfully.';
                log_audit_action($pdo, 'add_user', json_encode(['email' => $email]), get_current_user_id());
            }
            echo "<script>window.location.href = 'users.php?success=" . urlencode($success) . "';</script>";
            exit();
        } catch (Exception $e) {
            if ($e instanceof PDOException && $e->getCode() == 23000) {
                $error = 'An account with this email or employee code already exists.';
            } else {
                $error = 'An error occurred: ' . $e->getMessage();
            }
            error_log("User management error: " . $e->getMessage());
        }
    }
}

// Data Fetching for page display
$editing_user = null;
if (isset($_GET['action']) && ($_GET['action'] === 'edit' && isset($_GET['id']))) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $editing_user = $stmt->fetch();
}
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$managers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('manager', 'admin', 'hr_manager') ORDER BY full_name")->fetchAll();
$roles = ['user', 'manager', 'hr', 'hr_manager', 'admin'];
$shifts = $pdo->query("SELECT id, shift_name, start_time, end_time FROM shifts ORDER BY shift_name")->fetchAll();
$users_list = $pdo->query("SELECT u.*, d.name AS department_name, m.full_name AS manager_name, s.shift_name FROM users u LEFT JOIN departments d ON u.department_id = d.id LEFT JOIN users m ON u.direct_manager_id = m.id LEFT JOIN shifts s ON u.shift_id = s.id ORDER BY u.full_name")->fetchAll();

if(isset($_GET['success'])) $success = $_GET['success'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Manage Users</h1>
    <div>
        <a href="import_users_csv.php" class="btn btn-success"><i class="bi bi-upload me-1"></i> Import via CSV</a>
        <a href="?action=add#user-form" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add New User</a>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (isset($_GET['action']) && ($_GET['action'] === 'add' || $editing_user)): ?>
<div class="card shadow-sm mb-4" id="user-form">
    <div class="card-header"><h2 class="h5 mb-0"><?= $editing_user ? 'Edit User' : 'Add New User'; ?></h2></div>
    <div class="card-body">
        <form action="users.php" method="post">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editing_user['id'] ?? '') ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" required value="<?= htmlspecialchars($editing_user['full_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($editing_user['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="employee_code" class="form-label">Employee Code (from device)</label>
                    <input type="text" class="form-control" name="employee_code" value="<?= htmlspecialchars($editing_user['employee_code'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" <?= !$editing_user ? 'required' : '' ?>>
                    <?php if ($editing_user): ?><div class="form-text">Leave blank to keep current password.</div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" name="role" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>" <?= (isset($editing_user['role']) && $editing_user['role'] === $r) ? 'selected' : '' ?>><?= ucfirst(htmlspecialchars($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="department_id" class="form-label">Department</label>
                    <select class="form-select" name="department_id">
                        <option value="">-- None --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['id']) ?>" <?= (isset($editing_user['department_id']) && $editing_user['department_id'] == $dept['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-6">
                    <label for="direct_manager_id" class="form-label">Direct Manager</label>
                    <select class="form-select" name="direct_manager_id">
                        <option value="">-- None --</option>
                        <?php foreach ($managers as $manager): ?>
                            <?php if(isset($editing_user['id']) && $editing_user['id'] == $manager['id']) continue; ?>
                            <option value="<?= htmlspecialchars($manager['id']) ?>" <?= (isset($editing_user['direct_manager_id']) && $editing_user['direct_manager_id'] == $manager['id']) ? 'selected' : '' ?>><?= htmlspecialchars($manager['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="shift_id" class="form-label">Assigned Shift</label>
                    <select class="form-select" name="shift_id">
                        <option value="">-- None --</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?= htmlspecialchars($shift['id']) ?>" <?= (isset($editing_user['shift_id']) && $editing_user['shift_id'] == $shift['id']) ? 'selected' : '' ?>><?= htmlspecialchars($shift['shift_name'] . " (" . date("g:i A", strtotime($shift['start_time'])) . " - " . date("g:i A", strtotime($shift['end_time'])) . ")") ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><?= $editing_user ? 'Update User' : 'Create User'; ?></button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header"><h2 class="h5 mb-0">Existing Users</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr><th>Full Name</th><th>Employee Code</th><th>Email</th><th>Role</th><th>Department</th><th>Manager</th><th>Shift</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr><td colspan="8" class="text-center p-4">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><code class="text-muted"><?= htmlspecialchars($user['employee_code'] ?? 'N/A') ?></code></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= ucfirst(htmlspecialchars($user['role'])) ?></td>
                                <td><?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['manager_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['shift_name'] ?? 'N/A') ?></td>
                                <td class="text-end"><a href="?action=edit&id=<?= htmlspecialchars($user['id']) ?>#user-form" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill"></i> Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../app/templates/footer.php';
?>