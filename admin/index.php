<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

$page_title = "Admin Panel";

// --- Fetch counts for dashboard cards ---

// Count active users
$users_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
$user_count = $users_stmt->fetchColumn();

// Count registered devices
$devices_stmt = $pdo->query("SELECT COUNT(*) FROM devices");
$device_count = $devices_stmt->fetchColumn();

// **FIXED**: Changed table to 'vacation_requests' and statuses to match the schema
$leave_stmt = $pdo->query("SELECT COUNT(*) FROM vacation_requests WHERE status IN ('pending_manager', 'pending_hr')");
$pending_leave_count = $leave_stmt->fetchColumn();

// Count pending attendance violations
$violations_stmt = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE status = 'error'");
$violation_count = $violations_stmt->fetchColumn();


include __DIR__ . '/../app/templates/header.php';
?>

<h1 class="h3 mb-4"><?php echo htmlspecialchars($page_title); ?></h1>

<div class="row">
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Users</h5>
                        <p class="card-text">Manage employee accounts and roles.</p>
                    </div>
                    <i class="bi bi-people-fill fs-1 text-primary"></i>
                </div>
                 <h2 class="mt-3 mb-0 fw-bold"><?= $user_count ?></h2>
                 <small>Active Users</small>
            </div>
            <div class="card-footer bg-white border-top-0">
                 <a href="users.php" class="stretched-link">Go to User Management</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Devices</h5>
                        <p class="card-text">Manage fingerprint attendance devices.</p>
                    </div>
                    <i class="bi bi-fingerprint fs-1 text-info"></i>
                </div>
                 <h2 class="mt-3 mb-0 fw-bold"><?= $device_count ?></h2>
                 <small>Registered Devices</small>
            </div>
             <div class="card-footer bg-white border-top-0">
                 <a href="devices.php" class="stretched-link">Go to Device Management</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Leave Requests</h5>
                        <p class="card-text">Approve or deny employee leave.</p>
                    </div>
                    <i class="bi bi-calendar-check fs-1 text-success"></i>
                </div>
                 <h2 class="mt-3 mb-0 fw-bold"><?= $pending_leave_count ?></h2>
                 <small>Pending Requests</small>
            </div>
             <div class="card-footer bg-white border-top-0">
                 <a href="leave_management.php" class="stretched-link">Go to Leave Management</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 border-danger">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title text-danger">Attendance Violations</h5>
                        <p class="card-text">Resolve flagged punch records.</p>
                    </div>
                    <i class="bi bi-exclamation-triangle-fill fs-1 text-danger"></i>
                </div>
                 <h2 class="mt-3 mb-0 fw-bold">
                    <?= $violation_count ?>
                 </h2>
                 <small>Pending Issues</small>
            </div>
             <div class="card-footer bg-white border-top-0">
                 <a href="attendance_violations.php" class="stretched-link">Go to Violation Center</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4 mb-4">
         <div class="card h-100">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Attendance Logs</h5>
                        <p class="card-text">View the complete attendance log.</p>
                    </div>
                    <i class="bi bi-list-check fs-1 text-secondary"></i>
                </div>
            </div>
             <div class="card-footer bg-white border-top-0">
                 <a href="attendance_logs.php" class="stretched-link">View Logs</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-4 mb-4">
         <div class="card h-100">
            <div class="card-body">
                 <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Audit Logs</h5>
                        <p class="card-text">Track all administrative actions.</p>
                    </div>
                    <i class="bi bi-journal-text fs-1 text-dark"></i>
                </div>
            </div>
             <div class="card-footer bg-white border-top-0">
                 <a href="audit_logs.php" class="stretched-link">View Audit Logs</a>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../app/templates/footer.php'; ?>