<?php
// in file: reports/timesheet.php

require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

// --- Filter Handling ---
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';

$params = [':filter_date' => $filter_date];
$where_clauses = ["DATE(al.punch_time) = :filter_date"];

if ($user_id && is_numeric($user_id)) {
    $where_clauses[] = "u.id = :user_id";
    $params[':user_id'] = $user_id;
}

$where_sql = "WHERE " . implode(' AND ', $where_clauses);

// --- Data Fetching Logic ---
$sql = "
    SELECT
        u.id as user_id,
        u.full_name,
        u.employee_code,
        DATE(al.punch_time) as attendance_date,
        s.shift_name,
        s.start_time AS shift_start_time,
        s.end_time AS shift_end_time,
        s.is_night_shift,
        s.grace_period_in,
        s.grace_period_out,
        MIN(CASE WHEN al.punch_state IN (0, 4) THEN al.punch_time END) as first_in,
        MAX(CASE WHEN al.punch_state IN (1, 5) THEN al.punch_time END) as last_out,
        -- Corrected syntax for GROUP_CONCAT with ORDER BY inside it
        SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN al.punch_state IN (0,4) THEN al.expected_in END ORDER BY al.punch_time ASC SEPARATOR ','), ',', 1) as expected_first_in,
        SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN al.punch_state IN (1,5) THEN al.expected_out END ORDER BY al.punch_time DESC SEPARATOR ','), ',', 1) as expected_last_out,
        GROUP_CONCAT(DISTINCT al.violation_type ORDER BY al.violation_type ASC SEPARATOR ', ') as daily_violations
    FROM
        attendance_logs al
    JOIN
        users u ON al.employee_code = u.employee_code
    LEFT JOIN
        shifts s ON u.shift_id = s.id
    {$where_sql}
    GROUP BY
        u.id, u.full_name, u.employee_code, attendance_date, s.shift_name, s.start_time, s.end_time, s.is_night_shift, s.grace_period_in, s.grace_period_out
    ORDER BY
        u.full_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$timesheet_data = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --- CSV Export Logic ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "timesheet_report_" . $filter_date . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add headers to CSV - UPDATED
    fputcsv($output, ['Employee', 'Employee Code', 'Date', 'Shift', 'Shift Start', 'Shift End', 'Expected In', 'Actual First In', 'Expected Out', 'Actual Last Out', 'Total Hours', 'Violations']);

    // Add data rows to CSV - UPDATED
    foreach ($timesheet_data as $row) {
        $total_hours = 'Incomplete';
        if ($row['first_in'] && $row['last_out']) {
            $first_in = new DateTime($row['first_in']);
            $last_out = new DateTime($row['last_out']);
            $interval = $first_in->diff($last_out);
            $total_hours = $interval->format('%h hours, %i minutes');
        }

        fputcsv($output, [
            $row['full_name'],
            $row['employee_code'],
            $row['attendance_date'],
            $row['shift_name'] ?? 'N/A',
            $row['shift_start_time'] ? date('h:i A', strtotime($row['shift_start_time'])) : 'N/A',
            $row['shift_end_time'] ? date('h:i A', strtotime($row['shift_end_time'])) : 'N/A',
            $row['expected_first_in'] ? date('h:i:s A', strtotime($row['expected_first_in'])) : 'N/A',
            $row['first_in'] ? date('h:i:s A', strtotime($row['first_in'])) : '',
            $row['expected_last_out'] ? date('h:i:s A', strtotime($row['expected_last_out'])) : 'N/A',
            $row['last_out'] ? date('h:i:s A', strtotime($row['last_out'])) : '',
            $total_hours,
            $row['daily_violations'] ?? 'None'
        ]);
    }

    fclose($output);
    exit();
}


// --- HTML Rendering ---
$page_title = 'Daily Timesheet Report';
include __DIR__ . '/../app/templates/header.php';

$all_users = $pdo->query("SELECT id, full_name, employee_code FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Daily Timesheet Report</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Filter Report</h2></div>
    <div class="card-body">
        <form action="timesheet.php" method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="filter_date" class="form-label">Date</label>
                <input type="date" class="form-control" id="filter_date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="col-md-4">
                <label for="user_id" class="form-label">Employee</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Employees</option>
                    <?php foreach($all_users as $user): ?>
                        <option value="<?= htmlspecialchars($user['id']) ?>" <?= ($user_id == $user['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['employee_code'] ?? 'N/A') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Report for: <?= htmlspecialchars(date('F j, Y', strtotime($filter_date))) ?></h2>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-sm btn-success">
            <i class="bi bi-download me-1"></i> Export to CSV
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light text-center">
                    <tr>
                        <th>Employee</th>
                        <th>Shift</th>
                        <th>Expected In</th>
                        <th>Actual In</th>
                        <th>Expected Out</th>
                        <th>Actual Out</th>
                        <th>Total Hours</th>
                        <th>Violations</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    <?php if (empty($timesheet_data)): ?>
                        <tr><td colspan="8" class="text-center text-muted p-4">No attendance data found for the selected criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($timesheet_data as $row): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($row['full_name']) ?><br>
                                    <small class="text-muted">ID: <?= htmlspecialchars($row['employee_code']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['shift_name'] ?? 'N/A') ?>
                                    <?php if (isset($row['shift_start_time']) && isset($row['shift_end_time'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(date('h:i A', strtotime($row['shift_start_time']))) ?> - <?= htmlspecialchars(date('h:i A', strtotime($row['shift_end_time']))) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <?= $row['expected_first_in'] ? date('h:i:s A', strtotime($row['expected_first_in'])) : '<span class="text-muted">--</span>' ?>
                                </td>
                                <td class="align-middle">
                                    <?= $row['first_in'] ? date('h:i:s A', strtotime($row['first_in'])) : '<span class="text-muted">--</span>' ?>
                                </td>
                                <td class="align-middle">
                                    <?= $row['expected_last_out'] ? date('h:i:s A', strtotime($row['expected_last_out'])) : '<span class="text-muted">--</span>' ?>
                                </td>
                                <td class="align-middle">
                                    <?= $row['last_out'] ? date('h:i:s A', strtotime($row['last_out'])) : '<span class="text-muted">--</span>' ?>
                                </td>
                                <td class="align-middle">
                                    <?php
                                    if ($row['first_in'] && $row['last_out']) {
                                        $first_in_dt = new DateTime($row['first_in']);
                                        $last_out_dt = new DateTime($row['last_out']);
                                        
                                        // Handle night shift total hours calculation
                                        if (isset($row['is_night_shift']) && $row['is_night_shift'] && $last_out_dt < $first_in_dt) {
                                            $last_out_dt->modify('+1 day'); // Adjust if end time is on next day for night shift
                                        }

                                        $interval = $first_in_dt->diff($last_out_dt);
                                        echo $interval->format('%h hours, %i minutes');
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">Incomplete</span>';
                                    }
                                    ?>
                                </td>
                                <td class="align-middle">
                                    <?php if (!empty($row['daily_violations'])): ?>
                                        <?php
                                        $violations = explode(', ', $row['daily_violations']);
                                        foreach ($violations as $violation):
                                            $badge_class = 'bg-danger';
                                            if ($violation === 'double_punch') $badge_class = 'bg-info text-dark';
                                            echo '<span class="badge ' . $badge_class . ' me-1">' . htmlspecialchars(ucwords(str_replace('_', ' ', $violation))) . '</span>';
                                        endforeach;
                                        ?>
                                    <?php else: ?>
                                        <span class="badge bg-success">None</span>
                                    <?php endif; ?>
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