<?php
// in file: htdocs/admin/leave_management.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_role(['admin', 'hr_manager']);

$action = $_GET['action'] ?? 'list_types'; // Default action: list leave types
$id = $_GET['id'] ?? null; // For specific leave type or user ID
$error = '';
$success = '';
$current_user_role = get_current_user_role(); // Get current user's role

// Handle form submissions for Leave Type management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'leave_type') {
    $leave_type_name = trim($_POST['name'] ?? '');
    $accrual_days = filter_var($_POST['accrual_days_per_year'] ?? 0, FILTER_VALIDATE_FLOAT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($leave_type_name) || $accrual_days === false || $accrual_days < 0) {
        $error = 'Leave type name and valid accrual days are required.';
    } else {
        try {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // Update existing leave type
                $stmt = $pdo->prepare("UPDATE leave_types SET name = ?, accrual_days_per_year = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$leave_type_name, $accrual_days, $is_active, $_POST['id']]);
                $success = 'Leave Type updated successfully.';
            } else {
                // Create new leave type
                $stmt = $pdo->prepare("INSERT INTO leave_types (name, accrual_days_per_year, is_active) VALUES (?, ?, ?)");
                $stmt->execute([$leave_type_name, $accrual_days, $is_active]);
                $success = 'Leave Type created successfully.';
            }
            header("Location: leave_management.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    $action = (isset($_POST['id']) && !empty($_POST['id'])) ? 'edit_type' : 'add_type';
}

// Handle form submissions for User Leave Balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'adjust_balance') {
    $user_id_to_adjust = $_POST['user_id'] ?? null;
    $leave_type_id_to_adjust = $_POST['leave_type_id'] ?? null;
    $new_balance = filter_var($_POST['new_balance'] ?? null, FILTER_VALIDATE_FLOAT);

    if (empty($user_id_to_adjust) || empty($leave_type_id_to_adjust) || $new_balance === false) {
        $error = 'Invalid user, leave type, or new balance value.';
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT id FROM leave_balances WHERE user_id = ? AND leave_type_id = ?");
            $stmt_check->execute([$user_id_to_adjust, $leave_type_id_to_adjust]);
            $existing_balance_entry = $stmt_check->fetch();

            if ($existing_balance_entry) {
                $stmt_update = $pdo->prepare("UPDATE leave_balances SET balance_days = ?, last_updated_at = NOW() WHERE id = ?");
                $stmt_update->execute([$new_balance, $existing_balance_entry['id']]);
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_accrual_date) VALUES (?, ?, ?, CURDATE())");
                $stmt_insert->execute([$user_id_to_adjust, $leave_type_id_to_adjust, $new_balance]);
            }
            $success = 'User leave balance updated successfully.';
        } catch (PDOException $e) {
            $error = 'Database error adjusting balance: ' . $e->getMessage();
        }
        header("Location: leave_management.php?action=manage_user_balances&id=" . urlencode($user_id_to_adjust) . "&success=" . urlencode($success));
        exit();
    }
    $action = 'manage_user_balances';
    $id = $user_id_to_adjust; 
}

// Handle form submissions for Bulk User Leave Balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'bulk_adjust_balance') {
    $user_ids_to_adjust = $_POST['user_ids'] ?? [];
    $leave_type_id_to_adjust = $_POST['bulk_leave_type_id'] ?? null;
    $new_balance = filter_var($_POST['bulk_new_balance'] ?? null, FILTER_VALIDATE_FLOAT);

    if (empty($user_ids_to_adjust) || empty($leave_type_id_to_adjust) || $new_balance === false) {
        $error = 'You must select users, a leave type, and provide a valid new balance value.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt_check = $pdo->prepare("SELECT id FROM leave_balances WHERE user_id = ? AND leave_type_id = ?");
            $stmt_update = $pdo->prepare("UPDATE leave_balances SET balance_days = ?, last_updated_at = NOW() WHERE id = ?");
            $stmt_insert = $pdo->prepare("INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_accrual_date) VALUES (?, ?, ?, CURDATE())");

            foreach ($user_ids_to_adjust as $user_id) {
                $stmt_check->execute([$user_id, $leave_type_id_to_adjust]);
                $existing_balance_entry = $stmt_check->fetch();

                if ($existing_balance_entry) {
                    $stmt_update->execute([$new_balance, $existing_balance_entry['id']]);
                } else {
                    $stmt_insert->execute([$user_id, $leave_type_id_to_adjust, $new_balance]);
                }
            }

            $pdo->commit();
            $success = 'Selected users\' leave balances updated successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error adjusting balances: ' . $e->getMessage();
        }
    }
    $action = 'list_users';
}

