
<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

$error_message = '';
$success_message = $_GET['success'] ?? '';

// Handle POST requests for adding, editing, deleting logs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $log_id = filter_input(INPUT_POST, 'log_id', FILTER_VALIDATE_INT);
    $employee_code = sanitize_input($_POST['employee_code'] ?? '');
    $punch_time = sanitize_input($_POST['punch_time'] ?? '');
    $punch_state = filter_input(INPUT_POST, 'punch_state', FILTER_VALIDATE_INT);
    $status = sanitize_input($_POST['status'] ?? '');
    $shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);

    try {
        switch ($action) {
            case 'add_log':
                if (empty($employee_code) || empty($punch_time) || !isset($punch_state) || empty($status)) {
                    $error_message = "All fields are required to add an attendance log.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO attendance_logs (employee_code, punch_time, punch_state, status, shift_id) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$employee_code, $punch_time, $punch_state, $status, $shift_id])) {
                        $success_message = "Attendance log added successfully.";
                        log_audit_action($pdo, 'add_attendance_log', "Added log for {$employee_code} at {$punch_time}");
                    } else {
                        $error_message = "Failed to add attendance log.";
                    }
                }
                break;
            case 'edit_log':
                if (empty($log_id) || empty($employee_code) || empty($punch_time) || !isset($punch_state) || empty($status)) {
                    $error_message = "All fields are required to edit an attendance log.";
                } else {
                    $stmt = $pdo->prepare("UPDATE attendance_logs SET employee_code = ?, punch_time = ?, punch_state = ?, status = ?, shift_id = ? WHERE id = ?");
                    if ($stmt->execute([$employee_code, $punch_time, $punch_state, $status, $shift_id, $log_id])) {
                        $success_message = "Attendance log ID {$log_id} updated successfully.";
                        log_audit_action($pdo, 'edit_attendance_log', "Edited log ID {$log_id} for {$employee_code} to {$punch_time}");
                    } else {
                        $error_message = "Failed to update attendance log ID {$log_id}.";
                    }
                }
                break;
            case 'delete_log':
                if (empty($log_id)) {
                    $error_message = "Log ID is required to delete an attendance log.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM attendance_logs WHERE id = ?");
                    if ($stmt->execute([$log_id])) {
                        $success_message = "Attendance log ID {$log_id} deleted successfully.";
                        log_audit_action($pdo, 'delete_attendance_log', "Deleted log ID {$log_id}");
                    } else {
                        $error_message = "Failed to delete attendance log ID {$log_id}.";
                    }
                }
                break;
            default:
                $error_message = "Invalid action requested.";
                break;
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
        error_log("Attendance log action error: " . $e->getMessage());
    }
    // Redirect to prevent form resubmission on refresh
    header("Location: attendance_logs.php?success=" . urlencode($success_message) . "&error=" . urlencode($error_message));
    exit();
}

$page_title = 'Attendance Logs';
include __DIR__ . '/../app/templates/header.php'; // Include header after all PHP processing

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$page_size = 25;
$offset = ($page - 1) * $page_size;

// Filtering
$where_clauses = [];
$params = [];

