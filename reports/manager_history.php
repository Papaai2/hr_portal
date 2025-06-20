<?php
// in file: htdocs/reports/manager_history.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php'; // Added helper functions

require_role(['manager', 'admin', 'hr_manager']);

$manager_id = get_current_user_id();
$search_query = trim($_GET['search'] ?? '');

$sql = "
    SELECT r.*, u.full_name AS user_name
    FROM vacation_requests r
    JOIN users u ON r.user_id = u.id
    WHERE u.direct_manager_id = :manager_id
";

$params = [':manager_id' => $manager_id];

if (!empty($search_query)) {
    $sql .= " AND u.full_name LIKE :search_query";
    $params[':search_query'] = '%' . $search_query . '%';
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Removed getStatusBadgeClass() and getStatusText() functions from here

$page_title = 'Team Request History';
include __DIR__ . '/../app/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Team Request History</h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="manager_history.php" method="get" class="mb-4">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search by Employee Name" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Search</button>
                <?php if (!empty($_GET['search'])): ?>
                    <a href="manager_history.php" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light"><tr><th>Employee</th><th>Start Date</th><th>End Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="5" class="text-center text-muted p-4">No requests found for your team.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['user_name']) ?></td>
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
