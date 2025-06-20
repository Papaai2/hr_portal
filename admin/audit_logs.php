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

// Filtering (optional: by user, action, date)
$where = [];
$params = [];
if (!empty($_GET['user_id'])) {
    $where[] = 'al.user_id = ?';
    $params[] = $_GET['user_id'];
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
$sql = "SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id $where_sql ORDER BY al.created_at DESC LIMIT $page_size OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total for pagination
$count_sql = "SELECT COUNT(*) FROM audit_logs al $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $page_size);
?>
<div class="container mt-4">
    <h1 class="h3 mb-4">Audit Logs</h1>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-3">
            <input type="text" name="user_id" class="form-control" placeholder="User ID" value="<?= htmlspecialchars($_GET['user_id'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <input type="text" name="action" class="form-control" placeholder="Action" value="<?= htmlspecialchars($_GET['action'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($audit_logs)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No audit logs found.</td></tr>
                <?php else: ?>
                    <?php foreach ($audit_logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id'] ?? '') ?></td>
                            <td><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?> (ID: <?= htmlspecialchars($log['user_id'] ?? '') ?>)</td>
                            <td><?= htmlspecialchars($log['action'] ?? '') ?></td>
                            <td style="max-width:300px; word-break:break-word; white-space:pre-wrap;"><?= htmlspecialchars($log['details'] ?? '') ?></td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                            <td><?= htmlspecialchars($log['created_at'] ?? '') ?></td>
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