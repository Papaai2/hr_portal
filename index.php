<?php
// in file: htdocs/index.php

require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/helpers.php';

require_login();

$user_id = get_current_user_id();
$user_role = get_current_user_role();
$pending_manager_requests = [];
$pending_hr_requests = [];
$hr_view_pending_manager = [];

$my_leave_balances = [];
// FIX: The column name was corrected from `lb.balance` to `lb.balance_days` to match the database schema.
$stmt_my_balances = $pdo->prepare("
    SELECT lb.balance_days, lt.name AS leave_type_name
    FROM leave_balances lb
    JOIN leave_types lt ON lb.leave_type_id = lt.id
    WHERE lb.user_id = ?
    ORDER BY lt.name ASC
");
$stmt_my_balances->execute([$user_id]);
$my_leave_balances = $stmt_my_balances->fetchAll();

if ($user_role === 'manager' || $user_role === 'admin') {
    $stmt = $pdo->prepare("
        SELECT r.id, r.start_date, r.end_date, r.created_at, u.full_name AS user_name
        FROM vacation_requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'pending_manager' AND r.manager_id = ?
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$user_id]);
    $pending_manager_requests = $stmt->fetchAll();
}

if ($user_role === 'hr' || $user_role === 'admin' || $user_role === 'hr_manager') {
    $stmt = $pdo->prepare("
        SELECT r.id, r.start_date, r.end_date, r.status, r.created_at, u.full_name AS user_name
        FROM vacation_requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'pending_hr'
        ORDER BY r.created_at ASC
    ");
    $stmt->execute();
    $pending_hr_requests = $stmt->fetchAll();

    $stmt_hr_view = $pdo->query("
        SELECT r.id, r.start_date, r.end_date, r.created_at, u.full_name AS user_name, m.full_name as manager_name
        FROM vacation_requests r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users m ON r.manager_id = m.id
        WHERE r.status = 'pending_manager'
        ORDER BY r.created_at ASC
    ");
    $hr_view_pending_manager = $stmt_hr_view->fetchAll();
}


$page_title = 'Dashboard';
include __DIR__ . '/app/templates/header.php';
?>

<div class="mb-4">
    <h1 class="h2 text-white">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
    <p class="text-muted">You are logged in as a(n) <strong><?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></strong>.</p>
</div>

<!-- Dashboard Stats Banner -->
<div id="dashboard-stats" class="dashboard-stats mb-4">
    <div class="row">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-number">0</div> <!-- This is where JS injects the number -->
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-number">0</div> <!-- This is where JS injects the number -->
                <div class="stat-label">Approved This Month</div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-number">0</div> <!-- This is where JS injects the number -->
                <div class="stat-label">Team Members</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <?php if (!empty($pending_manager_requests) || !empty($pending_hr_requests)): ?>
            <div class="alert alert-info fw-bold">You have requests that require your action.</div>
        <?php endif; ?>

        <?php if (!empty($pending_manager_requests)): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header"><h2 class="h5 mb-0"><i class="bi bi-person-check-fill me-2"></i>Manager Approval Queue</h2></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light"><tr><th>Employee</th><th>Dates</th><th>Submitted</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($pending_manager_requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['user_name']) ?></td>
                                <td><?= date('M d', strtotime($request['start_date'])) . ' - ' . date('M d, Y', strtotime($request['end_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                <td><a href="/requests/view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($pending_hr_requests)): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header"><h2 class="h5 mb-0"><i class="bi bi-clipboard2-check-fill me-2"></i>HR Final Approval Queue</h2></div>
            <div class="card-body">
                 <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light"><tr><th>Employee</th><th>Dates</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($pending_hr_requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['user_name']) ?></td>
                                <td><?= date('M d', strtotime($request['start_date'])) . ' - ' . date('M d, Y', strtotime($request['end_date'])) ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($request['status']) ?>"><?= getStatusText($request['status']) ?></span></td>
                                <td><a href="/requests/view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user_role === 'hr' || $user_role === 'admin' || $user_role === 'hr_manager'): ?> <?php if (!empty($hr_view_pending_manager)): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-secondary text-white"><h2 class="h5 mb-0"><i class="bi bi-hourglass-split me-2"></i>Awaiting Manager Action</h2></div>
                <div class="card-body">
                    <p class="text-muted">The following requests are currently being reviewed by their respective managers.</p>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light"><tr><th>Employee</th><th>Manager</th><th>Submitted</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($hr_view_pending_manager as $request): ?>
                                <tr>
                                    <td><?= htmlspecialchars($request['user_name']) ?></td>
                                    <td><?= htmlspecialchars($request['manager_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                    <td><a href="/requests/view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (empty($pending_manager_requests) && empty($pending_hr_requests) && (empty($hr_view_pending_manager) || !in_array($user_role, ['hr', 'admin', 'hr_manager']))): ?> 
        <div id="calendar-widget" class="calendar-widget">
            <div class="calendar-header">
                <h3 class="h5 mb-0">Team Leave Calendar</h3>
                </div>
            <div class="calendar-grid">
                </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4 shadow-sm">
            <div class="card-header"><h2 class="h5 mb-0"><i class="bi bi-pie-chart-fill me-2"></i>My Leave Balances</h2></div>
            <div class="card-body">
                <?php if (empty($my_leave_balances)): ?>
                    <p class="text-muted">No leave balances found. Contact HR.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($my_leave_balances as $balance): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($balance['leave_type_name']) ?>
                                <span class="badge bg-primary rounded-pill fs-6"><?= htmlspecialchars(number_format($balance['balance_days'], 2)) ?> days</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
         <div class="quick-actions card shadow-sm">
             <div class="card-body">
                <h3 class="h5 mb-3"><i class="bi bi-lightning-charge-fill me-2"></i>Quick Actions</h3>
                <a href="/requests/create.php" class="quick-action-btn">
                    <div class="quick-action-icon"><i class="bi bi-calendar-plus-fill"></i></div>
                    <div><strong>New Leave Request</strong><small class="d-block text-muted">Submit a new request for time off.</small></div>
                </a>
                <a href="/requests/index.php" class="quick-action-btn">
                    <div class="quick-action-icon"><i class="bi bi-card-list"></i></div>
                    <div><strong>My Requests</strong><small class="d-block text-muted">View your past and present requests.</small></div>
                </a>
             </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/app/templates/footer.php'; ?>