<?php
// in file: htdocs/create_admin.php

require_once __DIR__ . '/app/bootstrap.php';

// Check if an admin user already exists
$stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$admin_exists = $stmt->fetch();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($admin_exists) {
        $error = 'An admin account already exists. This script is disabled for security reasons.';
    } else {
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email'], 'email');
        $password = $_POST['password'];

        if (empty($full_name) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "An account with this email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $sql = "INSERT INTO users (full_name, email, password, role, is_active, must_change_password) VALUES (?, ?, ?, 'admin', 1, 0)";
                $stmt = $pdo->prepare($sql);

                if ($stmt->execute([$full_name, $email, $hashed_password])) {
                    $message = "Admin user created successfully! <strong>Please delete this file (create_admin.php) immediately for security.</strong> You can now <a href='login.php'>log in</a>.";
                    // Re-check admin existence to disable the form
                    $admin_exists = true; 
                } else {
                    $error = "Failed to create admin user. Please check logs.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Initial Admin User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="bg-light">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm mt-5">
                <div class="card-body p-4">
                    <h1 class="h3 mb-3 fw-normal text-center">Create Initial Admin</h1>
                    <p class="text-center text-muted">Use this form only once to create the first administrator account.</p>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= $message ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <?php if (!$admin_exists): ?>
                        <form action="create_admin.php" method="POST" novalidate>
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="full_name" name="full_name" placeholder="John Doe" required>
                                <label for="full_name">Full Name</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                                <label for="email">Email address</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password">Password</label>
                            </div>

                            <button class="w-100 btn btn-lg btn-primary" type="submit">Create Admin</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            <p><i class="bi bi-exclamation-triangle-fill"></i> <strong>Script Disabled</strong></p>
                            An admin account already exists. For security, this script cannot be used again.
                            <br><br>
                            <strong>You MUST delete this file from the server now.</strong>
                        </div>
                    <?php endif; ?>
                    
                    <p class="mt-4 mb-1 text-center text-muted">&copy; <?= date('Y') ?> HR Portal</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
