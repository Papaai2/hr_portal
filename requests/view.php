<?php
// in file: htdocs/requests/view.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php';

require_login();

$request_id = $_GET['id'] ?? null;
if (!$request_id) { header('Location: /index.php'); exit(); }

$current_user_id = get_current_user_id();
$current_user_role = get_current_user_role();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $stmt = $pdo->prepare("SELECT r.*, u.full_name, m.full_name as manager_name FROM vacation_requests r JOIN users u ON r.user_id = u.id LEFT JOIN users m ON r.manager_id = m.id WHERE r.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if ($request) {
        $can_manage = ($current_user_id == $request['manager_id']);
        $can_hr_approve = in_array($current_user_role, ['hr', 'admin', 'hr_manager']);

        try {
            if ($action == 'approve_manager' && $can_manage && $request['status'] == 'pending_manager') {
                $sql = "UPDATE vacation_requests SET status = 'pending_hr', manager_action_at = NOW() WHERE id = ?";
                $pdo->prepare($sql)->execute([$request_id]);
                create_notification($pdo, $request['user_id'], "Your request was approved by your manager and sent to HR.", $request_id);
                $hr_users = $pdo->query("SELECT id FROM users WHERE role IN ('hr', 'admin', 'hr_manager')")->fetchAll();
                foreach ($hr_users as $hr_user) {
                    create_notification($pdo, $hr_user['id'], "A request from {$request['full_name']} requires final approval.", $request_id);
                }
                $success = 'Request approved and forwarded to HR.';

            } elseif ($action == 'approve_hr' && $can_hr_approve && $request['status'] == 'pending_hr') {
                $pdo->beginTransaction(); 

                $sql = "UPDATE vacation_requests SET status = 'approved', hr_id = ?, hr_action_at = NOW() WHERE id = ?";
                $pdo->prepare($sql)->execute([$current_user_id, $request_id]);
                
                $start = new DateTime($request['start_date']);
                $end = new DateTime($request['end_date']);
                $days_requested = $start->diff($end)->days + 1;

                $update_balance_sql = "UPDATE leave_balances SET balance_days = balance_days - ?, last_updated_at = NOW() WHERE user_id = ? AND leave_type_id = ?";
                $stmt_deduct = $pdo->prepare($update_balance_sql);
                $stmt_deduct->execute([$days_requested, $request['user_id'], $request['leave_type_id']]);

                create_notification($pdo, $request['user_id'], "Your vacation request has received final approval.", $request_id);
                $success = 'Request has been given final approval.';
                
                $pdo->commit();
            } elseif ($action == 'reject') {
                $rejection_reason = trim($_POST['rejection_reason']);
                if (empty($rejection_reason)) {
                    $error = 'A reason is required to reject a request.';
                } else {
                    if (($can_manage && $request['status'] == 'pending_manager') || ($can_hr_approve && $request['status'] == 'pending_hr')) {
                        $sql = "UPDATE vacation_requests SET status = 'rejected', rejection_reason = ?, hr_id = ?, manager_action_at = NOW(), hr_action_at = NOW() WHERE id = ?";
                        $pdo->prepare($sql)->execute([$rejection_reason, $current_user_id, $request_id]);
                        
                        create_notification($pdo, $request['user_id'], "Your vacation request has been rejected.", $request_id);

                        $hr_users = $pdo->query("SELECT id FROM users WHERE role IN ('hr', 'admin', 'hr_manager')")->fetchAll();
                        $rejecter_name = ($request['status'] == 'pending_manager') ? $request['manager_name'] : $_SESSION['full_name'];
                        foreach ($hr_users as $hr_user) {
                            create_notification($pdo, $hr_user['id'], "A request from {$request['full_name']} was rejected by {$rejecter_name}.", $request_id);
                        }
                        
                        $success = 'Request has been rejected.';
                    } else {
                        $error = 'Permission denied to reject this request.';
                    }
                }
            } elseif ($action == 'add_comment') {
                $comment = trim($_POST['comment']);
                if (!empty($comment)) {
                    $sql = "INSERT INTO request_comments (request_id, user_id, comment) VALUES (?, ?, ?)";
                    $pdo->prepare($sql)->execute([$request_id, $current_user_id, $comment]);
                    $commenter_name = $_SESSION['full_name'];
                    if ($current_user_id == $request['user_id']) {
                        create_notification($pdo, $request['manager_id'], "$commenter_name commented on a request.", $request_id);
                    } else {
                        create_notification($pdo, $request['user_id'], "$commenter_name commented on your request.", $request_id);
                    }
                    $success = 'Comment added.';
                }
            } else {
                 $error = "Invalid action or permission denied.";
            }

            if ($success && !$error) {
                header("Location: view.php?id=$request_id&success=" . urlencode($success));
                exit();
            }

        } catch (PDOException $e) { 
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage(); 
        }
    }
}

