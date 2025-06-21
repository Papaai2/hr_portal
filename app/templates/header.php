<?php
// in file: app/templates/header.php
require_once __DIR__ . '/../core/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME) : htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light" data-user-role="<?= htmlspecialchars($_SESSION['role'] ?? 'user') ?>">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/"><?php echo htmlspecialchars(SITE_NAME); ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav" aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="main-nav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="/index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/requests/index.php">My Requests</a></li>
                    
                    <?php if (in_array($_SESSION['role'], ['manager', 'admin', 'hr_manager'])): ?>
                        <li class="nav-item dropdown">
                          <a class="nav-link dropdown-toggle" href="#" id="manager-reports-dropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Team Reports
                          </a>
                          <ul class="dropdown-menu" aria-labelledby="manager-reports-dropdown">
                            <li><a class="dropdown-item" href="/reports/team.php">My Team</a></li>
                            <li><a class="dropdown-item" href="/reports/manager_history.php">Team History</a></li>
                          </ul>
                        </li>
                    <?php endif; ?>

                    <?php if (in_array($_SESSION['role'], ['hr', 'hr_manager', 'admin'])): ?>
                         <li class="nav-item dropdown">
                          <a class="nav-link dropdown-toggle" href="#" id="hr-reports-dropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            HR Reports
                          </a>
                          <ul class="dropdown-menu" aria-labelledby="hr-reports-dropdown">
                            <li><a class="dropdown-item" href="/reports/hr_history.php">Full History</a></li>
                            <li><a class="dropdown-item" href="/reports/user_balances.php">User Balances</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/reports/timesheet.php">Daily Timesheet</a></li>
                          </ul>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['role'], ['hr_manager', 'admin'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="admin-dropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Admin Panel
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="admin-dropdown">
                                <li><a class="dropdown-item" href="/admin/index.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="/admin/users.php">Users</a></li>
                                <li><a class="dropdown-item" href="/admin/departments.php">Departments</a></li>
                                <li><a class="dropdown-item" href="/admin/devices.php">Devices</a></li>
                                <li><a class="dropdown-item" href="/admin/leave_management.php">Leave Management</a></li>
                                <li><a class="dropdown-item" href="/admin/attendance_violations.php">Attendance Violations</a></li>
                                <li><a class="dropdown-item" href="/admin/attendance_logs.php">Attendance Logs</a></li>
                                <li><a class="dropdown-item" href="/admin/shifts.php">Shift Management</a></li> </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="notification-bell" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill position-relative fs-5">
                                <span id="notification-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none; font-size: 0.6em;"></span>
                            </i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="notification-bell" id="notification-dropdown">
                             <li><h6 class="dropdown-header">Notifications</h6></li>
                             <li><div id="notification-list"></div></li>
                             <li><a class="dropdown-item text-center small text-muted" href="/notifications.php">View all</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end mt-2">
                            <?php if (in_array($_SESSION['role'], ['admin', 'hr_manager'])): ?>
                                <li><a class="dropdown-item" href="/admin/audit_logs.php">Audit Logs</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container mt-4">