<?php
// in file: app/templates/header.php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/helpers.php'; // Ensure helpers are included for functions like getStatusBadgeClass
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME) : htmlspecialchars(SITE_NAME); ?></title>
    
    <!-- Bootstrap CSS (LOAD FIRST for proper overrides) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Font (Poppins) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Your Custom Stylesheets (LOAD AFTER Bootstrap) -->
    <link rel="stylesheet" href="/css/style.css">
    <!-- Removed dashboard-improvements.css as its contents are merged into style.css -->
</head>
<body>
    <!-- Main container for layout: flex for sidebar + content -->
    <div class="d-flex" id="wrapper">

        <!-- Sidebar - Fixed for larger screens, Offcanvas for small screens -->
        <nav class="sidebar border-end" id="sidebar-wrapper">
            <div class="sidebar-heading text-white bg-primary py-3 px-4 d-flex align-items-center justify-content-between">
                <a class="navbar-brand fw-bold m-0" href="/"><?php echo htmlspecialchars(SITE_NAME); ?></a>
                <!-- Close button for offcanvas on mobile -->
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close" style="font-size: 1.5rem;"></button>
            </div>
            <div class="list-group list-group-flush pt-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/index.php" class="list-group-item list-group-item-action py-2 ripple <?= (basename($_SERVER['PHP_SELF']) == 'index.php' && basename(dirname($_SERVER['PHP_SELF'])) == 'hr_portal-4048af2a620a72299cc7ea7879abb406dced4cf7') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2 me-3"></i><span>Dashboard</span>
                    </a>
                    <a href="/requests/index.php" class="list-group-item list-group-item-action py-2 ripple <?= (basename($_SERVER['PHP_SELF']) == 'index.php' && basename(dirname($_SERVER['PHP_SELF'])) == 'requests') ? 'active' : '' ?>">
                        <i class="bi bi-send-check me-3"></i><span>My Requests</span>
                    </a>
                    
                    <?php if (in_array($_SESSION['role'], ['manager', 'admin', 'hr_manager'])): ?>
                        <a class="list-group-item list-group-item-action py-2 ripple dropdown-toggle" href="#teamReportsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="teamReportsSubmenu">
                            <i class="bi bi-graph-up me-3"></i><span>Team Reports</span>
                        </a>
                        <div class="collapse" id="teamReportsSubmenu">
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/reports/team.php">My Team</a>
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/reports/manager_history.php">Team History</a>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($_SESSION['role'], ['hr', 'hr_manager', 'admin'])): ?>
                         <a class="list-group-item list-group-item-action py-2 ripple dropdown-toggle" href="#hrReportsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="hrReportsSubmenu">
                            <i class="bi bi-file-earmark-bar-graph me-3"></i><span>HR Reports</span>
                        </a>
                        <div class="collapse" id="hrReportsSubmenu">
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/reports/hr_history.php">Full History</a>
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/reports/user_balances.php">User Balances</a>
                            <hr class="dropdown-divider my-1">
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/reports/timesheet.php">Daily Timesheet</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['role'], ['hr_manager', 'admin'])): ?>
                        <a class="list-group-item list-group-item-action py-2 ripple dropdown-toggle" href="#adminPanelSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="adminPanelSubmenu">
                            <i class="bi bi-gear me-3"></i><span>Admin Panel</span>
                        </a>
                        <div class="collapse" id="adminPanelSubmenu">
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/admin/index.php">Dashboard</a>
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/admin/users.php">Users</a>
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/admin/departments.php">Departments</a>
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/admin/devices.php">Devices</a>
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/admin/leave_management.php">Leave Management</a>
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/admin/attendance_logs.php">Attendance Logs</a>
                            <a class="list-group-item list-group-item-action py-2 ripple ps-5" href="/admin/shifts.php">Shift Management</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/login.php" class="list-group-item list-group-item-action py-2 ripple">
                        <i class="bi bi-box-arrow-in-right me-3"></i><span>Login</span>
                    </a>
                <?php endif; ?>
            </div>
             <div class="mt-auto p-3 text-center small text-muted">
                &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>
            </div>
        </nav>

        <!-- Page Content Wrapper -->
        <div id="page-content-wrapper" class="d-flex flex-column flex-grow-1">
            <!-- Top Navbar (simplified) -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm fixed-top py-0">
                <div class="container-fluid">
                    <!-- Hamburger icon for mobile sidebar toggle -->
                    <button class="btn btn-primary d-lg-none" id="sidebarToggle">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    
                    <!-- Push content to the right -->
                    <div class="d-flex align-items-center ms-auto">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <ul class="navbar-nav">
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
                                            <li><a class="dropdown-item" href="/admin/audit_logs.php"><i class="bi bi-file-earmark-text me-2"></i>Audit Logs</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                                    </ul>
                                </li>
                            </ul>
                        <?php else: ?>
                            <ul class="navbar-nav">
                                <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <main class="container-fluid py-4 flex-grow-1">
                <!-- Content from specific pages like index.php, admin/attendance_logs.php will be loaded here -->