if (!empty($_GET['user_id'])) {
    $where_clauses[] = "u.id = ?";
    $params[] = $_GET['user_id'];
}
if (!empty($_GET['employee_code'])) {
    $where_clauses[] = "al.employee_code LIKE ?";
    $params[] = '%' . $_GET['employee_code'] . '%';
}
if (!empty($_GET['punch_date'])) {
    $where_clauses[] = "DATE(al.punch_time) = ?";
    $params[] = $_GET['punch_date'];
}
if (!empty($_GET['status'])) {
    $where_clauses[] = "al.status = ?";
    $params[] = $_GET['status'];
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch users and shifts for dropdowns
$all_users = $pdo->query("SELECT id, full_name, employee_code FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_shifts = $pdo->query("SELECT id, shift_name, start_time, end_time FROM shifts ORDER BY shift_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$statuses = ['valid', 'invalid'];

// Fetch logs (UPDATED SQL)
$sql = "
    SELECT
        al.*,
        u.full_name AS user_full_name,
        s.id AS shift_id,
        s.shift_name
    FROM
        attendance_logs al
    LEFT JOIN
        users u ON al.employee_code = u.employee_code
    LEFT JOIN
        shifts s ON al.shift_id = s.id
    {$where_sql}
    ORDER BY
        al.punch_time DESC
    LIMIT {$page_size} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate expected_in and expected_out for each log
foreach ($attendance_logs as &$log) {
    if (!empty($log['shift_id'])) {
        foreach ($all_shifts as $shift) {
            if ($shift['id'] == $log['shift_id']) {
                $date = date('Y-m-d', strtotime($log['punch_time']));
                $log['expected_in'] = $date . ' ' . $shift['start_time'];
                $log['expected_out'] = $date . ' ' . $shift['end_time'];
                break;
            }
        }
    } else {
        $log['expected_in'] = null;
        $log['expected_out'] = null;
    }
}
unset($log); // break reference

// Count total for pagination
$count_sql = "
    SELECT COUNT(al.id) FROM attendance_logs al
    LEFT JOIN users u ON al.employee_code = u.employee_code
    {$where_sql}
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $page_size);
?>

<div class="container mt-4">
    <h1 class="h3 mb-4">Attendance Logs</h1>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Filter Logs</h2></div>
        <div class="card-body">
            <form class="row g-3" method="get">
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Employee</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">All Employees</option>
                        <?php foreach($all_users as $user_option): ?>
                            <option value="<?= htmlspecialchars($user_option['id']) ?>" <?= (isset($_GET['user_id']) && $_GET['user_id'] == $user_option['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user_option['full_name']) ?> (<?= htmlspecialchars($user_option['employee_code'] ?? 'N/A') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="employee_code" class="form-label">Employee Code</label>
                    <input type="text" class="form-control" id="employee_code" name="employee_code" value="<?= htmlspecialchars($_GET['employee_code'] ?? '') ?>" placeholder="Search by Code">
                </div>
                <div class="col-md-2">
                    <label for="punch_date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="punch_date" name="punch_date" value="<?= htmlspecialchars($_GET['punch_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach($statuses as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= (isset($_GET['status']) && $_GET['status'] == $s) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="attendance_logs.php" class="btn btn-outline-secondary">Clear Filters</a>
                    <button type="submit" class="btn btn-primary">Filter Logs</button>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLogModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Log
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Employee</th>
                    <th>Employee Code</th>
                    <th>Punch Time</th>
                    <th>Punch State</th>
                    <th>Status</th>
                    <th>Expected Times</th>
                    <th>Shift</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance_logs)): ?>
                    <tr><td colspan="9" class="text-center text-muted">No attendance logs found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($attendance_logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id']) ?></td>
                            <td><?= htmlspecialchars($log['user_full_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($log['employee_code']) ?></td>
                            <td><?= htmlspecialchars($log['punch_time']) ?></td>
                            <td><?= $log['punch_state'] == 0 ? '<span class="badge bg-success">In</span>' : '<span class="badge bg-secondary">Out</span>' ?></td>
                            <td>
                                <span class="badge <?= getStatusBadgeClass($log['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($log['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($log['expected_in']) || !empty($log['expected_out'])): ?>
                                    In: <?= htmlspecialchars($log['expected_in'] ? date('h:i A', strtotime($log['expected_in'])) : '--') ?><br>
                                    Out: <?= htmlspecialchars($log['expected_out'] ? date('h:i A', strtotime($log['expected_out'])) : '--') ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['shift_name'])): ?>
                                    <?= htmlspecialchars($log['shift_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-warning edit-log-btn"
                                        data-bs-toggle="modal" data-bs-target="#editLogModal"
                                        data-id="<?= htmlspecialchars($log['id']) ?>"
                                        data-employee-code="<?= htmlspecialchars($log['employee_code']) ?>"
                                        data-punch-time="<?= htmlspecialchars($log['punch_time']) ?>"
                                        data-punch-state="<?= htmlspecialchars($log['punch_state']) ?>"
                                        data-status="<?= htmlspecialchars($log['status']) ?>"
                                        data-shift-id="<?= htmlspecialchars($log['shift_id'] ?? '') ?>"
                                        title="Edit Log"><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-danger delete-log-btn"
                                        data-id="<?= htmlspecialchars($log['id']) ?>"
                                        title="Delete Log"><i class="bi bi-trash-fill"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav aria-label="Pagination">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<div class="modal fade" id="addLogModal" tabindex="-1" aria-labelledby="addLogModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="attendance_logs.php" method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLogModalLabel">Add New Attendance Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_log">
                    <div class="mb-3">
                        <label for="add_employee_code" class="form-label">Employee Code</label>
                        <select class="form-select" id="add_employee_code" name="employee_code" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach($all_users as $user_option): ?>
                                <option value="<?= htmlspecialchars($user_option['employee_code']) ?>">
                                    <?= htmlspecialchars($user_option['full_name']) ?> (<?= htmlspecialchars($user_option['employee_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add_punch_time" class="form-label">Punch Time</label>
                        <input type="datetime-local" class="form-control" id="add_punch_time" name="punch_time" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_punch_state" class="form-label">Punch State</label>
                        <select class="form-select" id="add_punch_state" name="punch_state" required>
                            <option value="0">In</option>
                            <option value="1">Out</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add_status" class="form-label">Status</label>
                        <select class="form-select" id="add_status" name="status" required>
                            <?php foreach($statuses as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add_shift_id" class="form-label">Shift</label>
                        <select class="form-select" id="add_shift_id" name="shift_id">
                            <option value="">-- No Shift --</option>
                            <?php foreach($all_shifts as $shift): ?>
                                <option value="<?= htmlspecialchars($shift['id']) ?>">
                                    <?= htmlspecialchars($shift['shift_name']) ?> (<?= htmlspecialchars($shift['start_time']) ?> - <?= htmlspecialchars($shift['end_time']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Log</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editLogModal" tabindex="-1" aria-labelledby="editLogModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="attendance_logs.php" method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLogModalLabel">Edit Attendance Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_log">
                    <input type="hidden" name="log_id" id="edit_log_id">
                    <div class="mb-3">
                        <label for="edit_employee_code" class="form-label">Employee Code</label>
                        <input type="text" class="form-control" id="edit_employee_code" name="employee_code" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_punch_time" class="form-label">Punch Time</label>
                        <input type="datetime-local" class="form-control" id="edit_punch_time" name="punch_time" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_punch_state" class="form-label">Punch State</label>
                        <select class="form-select" id="edit_punch_state" name="punch_state" required>
                            <option value="0">In</option>
                            <option value="1">Out</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <?php foreach($statuses as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_shift_id" class="form-label">Shift</label>
                        <select class="form-select" id="edit_shift_id" name="shift_id">
                            <option value="">-- No Shift --</option>
                            <?php foreach($all_shifts as $shift): ?>
                                <option value="<?= htmlspecialchars($shift['id']) ?>">
                                    <?= htmlspecialchars($shift['shift_name']) ?> (<?= htmlspecialchars($shift['start_time']) ?> - <?= htmlspecialchars($shift['end_time']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<form id="deleteLogForm" action="attendance_logs.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_log">
    <input type="hidden" name="log_id" id="delete_log_id">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Log Modal Logic
    const editLogModal = document.getElementById('editLogModal');
    if (editLogModal) {
        editLogModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const logId = button.getAttribute('data-id');
            const employeeCode = button.getAttribute('data-employee-code');
            const punchTime = button.getAttribute('data-punch-time');
            const punchState = button.getAttribute('data-punch-state');
            const status = button.getAttribute('data-status');
            const shiftId = button.getAttribute('data-shift-id');

            editLogModal.querySelector('#edit_log_id').value = logId;
            editLogModal.querySelector('#edit_employee_code').value = employeeCode;
            // Format datetime-local input
            const formattedPunchTime = punchTime.substring(0, 10) + 'T' + punchTime.substring(11, 16);
            editLogModal.querySelector('#edit_punch_time').value = formattedPunchTime;
            editLogModal.querySelector('#edit_punch_state').value = punchState;
            editLogModal.querySelector('#edit_status').value = status;
            editLogModal.querySelector('#edit_shift_id').value = shiftId || '';
        });
    }

    // Delete Log Logic
    document.querySelectorAll('.delete-log-btn').forEach(button => {
        button.addEventListener('click', function() {
            const logId = this.getAttribute('data-id');
            if (confirm(`Are you sure you want to delete attendance log ID ${logId}? This action cannot be undone.`)) {
                document.getElementById('delete_log_id').value = logId;
                document.getElementById('deleteLogForm').submit();
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>