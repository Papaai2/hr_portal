<?php
// fake_device_server.php - Simulates a real FingerTec device

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

$host = '127.0.0.1';
$port = 8099;

echo "Fake FingerTec Server (Final) listening on tcp://{$host}:{$port}\n";

// Simulate device storage
$users = [
    '10' => ['user_id' => '10', 'name' => 'FT User One', 'privilege' => '0', 'card_id' => 'N/A', 'group_id' => 'N/A'],
    '11' => ['user_id' => '11', 'name' => 'FT Admin', 'privilege' => '14', 'card_id' => 'N/A', 'group_id' => 'N/A'],
];
$attendance_logs = [
    ['employee_code' => '10', 'punch_time' => '2025-06-21 08:59:10', 'status' => '0'],
    ['employee_code' => '11', 'punch_time' => '2025-06-21 18:01:05', 'status' => '1'],
];

function generate_fingertec_user_response(array $users_data): string {
    $response = "DATA\r\n";
    foreach ($users_data as $user) {
        $response .= "PIN={$user['user_id']}\tName={$user['name']}\tPri={$user['privilege']}\r\n";
    }
    $response .= "OK\r\n";
    return $response;
}

function generate_fingertec_attendance_response(array $logs_data): string {
    $response = "DATA\r\n";
    foreach ($logs_data as $log) {
        $response .= "PIN={$log['employee_code']}\tDateTime={$log['punch_time']}\tStatus={$log['status']}\r\n";
    }
    $response .= "OK\r\n";
    return $response;
}

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $host, $port);
socket_listen($socket);

while($client = socket_accept($socket)) {
    echo "Accepted connection.\n";
    $keep_alive = true;
    while ($keep_alive) {
        $command = @socket_read($client, 2048, PHP_NORMAL_READ);
        if ($command === false || $command === "") {
            echo "Client disconnected or no more data.\n";
            $keep_alive = false;
            break;
        }
        
        $command = trim($command);
        echo "Received command: " . $command . "\n";
        $response = "OK\r\n"; // Default response

        global $users, $attendance_logs; // Access global storage

        if (strpos($command, 'DATA QUERY user') !== false) {
            $response = generate_fingertec_user_response($users);
            echo "Sent user data.\n";
        } elseif (strpos($command, 'DATA QUERY attlog') !== false) {
            $response = generate_fingertec_attendance_response($attendance_logs);
            echo "Sent attendance data.\n";
        } elseif (strpos($command, 'DATA ADD user') !== false) {
            // Parse user data from command: DATA ADD user=PIN=X\tName=Y\tPri=Z...
            parse_str(str_replace(["DATA ADD user=", "\t"], ["", "&"], $command), $userData);
            if (!empty($userData['PIN'])) {
                $users[$userData['PIN']] = [
                    'user_id' => $userData['PIN'],
                    'name' => $userData['Name'] ?? 'N/A',
                    'privilege' => $userData['Pri'] ?? '0',
                    'card_id' => $userData['Card'] ?? 'N/A',
                    'group_id' => $userData['Group'] ?? 'N/A',
                ];
                echo "Simulated user {$userData['PIN']} added.\n";
            } else {
                echo "Failed to parse user data for add.\n";
                $response = "ERROR: Invalid user data\r\n";
            }
        } elseif (strpos($command, 'DATA DELETE user') !== false) {
            // Parse user ID from command: DATA DELETE user=PIN=X
            parse_str(str_replace("DATA DELETE user=", "", $command), $userData);
            if (!empty($userData['PIN']) && isset($users[$userData['PIN']])) {
                unset($users[$userData['PIN']]);
                echo "Simulated user {$userData['PIN']} deleted.\n";
            } else {
                echo "User {$userData['PIN']} not found for deletion.\n";
                $response = "ERROR: User not found\r\n";
            }
        } elseif (strpos($command, 'DATA UPDATE user') !== false) {
            // FingerTec update is often a re-add.
            // This logic is handled by the driver calling delete then add.
            // So, we just process the add command if it comes.
            echo "Simulated user update (handled by ADD/DELETE).\n";
        } elseif (strpos($command, 'CLEAR LOG') !== false) {
            $attendance_logs = [];
            echo "Simulated clear logs.\n";
        } elseif (strpos($command, 'EXIT') !== false || strpos($command, 'DISCONNECT') !== false) {
            echo "Received exit command. Closing connection.\n";
            $keep_alive = false;
        } else {
            $response = "ERROR: Unknown command\r\n";
            echo "Unknown command received.\n";
        }

        @socket_write($client, $response, strlen($response));
    }
    socket_close($client);
    echo "Closed connection.\n\n";
}

socket_close($socket);