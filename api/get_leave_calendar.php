<?php
// in file: htdocs/api/get_leave_calendar.php

header('Content-Type: application/json');

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_login();

$user_id = get_current_user_id();
$year = $_GET['year'] ?? null;
$month = $_GET['month'] ?? null;

try {
    // Basic validation for year and month
    if (!is_numeric($year) || !is_numeric($month) || $month < 1 || $month > 12) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid year or month.']);
        exit();
    }

    // Fetch leave data for the specified month for all users visible to the current user's role
    // This example fetches all approved leaves for the month, adjust visibility based on roles.
    $stmt = $pdo->prepare("
        SELECT vr.start_date, vr.end_date, u.full_name AS employee, lt.name AS leave_type
        FROM vacation_requests vr
        JOIN users u ON vr.user_id = u.id
        JOIN leave_types lt ON vr.leave_type_id = lt.id
        WHERE vr.status = 'approved'
          AND (
                (YEAR(vr.start_date) = ? AND MONTH(vr.start_date) = ?)
                OR (YEAR(vr.end_date) = ? AND MONTH(vr.end_date) = ?)
                OR (vr.start_date < ? AND vr.end_date > ?)
              )
        ORDER BY vr.start_date ASC
    ");
    $first_day_of_month = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $last_day_of_month = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime($first_day_of_month));
    $stmt->execute([$year, $month, $year, $month, $first_day_of_month, $last_day_of_month]);
    $raw_leave_requests = $stmt->fetchAll();

    $leave_days = [];
    foreach ($raw_leave_requests as $request) {
        $current_date = new DateTime($request['start_date']);
        $end_date = new DateTime($request['end_date']);
        while ($current_date <= $end_date) {
            $leave_days[] = [
                'date' => $current_date->format('Y-m-d'),
                'employee' => $request['employee'],
                'type' => $request['leave_type']
            ];
            $current_date->modify('+1 day');
        }
    }

    echo json_encode([
        'success' => true,
        'leaveDays' => $leave_days
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>