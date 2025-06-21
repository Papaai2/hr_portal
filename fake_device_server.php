<?php
// fake_device_server.php - Simulates a real FingerTec device

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

$host = '127.0.0.1';
$port = 8099; // Port for Fingertec test device

echo "Fake FingerTec Server listening on tcp://{$host}:{$port}\n";

// The user data payload. The final "OK" is critical.
$users_response = "DATA\r\nPIN=10\tName=FT User One\tPri=0\r\nPIN=11\tName=FT Admin\tPri=14\r\nOK\r\n";
$attendance_response = "DATA\r\nPIN=10\tDateTime=2025-06-21 08:59:00\tStatus=0\r\nPIN=11\tDateTime=2025-06-21 17:05:00\tStatus=1\r\nOK\r\n";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $host, $port);
socket_listen($socket);

while($client = socket_accept($socket)) {
    echo "Accepted connection.\n";
    
    $command = socket_read($client, 1024);
    echo "Received command: " . trim($command) . "\n";
    $response = "OK\r\n"; // Default response

    if (strpos($command, 'DATA QUERY user') !== false) {
        $response = $users_response;
        echo "Sent user data.\n";
    } elseif (strpos($command, 'DATA QUERY attlog') !== false) {
        $response = $attendance_response;
        echo "Sent attendance data.\n";
    }

    socket_write($client, $response, strlen($response));
    socket_close($client);
    echo "Closed connection.\n\n";
}

socket_close($socket);