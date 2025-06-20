<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

$page_title = "Leave Management";
$success_message = '';
$error_message = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';

    $pdo->beginTransaction();
    try {
        // Fetch the vacation request details
        $stmt = $pdo->prepare("SELECT * FROM vacation_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        if ($request && in_array($request['status'], ['pending_manager', 'pending_hr'])) {
            // Update the request status
            $stmt = $pdo->prepare("UPDATE vacation_requests SET status = ?, hr_id = ?, hr_action_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $_SESSION['user_id'], $requestId]);

            // If approved, deduct the duration from the user's balance
            if ($newStatus === 'approved') {
                $startDate = new DateTime($request['start_date']);
                $endDate = new DateTime($request['end_date']);
                $duration = $endDate->diff($startDate)->days + 1; // Simple duration calculation

                // **FIXED**: Update the separate leave_balances table
                $stmt = $pdo->prepare(
                    "UPDATE leave_balances 
                     SET balance_days = balance_days - ? 
                     WHERE user_id = ? AND leave_type_id = ? AND balance_days >= ?"
                );
                $stmt->execute([$duration, $request['user_id'], $request['leave_type_id'], $duration]);

                if ($stmt->rowCount() == 0) {
                     throw new Exception("Failed to update user balance: Insufficient balance or balance record not found.");
                }
            }
            
            $pdo->commit();
            $success_message = "Request has been successfully {$newStatus}.";
        } else {
            throw new Exception("Request not found or already actioned.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Failed to update request: " . $e->getMessage();
    }
}


// Fetch pending leave requests
$stmt = $pdo->prepare(
    "SELECT vr.*, u.full_name, lt.name as leave_type_name
     FROM vacation_requests vr
     JOIN users u ON vr.user_id = u.id
     JOIN leave_types lt ON vr.leave_type_id = lt.id
     WHERE vr.status IN ('pending_manager', 'pending_hr')
     ORDER BY vr.created_at ASC"
);
$stmt->execute();
$pending_requests = $stmt->fetchAll();

include __DIR__ . '/../app/templates/header.php';
?>

<h1 class="h3 mb-3"><?php echo htmlspecialchars($page_title); ?></h1>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>


<div class="card">
    <div class="card-header">
        <h5 class="card-title">Pending Leave Requests</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_requests)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No pending requests.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['full_name']); ?></td>
                                <td><?= htmlspecialchars($request['leave_type_name']); ?></td>
                                <td><?= htmlspecialchars($request['start_date']); ?> to <?= htmlspecialchars($request['end_date']); ?></td>
                                <td><?= htmlspecialchars($request['reason']); ?></td>
                                <td><span class="badge bg-warning"><?= htmlspecialchars(str_replace('_', ' ', $request['status'])) ?></span></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?= $request['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                        <button type="submit" name="action" value="deny" class="btn btn-sm btn-danger">Deny</button>
                                    </form>
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