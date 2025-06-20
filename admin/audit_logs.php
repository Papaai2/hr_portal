<?php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php';

require_role(['admin', 'hr_manager']);

$page_title = 'Audit Logs';
include __DIR__ . '/../app/templates/header.php';

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$page_size = 25;
$offset = ($page - 1) * $page_size;

// Filtering (optional: by user, action, date, employee_code)
$where = [];
$params = [];
if (!empty($_GET['user_id'])) {
    $where[] = 'al.user_id = ?';
    $params[] = $_GET['user_id'];
}
if (!empty($_GET['employee_code'])) { // NEW: Filter by employee_code
    $where[] = 'u.employee_code LIKE ?';
    $params[] = '%' . $_GET['employee_code'] . '%';
}
if (!empty($_GET['action'])) {
    $where[] = 'al.action LIKE ?';
    $params[] = '%' . $_GET['action'] . '%';
}
if (!empty($_GET['date'])) {
    $where[] = 'DATE(al.created_at) = ?';
    $params[] = $_GET['date'];
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch logs
// MODIFIED: Select u.employee_code
$sql = "SELECT al.*, u.full_name, u.employee_code FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id {$where_sql} ORDER BY al.created_at DESC LIMIT {$page_size} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total for pagination
$count_sql = "SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id {$where_sql}"; // MODIFIED: Join with users for count if employee_code filter is used
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $page_size);

// Fetch all users for the dropdown filter
$all_users = $pdo->query("SELECT id, full_name, employee_code FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container mt-4">
    <h1 class="h3 mb-4">Audit Logs</h1>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-3">
            <label for="user_id" class="form-label">User</label>
            <select class="form-select" id="user_id" name="user_id">
                <option value="">All Users</option>
                <?php foreach($all_users as $user_option): ?>
                    <option value="<?= htmlspecialchars($user_option['id']) ?>" <?= (isset($_GET['user_id']) && $_GET['user_id'] == $user_option['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user_option['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="employee_code" class="form-label">Employee Code</label> <input type="text" name="employee_code" class="form-control" placeholder="Employee Code" value="<?= htmlspecialchars($_GET['employee_code'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label for="action" class="form-label">Action</label>
            <input type="text" name="action" class="form-control" placeholder="Action" value="<?= htmlspecialchars($_GET['action'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label for="date" class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
        </div>
        <div class="col-12 mt-3">
            <button type="submit" class="btn btn-primary me-2">Filter</button>
            <a href="audit_logs.php" class="btn btn-outline-secondary">Clear Filters</a>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Employee Code</th> <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($audit_logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No audit logs found.</td></tr> <?php else: ?>
                    <?php foreach ($audit_logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id']) ?></td>
                            <td><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?> (ID: <?= htmlspecialchars($log['user_id']) ?>)</td>
                            <td><code class="text-muted"><?= htmlspecialchars($log['employee_code'] ?? 'N/A') ?></code></td> <td><?= htmlspecialchars($log['action']) ?></td>
                            <td style="max-width:300px; word-break:break-word; white-space:pre-wrap;"><?= htmlspecialchars($log['details']) ?></td>
                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Audit log pagination">
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