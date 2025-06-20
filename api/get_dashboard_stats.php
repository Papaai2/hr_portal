<?php
// in file: htdocs/api/get_dashboard_stats.php

header('Content-Type: application/json');

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_login();

$user_id = get_current_user_id();
$user_role = get_current_user_role();

try {
    $pending_requests = 0;
    $approved_this_month = 0;
    $team_size = 0;

    // Pending Requests (Manager/HR/Admin)
    if ($user_role === 'manager' || $user_role === 'admin') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vacation_requests WHERE status = 'pending_manager' AND manager_id = ?");
        $stmt->execute([$user_id]);
        $pending_requests += $stmt->fetchColumn();
    }
    if ($user_role === 'hr' || $user_role === 'admin' || $user_role === 'hr_manager') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vacation_requests WHERE status = 'pending_hr'");
        $stmt->execute();
        $pending_requests += $stmt->fetchColumn();
    }

    // Approved This Month (for all roles, maybe filter by manager/user for specific views)
    // This example fetches all approved requests this month. You might need to filter by user's team/department.
    $current_month = date('Y-m-01');
    $next_month = date('Y-m-01', strtotime('+1 month'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vacation_requests WHERE status = 'approved' AND hr_action_at >= ? AND hr_action_at < ?");
    $stmt->execute([$current_month, $next_month]);
    $approved_this_month = $stmt->fetchColumn();

    // Team Size (for managers)
    if ($user_role === 'manager' || $user_role === 'admin' || $user_role === 'hr_manager') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE direct_manager_id = ?");
        $stmt->execute([$user_id]);
        $team_size = $stmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'pendingRequests' => $pending_requests,
            'approvedThisMonth' => $approved_this_month,
            'teamSize' => $team_size
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>