// Handle Bulk Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'bulk_action') {
    $bulk_action = $_POST['bulk_action'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($bulk_action === 'reset_all_balances') {
            $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
            $leave_types = $pdo->query("SELECT id FROM leave_types WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);

            $stmt_upsert = $pdo->prepare("
                INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_updated_at, last_accrual_date)
                VALUES (?, ?, 0, NOW(), CURDATE())
                ON DUPLICATE KEY UPDATE balance_days = 0, last_updated_at = NOW()");
            
            foreach ($users as $user_id_item) {
                foreach ($leave_types as $leave_type_id_item) {
                    $stmt_upsert->execute([$user_id_item, $leave_type_id_item]);
                }
            }
            $success = 'All active users\' leave balances have been reset to 0.';

        } elseif ($bulk_action === 'perform_annual_accrual') {
            $stmt_accrual_types = $pdo->query("SELECT id, accrual_days_per_year FROM leave_types WHERE is_active = 1 AND accrual_days_per_year > 0")->fetchAll();

            $stmt_update_accrual = $pdo->prepare("
                INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_accrual_date)
                VALUES (?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE balance_days = balance_days + VALUES(balance_days), last_accrual_date = CURDATE()
            ");
            
            $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($users as $user_id_accrual) {
                foreach ($stmt_accrual_types as $accrual_type) {
                     $stmt_update_accrual->execute([$user_id_accrual, $accrual_type['id'], $accrual_type['accrual_days_per_year']]);
                }
            }
            $success = 'Annual leave accrual performed for all active users.';
        } else {
            $error = 'Invalid bulk action.';
        }
        $pdo->commit();
        header("Location: leave_management.php?action=bulk_operations&success=" . urlencode($success));
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Database error during bulk operation: ' . $e->getMessage();
    }
    $action = 'bulk_operations';
}

// Handle Delete Leave Type action
if ($action === 'delete_type' && $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: leave_management.php?success=" . urlencode('Leave Type deleted successfully.'));
        exit();
    } catch (PDOException $e) {
        header("Location: leave_management.php?error=" . urlencode('Cannot delete Leave Type. It may be associated with existing requests or balances.'));
        exit();
    }
}

if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);

$page_title = 'Manage Leave';
include __DIR__ . '/../app/templates/header.php';

// --- Data Fetching for Display ---
$editing_leave_type = null;
if ($action === 'edit_type' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM leave_types WHERE id = ?");
    $stmt->execute([$id]);
    $editing_leave_type = $stmt->fetch();
}

$all_leave_types = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();

