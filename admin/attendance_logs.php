<?php
// in file: admin/attendance_logs.php

require_once __DIR__ . '/../app/bootstrap.php';
require_role('admin');

$page_title = 'Attendance Logs';
include __DIR__ . '/../app/templates/header.php';

// --- Data Fetching ---
// Pagination setup
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 25;
$offset = ($page - 1) * $records_per_page;

// Count total records for pagination
$total_records_stmt = $pdo->query("SELECT COUNT(*) FROM attendance_logs");
$total_records = $total_records_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);


// Fetch records for the current page
$stmt = $pdo->prepare("
    SELECT al.punch_time, al.punch_state, u.full_name, u.employee_code, d.name as device_name
    FROM attendance_logs al
    LEFT JOIN users u ON al.employee_code = u.employee_code
    LEFT JOIN devices d ON al.device_id = d.id
    ORDER BY al.punch_time DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Helper function to get text for punch state
 * @param int $state The punch state code
 * @return string The display text
 */
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

<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">Raw Data from Devices</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Employee</th>
                        <th>Status</th>
                        <th>Device</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="4" class="text-center text-muted p-4">No attendance logs found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('M d, Y, h:i:s A', strtotime($log['punch_time']))); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($log['full_name'] ?? 'Unknown User'); ?>
                                    <small class="text-muted d-block">ID: <?php echo htmlspecialchars($log['employee_code']); ?></small>
                                </td>
                                <td>
                                    <?php $stateInfo = getPunchStateText($log['punch_state']); ?>
                                    <strong class="<?php echo $stateInfo['class']; ?>"><?php echo $stateInfo['text']; ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['device_name'] ?? 'N/A'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-4">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>

<?php
include __DIR__ . '/../app/templates/footer.php';
?>