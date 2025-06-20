<?php
// admin/devices.php

// --- Bootstrap The Application ---
// By including this single file, we ensure the entire application
// environment (session, database, helpers, auth) is loaded correctly.
require_once __DIR__ . '/../app/bootstrap.php';

// --- Authentication Check ---
// This check will now work reliably.
if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit;
}

// --- Database Connection ---
$db = new Database();
$pdo = $db->getConnection();
$message = '';
$message_type = '';

// --- BEGIN BACKEND LOGIC (FORM PROCESSING) ---

// Handle POST requests for Add, Update, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- Add New Device ---
    if (isset($_POST['add_device'])) {
        $name = sanitize_input($_POST['name']);
        $ip_address = sanitize_input($_POST['ip_address']);
        $port = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
        $device_brand = sanitize_input($_POST['device_brand']);

        if ($name && $ip_address && $port && $device_brand) {
            try {
                $stmt = $pdo->prepare("INSERT INTO devices (name, ip_address, port, device_brand) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $ip_address, $port, $device_brand])) {
                    $message = 'Device added successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to add device.';
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = 'Please fill in all fields correctly.';
            $message_type = 'error';
        }
    }

    // --- Update Existing Device ---
    if (isset($_POST['update_device'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = sanitize_input($_POST['name']);
        $ip_address = sanitize_input($_POST['ip_address']);
        $port = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
        $device_brand = sanitize_input($_POST['device_brand']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id && $name && $ip_address && $port && $device_brand) {
            try {
                $stmt = $pdo->prepare("UPDATE devices SET name = ?, ip_address = ?, port = ?, device_brand = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$name, $ip_address, $port, $device_brand, $is_active, $id])) {
                    $message = 'Device updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update device.';
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = 'Invalid data submitted.';
            $message_type = 'error';
        }
    }

    // --- Delete Device ---
    if (isset($_POST['delete_device'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                // First, delete related attendance logs to avoid foreign key constraints
                $stmt_delete_logs = $pdo->prepare("DELETE FROM attendance_logs WHERE device_id = ?");
                $stmt_delete_logs->execute([$id]);

                // Then, delete the device itself
                $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $message = 'Device and related logs deleted successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete device.';
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// --- Fetch all devices for display ---
$stmt_get_all = $pdo->query("SELECT * FROM devices ORDER BY name ASC");
$devices = $stmt_get_all->fetchAll(PDO::FETCH_ASSOC);

// --- END BACKEND LOGIC ---


// --- BEGIN TEMPLATE ---
// The header file should handle the HTML head, body tag, and main navigation.
include __DIR__ . '/../app/templates/header.php';
?>

<div class="container mx-auto my-10 px-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manage Attendance Devices</h1>

    <!-- Display Feedback Message -->
    <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Add New Device Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold mb-4">Add New Device</h2>
        <form action="devices.php" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Device Name</label>
                <input type="text" id="name" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g., Main Entrance">
            </div>
            <div>
                <label for="ip_address" class="block text-sm font-medium text-gray-700">IP Address</label>
                <input type="text" id="ip_address" name="ip_address" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g., 192.168.1.201">
            </div>
            <div>
                <label for="port" class="block text-sm font-medium text-gray-700">Port</label>
                <input type="number" id="port" name="port" required value="4370" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="device_brand" class="block text-sm font-medium text-gray-700">Device Brand</label>
                <select id="device_brand" name="device_brand" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="zkteco">ZKTeco</option>
                    <option value="fingertec">Fingertec</option>
                    <!-- Add other brands here in the future -->
                </select>
            </div>
            <button type="submit" name="add_device" class="bg-indigo-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Add Device
            </button>
        </form>
    </div>

    <!-- Device List Table -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Configured Devices</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Port</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Sync</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($devices)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 whitespace-nowrap text-center text-gray-500">No devices configured yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($device['name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($device['ip_address']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($device['port']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(ucfirst($device['device_brand'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($device['is_active']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                 <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $device['last_sync_timestamp'] ? date('Y-m-d H:i:s', strtotime($device['last_sync_timestamp'])) : 'Never'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <!-- A modal-based edit form would be a great enhancement. -->
                                    <form action="devices.php" method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?php echo $device['id']; ?>">
                                        <button type="submit" name="delete_device" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this device? This will also remove all associated attendance logs.');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// This should include the closing body and html tags.
include __DIR__ . '/../app/templates/footer.php';
?>
