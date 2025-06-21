<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['manager', 'hr_manager', 'admin']);

$current_user_id = get_current_user_id();
$current_user_role = get_current_user_role();
$page_title = 'My Team';

// Base SQL to get user details
$sql = "SELECT full_name, email, employee_code FROM users";
$params = [];

// If the logged-in user is a 'manager', filter the list to their direct reports.
if ($current_user_role === 'manager') {
    $sql .= " WHERE manager_id = ?";
    $params[] = $current_user_id;
}
// For 'hr_manager' and 'admin', no filter is applied, so they see all users.

$sql .= " ORDER BY full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$team_members = $stmt->fetchAll();

include __DIR__ . '/../app/templates/header.php';
?>

<div class="container mt-4">
    <h1 class="h3 mb-4"><?= htmlspecialchars($page_title) ?></h1>

    <div class="card">
        <div class="card-header">
            <?php if ($current_user_role === 'manager'): ?>
                Your Direct Reports
            <?php else: ?>
                All Users Directory
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Employee Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($team_members)): ?>
                            <tr>
                                <td colspan="3" class="text-center">No team members found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($team_members as $member): ?>
                                <tr>
                                    <td><?= htmlspecialchars($member['full_name']) ?></td>
                                    <td><?= htmlspecialchars($member['email']) ?></td>
                                    <td><?= htmlspecialchars($member['employee_code'] ?? 'N/A') ?></td>
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