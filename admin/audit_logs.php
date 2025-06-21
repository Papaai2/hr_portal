<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role('admin');

$page_title = 'Audit Logs';

// Pagination settings
$results_per_page = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $results_per_page;

// Filtering settings
$filter_user = sanitize_input($_GET['user_id'] ?? '');
$filter_action = sanitize_input($_GET['action'] ?? '');
$filter_start_date = sanitize_input($_GET['start_date'] ?? '');
$filter_end_date = sanitize_input($_GET['end_date'] ?? '');

// Build the query
$where_clauses = [];
$params = [];

if (!empty($filter_user)) {
    $where_clauses[] = 'u.id = ?';
    $params[] = $filter_user;
}
if (!empty($filter_action)) {
    $where_clauses[] = 'al.action = ?';
    $params[] = $filter_action;
}
if (!empty($filter_start_date)) {
    $where_clauses[] = 'al.created_at >= ?';
    $params[] = $filter_start_date . ' 00:00:00';
}
if (!empty($filter_end_date)) {
    $where_clauses[] = 'al.created_at <= ?';
    $params[] = $filter_end_date . ' 23:59:59';
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total number of logs for pagination
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id {$where_sql}");
$total_stmt->execute($params);
$total_logs = $total_stmt->fetchColumn();
$total_pages = ceil($total_logs / $results_per_page);

// Fetch the logs for the current page
$stmt_params = array_merge($params, [$results_per_page, $offset]);
$stmt = $pdo->prepare(
    "SELECT al.*, u.full_name, u.employee_code 
     FROM audit_logs al 
     LEFT JOIN users u ON al.user_id = u.id
     {$where_sql}
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($stmt_params);
$logs = $stmt->fetchAll();

// Fetch users and action types for filter dropdowns
$users = $pdo->query("SELECT id, full_name, employee_code FROM users ORDER BY full_name")->fetchAll();
$action_types = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../app/templates/header.php';
?>

<h1 class="h2 mb-4">Audit Logs</h1>

<div class="card mb-4">
    <div class="card-header">Filter Logs</div>
    <div class="card-body">
        <form action="audit_logs.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="user_id" class="form-label">User</label>
                <select name="user_id" id="user_id" class="form-select">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($filter_user == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name'] . ' (' . ($user['employee_code'] ?? 'N/A') . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="action" class="form-label">Action</label>
                <select name="action" id="action" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($filter_action == $type) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="start_date" class="form-label">Date From</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($filter_start_date); ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">Date To</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($filter_end_date); ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" class="text-center">No audit logs found matching your criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                <td>
                                    <?php if ($log['full_name']): ?>
                                        <a href="?user_id=<?php echo $log['user_id']; ?>">
                                            <?php echo htmlspecialchars($log['full_name']); ?>
                                            (<?php echo htmlspecialchars($log['employee_code'] ?? 'N/A'); ?>)
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown (ID: <?php echo htmlspecialchars($log['user_id'] ?? 'N/A'); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&user_id=<?php echo htmlspecialchars($filter_user); ?>&action=<?php echo htmlspecialchars($filter_action); ?>&start_date=<?php echo htmlspecialchars($filter_start_date); ?>&end_date=<?php echo htmlspecialchars($filter_end_date); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>


<?php
include __DIR__ . '/../app/templates/footer.php';
?>