<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

$error_message = '';
$success_message = $_GET['success'] ?? '';

// Removed all POST handling for add, edit, delete actions as per request.
// This page is now read-only for attendance logs.

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
$statuses = ['valid', 'invalid']; // Explicitly define statuses
// Violation types are no longer used for manual entry but are still relevant if displaying from database
$violation_types_for_display = ['double_punch', 'late_in', 'early_out', 'manual_invalid', 'missing_punch', 'mismatch_punch_state'];


// Fetch logs
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

// Calculate expected_in and expected_out for each log (Logic remains same)
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

    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

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
                    <th>Violation Type</th>
                    <th>Expected Times</th>
                    <th>Shift</th>
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
                                <?php if (!empty($log['violation_type'])): ?>
                                    <span class="badge bg-danger"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($log['violation_type']))) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
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

<?php include __DIR__ . '/../app/templates/footer.php'; ?>