<?php
require_once __DIR__ . '/app/bootstrap.php'; // For config, error_handler, etc.
require_once APP_PATH . '/core/drivers/ZKTecoDriver.php';
require_once APP_PATH . '/core/drivers/FingertecDriver.php';

echo "<h2>Testing ZKTeco Driver</h2>";
$zk_ip = '127.0.0.1'; // Or the IP where your fake_zk_server.php is running
$zk_port = 8100; // Corrected port for ZKTeco

$zkDriver = new ZKTecoDriver(['host' => $zk_ip, 'port' => $zk_port, 'debug' => true]);

if ($zkDriver->connect($zk_ip, $zk_port)) {
    echo "<p>ZKTeco Connected!</p>";

    // Test Add User
    $new_zk_user_id = '1001';
    $new_zk_user_data = [
        'name' => 'Test ZK User',
        'privilege' => 0, // 0 for user
        'password' => '12345',
        'card_id' => 12345,
        'group_id' => 1
    ];
    if ($zkDriver->addUser($new_zk_user_id, $new_zk_user_data)) {
        echo "<p style='color:green;'>ZKTeco: User {$new_zk_user_id} added successfully.</p>";
    } else {
        echo "<p style='color:red;'>ZKTeco: Failed to add user {$new_zk_user_id}. Error: " . $zkDriver->getLastError() . "</p>";
    }

    // Test Get Users (to verify add)
    $zk_users = $zkDriver->getUsers();
    echo "<p>ZKTeco Users: " . count($zk_users) . "</p>";
    // echo "<pre>"; print_r($zk_users); echo "</pre>"; // Uncomment to see full user data

    // Test Update User (re-add with new data)
    $updated_zk_user_data = [
        'name' => 'Updated ZK User',
        'privilege' => 0,
        'password' => 'newpass',
        'card_id' => 54321,
        'group_id' => 1
    ];
    if ($zkDriver->updateUser($new_zk_user_id, $updated_zk_user_data)) {
        echo "<p style='color:green;'>ZKTeco: User {$new_zk_user_id} updated successfully.</p>";
    } else {
        echo "<p style='color:red;'>ZKTeco: Failed to update user {$new_zk_user_id}. Error: " . $zkDriver->getLastError() . "</p>";
    }

    // Test Clear Attendance Data
    if ($zkDriver->clearAttendanceData()) {
        echo "<p style='color:green;'>ZKTeco: Attendance data cleared successfully.</p>";
    } else {
        echo "<p style='color:red;'>ZKTeco: Failed to clear attendance data. Error: " . $zkDriver->getLastError() . "</p>";
    }

    // Test Delete User
    if ($zkDriver->deleteUser($new_zk_user_id)) {
        echo "<p style='color:green;'>ZKTeco: User {$new_zk_user_id} deleted successfully.</p>";
    } else {
        echo "<p style='color:red;'>ZKTeco: Failed to delete user {$new_zk_user_id}. Error: " . $zkDriver->getLastError() . "</p>";
    }

    $zkDriver->disconnect();
} else {
    echo "<p style='color:red;'>Failed to connect to ZKTeco device. Error: " . $zkDriver->getLastError() . "</p>";
}

echo "<h2>Testing FingerTec Driver</h2>";
$ft_ip = '127.0.0.1'; // Or the IP where your fake_device_server.php is running
$ft_port = 8099; // Corrected port for FingerTec

$ftDriver = new FingertecDriver(['host' => $ft_ip, 'port' => $ft_port, 'debug' => true]);

if ($ftDriver->connect($ft_ip, $ft_port)) {
    echo "<p>FingerTec Connected!</p>";

    // Test Add User
    $new_ft_user_id = '2001';
    $new_ft_user_data = [
        'name' => 'Test FT User',
        'privilege' => 0, // 0 for user
        'password' => 'ftpass',
        'card_id' => '98765',
    ];
    if ($ftDriver->addUser($new_ft_user_id, $new_ft_user_data)) {
        echo "<p style='color:green;'>FingerTec: User {$new_ft_user_id} added successfully.</p>";
    } else {
        echo "<p style='color:red;'>FingerTec: Failed to add user {$new_ft_user_id}. Error: " . $ftDriver->getLastError() . "</p>";
    }

    // Test Get Users (to verify add)
    $ft_users = $ftDriver->getUsers();
    echo "<p>FingerTec Users: " . count($ft_users) . "</p>";
    // echo "<pre>"; print_r($ft_users); echo "</pre>"; // Uncomment to see full user data

    // Test Update User
    $updated_ft_user_data = [
        'name' => 'Updated FT User',
        'privilege' => 1, // 1 for admin
        'password' => 'newftpass',
        'card_id' => '112233',
    ];
    if ($ftDriver->updateUser($new_ft_user_id, $updated_ft_user_data)) {
        echo "<p style='color:green;'>FingerTec: User {$new_ft_user_id} updated successfully.</p>";
    } else {
        echo "<p style='color:red;'>FingerTec: Failed to update user {$new_ft_user_id}. Error: " . $ftDriver->getLastError() . "</p>";
    }

    // Test Clear Attendance Data
    if ($ftDriver->clearAttendanceData()) {
        echo "<p style='color:green;'>FingerTec: Attendance data cleared successfully.</p>";
    } else {
        echo "<p style='color:red;'>FingerTec: Failed to clear attendance data. Error: " . $ftDriver->getLastError() . "</p>";
    }

    // Test Delete User
    if ($ftDriver->deleteUser($new_ft_user_id)) {
        echo "<p style='color:green;'>FingerTec: User {$new_ft_user_id} deleted successfully.</p>";
    } else {
        echo "<p style='color:red;'>FingerTec: Failed to delete user {$new_ft_user_id}. Error: " . $ftDriver->getLastError() . "</p>";
    }

    $ftDriver->disconnect();
} else {
    echo "<p style='color:red;'>Failed to connect to FingerTec device. Error: " . $ftDriver->getLastError() . "</p>";
}
?>