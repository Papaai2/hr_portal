<?php
// in file: htdocs/reports/team.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_role(['manager', 'admin', 'hr_manager']);

$manager_id = get_current_user_id();

// Fetch team members
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.employee_code, d.name AS department_name -- ADDED u.employee_code
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.direct_manager_id = ?
    ORDER BY u.full_name
");
$stmt->execute([$manager_id]);
$team_members = $stmt->fetchAll();

$page_title = 'My Team';
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">My Team Members</h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Full Name</th>
                        <th>Employee Code</th> <th>Email</th>
                        <th>Department</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($team_members)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted p-4">You have no team members assigned to you.</td> </tr>
                    <?php else: ?>
                        <?php foreach ($team_members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($member['employee_code'] ?? 'N/A'); ?></code></td> <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo htmlspecialchars($member['department_name'] ?? 'N/A'); ?></td>
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