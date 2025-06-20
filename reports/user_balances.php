<?php
// in file: htdocs/reports/user_balances.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_role(['hr', 'admin', 'hr_manager']);

$error = '';

// --- Filter and Data Fetching Logic ---
$search_query = trim($_GET['search'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$user_id_filter = trim($_GET['user_id'] ?? '');

$sql_users = "
    SELECT u.id AS user_id, u.full_name, u.email, u.role, d.name AS department_name,
           GROUP_CONCAT(CONCAT(lt.name, ':', lb.balance_days) ORDER BY lt.name SEPARATOR ';') AS balances_str
    FROM users u
    LEFT JOIN leave_balances lb ON u.id = lb.user_id
    LEFT JOIN leave_types lt ON lb.leave_type_id = lt.id
    LEFT JOIN departments d ON u.department_id = d.id
";

$where_clauses = ["lt.is_active = 1"];
$sql_params = [];

if (!empty($search_query)) {
    $where_clauses[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $sql_params[] = "%$search_query%";
    $sql_params[] = "%$search_query%";
}
if(!empty($user_id_filter)) {
    $where_clauses[] = "u.id = ?";
    $sql_params[] = $user_id_filter;
}
if (!empty($start_date)) {
    $where_clauses[] = "lb.last_updated_at >= ?";
    $sql_params[] = $start_date;
}
if (!empty($end_date)) {
    // Add 1 day to end date to include the whole day
    $end_date_obj = new DateTime($end_date);
    $end_date_obj->modify('+1 day');
    $where_clauses[] = "lb.last_updated_at < ?";
    $sql_params[] = $end_date_obj->format('Y-m-d');
}

if (!empty($where_clauses)) {
    $sql_users .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql_users .= " GROUP BY u.id, u.full_name, u.email, u.role, d.name ORDER BY u.full_name ASC";

try {
    $stmt_users = $pdo->prepare($sql_users);
    $stmt_users->execute($sql_params);
    $users_data = $stmt_users->fetchAll();
} catch (PDOException $e) {
    $error = "Database error fetching user balances: " . $e->getMessage();
    $users_data = [];
}

// Fetch all users for the dropdown filter
$all_users_for_filter = $pdo->query("SELECT id, full_name, email FROM users ORDER BY full_name ASC")->fetchAll();


// --- CSV Export Logic ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user_balances_report_'.date('Y-m-d').'.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Name', 'Email', 'Role', 'Department', 'Leave Type', 'Balance (Days)']);

    foreach ($users_data as $user) {
        if ($user['balances_str']) {
            $balances = [];
            foreach (explode(';', $user['balances_str']) as $balance_pair) {
                @list($type_name, $balance) = explode(':', $balance_pair);
                if(isset($type_name) && isset($balance)) {
                    fputcsv($output, [
                        $user['full_name'], $user['email'], ucfirst($user['role']),
                        $user['department_name'] ?? 'N/A', $type_name, number_format((float)$balance, 2)
                    ]);
                }
            }
        } else {
             fputcsv($output, [
                $user['full_name'], $user['email'], ucfirst($user['role']),
                $user['department_name'] ?? 'N/A', 'N/A', '0.00'
            ]);
        }
    }
    fclose($output);
    exit();
}

$page_title = 'User Leave Balances Report';
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">User Leave Balances Report</h1>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">Filter Balances</h2>
    </div>
    <div class="card-body">
        <form action="user_balances.php" method="get" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search Name/Email</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>">
                </div>
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Specific User</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach($all_users_for_filter as $user_filter): ?>
                            <option value="<?= $user_filter['id'] ?>" <?= $user_id_filter == $user_filter['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user_filter['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2 align-self-end">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button>
                </div>
            </div>
            <div class="row g-3 mt-2">
                 <div class="col-md-2 ms-auto">
                     <a href="user_balances.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Clear</a>
                 </div>
            </div>
        </form>
        
        <hr>

        <div class="mb-3">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
                <i class="bi bi-download me-1"></i> Export Results to CSV
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Employee Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Balances</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users_data)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">No results found for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_data as $user): ?>
                             <tr>
                                <td><?= htmlspecialchars($user['full_name']); ?></td>
                                <td><?= htmlspecialchars($user['email']); ?></td>
                                <td><?= ucfirst(htmlspecialchars($user['role'])); ?></td>
                                <td><?= htmlspecialchars($user['department_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!$user['balances_str']): ?>
                                        <span class="text-muted">No balances set</span>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0 small">
                                            <?php 
                                            $balances = explode(';', $user['balances_str']);
                                            foreach ($balances as $balance_pair): 
                                                list($type_name, $balance) = explode(':', $balance_pair);
                                            ?>
                                                <li><strong><?= htmlspecialchars($type_name) ?>:</strong> <?= htmlspecialchars(number_format($balance, 2)) ?> days</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
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
