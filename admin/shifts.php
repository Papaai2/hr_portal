<?php
// file: admin/shifts.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php';

require_role(['admin', 'hr_manager']);

$page_title = 'Shift Management';
$feedback = ['success' => '', 'error' => ''];

// Handle form submissions for adding/editing shifts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shift_id = intval($_POST['shift_id'] ?? 0);
    $shift_name = sanitize_input($_POST['shift_name'] ?? '');
    $start_time = sanitize_input($_POST['start_time'] ?? '');
    $end_time = sanitize_input($_POST['end_time'] ?? '');
    $grace_in_minutes = filter_input(INPUT_POST, 'grace_in_minutes', FILTER_VALIDATE_INT) ?? 0;
    $grace_out_minutes = filter_input(INPUT_POST, 'grace_out_minutes', FILTER_VALIDATE_INT) ?? 0;
    $is_night_shift = isset($_POST['is_night_shift']) ? 1 : 0;

    if (empty($shift_name) || empty($start_time) || empty($end_time)) {
        $feedback['error'] = 'Shift name, start time, and end time are required.';
    } else {
        try {
            if ($shift_id) { // Edit existing shift
                $sql = "UPDATE shifts SET shift_name = ?, start_time = ?, end_time = ?, is_night_shift = ?, grace_in_minutes = ?, grace_out_minutes = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$shift_name, $start_time, $end_time, $is_night_shift, $grace_in_minutes, $grace_out_minutes, $shift_id]);
                $feedback['success'] = 'Shift updated successfully.';
                log_audit_action($pdo, 'update_shift', "Updated shift '{$shift_name}' (ID: {$shift_id}).");
            } else { // Add new shift
                $sql = "INSERT INTO shifts (shift_name, start_time, end_time, is_night_shift, grace_in_minutes, grace_out_minutes) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$shift_name, $start_time, $end_time, $is_night_shift, $grace_in_minutes, $grace_out_minutes]);
                $new_shift_id = $pdo->lastInsertId();
                $feedback['success'] = 'Shift added successfully.';
                log_audit_action($pdo, 'add_shift', "Created new shift '{$shift_name}' (ID: {$new_shift_id}).");
            }
        } catch (PDOException $e) {
            $feedback['error'] = 'Database Error: ' . $e->getMessage();
            error_log("Error saving shift: " . $e->getMessage());
        }
    }
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $shift_id_to_delete = intval($_GET['id']);
    if ($shift_id_to_delete > 0) {
        $stmt_name = $pdo->prepare("SELECT shift_name FROM shifts WHERE id = ?");
        $stmt_name->execute([$shift_id_to_delete]);
        $shift_name_deleted = $stmt_name->fetchColumn();
        
        try {
            $pdo->prepare("UPDATE users SET shift_id = NULL WHERE shift_id = ?")->execute([$shift_id_to_delete]);
            $pdo->prepare("DELETE FROM shifts WHERE id = ?")->execute([$shift_id_to_delete]);
            $feedback['success'] = 'Shift deleted successfully and unassigned from users.';
            log_audit_action($pdo, 'delete_shift', "Deleted shift '{$shift_name_deleted}' (ID: {$shift_id_to_delete}).");
        } catch (PDOException $e) {
            $feedback['error'] = 'Error deleting shift: ' . $e->getMessage();
            error_log("Error deleting shift: " . $e->getMessage());
        }
    }
}

// Fetch all shifts
$shifts = $pdo->query("SELECT * FROM shifts ORDER BY shift_name")->fetchAll(PDO::FETCH_ASSOC);

$editing_shift = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ?");
    $stmt->execute([intval($_GET['id'])]);
    $editing_shift = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../app/templates/header.php';
?>

<div class="container mt-4">
    <h1 class="h3 mb-4">Shift Management</h1>

    <?php if ($feedback['success']): ?><div class="alert alert-success"><?= htmlspecialchars($feedback['success']) ?></div><?php endif; ?>
    <?php if ($feedback['error']): ?><div class="alert alert-danger"><?= htmlspecialchars($feedback['error']) ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h4><?= $editing_shift ? 'Edit Shift' : 'Add New Shift' ?></h4>
        </div>
        <div class="card-body">
            <form method="POST" action="shifts.php">
                <input type="hidden" name="shift_id" value="<?= htmlspecialchars($editing_shift['id'] ?? '') ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="shift_name" class="form-label">Shift Name</label>
                        <input type="text" class="form-control" id="shift_name" name="shift_name" value="<?= htmlspecialchars($editing_shift['shift_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="start_time" class="form-label">Start Time</label>
                        <input type="time" class="form-control" id="start_time" name="start_time" value="<?= htmlspecialchars($editing_shift['start_time'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="end_time" class="form-label">End Time</label>
                        <input type="time" class="form-control" id="end_time" name="end_time" value="<?= htmlspecialchars($editing_shift['end_time'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="grace_in_minutes" class="form-label">Grace In (mins)</label>
                        <input type="number" class="form-control" id="grace_in_minutes" name="grace_in_minutes" value="<?= htmlspecialchars($editing_shift['grace_in_minutes'] ?? '0') ?>" required>
                    </div>
                     <div class="col-md-2">
                        <label for="grace_out_minutes" class="form-label">Grace Out (mins)</label>
                        <input type="number" class="form-control" id="grace_out_minutes" name="grace_out_minutes" value="<?= htmlspecialchars($editing_shift['grace_out_minutes'] ?? '0') ?>" required>
                    </div>
                    <div class="col-md-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_night_shift" name="is_night_shift" <?= ($editing_shift['is_night_shift'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_night_shift">Night Shift</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><?= $editing_shift ? 'Update Shift' : 'Add Shift' ?></button>
                    <?php if ($editing_shift): ?>
                        <a href="shifts.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <h2 class="h4 mb-3">Existing Shifts</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Time Range</th>
                    <th>Grace In (mins)</th>
                    <th>Grace Out (mins)</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shifts as $shift): ?>
                    <tr>
                        <td><?= htmlspecialchars($shift['shift_name']) ?></td>
                        <td><?= htmlspecialchars(date('h:i A', strtotime($shift['start_time']))) ?> - <?= htmlspecialchars(date('h:i A', strtotime($shift['end_time']))) ?></td>
                        <td><?= htmlspecialchars($shift['grace_in_minutes']) ?></td>
                        <td><?= htmlspecialchars($shift['grace_out_minutes']) ?></td>
                        <td><?= $shift['is_night_shift'] ? '<span class="badge bg-dark">Night</span>' : '<span class="badge bg-light text-dark">Day</span>' ?></td>
                        <td>
                            <a href="?action=edit&id=<?= htmlspecialchars($shift['id']) ?>" class="btn btn-sm btn-info">Edit</a>
                            <a href="?action=delete&id=<?= htmlspecialchars($shift['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>