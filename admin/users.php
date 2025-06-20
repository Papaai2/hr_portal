<?php
// in file: htdocs/admin/users.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_role(['admin', 'hr', 'hr_manager']);

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Get current user's role for permission checks
$current_user_role = get_current_user_role();

// Handle form submissions for Create and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['id'] ?? null;
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $direct_manager_id = !empty($_POST['direct_manager_id']) ? $_POST['direct_manager_id'] : null;
    $password = $_POST['password'];

    // --- Start of MODIFICATION for preventing HR Manager from changing admin accounts ---
    if ($user_id && $current_user_role === 'hr_manager') {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $target_user_role = $stmt->fetchColumn();

        if ($target_user_role === 'admin') {
            $error = 'HR Managers cannot change or delete Admin accounts.';
            $action = 'edit'; // Stay on the edit form if this error occurs
        }
    }
    // --- End of MODIFICATION ---

    // Only proceed if no error from the new check
    if (empty($error)) { 
        // Basic Validation
        if (empty($full_name) || empty($email) || empty($role)) {
            $error = 'Full Name, Email, and Role are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif ($user_id && !empty($password) && strlen($password) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif (!$user_id && empty($password)) {
            $error = 'Password is required for new users.';
        } elseif (!$user_id && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            try {
                if ($user_id) {
                    // --- UPDATE USER ---
                    $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, department_id = ?, direct_manager_id = ? WHERE id = ?";
                    $params = [$full_name, $email, $role, $department_id, $direct_manager_id, $user_id];
                    $pdo->prepare($sql)->execute($params);

                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        // When an admin resets a password, force user to change it on next login
                        $pdo->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?")->execute([$hashed_password, $user_id]);
                    }
                    $success = 'User updated successfully.';

                } else {
                    // --- CREATE USER ---
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (full_name, email, password, role, department_id, direct_manager_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)";
                    $params = [$full_name, $email, $hashed_password, $role, $department_id, $direct_manager_id];
                    $pdo->prepare($sql)->execute($params);
                    $success = 'User created successfully.';
                }

                header("Location: users.php?success=" . urlencode($success));
                exit();

            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate email)
                    $error = 'An account with this email address already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
    // If there was an error, we want to stay on the form page, so we set the action.
    if (!empty($error)) {
        $action = $user_id ? 'edit' : 'add';
    }
}


// Handle Delete action
if ($action === 'delete' && $id) {
    if ($id == get_current_user_id()) {
         header("Location: users.php?error=" . urlencode('You cannot delete your own account.'));
         exit();
    }

    // --- Start of MODIFICATION for preventing HR Manager from deleting admin accounts ---
    if ($current_user_role === 'hr_manager') {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $target_user_role = $stmt->fetchColumn();

        if ($target_user_role === 'admin') {
            header("Location: users.php?error=" . urlencode('HR Managers cannot change or delete Admin accounts.'));
            exit();
        }
    }
    // --- End of MODIFICATION ---

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: users.php?success=" . urlencode('User deleted successfully.'));
    exit();
}

// Get success/error messages from URL
if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);

$page_title = 'Manage Users';
include __DIR__ . '/../app/templates/header.php';

// --- Data Fetching for Display ---
$editing_user = null;
if (($action === 'edit' && $id) || ($action === 'add' && $error)) {
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $editing_user = $stmt->fetch();
    } else {
        // In case of an 'add' error, repopulate form with submitted data
        $editing_user = $_POST;
    }
}

// Fetch lists for form dropdowns
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$managers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('manager', 'admin', 'hr_manager') ORDER BY full_name")->fetchAll(); 
$roles = ['user', 'manager', 'hr', 'hr_manager', 'admin'];

// Fetch all users for the list view
$users = []; 
$users_stmt = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.role, d.name AS department_name, m.full_name AS manager_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN users m ON u.direct_manager_id = m.id
    ORDER BY u.full_name
");
$users = $users_stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Manage Users</h1>
    <div>
        <a href="bulk_upload_users.php" class="btn btn-success">
            <i class="bi bi-upload me-1"></i> Bulk Upload
        </a>
        <a href="?action=add" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add New User
        </a>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $editing_user)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0"><?php echo $action === 'add' ? 'Add New User' : 'Edit User'; ?></h2>
    </div>
    <div class="card-body">
        <form action="users.php" method="post">
            <input type="hidden" name="id" value="<?php echo $editing_user['id'] ?? ''; ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($editing_user['full_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($editing_user['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?>>
                    <?php if ($action === 'edit'): ?><div class="form-text">Leave blank to keep current password. Setting a new password will require the user to change it on their next login.</div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r; ?>" <?php echo isset($editing_user['role']) && $editing_user['role'] === $r ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="department_id" class="form-label">Department</label>
                    <select class="form-select" id="department_id" name="department_id">
                        <option value="">-- None --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo isset($editing_user['department_id']) && $editing_user['department_id'] == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="direct_manager_id" class="form-label">Direct Manager</label>
                    <select class="form-select" id="direct_manager_id" name="direct_manager_id">
                        <option value="">-- None --</option>
                        <?php foreach ($managers as $manager): ?>
                            <?php if(isset($editing_user['id']) && $editing_user['id'] == $manager['id']) continue; // Prevent user from being their own manager ?>
                            <option value="<?php echo $manager['id']; ?>" <?php echo isset($editing_user['direct_manager_id']) && $editing_user['direct_manager_id'] == $manager['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($manager['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><?php echo $action === 'add' ? 'Create User' : 'Update User'; ?></button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">Existing Users</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Manager</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($user['role'])); ?></td>
                            <td><?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['manager_name'] ?? 'N/A'); ?></td>
                            <td class="text-end">
                                <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill"></i> Edit</a>
                                <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.');"><i class="bi bi-trash-fill"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../app/templates/footer.php';
?>
