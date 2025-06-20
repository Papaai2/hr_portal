<?php
// in file: htdocs/admin/departments.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_role('admin');

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle form submissions for Create and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_name = trim($_POST['name'] ?? '');

    if (empty($dept_name)) {
        $error = 'Department name cannot be empty.';
    } else {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update existing department
            $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $stmt->execute([$dept_name, $_POST['id']]);
            $success = 'Department updated successfully.';
        } else {
            // Create new department
            $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->execute([$dept_name]);
            $success = 'Department created successfully.';
        }
        // Redirect to the list view to prevent form resubmission
        header("Location: departments.php?success=" . urlencode($success));
        exit();
    }
}

// Handle Delete action
if ($action === 'delete' && $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: departments.php?success=" . urlencode('Department deleted successfully.'));
        exit();
    } catch (PDOException $e) {
        // Handle foreign key constraint error (if a user is in this department)
        header("Location: departments.php?error=" . urlencode('Cannot delete department. It may have users assigned to it.'));
        exit();
    }
}

// Get success/error messages from URL parameters
if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);

$page_title = 'Manage Departments';
include __DIR__ . '/../app/templates/header.php';

// Fetch department for editing if in edit mode
$editing_dept = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    $editing_dept = $stmt->fetch();
}

// Fetch all departments for the list
$stmt = $pdo->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Manage Departments</h1>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<!-- Form for Adding/Editing a Department -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0"><?php echo $editing_dept ? 'Edit Department' : 'Add New Department'; ?></h2>
    </div>
    <div class="card-body">
        <form action="departments.php" method="post">
            <?php if ($editing_dept): ?>
                <input type="hidden" name="id" value="<?php echo $editing_dept['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="name" class="form-label">Department Name</label>
                <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($editing_dept['name'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $editing_dept ? 'Update Department' : 'Add Department'; ?></button>
            <?php if ($editing_dept): ?>
                <a href="departments.php" class="btn btn-secondary">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- List of Existing Departments -->
<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">Existing Departments</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Department Name</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted">No departments found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                <td class="text-end">
                                    <a href="departments.php?action=edit&id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill"></i> Edit</a>
                                    <a href="departments.php?action=delete&id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this department?');"><i class="bi bi-trash-fill"></i> Delete</a>
                                </td>
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
?><?php
// in file: htdocs/admin/departments.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_role('admin');

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle form submissions for Create and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_name = trim($_POST['name'] ?? '');

    if (empty($dept_name)) {
        $error = 'Department name cannot be empty.';
    } else {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update existing department
            $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $stmt->execute([$dept_name, $_POST['id']]);
            $success = 'Department updated successfully.';
        } else {
            // Create new department
            $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->execute([$dept_name]);
            $success = 'Department created successfully.';
        }
        // Redirect to the list view to prevent form resubmission
        header("Location: departments.php?success=" . urlencode($success));
        exit();
    }
}

// Handle Delete action
if ($action === 'delete' && $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: departments.php?success=" . urlencode('Department deleted successfully.'));
        exit();
    } catch (PDOException $e) {
        // Handle foreign key constraint error (if a user is in this department)
        header("Location: departments.php?error=" . urlencode('Cannot delete department. It may have users assigned to it.'));
        exit();
    }
}

// Get success/error messages from URL parameters
if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);

$page_title = 'Manage Departments';
include __DIR__ . '/../app/templates/header.php';

// Fetch department for editing if in edit mode
$editing_dept = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    $editing_dept = $stmt->fetch();
}

// Fetch all departments for the list
$stmt = $pdo->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Manage Departments</h1>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<!-- Form for Adding/Editing a Department -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0"><?php echo $editing_dept ? 'Edit Department' : 'Add New Department'; ?></h2>
    </div>
    <div class="card-body">
        <form action="departments.php" method="post">
            <?php if ($editing_dept): ?>
                <input type="hidden" name="id" value="<?php echo $editing_dept['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="name" class="form-label">Department Name</label>
                <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($editing_dept['name'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $editing_dept ? 'Update Department' : 'Add Department'; ?></button>
            <?php if ($editing_dept): ?>
                <a href="departments.php" class="btn btn-secondary">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- List of Existing Departments -->
<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">Existing Departments</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Department Name</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted">No departments found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                <td class="text-end">
                                    <a href="departments.php?action=edit&id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill"></i> Edit</a>
                                    <a href="departments.php?action=delete&id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this department?');"><i class="bi bi-trash-fill"></i> Delete</a>
                                </td>
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