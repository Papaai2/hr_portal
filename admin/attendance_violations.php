<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

$page_title = "Attendance Violations";
$success_message = '';
$error_message = '';

// --- Handle Actions to Resolve Violations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $action = $_POST['action'] ?? '';
        $log_id = filter_input(INPUT_POST, 'log_id', FILTER_VALIDATE_INT);
        $current_user_id = $_SESSION['user_id'];
        $note = "Action by user #{$current_user_id} on " . date('Y-m-d H:i:s');

        switch ($action) {
            case 'delete_punch':
                if (!$log_id) throw new Exception("Invalid Log ID.");
                $stmt = $pdo->prepare("DELETE FROM attendance_logs WHERE id = ?");
                $stmt->execute([$log_id]);
                $success_message = "Punch record #{$log_id} has been deleted.";
                log_audit_action($pdo, 'delete_attendance_punch', json_encode(['log_id' => $log_id]), get_current_user_id());
                break;

            case 'swap_state':
                if (!$log_id) throw new Exception("Invalid Log ID.");
                $update_note = "State swapped. " . $note;
                $stmt = $pdo->prepare(
                    "UPDATE attendance_logs SET punch_state = 1 - punch_state, status = 'corrected', violation_type = NULL, notes = ? WHERE id = ?"
                );
                $stmt->execute([$update_note, $log_id]);
                $success_message = "Punch record #{$log_id} state has been swapped.";
                log_audit_action($pdo, 'swap_punch_state', json_encode(['log_id' => $log_id]), get_current_user_id());
                break;

            case 'add_punch':
                $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                $punch_time = $_POST['punch_time'] ?? '';
                $punch_state = filter_input(INPUT_POST, 'punch_state', FILTER_VALIDATE_INT);

                if (!$user_id || empty($punch_time) || !in_array($punch_state, [0, 1])) {
                    throw new Exception("All fields are required to add a punch.");
                }

                $userStmt = $pdo->prepare("SELECT employee_code FROM users WHERE id = ?");
                $userStmt->execute([$user_id]);
                $employee_code = $userStmt->fetchColumn();

                if (!$employee_code) {
                    throw new Exception("Selected user does not have an employee code.");
                }

                $insert_note = "Manually added. " . $note;
                $stmt = $pdo->prepare(
                    "INSERT INTO attendance_logs (employee_code, punch_time, punch_state, status, notes, device_id) VALUES (?, ?, ?, 'corrected', ?, NULL)"
                );
                $stmt->execute([$employee_code, $punch_time, $punch_state, $insert_note]);
                $success_message = "Manual punch has been successfully added for employee #{$employee_code}.";
                log_audit_action($pdo, 'add_manual_punch', json_encode(['employee_code' => $employee_code, 'punch_time' => $punch_time]), get_current_user_id());
                break;
            
            default:
                throw new Exception("Invalid action specified.");
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "An error occurred: " . $e->getMessage();
        error_log("Attendance violation action error: " . $e->getMessage());
    }
}


// --- Fetch Data for Display ---
$stmt = $pdo->query(
    "SELECT al.*, u.full_name, u.employee_code -- ADDED u.employee_code
     FROM attendance_logs al 
     JOIN users u ON al.employee_code = u.employee_code 
     WHERE al.status = 'error' 
     ORDER BY al.punch_time DESC"
);
$violations = $stmt->fetchAll();

$user_stmt = $pdo->query("SELECT id, full_name, employee_code FROM users WHERE is_active = 1 AND employee_code IS NOT NULL AND employee_code != '' ORDER BY full_name ASC");
$users = $user_stmt->fetchAll();


include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?php echo htmlspecialchars($page_title); ?></h1>
</div>
<p>Review and resolve illogical or automatically flagged attendance punches. Corrected punches will be used in reports.</p>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
<div class="card mb-4">
    <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Add Missing Punch</h5></div>
    <div class="card-body">
        <form action="attendance_violations.php" method="POST" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="add_punch">
            <div class="col-md-4">
                <label for="user_id" class="form-label">Employee</label>
                <select name="user_id" id="user_id" class="form-select" required>
                    <option value="">Select Employee...</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['full_name'] . ' (' . $user['employee_code'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="punch_time" class="form-label">Punch Date and Time</label>
                <input type="datetime-local" name="punch_time" id="punch_time" class="form-control" required>
            </div>
            <div class="col-md-2">
                 <label class="form-label">Punch Type</label>
                 <div class="form-check">
                    <input class="form-check-input" type="radio" name="punch_state" id="punch_in" value="0" checked>
                    <label class="form-check-label" for="punch_in">IN</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="punch_state" id="punch_out" value="1">
                    <label class="form-check-label" for="punch_out">OUT</label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Add Punch</button>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Pending Violations</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Employee</th><th>Employee Code</th><th>Punch Time</th><th>Type</th><th>Violation</th><th class="text-end">Actions</th></tr></thead> <tbody>
                    <?php if (empty($violations)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No pending violations found. Great job!</td></tr> <?php endif; ?>
                    <?php foreach ($violations as $v): ?>
                        <tr class="table-danger">
                            <td><?= htmlspecialchars($v['full_name']) ?></td>
                            <td><code class="text-muted"><?= htmlspecialchars($v['employee_code']) ?></code></td> <td><?= htmlspecialchars((new DateTime($v['punch_time']))->format('Y-m-d H:i:s')) ?></td>
                            <td><span class="badge bg-<?= $v['punch_state'] == 0 ? 'success' : 'secondary' ?>"><?= $v['punch_state'] == 0 ? 'IN' : 'OUT' ?></span></td>
                            <td><span class="badge bg-danger"><?= htmlspecialchars(str_replace('_', ' ', $v['violation_type'])) ?></span></td>
                            <td class="text-end">
                                <form action="attendance_violations.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to swap the state for this punch?');">
                                    <input type="hidden" name="log_id" value="<?= htmlspecialchars($v['id']) ?>">
                                    <input type="hidden" name="action" value="swap_state">
                                    <button type="submit" class="btn btn-sm btn-warning" title="Swap IN/OUT State"><i class="bi bi-arrow-left-right"></i> Swap</button>
                                </form>
                                <form action="attendance_violations.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this punch record?');">
                                    <input type="hidden" name="log_id" value="<?= htmlspecialchars($v['id']) ?>">
                                    <input type="hidden" name="action" value="delete_punch">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete Punch"><i class="bi bi-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>