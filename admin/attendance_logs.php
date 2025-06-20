<?php
// in file: admin/attendance_logs.php

require_once __DIR__ . '/../app/bootstrap.php';
require_role('admin');

$page_title = 'Attendance Logs';
include __DIR__ . '/../app/templates/header.php';

// --- Filter Handling ---
$where_clauses = [];
$params = [];
$filter_query_string = '';

// Date Range Filter
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
if ($start_date) {
    $where_clauses[] = "al.punch_time >= :start_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
}
if ($end_date) {
    $where_clauses[] = "al.punch_time <= :end_date";
    $params[':end_date'] = $end_date . ' 23:59:59';
}

// Employee Filter
$user_id = $_GET['user_id'] ?? '';
if ($user_id && is_numeric($user_id)) {
    $where_clauses[] = "u.id = :user_id";
    $params[':user_id'] = $user_id;
}

// Device Filter
$device_id = $_GET['device_id'] ?? '';
if ($device_id && is_numeric($device_id)) {
    $where_clauses[] = "d.id = :device_id";
    $params[':device_id'] = $device_id;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query_array);
    unset($query_array['page']);
    $filter_query_string = http_build_query($query_array);
}


// --- Data Fetching ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 25;
$offset = ($page - 1) * $records_per_page;

// Count total records with filters applied
$total_records_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs al LEFT JOIN users u ON al.employee_code = u.employee_code LEFT JOIN devices d ON al.device_id = d.id $where_sql");
$total_records_stmt->execute($params);
$total_records = $total_records_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Fetch records for the current page with filters
$sql = "
    SELECT al.punch_time, al.punch_state, u.full_name, al.employee_code, d.name as device_name
    FROM attendance_logs al
    LEFT JOIN users u ON al.employee_code = u.employee_code
    LEFT JOIN devices d ON al.device_id = d.id
    $where_sql
    ORDER BY al.punch_time DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
// Bind filter params
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users and devices for filter dropdowns
$all_users = $pdo->query("SELECT id, full_name, employee_code FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_devices = $pdo->query("SELECT id, name FROM devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);


function getPunchStateText($state) {
    $states = [
        0 => ['text' => 'Check-In', 'class' => 'text-success'],
        1 => ['text' => 'Check-Out', 'class' => 'text-danger'],
        2 => ['text' => 'Break-Out', 'class' => 'text-warning'],
        3 => ['text' => 'Break-In', 'class' => 'text-info'],
        4 => ['text' => 'Overtime-In', 'class' => 'text-primary'],
        5 => ['text' => 'Overtime-Out', 'class' => 'text-secondary'],
    ];
    return $states[$state] ?? ['text' => 'Unknown (' . $state . ')', 'class' => 'text-muted'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Attendance Punch Logs</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Filter Logs</h2></div>
    <div class="card-body">
        <form action="attendance_logs.php" method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">From Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">To Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-3">
                <label for="user_id" class="form-label">Employee</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Employees</option>
                    <?php foreach($all_users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= ($user_id == $user['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['employee_code'] ?? 'N/A') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="device_id" class="form-label">Device</label>
                <select class="form-select" id="device_id" name="device_id">
                    <option value="">All Devices</option>
                    <?php foreach($all_devices as $device): ?>
                        <option value="<?= $device['id'] ?>" <?= ($device_id == $device['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($device['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <a href="attendance_logs.php" class="btn btn-outline-secondary me-2">Clear Filters</a>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>


<div class="card shadow-sm">
    <div class="card-header"><h2 class="h5 mb-0">Log Results</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr><th>Timestamp</th><th>Employee</th><th>Status</th><th>Device</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="4" class="text-center text-muted p-4">No attendance logs found for the selected filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d, Y, h:i:s A', strtotime($log['punch_time']))) ?></td>
                                <td>
                                    <?= htmlspecialchars($log['full_name'] ?? 'Unknown User') ?><br>
                                    <small class="text-muted">ID: <?= htmlspecialchars($log['employee_code']) ?></small>
                                </td>
                                <td>
                                    <?php $stateInfo = getPunchStateText($log['punch_state']); ?>
                                    <strong class="<?= $stateInfo['class'] ?>"><?= $stateInfo['text'] ?></strong>
                                </td>
                                <td><?= htmlspecialchars($log['device_name'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <nav><ul class="pagination justify-content-center mt-4">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&<?= $filter_query_string ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../app/templates/footer.php';
?>