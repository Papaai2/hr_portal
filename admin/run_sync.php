<?php
// in file: admin/run_sync.php
// This new file runs the command-line sync script and displays the output.

require_once __DIR__ . '/../app/bootstrap.php';
require_role(['admin', 'hr_manager']);

$page_title = 'Device Synchronization';
include __DIR__ . '/../app/templates/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Device Synchronization</h1>
    <a href="users.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Users
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Synchronization Log</h5>
        <span class="badge bg-primary">In Progress...</span>
    </div>
    <div class="card-body">
        <p>Attempting to push all users from the portal database to all registered devices. Please do not close this page until the process is complete.</p>
        
        <pre class="bg-dark text-white p-3 rounded" style="min-height: 300px; max-height: 600px; overflow-y: auto;">
<?php
// Flush the output buffer to show the header before the script runs
ob_flush();
flush();

// --- Execute the command-line script ---
// We construct the full path to the PHP executable and the script for reliability.
// Note: Your path to php.exe might be different if XAMPP is installed elsewhere.
$php_executable = 'C:\xampp\php\php.exe'; // Common path for XAMPP on Windows
$script_path = realpath(__DIR__ . '/../scripts/sync_users_to_devices.php');

if (!file_exists($php_executable)) {
    echo "ERROR: PHP executable not found at: {$php_executable}\nPlease update the path in admin/run_sync.php\n";
} elseif (!$script_path) {
    echo "ERROR: Sync script not found. Ensure 'scripts/sync_users_to_devices.php' exists.\n";
} else {
    // Use passthru() to execute the command and display its output directly and in real-time.
    passthru('"' . $php_executable . '" "' . $script_path . '"');
}
?>
        </pre>
    </div>
    <div class="card-footer">
        Process complete. You can now return to the user list.
    </div>
</div>


<?php
include __DIR__ . '/../app/templates/footer.php';
?>