<?php
// in file: create_admin.php

// --- IMPORTANT SECURITY WARNING ---
// This script is a potential security risk.
// It allows for the creation of an admin user without authentication.
// YOU MUST DELETE THIS FILE FROM YOUR SERVER IMMEDIATELY AFTER USE.
// --- --- --- --- --- --- --- --- ---

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include necessary files only when the form is submitted
    require_once __DIR__ . '/app/core/database.php';

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($email) || empty($password)) {
        $message = 'All fields are required.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        try {
            // Check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = "An account with this email address already exists.";
                $message_type = 'error';
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert the new admin user
                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'admin')"
                );
                $stmt->execute([$full_name, $email, $hashed_password]);

                $message = "Admin user '" . htmlspecialchars($full_name) . "' created successfully. <strong>Please delete this file now!</strong>";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: #333; }
        .warning { background-color: #ffc107; color: #333; padding: 15px; border-radius: 5px; text-align: center; font-weight: bold; margin-bottom: 20px; border: 1px solid #e0a800;}
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Initial Admin User</h1>
        <div class="warning">
            SECURITY RISK: Delete this file (<code>create_admin.php</code>) immediately after use.
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="create_admin.php" method="post">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Create Admin</button>
        </form>
    </div>
</body>
</html><?php
// in file: create_admin.php

// --- IMPORTANT SECURITY WARNING ---
// This script is a potential security risk.
// It allows for the creation of an admin user without authentication.
// YOU MUST DELETE THIS FILE FROM YOUR SERVER IMMEDIATELY AFTER USE.
// --- --- --- --- --- --- --- --- ---

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include necessary files only when the form is submitted
    require_once __DIR__ . '/app/core/database.php';

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($full_name) || empty($email) || empty($password)) {
        $message = 'All fields are required.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        try {
            // Check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = "An account with this email address already exists.";
                $message_type = 'error';
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert the new admin user
                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'admin')"
                );
                $stmt->execute([$full_name, $email, $hashed_password]);

                $message = "Admin user '" . htmlspecialchars($full_name) . "' created successfully. <strong>Please delete this file now!</strong>";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: #333; }
        .warning { background-color: #ffc107; color: #333; padding: 15px; border-radius: 5px; text-align: center; font-weight: bold; margin-bottom: 20px; border: 1px solid #e0a800;}
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Initial Admin User</h1>
        <div class="warning">
            SECURITY RISK: Delete this file (<code>create_admin.php</code>) immediately after use.
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="create_admin.php" method="post">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Create Admin</button>
        </form>
    </div>
</body>
</html>