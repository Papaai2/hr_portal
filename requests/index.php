<?php
// in file: htdocs/requests/index.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php'; // Ensures helpers are loaded once

require_login();

$user_id = get_current_user_id();

// Fetch all requests for the current user
$stmt = $pdo->prepare("
    SELECT * FROM vacation_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll();

// Note: The duplicated getStatusBadgeClass() and getStatusText() functions have been removed.

$page_title = 'My Vacation Requests';
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">My Vacation Requests</h1>
    <a href="create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Request
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Submitted On</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">You have not submitted any requests yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <span class="badge rounded-pill <?= getStatusBadgeClass($request['status']) ?>">
                                        <?= getStatusText($request['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="view.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary">
                                       <i class="bi bi-eye-fill"></i> View Details
                                    </a>
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
