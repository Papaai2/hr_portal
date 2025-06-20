<?php
// in file: htdocs/change_password.php

require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/database.php';

require_login();

$error_message = '';
$success_message = '';
$user_id = get_current_user_id();

// This page is only for users who MUST change their password.
// If they don't have the flag, redirect them away.
$stmt_check = $pdo->prepare("SELECT must_change_password FROM users WHERE id = ?");
$stmt_check->execute([$user_id]);
$must_change = $stmt_check->fetchColumn();

if (!$must_change && basename($_SERVER['PHP_SELF']) === 'change_password.php') {
    header('Location: /index.php');
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please enter and confirm your new password.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?";
            $pdo->prepare($sql)->execute([$hashed_password, $user_id]);
            
            // Log the user out to force re-login with the new password
            session_unset();
            session_destroy();

            header('Location: /login.php?message=password_changed');
            exit();

        } catch (PDOException $e) {
            $error_message = 'A database error occurred. Could not update password.';
        }
    }
}

$page_title = 'Change Your Password';
// We don't include the standard header because the user should not be able to navigate away.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME) : htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="bg-light">
<div class="container d-flex align-items-center justify-content-center vh-100">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-lg border-0">
             <div class="card-header text-center bg-primary text-white">
                <h1 class="h3 mb-0 py-2"><?php echo htmlspecialchars(SITE_NAME); ?></h1>
            </div>
            <div class="card-body p-4">
                <h2 class="card-title text-center h4 mb-3">Change Your Password</h2>
                <p class="text-muted text-center">For security reasons, you must change your password before you can proceed.</p>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>
                
                <form action="change_password.php" method="post">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Password and Log In</button>
                    </div>
                </form>
            </div>
             <div class="card-footer text-center py-3">
                <a href="/logout.php" class="text-muted small">Log Out</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
