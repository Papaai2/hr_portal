<?php
// file: admin/shifts.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';
require_once __DIR__ . '/../app/core/helpers.php';

// Ensure user is authorized to manage shifts
require_role(['admin', 'hr_manager']);

$page_title = 'Shift Management';
include __DIR__ . '/../app/templates/header.php';

$feedback = [
    'success' => '',
    'error' => ''
];

// Handle form submissions for adding/editing shifts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shift_name = sanitize_input($_POST['shift_name'] ?? '');
    $start_time = sanitize_input($_POST['start_time'] ?? '');
    $end_time = sanitize_input($_POST['end_time'] ?? '');
    $grace_period_in = intval($_POST['grace_period_in'] ?? 0);
    $grace_period_out = intval($_POST['grace_period_out'] ?? 0);
    $break_start_time = !empty($_POST['break_start_time']) ? sanitize_input($_POST['break_start_time']) : null;
    $break_end_time = !empty($_POST['break_end_time']) ? sanitize_input($_POST['break_end_time']) : null;
    $is_night_shift = isset($_POST['is_night_shift']) ? 1 : 0;
    $shift_id = intval($_POST['shift_id'] ?? 0); // For editing

    // Basic validation
    if (empty($shift_name) || empty($start_time) || empty($end_time)) {
        $feedback['error'] = 'Shift name, start time, and end time are required.';
    } elseif ($start_time >= $end_time && $is_night_shift == 0) {
        $feedback['error'] = 'End time must be after start time for non-night shifts.';
    } else {
        if ($shift_id) { // Edit existing shift
            $sql = "UPDATE shifts SET shift_name = ?, start_time = ?, end_time = ?, grace_period_in = ?, grace_period_out = ?, break_start_time = ?, break_end_time = ?, is_night_shift = ? WHERE id = ?";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$shift_name, $start_time, $end_time, $grace_period_in, $grace_period_out, $break_start_time, $break_end_time, $is_night_shift, $shift_id]);
                $feedback['success'] = 'Shift updated successfully.';
                log_audit_action($pdo, 'update_shift', json_encode(['shift_id' => $shift_id, 'name' => $shift_name]), get_current_user_id());
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate entry error code
                    $feedback['error'] = 'Shift name already exists. Please choose a different name.';
                } else {
                    $feedback['error'] = 'Error updating shift: ' . $e->getMessage();
                }
                error_log("Error updating shift: " . $e->getMessage());
            }
        } else { // Add new shift
            $sql = "INSERT INTO shifts (shift_name, start_time, end_time, grace_period_in, grace_period_out, break_start_time, break_end_time, is_night_shift) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$shift_name, $start_time, $end_time, $grace_period_in, $grace_period_out, $break_start_time, $break_end_time, $is_night_shift]);
                $feedback['success'] = 'Shift added successfully.';
                log_audit_action($pdo, 'add_shift', json_encode(['name' => $shift_name]), get_current_user_id());
            } catch (PDOException $e) {
                 if ($e->getCode() == 23000) { // Duplicate entry error code
                    $feedback['error'] = 'Shift name already exists. Please choose a different name.';
                } else {
                    $feedback['error'] = 'Error adding shift: ' . $e->getMessage();
                }
                error_log("Error adding shift: " . $e->getMessage());
            }
        }
    }
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $shift_id_to_delete = intval($_GET['id']);
    if ($shift_id_to_delete > 0) {
        $sql = "DELETE FROM shifts WHERE id = ?";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$shift_id_to_delete]);
            // Also nullify shift_id for users assigned to this shift
            $sql_update_users = "UPDATE users SET shift_id = NULL WHERE shift_id = ?";
            $stmt_update_users = $pdo->prepare($sql_update_users);
            $stmt_update_users->execute([$shift_id_to_delete]);

            $feedback['success'] = 'Shift deleted successfully and unassigned from users.';
            log_audit_action($pdo, 'delete_shift', json_encode(['shift_id' => $shift_id_to_delete]), get_current_user_id());
        } catch (PDOException $e) {
            $feedback['error'] = 'Error deleting shift: ' . $e->getMessage();
            error_log("Error deleting shift: " . $e->getMessage());
        }
    }
}

// Fetch all shifts
$shifts = [];
try {
    $stmt = $pdo->query("SELECT * FROM shifts ORDER BY shift_name");
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback['error'] = 'Error fetching shifts: ' . $e->getMessage();
    error_log("Error fetching shifts: " . $e->getMessage());
}

