<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['manager', 'hr_manager', 'admin']);

$current_user_id = get_current_user_id();
$current_user_role = get_current_user_role();
// Update the page title to reflect its new purpose
$page_title = 'Team Vacation Requests';

// Base SQL query
$sql = "
    SELECT r.*, u.full_name, lt.name as leave_type_name
    FROM vacation_requests r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN leave_types lt ON r.leave_type_id = lt.id
";

$params = [];
$where_clauses = [];

// If the user is a manager (but not hr_manager or admin), show only their direct reports.
if ($current_user_role === 'manager') {
    $where_clauses[] = "u.manager_id = ?";
    $params[] = $current_user_id;
}
// HR Managers and Admins see all requests, so no WHERE clause is added for them.

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Order by creation date to show the newest requests first
$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

include __DIR__ . '/../app/templates/header.php';
?>

<div class="container mt-4">
    <h1 class="h3 mb-4"><?= htmlspecialchars($page_title) ?></h1>

    <div class="card">
        <div class="card-header">
            All Team Requests
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Employee Name</th>
                            <th>Leave Type</th>
                            <th>Dates</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Submitted On</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No team requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($request['id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($request['full_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($request['leave_type_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M d, Y', strtotime($request['start_date'])) ?> to <?= date('M d, Y', strtotime($request['end_date'])) ?></td>
                                    <td><?= htmlspecialchars($request['duration_days'] ?? '0') ?> days</td>
                                    <td><span class="badge <?= getStatusBadgeClass($request['status'] ?? '') ?>"><?= getStatusText($request['status'] ?? '') ?></span></td>
                                    <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                    <td>
                                        <a href="/requests/view.php?id=<?= htmlspecialchars($request['id'] ?? '') ?>" class="btn btn-sm btn-primary">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>