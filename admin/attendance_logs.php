<?php
// in file: papaai2/hr_portal/hr_portal-8400a68bf21466dd602fc0a4938668fb59725c72/admin/attendance_logs.php

require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

$page_title = 'Attendance Logs';
include __DIR__ . '/../app/templates/header.php';

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
if (!empty($_GET['employee_code'])) { // NEW: Filter by employee_code
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
if (!empty($_GET['violation_type'])) {
    $where_clauses[] = "al.violation_type = ?";
    $params[] = $_GET['violation_type'];
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch logs
// MODIFIED: Join with users table to get full_name and filter by u.id
$sql = "
    SELECT
        al.*,
        u.full_name AS user_full_name,
        s.shift_name,
        s.start_time AS shift_start_time,
        s.end_time AS shift_end_time
    FROM
        attendance_logs al
    LEFT JOIN
        users u ON al.employee_code = u.employee_code
    LEFT JOIN
        shifts s ON u.shift_id = s.id
    {$where_sql}
    ORDER BY
        al.punch_time DESC
    LIMIT {$page_size} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total for pagination
$count_sql = "
    SELECT
        COUNT(al.id)
    FROM
        attendance_logs al
    LEFT JOIN
        users u ON al.employee_code = u.employee_code
    {$where_sql}
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $page_size);

// Fetch users for filter dropdown
$all_users = $pdo->query("SELECT id, full_name, employee_code FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Define possible statuses and violation types for filters
$statuses = ['unprocessed', 'processed', 'error'];
$violation_types = ['double_punch', 'late_in', 'early_out']; // Expanded violation types
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
                    <label for="employee_code" class="form-label">Employee Code</label> <input type="text" class="form-control" id="employee_code" name="employee_code" value="<?= htmlspecialchars($_GET['employee_code'] ?? '') ?>" placeholder="Search by Employee Code">
                </div>
                <div class="col-md-3">
                    <label for="punch_date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="punch_date" name="punch_date" value="<?= htmlspecialchars($_GET['punch_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach($statuses as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= (isset($_GET['status']) && $_GET['status'] == $s) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="violation_type" class="form-label">Violation Type</label>
                    <select class="form-select" id="violation_type" name="violation_type">
                        <option value="">All Violations</option>
                        <?php foreach($violation_types as $vt): ?>
                            <option value="<?= htmlspecialchars($vt) ?>" <?= (isset($_GET['violation_type']) && $_GET['violation_type'] == $vt) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $vt))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter Logs</button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="attendance_logs.php" class="btn btn-outline-secondary w-100">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Employee</th> <th>Employee Code</th> <th>Punch Time</th>
                    <th>Punch State</th>
                    <th>Device ID</th>
                    <th>Status</th>
                    <th>Violation Type</th>
                    <th>Expected Times</th> <th>Shift</th> </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance_logs)): ?>
                    <tr><td colspan="10" class="text-center text-muted">No attendance logs found.</td></tr>
                <?php else: ?>
                    <?php foreach ($attendance_logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id']) ?></td>
                            <td><?= htmlspecialchars($log['user_full_name'] ?? 'N/A') ?></td> <td><?= htmlspecialchars($log['employee_code']) ?></td> <td><?= htmlspecialchars($log['punch_time']) ?></td>
                            <td><?= $log['punch_state'] == 0 ? 'Punch In' : 'Punch Out' ?></td>
                            <td><?= htmlspecialchars($log['device_id']) ?></td>
                            <td>
                                <span class="badge 
                                    <?php
                                        if ($log['status'] === 'error') echo 'bg-danger';
                                        elseif ($log['status'] === 'processed') echo 'bg-success';
                                        else echo 'bg-warning text-dark';
                                    ?>
                                ">
                                    <?= htmlspecialchars(ucfirst($log['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($log['violation_type'])): ?>
                                    <span class="badge 
                                        <?php
                                            if ($log['violation_type'] === 'double_punch') echo 'bg-info text-dark';
                                            else echo 'bg-warning text-dark'; // Default for other violations like late_in, early_out
                                        ?>
                                    ">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['violation_type']))) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td> <?php if (!empty($log['expected_in']) || !empty($log['expected_out'])): ?>
                                    In: <?= htmlspecialchars($log['expected_in'] ? date('h:i A', strtotime($log['expected_in'])) : '--') ?><br>
                                    Out: <?= htmlspecialchars($log['expected_out'] ? date('h:i A', strtotime($log['expected_out'])) : '--') ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td> <?= htmlspecialchars($log['shift_name'] ?? 'N/A') ?>
                                <?php if (!empty($log['shift_start_time']) && !empty($log['shift_end_time'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(date('h:i A', strtotime($log['shift_start_time']))) ?> - <?= htmlspecialchars(date('h:i A', strtotime($log['shift_end_time']))) ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav aria-label="Attendance log pagination">
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