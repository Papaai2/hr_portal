<?php
// in file: htdocs/reports/hr_history.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php'; // Added helper functions

require_role(['hr', 'hr_manager', 'admin']);

$search_query = trim($_GET['search'] ?? '');

$sql = "
    SELECT r.*, u.full_name AS user_name, m.full_name AS manager_name
    FROM vacation_requests r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users m ON r.manager_id = m.id
";

$params = [];

if (!empty($search_query)) {
    $sql .= " WHERE u.full_name LIKE :search_query OR m.full_name LIKE :search_query_manager";
    $params[':search_query'] = '%' . $search_query . '%';
    $params[':search_query_manager'] = '%' . $search_query . '%';
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Removed getStatusBadgeClass() and getStatusText() functions from here

$page_title = 'Full Request History';
include __DIR__ . '/../app/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Full Request History</h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="hr_history.php" method="get" class="mb-4">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search by Employee or Manager Name" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Search</button>
                <?php if (!empty($_GET['search'])): ?>
                    <a href="hr_history.php" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light"><tr><th>Employee</th><th>Manager</th><th>Start Date</th><th>End Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="6" class="text-center text-muted p-4">No requests found in the system.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['user_name']) ?></td>
                                <td><?= htmlspecialchars($request['manager_name'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($request['start_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($request['end_date'])) ?></td>
                                <td><span class="badge rounded-pill <?= getStatusBadgeClass($request['status']) ?>"><?= getStatusText($request['status']) ?></span></td>
                                <td class="text-end"><a href="/requests/view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye-fill"></i> View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../app/templates/footer.php'; ?>