?>

<div class="container mt-4">
    <h1 class="h3 mb-4">Shift Management</h1>

    <?php if ($feedback['success']): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($feedback['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($feedback['error']): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($feedback['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h4><?= (isset($_GET['edit']) && intval($_GET['edit']) > 0) ? 'Edit Shift' : 'Add New Shift' ?></h4>
        </div>
        <div class="card-body">
            <?php
            $edit_shift = null;
            if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
                $edit_id = intval($_GET['edit']);
                $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ?");
                $stmt->execute([$edit_id]);
                $edit_shift = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$edit_shift) {
                    $feedback['error'] = 'Shift not found for editing.';
                }
            }
            ?>
            <form method="POST" action="shifts.php">
                <input type="hidden" name="shift_id" value="<?= htmlspecialchars($edit_shift['id'] ?? '') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="shift_name" class="form-label">Shift Name</label>
                        <input type="text" class="form-control" id="shift_name" name="shift_name" value="<?= htmlspecialchars($edit_shift['shift_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="start_time" class="form-label">Start Time</label>
                        <input type="time" class="form-control" id="start_time" name="start_time" value="<?= htmlspecialchars($edit_shift['start_time'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="end_time" class="form-label">End Time</label>
                        <input type="time" class="form-control" id="end_time" name="end_time" value="<?= htmlspecialchars($edit_shift['end_time'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="grace_period_in" class="form-label">Grace Period (In, min)</label>
                        <input type="number" class="form-control" id="grace_period_in" name="grace_period_in" value="<?= htmlspecialchars($edit_shift['grace_period_in'] ?? 0) ?>" min="0">
                    </div>
                    <div class="col-md-3">
                        <label for="grace_period_out" class="form-label">Grace Period (Out, min)</label>
                        <input type="number" class="form-control" id="grace_period_out" name="grace_period_out" value="<?= htmlspecialchars($edit_shift['grace_period_out'] ?? 0) ?>" min="0">
                    </div>
                    <div class="col-md-3">
                        <label for="break_start_time" class="form-label">Break Start Time (Optional)</label>
                        <input type="time" class="form-control" id="break_start_time" name="break_start_time" value="<?= htmlspecialchars($edit_shift['break_start_time'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="break_end_time" class="form-label">Break End Time (Optional)</label>
                        <input type="time" class="form-control" id="break_end_time" name="break_end_time" value="<?= htmlspecialchars($edit_shift['break_end_time'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_night_shift" name="is_night_shift" <?= ($edit_shift['is_night_shift'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_night_shift">
                                This is a night shift (crosses midnight)
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?= (isset($_GET['edit']) && intval($_GET['edit']) > 0) ? 'Update Shift' : 'Add Shift' ?></button>
                        <?php if (isset($_GET['edit']) && intval($_GET['edit']) > 0): ?>
                            <a href="shifts.php" class="btn btn-secondary">Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <h2 class="h4 mb-3">Existing Shifts</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Grace In</th>
                    <th>Grace Out</th>
                    <th>Break</th>
                    <th>Night Shift</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shifts)): ?>
                    <tr><td colspan="9" class="text-center text-muted">No shifts defined yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><?= htmlspecialchars($shift['id']) ?></td>
                            <td><?= htmlspecialchars($shift['shift_name']) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($shift['start_time']))) ?></td>
                            <td><?= htmlspecialchars(date('h:i A', strtotime($shift['end_time']))) ?></td>
                            <td><?= htmlspecialchars($shift['grace_period_in']) ?> min</td>
                            <td><?= htmlspecialchars($shift['grace_period_out']) ?> min</td>
                            <td>
                                <?php if (!empty($shift['break_start_time']) && !empty($shift['break_end_time'])): ?>
                                    <?= htmlspecialchars(date('h:i A', strtotime($shift['break_start_time']))) ?> - <?= htmlspecialchars(date('h:i A', strtotime($shift['break_end_time']))) ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?= $shift['is_night_shift'] ? 'Yes' : 'No' ?></td>
                            <td>
                                <a href="?edit=<?= htmlspecialchars($shift['id']) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                                <a href="?action=delete&id=<?= htmlspecialchars($shift['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this shift? All users assigned to this shift will have their shift unassigned.');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>