$users_with_balances = [];
if ($action === 'list_users' || ($action === 'manage_user_balances' && $id)) {
    $search_query = trim($_GET['search'] ?? '');
    $sql_users = "
        SELECT u.id AS user_id, u.full_name, u.email, u.role, d.name AS department_name,
               GROUP_CONCAT(CONCAT(lt.name, ':', lb.balance_days) ORDER BY lt.name SEPARATOR ';') AS balances_str
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN leave_balances lb ON u.id = lb.user_id AND lb.balance_days IS NOT NULL
        LEFT JOIN leave_types lt ON lb.leave_type_id = lt.id AND lt.is_active = 1
    ";
    $sql_params = [];

    if (!empty($search_query)) {
        $sql_users .= " WHERE u.full_name LIKE ? OR u.email LIKE ?";
        $sql_params = ["%$search_query%", "%$search_query%"];
    }

    $sql_users .= " GROUP BY u.id, u.full_name, u.email, u.role, d.name ORDER BY u.full_name ASC";
    
    $stmt_users = $pdo->prepare($sql_users);
    $stmt_users->execute($sql_params);
    $users_data = $stmt_users->fetchAll();

    foreach ($users_data as $user) {
        $user_id = $user['user_id'];
        $users_with_balances[$user_id] = [
            'id' => $user['user_id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_name' => $user['department_name'],
            'balances' => []
        ];
        if ($user['balances_str']) {
            foreach (explode(';', $user['balances_str']) as $balance_pair) {
                list($type_name, $balance) = explode(':', $balance_pair);
                $users_with_balances[$user_id]['balances'][$type_name] = $balance;
            }
        }
    }
}

$user_to_adjust = null;
$user_specific_balances = [];
if ($action === 'manage_user_balances' && $id) {
    $stmt_user = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ?");
    $stmt_user->execute([$id]);
    $user_to_adjust = $stmt_user->fetch();

    if ($user_to_adjust) {
        $stmt_specific_balances = $pdo->prepare("
            SELECT lb.balance_days, lt.id AS leave_type_id, lt.name AS leave_type_name
            FROM leave_types lt
            LEFT JOIN leave_balances lb ON lt.id = lb.leave_type_id AND lb.user_id = ?
            WHERE lt.is_active = 1
            ORDER BY lt.name ASC
        ");
        $stmt_specific_balances->execute([$id]);
        $user_specific_balances = $stmt_specific_balances->fetchAll();
    } else {
        $error = 'User not found for balance management.';
        $action = 'list_users';
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Manage Leave</h1>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= in_array($action, ['list_types', 'add_type', 'edit_type']) ? 'active' : '' ?>" href="?action=list_types">Leave Types</a></li>
    <li class="nav-item"><a class="nav-link <?= in_array($action, ['list_users', 'manage_user_balances']) ? 'active' : '' ?>" href="?action=list_users">User Balances</a></li>
    <li class="nav-item"><a class="nav-link <?= $action === 'bulk_operations' ? 'active' : '' ?>" href="?action=bulk_operations">Bulk Operations</a></li>
</ul>

<?php if (in_array($action, ['list_types', 'add_type', 'edit_type'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h2 class="h5 mb-0"><?= $editing_leave_type ? 'Edit Leave Type' : 'Add New Leave Type' ?></h2></div>
        <div class="card-body">
            <form action="leave_management.php" method="post">
                <input type="hidden" name="form_type" value="leave_type">
                <?php if ($editing_leave_type): ?><input type="hidden" name="id" value="<?= $editing_leave_type['id'] ?>"><?php endif; ?>
                <div class="mb-3"><label for="name" class="form-label">Leave Type Name</label><input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($editing_leave_type['name'] ?? '') ?>"></div>
                <div class="mb-3"><label for="accrual_days_per_year" class="form-label">Annual Accrual Days</label><input type="number" step="0.01" class="form-control" id="accrual_days_per_year" name="accrual_days_per_year" required value="<?= htmlspecialchars($editing_leave_type['accrual_days_per_year'] ?? '0.00') ?>"></div>
                <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= ($editing_leave_type['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Is Active</label></div>
                <button type="submit" class="btn btn-primary"><?= $editing_leave_type ? 'Update Leave Type' : 'Add Leave Type' ?></button>
                <?php if ($editing_leave_type): ?><a href="leave_management.php" class="btn btn-secondary">Cancel Edit</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header"><h2 class="h5 mb-0">Existing Leave Types</h2></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Name</th><th>Annual Accrual (Days)</th><th>Active</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($all_leave_types)): ?><tr><td colspan="4" class="text-center text-muted">No leave types found.</td></tr>
                        <?php else: foreach ($all_leave_types as $type): ?>
                            <tr>
                                <td><?= htmlspecialchars($type['name']) ?></td><td><?= htmlspecialchars(number_format($type['accrual_days_per_year'], 2)) ?></td>
                                <td><?= $type['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?></td>
                                <td class="text-end">
                                    <a href="?action=edit_type&id=<?= $type['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-fill"></i> Edit</a>
                                    <a href="?action=delete_type&id=<?= $type['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure...?');"><i class="bi bi-trash-fill"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($action === 'list_users'): ?>
    <div class="card shadow-sm">
        <div class="card-header"><h2 class="h5 mb-0">User Leave Balances</h2></div>
        <div class="card-body">
            <form action="leave_management.php" method="get" class="mb-4"><input type="hidden" name="action" value="list_users">
                <div class="input-group"><input type="text" class="form-control" placeholder="Search by Name or Email" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button><?php if (!empty($_GET['search'])): ?><a href="?action=list_users" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i></a><?php endif; ?></div>
            </form>
            <form action="leave_management.php" method="post">
                <input type="hidden" name="form_type" value="bulk_adjust_balance">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light"><tr><th><input type="checkbox" id="select_all_users"></th><th>Employee</th><th>Email</th><th>Balances</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($users_with_balances)): ?><tr><td colspan="5" class="text-center text-muted">No users found.</td></tr>
                            <?php else: foreach ($users_with_balances as $user): ?>
                                <tr>
                                    <td><input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>" class="user-checkbox"></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?> <small class="text-muted d-block"><?= htmlspecialchars($user['department_name'] ?? 'No Dept.') ?></small></td>
                                    <td><?= htmlspecialchars($user['email']) ?> <small class="text-muted d-block"><?= ucfirst(htmlspecialchars($user['role'])) ?></small></td>
                                    <td><?php if (empty($user['balances'])): ?><span class="text-muted">No balances set</span><?php else: ?><ul class="list-unstyled mb-0 small"><?php foreach ($user['balances'] as $type_name => $balance_days): ?><li><strong><?= htmlspecialchars($type_name) ?>:</strong> <?= htmlspecialchars(number_format($balance_days, 2)) ?></li><?php endforeach; ?></ul><?php endif; ?></td>
                                    <td class="text-end"><a href="?action=manage_user_balances&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear-fill"></i> Adjust</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="bulk-action-container" style="display: none;" class="mt-3 p-3 border rounded bg-light">
                    <h3 class="h6">Bulk Adjust Balances</h3>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4"><label for="bulk_leave_type_id" class="form-label">Leave Type</label><select class="form-select" id="bulk_leave_type_id" name="bulk_leave_type_id"><option value="">--</option><?php foreach ($all_leave_types as $type): ?><option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label for="bulk_new_balance" class="form-label">Set New Balance</label><input type="number" step="0.01" class="form-control" id="bulk_new_balance" name="bulk_new_balance"></div>
                        <div class="col-md-4"><button type="submit" class="btn btn-primary w-100">Apply to Selected</button></div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sa = document.getElementById('select_all_users'), ucb = document.querySelectorAll('.user-checkbox'), bac = document.getElementById('bulk-action-container');
        const t = () => { bac.style.display = Array.from(ucb).some(cb => cb.checked) ? 'block' : 'none'; };
        if(sa) sa.addEventListener('change', () => { ucb.forEach(cb => cb.checked = sa.checked); t(); });
        ucb.forEach(cb => cb.addEventListener('change', () => { if(sa) sa.checked = Array.from(ucb).every(c => c.checked); t(); }));
        t();
    });
    </script>
<?php endif; ?>

<?php if ($action === 'manage_user_balances' && $user_to_adjust): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Adjust Balances for: <?= htmlspecialchars($user_to_adjust['full_name']) ?></h2></div>
        <div class="card-body">
            <form action="leave_management.php" method="post">
                <input type="hidden" name="form_type" value="adjust_balance"><input type="hidden" name="user_id" value="<?= htmlspecialchars($user_to_adjust['id']) ?>">
                <div class="mb-3"><label for="leave_type_id" class="form-label">Leave Type</label><select class="form-select" id="leave_type_id" name="leave_type_id" required><option value="">--</option><?php foreach ($user_specific_balances as $be): ?><option value="<?= $be['leave_type_id'] ?>"><?= htmlspecialchars($be['leave_type_name']) ?> (Current: <?= htmlspecialchars(number_format($be['balance_days'] ?? 0, 2)) ?>)</option><?php endforeach; ?></select></div>
                <div class="mb-3"><label for="new_balance" class="form-label">Set New Balance</label><input type="number" step="0.01" class="form-control" id="new_balance" name="new_balance" required><div class="form-text">Enter the total new balance.</div></div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i> Update Balance</button><a href="?action=list_users" class="btn btn-secondary">Back</a>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($action === 'bulk_operations'): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-danger text-white"><h2 class="h5 mb-0">Bulk Operations <i class="bi bi-exclamation-triangle-fill"></i></h2></div>
        <div class="card-body">
            <p class="text-danger fw-bold">Use with extreme caution! These actions affect all users and cannot be undone.</p>
            <h3 class="h6 mt-4">Reset All Balances</h3><p class="text-muted">Sets the leave balance for all users to zero (0.00) for all active leave types.</p>
            <form action="leave_management.php" method="post" onsubmit="return confirm('RESET ALL BALANCES TO ZERO for all users?');"><input type="hidden" name="form_type" value="bulk_action"><input type="hidden" name="bulk_action" value="reset_all_balances"><button type="submit" class="btn btn-danger"><i class="bi bi-eraser-fill me-1"></i> Reset</button></form>
            <hr class="my-4">
            <h3 class="h6">Perform Annual Accrual</h3><p class="text-muted">Adds the defined annual accrual days to all users' balances for each active leave type.</p>
            <form action="leave_management.php" method="post" onsubmit="return confirm('PERFORM ANNUAL LEAVE ACCRUAL for all users?');"><input type="hidden" name="form_type" value="bulk_action"><input type="hidden" name="bulk_action" value="perform_annual_accrual"><button type="submit" class="btn btn-warning text-dark"><i class="bi bi-calendar-plus-fill me-1"></i> Accrue</button></form>
        </div>
    </div>
<?php endif; ?>

<?php
include __DIR__ . '/../app/templates/footer.php';
?>
