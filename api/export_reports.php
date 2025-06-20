<?php
// in file: htdocs/api/export_reports.php
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php'; // For getStatusText, etc.

require_role(['hr', 'admin', 'hr_manager']); // Only HR/Admin roles can export reports

$report_type = $_GET['report_type'] ?? '';
$search_query = trim($_GET['search'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$user_id_filter = trim($_GET['user_id'] ?? '');

if (empty($report_type)) {
    http_response_code(400);
    exit('Error: Report type is required.');
}

try {
    if ($report_type === 'user_balances') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="user_balances_report_'.date('Y-m-d').'.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Employee Name', 'Email', 'Role', 'Department', 'Leave Type', 'Balance (Days)']);

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
            $end_date_obj = new DateTime($end_date);
            $end_date_obj->modify('+1 day');
            $where_clauses[] = "lb.last_updated_at < ?";
            $sql_params[] = $end_date_obj->format('Y-m-d');
        }

        if (!empty($where_clauses)) {
            $sql_users .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $sql_users .= " GROUP BY u.id, u.full_name, u.email, u.role, d.name ORDER BY u.full_name ASC";

        $stmt_users = $pdo->prepare($sql_users);
        $stmt_users->execute($sql_params);
        $users_data = $stmt_users->fetchAll();

        foreach ($users_data as $user) {
            if ($user['balances_str']) {
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
    } elseif ($report_type === 'full_history') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="full_request_history_'.date('Y-m-d').'.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Employee Name', 'Manager Name', 'Start Date', 'End Date', 'Status', 'Reason', 'Rejection Reason']);

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

        foreach ($requests as $request) {
            fputcsv($output, [
                $request['user_name'],
                $request['manager_name'] ?? 'N/A',
                date('Y-m-d', strtotime($request['start_date'])),
                date('Y-m-d', strtotime($request['end_date'])),
                getStatusText($request['status']),
                $request['reason'],
                $request['rejection_reason']
            ]);
        }
        fclose($output);
        exit();
    } else {
        http_response_code(400);
        exit('Error: Invalid report type specified.');
    }

} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
}
?>