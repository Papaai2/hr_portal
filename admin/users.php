<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role('admin');

$page_title = 'Manage Users';
$feedback = [];
$editing_user = null;

try {
    // Handle POST requests for creating, updating, or deleting users
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        $pdo->beginTransaction();

        // --- UPDATE ---
        if ($action === 'update' && $id) {
            $stmt_before = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt_before->execute([$id]);
            $before_data = $stmt_before->fetch(PDO::FETCH_ASSOC);

            $full_name = sanitize_input($_POST['full_name']);
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
            $shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
            // New manager_id field
            $manager_id = filter_input(INPUT_POST, 'manager_id', FILTER_VALIDATE_INT);
            if (empty($manager_id)) $manager_id = null; // Allow un-assigning a manager

            $role = in_array($_POST['role'], ['user', 'hr_manager', 'admin', 'manager']) ? $_POST['role'] : 'user';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $password = $_POST['password'];

            if (!$full_name || !$email) throw new Exception("Full name and email are required.");

            $update_sql = "UPDATE users SET full_name=?, email=?, department_id=?, shift_id=?, role=?, is_active=?, manager_id=? WHERE id=?";
            $params = [$full_name, $email, $department_id, $shift_id, $role, $is_active, $manager_id, $id];
            
            if (!empty($password)) {
                 $update_sql = "UPDATE users SET full_name=?, email=?, department_id=?, shift_id=?, role=?, is_active=?, manager_id=?, password=? WHERE id=?";
                 $params = [$full_name, $email, $department_id, $shift_id, $role, $is_active, $manager_id, password_hash($password, PASSWORD_DEFAULT), $id];
            }
            
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute($params);

            // Audit Trail Logic
            $details_string = "Updated user {$full_name} (ID: $id).";
            log_audit_action($pdo, 'user_updated', $details_string);
            $_SESSION['feedback'] = ['success' => "User updated successfully."];

        // --- CREATE ---
        } elseif ($action === 'create') {
            $full_name = sanitize_input($_POST['full_name']);
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
            $shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
            // New manager_id field
            $manager_id = filter_input(INPUT_POST, 'manager_id', FILTER_VALIDATE_INT);
            if (empty($manager_id)) $manager_id = null;

            $role = in_array($_POST['role'], ['user', 'hr_manager', 'admin', 'manager']) ? $_POST['role'] : 'user';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $password = $_POST['password'];

            if (!$full_name || !$email || empty($password)) throw new Exception("Full name, email, and password are required for new users.");
            
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, department_id, shift_id, role, is_active, manager_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, password_hash($password, PASSWORD_DEFAULT), $department_id, $shift_id, $role, $is_active, $manager_id]);
            $new_user_id = $pdo->lastInsertId();

            log_audit_action($pdo, 'user_created', "Created user {$full_name} (ID: {$new_user_id}) with role '{$role}'.");
            $_SESSION['feedback'] = ['success' => "User created successfully."];
        }
        
        $pdo->commit();
        header("Location: users.php");
        exit;
    }

    // --- DELETE ---
    if (isset($_GET['action']) && $_GET['action'] === 'delete') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) throw new Exception("Invalid user ID for deletion.");
        
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user_name = $stmt->fetchColumn();

        if ($user_name) {
            $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->execute([$id]);
            log_audit_action($pdo, 'user_deleted', "Deleted user {$user_name} (ID: {$id}).");
            $_SESSION['feedback'] = ['success' => "User deleted successfully."];
        } else {
            $_SESSION['feedback'] = ['error' => "User with ID {$id} not found."];
        }
        
        header("Location: users.php");
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $feedback['error'] = "An error occurred: " . $e->getMessage();
}

if (isset($_SESSION['feedback'])) {
    $feedback = $_SESSION['feedback'];
    unset($_SESSION['feedback']);
}

// Fetch data for the form and user list
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$shifts = $pdo->query("SELECT id, shift_name FROM shifts ORDER BY shift_name")->fetchAll();
// Fetch a list of potential managers
$managers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('manager', 'hr_manager', 'admin') ORDER BY full_name")->fetchAll();
// Fetch all users and join their manager's name
$users = $pdo->query("
    SELECT u.*, d.name as department_name, s.shift_name as shift_name, m.full_name as manager_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
    LEFT JOIN shifts s ON u.shift_id = s.id 
    LEFT JOIN users m ON u.manager_id = m.id
    ORDER BY u.full_name
")->fetchAll();

if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $editing_user = $stmt->fetch();
}

include __DIR__ . '/../app/templates/header.php';
?>

<?php if (!empty($feedback['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($feedback['success']); ?></div><?php endif; ?>
<?php if (!empty($feedback['error'])): ?><div class="alert alert-danger"><?php echo htmlspecialchars($feedback['error']); ?></div><?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center"><h5 class="card-title mb-0">Users</h5><a href="?action=create#user-form" class="btn btn-primary">Add New User</a></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>Name</th><th>Email</th><th>Department</th><th>Direct Manager</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['manager_name'] ?? 'N/A'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td class="text-end">
                                        <a href="?action=edit&id=<?php echo $user['id']; ?>#user-form" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['action']) && ($_GET['action'] === 'edit' || $_GET['action'] === 'create')): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card" id="user-form">
            <div class="card-header"><h5 class="card-title mb-0"><?php echo $editing_user ? 'Edit User' : 'Add New User'; ?></h5></div>
            <div class="card-body">
                <form action="users.php" method="POST">
                    <input type="hidden" name="action" value="<?php echo $editing_user ? 'update' : 'create'; ?>">
                    <?php if ($editing_user): ?><input type="hidden" name="id" value="<?php echo $editing_user['id']; ?>"><?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label for="full_name" class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($editing_user['full_name'] ?? ''); ?>" required></div>
                        <div class="col-md-6 mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($editing_user['email'] ?? ''); ?>" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?><option value="<?php echo $dept['id']; ?>" <?php echo (isset($editing_user['department_id']) && $editing_user['department_id'] == $dept['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="shift_id" class="form-label">Shift</label>
                            <select name="shift_id" class="form-select">
                                <option value="">Select Shift</option>
                                <?php foreach ($shifts as $shift): ?><option value="<?php echo $shift['id']; ?>" <?php echo (isset($editing_user['shift_id']) && $editing_user['shift_id'] == $shift['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($shift['shift_name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" <?php if (!$editing_user) echo 'required'; ?>>
                            <?php if($editing_user): ?><small class="form-text text-muted">Leave blank to keep current password.</small><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="user" <?php echo (isset($editing_user['role']) && $editing_user['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                <option value="manager" <?php echo (isset($editing_user['role']) && $editing_user['role'] == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                <option value="hr_manager" <?php echo (isset($editing_user['role']) && $editing_user['role'] == 'hr_manager') ? 'selected' : ''; ?>>HR Manager</option>
                                <option value="admin" <?php echo (isset($editing_user['role']) && $editing_user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="manager_id" class="form-label">Direct Manager</label>
                            <select name="manager_id" class="form-select">
                                <option value="">No Manager</option>
                                <?php foreach ($managers as $manager): ?>
                                    <?php if (isset($editing_user['id']) && $editing_user['id'] === $manager['id']) continue; // A user cannot be their own manager ?>
                                    <option value="<?php echo $manager['id']; ?>" <?php echo (isset($editing_user['manager_id']) && $editing_user['manager_id'] == $manager['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($manager['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3 align-self-center">
                           <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo (isset($editing_user['is_active']) && $editing_user['is_active']) || !$editing_user ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">User is Active</label>
                           </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo $editing_user ? 'Update User' : 'Create User'; ?></button>
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>