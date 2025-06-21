<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role('admin');

$page_title = "Manage Departments";
$feedback = [];
$editing_dept = null;

try {
    // Handle POST requests (Create/Update)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (empty($name)) {
            throw new Exception("Department name cannot be empty.");
        }

        if ($id) { // Update existing department
            $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            $_SESSION['feedback'] = ['success' => "Department updated successfully."];
        } else { // Create new department
            $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $_SESSION['feedback'] = ['success' => "Department created successfully."];
        }

        // Redirect to avoid form resubmission
        header("Location: departments.php");
        exit;
    }

    // Handle GET requests (Delete/Edit)
    $action = $_GET['action'] ?? '';
    if ($action) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception("Invalid ID specified.");
        }

        if ($action === 'delete') {
            // Check for associated users before deleting
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                 throw new Exception("Cannot delete department: it has users assigned to it. Please reassign users first.");
            }

            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['feedback'] = ['success' => "Department deleted successfully."];
            header("Location: departments.php");
            exit;

        } elseif ($action === 'edit') {
            $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $editing_dept = $stmt->fetch();
            if (!$editing_dept) {
                throw new Exception("Department not found.");
            }
        }
    }

} catch (Exception $e) {
    $feedback = ['error' => "An error occurred: " . $e->getMessage()];
}

// Session feedback for redirect messages
if (isset($_SESSION['feedback'])) {
    $feedback = $_SESSION['feedback'];
    unset($_SESSION['feedback']);
}

// Fetch all departments for display
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
</div>

<?php if (!empty($feedback['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($feedback['success']); ?></div>
<?php endif; ?>
<?php if (!empty($feedback['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($feedback['error']); ?></div>
<?php endif; ?>


<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Existing Departments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No departments found. Add one using the form.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['description'] ?? ''); ?></td>
                                        <td class="text-end">
                                            <a href="?action=edit&id=<?php echo $dept['id']; ?>#department-form" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="?action=delete&id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this department?');">Delete</a>
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

    <div class="col-md-5">
        <div class="card" id="department-form">
            <div class="card-header">
                 <h5 class="card-title mb-0"><?php echo $editing_dept ? 'Edit Department' : 'Add New Department'; ?></h5>
            </div>
            <div class="card-body">
                <form action="departments.php" method="post">
                    <?php if ($editing_dept): ?>
                        <input type="hidden" name="id" value="<?php echo $editing_dept['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="name" class="form-label">Department Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($editing_dept['name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($editing_dept['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo $editing_dept ? 'Update Department' : 'Add Department'; ?></button>
                    <?php if ($editing_dept): ?>
                        <a href="departments.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>