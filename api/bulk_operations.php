<?php
// in file: htdocs/api/bulk_operations.php
header('Content-Type: application/json');

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_role(['admin', 'hr_manager']); // Only Admins and HR Managers can perform bulk operations

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$bulk_action = $input['action'] ?? '';
$user_ids_to_adjust = $input['user_ids'] ?? [];
$leave_type_id_to_adjust = $input['leave_type_id'] ?? null;
$new_balance = filter_var($input['new_balance'] ?? null, FILTER_VALIDATE_FLOAT);

try {
    $pdo->beginTransaction();

    if ($bulk_action === 'reset_all_balances') {
        $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $leave_types = $pdo->query("SELECT id FROM leave_types WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);

        $stmt_upsert = $pdo->prepare("
            INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_updated_at, last_accrual_date)
            VALUES (?, ?, 0, NOW(), CURDATE())
            ON DUPLICATE KEY UPDATE balance_days = 0, last_updated_at = NOW()" );

        foreach ($users as $user_id_item) {
            foreach ($leave_types as $leave_type_id_item) {
                $stmt_upsert->execute([$user_id_item, $leave_type_id_item]);
            }
        }
        $message = 'All active users\' leave balances have been reset to 0.';

    } elseif ($bulk_action === 'perform_annual_accrual') {
        $stmt_accrual_types = $pdo->query("SELECT id, accrual_days_per_year FROM leave_types WHERE is_active = 1 AND accrual_days_per_year > 0")->fetchAll();

        $stmt_update_accrual = $pdo->prepare("
            INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_accrual_date)
            VALUES (?, ?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE balance_days = balance_days + VALUES(balance_days), last_accrual_date = CURDATE()
        ");

        $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($users as $user_id_accrual) {
            foreach ($stmt_accrual_types as $accrual_type) {
                 $stmt_update_accrual->execute([$user_id_accrual, $accrual_type['id'], $accrual_type['accrual_days_per_year']]);
            }
        }
        $message = 'Annual leave accrual performed for all active users.';
    } elseif ($bulk_action === 'adjust_selected_balances') {
        if (empty($user_ids_to_adjust) || empty($leave_type_id_to_adjust) || $new_balance === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You must select users, a leave type, and provide a valid new balance value.']);
            $pdo->rollBack();
            exit();
        }

        $stmt_check = $pdo->prepare("SELECT id FROM leave_balances WHERE user_id = ? AND leave_type_id = ?");
        $stmt_update = $pdo->prepare("UPDATE leave_balances SET balance_days = ?, last_updated_at = NOW() WHERE id = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO leave_balances (user_id, leave_type_id, balance_days, last_accrual_date) VALUES (?, ?, ?, CURDATE())");

        foreach ($user_ids_to_adjust as $user_id) {
            $stmt_check->execute([$user_id, $leave_type_id_to_adjust]);
            $existing_balance_entry = $stmt_check->fetch();

            if ($existing_balance_entry) {
                $stmt_update->execute([$new_balance, $existing_balance_entry['id']]);
            } else {
                $stmt_insert->execute([$user_id, $leave_type_id_to_adjust, $new_balance]);
            }
        }
        $message = 'Selected users\' leave balances updated successfully.';
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid bulk action.']);
        $pdo->rollBack();
        exit();
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>