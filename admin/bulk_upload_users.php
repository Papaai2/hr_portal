<?php
// in file: htdocs/admin/bulk_upload_users.php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/database.php';

require_role(['admin', 'hr_manager']);

$page_title = 'Bulk Upload Users';
include __DIR__ . '/../app/templates/header.php';

$error = '';
$success = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['user_csv'])) {
    if ($_FILES['user_csv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error. Code: ' . $_FILES['user_csv']['error'];
    } else {
        $file = $_FILES['user_csv']['tmp_name'];
        $handle = fopen($file, "r");

        if ($handle !== FALSE) {
            // Skip header row
            fgetcsv($handle, 1000, ",");

            $row_number = 1;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_number++;
                $full_name = trim($data[0] ?? '');
                $email = trim($data[1] ?? '');
                $password = trim($data[2] ?? '');
                $role = trim($data[3] ?? 'user');
                $manager_email = trim($data[4] ?? '');

                if (empty($full_name) || empty($email) || empty($password)) {
                    $results[] = ['status' => 'error', 'message' => "Row $row_number: Missing required data (Full Name, Email, Password).", 'data' => implode(', ', $data)];
                    continue;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $results[] = ['status' => 'error', 'message' => "Row $row_number: Invalid email format for user.", 'data' => implode(', ', $data)];
                    continue;
                }
                
                $direct_manager_id = null;
                if (!empty($manager_email)) {
                    if (!filter_var($manager_email, FILTER_VALIDATE_EMAIL)) {
                         $results[] = ['status' => 'error', 'message' => "Row $row_number: Invalid email format for manager.", 'data' => implode(', ', $data)];
                         continue;
                    }
                    $stmt_manager = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt_manager->execute([$manager_email]);
                    $manager = $stmt_manager->fetch();
                    if ($manager) {
                        $direct_manager_id = $manager['id'];
                    } else {
                        $results[] = ['status' => 'error', 'message' => "Row $row_number: Manager with email '$manager_email' not found.", 'data' => implode(', ', $data)];
                        continue;
                    }
                }

                try {
                    $stmt_user_exists = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt_user_exists->execute([$email]);
                    if ($stmt_user_exists->fetch()) {
                        $results[] = ['status' => 'error', 'message' => "Row $row_number: User email '$email' already exists.", 'data' => implode(', ', $data)];
                        continue;
                    }
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (full_name, email, password, role, direct_manager_id) VALUES (?, ?, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([$full_name, $email, $hashed_password, $role, $direct_manager_id]);
                    $results[] = ['status' => 'success', 'message' => "Successfully created user '$full_name'.", 'data' => implode(', ', $data)];

                } catch (PDOException $e) {
                    $results[] = ['status' => 'error', 'message' => "Row $row_number: Database error - " . $e->getMessage(), 'data' => implode(', ', $data)];
                }
            }
            fclose($handle);
            $success = "CSV processing complete. See results below.";
        } else {
            $error = 'Could not open the uploaded file.';
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Bulk Upload Users</h1>
    <a href="users.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Users
    </a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">Upload CSV File</h2>
    </div>
    <div class="card-body">
        <p>Upload a CSV file with the columns: `full_name`, `email`, `password`, `role`, `direct_manager_email`.</p>
        <ul>
            <li>The `role` column is optional and defaults to 'user'. Allowed roles are: user, manager, hr, hr_manager, admin.</li>
            <li>The `direct_manager_email` is optional. If provided, the system will assign the user to that manager.</li>
        </ul>
        
        <a href="sample_users.csv" download>Download Sample CSV</a>
        
        <form action="bulk_upload_users.php" method="post" enctype="multipart/form-data" class="mt-3">
            <div class="mb-3">
                <label for="user_csv" class="form-label">Select CSV file</label>
                <input class="form-control" type="file" id="user_csv" name="user_csv" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Upload and Process</button>
        </form>
    </div>
</div>

<?php if (!empty($results)): ?>
<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">Upload Results</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Original Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr class="<?php echo $result['status'] === 'success' ? 'table-success' : 'table-danger'; ?>">
                            <td><?php echo ucfirst($result['status']); ?></td>
                            <td><?php echo htmlspecialchars($result['message']); ?></td>
                            <td><?php echo htmlspecialchars($result['data']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>


<?php
include __DIR__ . '/../app/templates/footer.php';
?>
