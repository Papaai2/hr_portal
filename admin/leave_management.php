<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

$page_title = "Leave Management";
$feedback = ['success' => '', 'error' => ''];

// --- FORM PROCESSING FOR LEAVE TYPES AND BULK ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo->beginTransaction();
    try {
        if ($action === 'save_leave_type') {
            $type_id = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);
            $type_name = sanitize_input($_POST['name'] ?? '');
            $accrual_days = filter_input(INPUT_POST, 'accrual_days', FILTER_VALIDATE_FLOAT);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($type_name) || $accrual_days === false) {
                throw new Exception("Leave type name and accrual days are required.");
            }

            if ($type_id) { // Update
                $stmt = $pdo->prepare("UPDATE leave_types SET name = ?, accrual_days_per_year = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$type_name, $accrual_days, $is_active, $type_id]);
                $feedback['success'] = "Leave type updated successfully.";
            } else { // Create
                $stmt = $pdo->prepare("INSERT INTO leave_types (name, accrual_days_per_year, is_active) VALUES (?, ?, ?)");
                $stmt->execute([$type_name, $accrual_days, $is_active]);
                $feedback['success'] = "Leave type created successfully.";
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $feedback['error'] = "An error occurred: " . $e->getMessage();
        error_log("Leave Management Error: " . $e->getMessage());
    }
}

// Handle delete action for leave types
if (isset($_GET['action']) && $_GET['action'] === 'delete_type' && isset($_GET['id'])) {
    $type_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($type_id_to_delete) {
        try {
            $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ?");
            $stmt->execute([$type_id_to_delete]);
            $feedback['success'] = "Leave type deleted successfully.";
        } catch (PDOException $e) {
            $feedback['error'] = "Cannot delete this leave type. It may be in use by leave balances or requests.";
        }
    }
}


// --- DATA FETCHING FOR PAGE DISPLAY ---
$leave_types = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();
$all_users = $pdo->query("SELECT id, full_name, employee_code FROM users ORDER BY full_name ASC")->fetchAll();
$editing_type = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_type' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM leave_types WHERE id = ?");
    $stmt->execute([filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)]);
    $editing_type = $stmt->fetch();
}


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
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Existing Leave Types</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Annual Accrual (Days)</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_types as $type): ?>
                                <tr>
                                    <td><?= htmlspecialchars($type['name']) ?></td>
                                    <td><?= htmlspecialchars($type['accrual_days_per_year']) ?></td>
                                    <td><span class="badge bg-<?= $type['is_active'] ? 'success' : 'secondary' ?>"><?= $type['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td class="text-end">
                                        <a href="?action=edit_type&id=<?= $type['id'] ?>#leave-type-form" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="?action=delete_type&id=<?= $type['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this leave type?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100" id="leave-type-form">
            <div class="card-header">
                <h5 class="card-title mb-0"><?= $editing_type ? 'Edit' : 'Add New' ?> Leave Type</h5>
            </div>
            <div class="card-body">
                <form action="leave_management.php" method="POST">
                    <input type="hidden" name="action" value="save_leave_type">
                    <input type="hidden" name="type_id" value="<?= $editing_type['id'] ?? '' ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Type Name</label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($editing_type['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="accrual_days" class="form-label">Accrual Days Per Year</label>
                        <input type="number" step="0.01" class="form-control" name="accrual_days" value="<?= htmlspecialchars($editing_type['accrual_days_per_year'] ?? '0.00') ?>" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (isset($editing_type['is_active']) && $editing_type['is_active']) || !$editing_type ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Is Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editing_type ? 'Update' : 'Save' ?> Type</button>
                    <?php if ($editing_type): ?>
                        <a href="leave_management.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Bulk & Year-End Operations</h5>
    </div>
    <div class="card-body">
        <div id="bulk-feedback"></div>
        <div class="row">
            <div class="col-md-6 border-end">
                <h6>Automated Actions</h6>
                <p class="text-muted">Run company-wide leave operations.</p>
                <button class="btn btn-info" id="btn-annual-accrual"><i class="bi bi-calendar-plus me-1"></i> Perform Annual Accrual</button>
                <button class="btn btn-danger" id="btn-reset-balances"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset All Balances to 0</button>
            </div>
            <div class="col-md-6">
                <h6>Manual Balance Adjustment</h6>
                 <p class="text-muted">Set a specific balance for selected users.</p>
                <div class="row g-2">
                    <div class="col-12">
                        <label for="bulk-users" class="form-label">Select Users (multiple allowed)</label>
                        <select id="bulk-users" class="form-select" multiple size="5">
                             <?php foreach ($all_users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name'] . ' (' . ($user['employee_code'] ?? 'N/A') . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="bulk-leave-type" class="form-label">Leave Type</label>
                        <select id="bulk-leave-type" class="form-select">
                            <option value="">Select type...</option>
                            <?php foreach ($leave_types as $type): ?>
                                <?php if($type['is_active']): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                         <label for="bulk-new-balance" class="form-label">Set New Balance</label>
                        <input type="number" step="0.01" id="bulk-new-balance" class="form-control" placeholder="e.g., 15.5">
                    </div>
                </div>
                <button class="btn btn-primary mt-3" id="btn-adjust-balances"><i class="bi bi-pencil-square me-1"></i> Adjust Selected Balances</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const feedbackDiv = document.getElementById('bulk-feedback');

    async function handleBulkAction(action, data = {}) {
        if (!confirm(`Are you sure you want to perform this bulk action: '${action}'? This cannot be undone.`)) {
            return;
        }

        feedbackDiv.innerHTML = `<div class="alert alert-info">Processing...</div>`;
        
        try {
            const response = await fetch('/api/bulk_operations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...data })
            });
            
            const result = await response.json();

            if (response.ok && result.success) {
                feedbackDiv.innerHTML = `<div class="alert alert-success">${result.message}</div>`;
            } else {
                feedbackDiv.innerHTML = `<div class="alert alert-danger">Error: ${result.message || 'An unknown error occurred.'}</div>`;
            }
        } catch (error) {
            feedbackDiv.innerHTML = `<div class="alert alert-danger">A network error occurred: ${error.message}</div>`;
        }
    }

    document.getElementById('btn-annual-accrual').addEventListener('click', () => {
        handleBulkAction('perform_annual_accrual');
    });

    document.getElementById('btn-reset-balances').addEventListener('click', () => {
        handleBulkAction('reset_all_balances');
    });

    document.getElementById('btn-adjust-balances').addEventListener('click', () => {
        const selectedUsers = Array.from(document.getElementById('bulk-users').selectedOptions).map(opt => opt.value);
        const leaveTypeId = document.getElementById('bulk-leave-type').value;
        const newBalance = document.getElementById('bulk-new-balance').value;

        if (selectedUsers.length === 0 || !leaveTypeId || newBalance === '') {
            feedbackDiv.innerHTML = `<div class="alert alert-warning">Please select users, a leave type, and enter a new balance.</div>`;
            return;
        }

        handleBulkAction('adjust_selected_balances', {
            user_ids: selectedUsers,
            leave_type_id: leaveTypeId,
            new_balance: newBalance
        });
    });
});
</script>

<?php
include __DIR__ . '/../app/templates/footer.php';
?>