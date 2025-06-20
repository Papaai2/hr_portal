<?php
// A Fake ZKTeco/Fingertec Device Server
// Run this script from the command line: php fake_device_server.php

$ip = '127.0.0.1';
$port = 4370; // The default port the devices use

// --- Create a UDP socket ---
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("Could not create socket\n");
}

// --- Bind the socket to the IP and Port ---
if (!socket_bind($socket, $ip, $port)) {
    die("Could not bind to address {$ip}:{$port} - Is another service using it?\n");
}

echo "Fake Device Server listening on udp://{$ip}:{$port}\n";
echo "--------------------------------------------------\n";
echo "Waiting for a connection from the HR Portal script...\n";
echo "Press Ctrl+C to stop.\n\n";

// --- Main loop to listen for incoming data ---
while (true) {
    $buffer = '';
    $client_ip = '';
    $client_port = 0;

    // Wait for data from a client (your HR Portal script)
    $bytes_received = socket_recvfrom($socket, $buffer, 2048, 0, $client_ip, $client_port);

    if ($bytes_received === false) {
        echo "socket_recvfrom failed: " . socket_strerror(socket_last_error($socket)) . "\n";
        break;
    }

    echo "Received a request from {$client_ip}:{$client_port}\n";

    // --- Prepare the Fake Response Data ---
    // This mimics the raw data a ZKTeco device sends for attendance logs.
    // Format: EmployeeCode(string) \t Timestamp(YYYY-MM-DD HH:MM:SS) \t PunchState(int) \n
    $fake_log_data = 
        "1001\t2025-06-21 08:59:15\t0\n" .  // User 1001, Check-In
        "1002\t2025-06-21 09:01:05\t0\n" .  // User 1002, Check-In
        "1001\t2025-06-21 17:30:45\t1\n" .  // User 1001, Check-Out
        "1002\t2025-06-21 17:35:10\t1\n";   // User 1002, Check-Out

    // The TAD_PHP_Library expects a specific header format. We'll create a minimal one.
    // This header includes a session ID and checksum which the library will parse.
    $header = pack('HHHH', 1500, 0, 1234, 1); // command, chksum, session_id, reply_id
    
    $response_packet = $header . $fake_log_data;

    // --- Send the fake data back to the client ---
    socket_sendto($socket, $response_packet, strlen($response_packet), 0, $client_ip, $client_port);
    echo "Sent fake attendance log packet back to the script.\n\n";
}

socket_close($socket);