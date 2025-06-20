<?php
// in file: htdocs/admin/index.php

require_once __DIR__ . '/../app/bootstrap.php';

// Only admins and HR Managers can access this page and any other page in the /admin/ folder
require_role(['admin', 'hr_manager']);

$page_title = 'Admin Dashboard';
include __DIR__ . '/../app/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Admin Dashboard</h1>
</div>

<p class="text-muted">From here you can manage the core components of the HR Portal.</p>

<div class="row g-4 mt-3">
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <i class="bi bi-people-fill text-primary" style="font-size: 3rem;"></i>
                <h2 class="card-title h4 mt-3">Manage Users</h2>
                <p class="card-text text-muted">Create, edit, and assign roles to user accounts.</p>
                <a href="users.php" class="btn btn-primary mt-auto">Go to Users</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <i class="bi bi-calendar-range text-info" style="font-size: 3rem;"></i>
                <h2 class="card-title h4 mt-3">Manage Leave</h2>
                <p class="card-text text-muted">Define leave types and adjust employee leave balances.</p>
                <a href="leave_management.php" class="btn btn-info mt-auto">Go to Leave Management</a>
            </div>
        </div>
    </div>

    <?php if ($_SESSION['role'] === 'admin'): // Only show these sections for the main 'admin' role ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <i class="bi bi-building text-success" style="font-size: 3rem;"></i>
                <h2 class="card-title h4 mt-3">Manage Departments</h2>
                <p class="card-text text-muted">Add new departments and organize your company structure.</p>
                <a href="departments.php" class="btn btn-success mt-auto">Go to Departments</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <i class="bi bi-fingerprint text-warning" style="font-size: 3rem;"></i>
                <h2 class="card-title h4 mt-3">Manage Devices</h2>
                <p class="card-text text-muted">Configure and manage physical attendance devices.</p>
                <a href="devices.php" class="btn btn-warning mt-auto text-dark">Go to Devices</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <i class="bi bi-card-checklist text-secondary" style="font-size: 3rem;"></i>
                <h2 class="card-title h4 mt-3">Attendance Logs</h2>
                <p class="card-text text-muted">View raw punch data received from all devices.</p>
                <a href="attendance_logs.php" class="btn btn-secondary mt-auto">View Logs</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../app/templates/footer.php';
?>