$stmt = $pdo->prepare("
    SELECT r.*, u.full_name, u.email, d.name AS department_name, lt.name AS leave_type_name
    FROM vacation_requests r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN leave_types lt ON r.leave_type_id = lt.id
    WHERE r.id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) { http_response_code(404); exit("Request not found."); }

$is_owner = ($current_user_id == $request['user_id']);
$is_manager = ($current_user_id == $request['manager_id']);
$is_hr_or_admin = in_array($current_user_role, ['hr', 'admin', 'hr_manager']);

if (!$is_owner && !$is_manager && !$is_hr_or_admin) { http_response_code(403); exit("Access Denied."); }

$attachments = $pdo->prepare("SELECT * FROM request_attachments WHERE request_id = ?");
$attachments->execute([$request_id]);
$attachments = $attachments->fetchAll();

$comments = $pdo->prepare("
    SELECT c.*, u.full_name, u.role 
    FROM request_comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.request_id = ? ORDER BY c.created_at ASC
");
$comments->execute([$request_id]);
$comments = $comments->fetchAll();

// The getStatusBadgeClass() and getStatusText() functions were removed from here.

if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);

$page_title = 'View Request Details';
include __DIR__ . '/../app/templates/header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0">Request Details</h1>
                <span class="badge rounded-pill fs-6 <?= getStatusBadgeClass($request['status']) ?>"><?= getStatusText($request['status']) ?></span>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                <dl class="row">
                    <dt class="col-sm-4">Employee:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($request['full_name']); ?></dd>
                    <dt class="col-sm-4">Department:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($request['department_name'] ?? 'N/A'); ?></dd>
                    <dt class="col-sm-4">Leave Type:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($request['leave_type_name'] ?? 'N/A'); ?></dd>
                    <dt class="col-sm-4">Dates:</dt><dd class="col-sm-8"><?php echo date('F j, Y', strtotime($request['start_date'])); ?> to <?php echo date('F j, Y', strtotime($request['end_date'])); ?></dd>
                    <dt class="col-sm-4">Reason:</dt><dd class="col-sm-8"><p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p></dd>
                    <?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
                        <dt class="col-sm-4 text-danger">Rejection Reason:</dt><dd class="col-sm-8 text-danger"><?php echo htmlspecialchars($request['rejection_reason']); ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($attachments)): ?>
                        <dt class="col-sm-4">Attachments:</dt>
                        <dd class="col-sm-8"><ul class="list-unstyled mb-0">
                            <?php foreach ($attachments as $file): ?>
                                <li><a href="/download.php?id=<?php echo $file['id']; ?>"><i class="bi bi-paperclip"></i> <?php echo htmlspecialchars($file['file_name']); ?></a></li>
                            <?php endforeach; ?></ul></dd>
                    <?php endif; ?>
                </dl>
                <div class="mt-4 pt-3 border-top">
                    <?php if ($is_manager && $request['status'] == 'pending_manager'): ?>
                    <div class="alert alert-info"><strong>Action Required:</strong> Please review and act on this request.<div class="mt-2">
                             <form action="" method="post" class="d-inline"><input type="hidden" name="action" value="approve_manager"><button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Approve & Send to HR</button></form>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-lg me-1"></i>Reject</button></div></div>
                    <?php endif; ?>
                    <?php if ($is_hr_or_admin && $request['status'] == 'pending_hr'): ?>
                    <div class="alert alert-info"><strong>Final Approval Required:</strong> This request has been approved by the manager.<div class="mt-2">
                            <form action="" method="post" class="d-inline"><input type="hidden" name="action" value="approve_hr"><button type="submit" class="btn btn-success"><i class="bi bi-check-circle-fill me-1"></i>Final Approve</button></form>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-lg me-1"></i>Reject</button></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header"><h2 class="h5 mb-0"><i class="bi bi-chat-dots-fill me-2"></i>Communication Log</h2></div>
            <div class="card-body">
                <div class="mb-3" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($comments)): ?>
                        <p class="text-muted">No comments yet.</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0"><i class="bi bi-person-circle fs-3 text-muted"></i></div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($comment['full_name']); ?> <small class="text-muted">(<?= ucfirst($comment['role']) ?>)</small></div>
                                    <p class="mb-1 bg-light p-2 rounded"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    <small class="text-muted"><?php echo date('M j, Y, g:i a', strtotime($comment['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div><hr>
                <form action="" method="post">
                    <input type="hidden" name="action" value="add_comment">
                    <div class="mb-3">
                        <label for="comment" class="form-label fw-bold">Add a Comment</label>
                        <textarea class="form-control" name="comment" rows="3" placeholder="Type your message here..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-send me-1"></i>Post Comment</button>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form action="" method="post"><div class="modal-header"><h5 class="modal-title" id="rejectModalLabel">Reject Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="action" value="reject"><div class="mb-3"><label for="rejection_reason" class="form-label"><strong>Reason for Rejection (Required)</strong></label><textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Confirm Rejection</button></div></form></div></div></div>
<?php include __DIR__ . '/../app/templates/footer.php'; ?>
