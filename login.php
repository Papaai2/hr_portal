<?php
// in file: login.php
session_start();

// If user is already logged in, redirect them away
if (isset($_SESSION['user_id'])) {
    // Exception: if they somehow get here but still need to change password
    require_once __DIR__ . '/app/core/database.php';
    $stmt_check = $pdo->prepare("SELECT must_change_password FROM users WHERE id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    if($stmt_check->fetchColumn()){
         header('Location: /change_password.php');
         exit();
    }

    header('Location: /index.php');
    exit();
}

require_once __DIR__ . '/app/core/database.php';

$error_message = '';
$success_message = '';
$email_value = ''; // To retain email on failed login

if (isset($_GET['message']) && $_GET['message'] === 'password_changed') {
    $success_message = 'Password changed successfully. Please log in again with your new password.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $email_value = htmlspecialchars($email); // Retain for the form

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, password, full_name, role, must_change_password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Password is correct, start the session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Check if user must change password
                if ($user['must_change_password']) {
                    header('Location: /change_password.php');
                    exit();
                }

                // Redirect to the main dashboard
                header('Location: /index.php');
                exit();
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'A database error occurred. Please try again later.';
            // In a production environment, log the error instead of showing a generic message
            // error_log($e->getMessage());
        }
    }
}

$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME) : htmlspecialchars(SITE_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        /* Specific styles for the login page only */
        html, body {
            height: 100%;
        }
        body.login-page {
            display: flex;
            align-items: center; 
            justify-content: center; 
            background-color: #f0f2f5;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
        }
    </style>
</head>
<body class="login-page">

<div class="login-card">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            <h1 class="text-center h3 mb-4 fw-bold"><?php echo htmlspecialchars(SITE_NAME); ?> Login</h1>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="post" novalidate>
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?php echo $email_value; ?>" required autofocus>
                    <label for="email">Email address</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary btn-lg" type="submit">Sign in</button>
                </div>
            </form>
        </div>
        <div class="card-footer text-center py-3">
            <div class="small text-muted">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>
