<?php
// in file: fake_device_server.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$ip = '127.0.0.1';
$port = 4370;

$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) die("Could not create socket\n");
if (!socket_bind($socket, $ip, $port)) die("Could not bind to address {$ip}:{$port}\n");

echo "Fake Device Server (UDP for Fingertec) listening on udp://{$ip}:{$port}\nPress Ctrl+C to stop.\n\n";

while (true) {
    $buffer = '';
    $client_ip = '';
    $client_port = 0;
    $bytes_received = socket_recvfrom($socket, $buffer, 2048, 0, $client_ip, $client_port);

    if (!$bytes_received) continue;

    echo "Received " . $bytes_received . " bytes from {$client_ip}:{$client_port}\n";
    echo " -> Received Command: " . $buffer . "\n";
    
    $response_packet = '';
    
    // Check for the simple command string
    if (trim($buffer) === 'GET_USERS') {
        echo " -> Action: Responding with Fake User List.\n";
        
        $fake_users = [
            [
                'employee_code' => 'FG-001',
                'name' => 'Fingertec User 1',
                'role' => 'User',
            ],
            [
                'employee_code' => 'FG-002',
                'name' => 'Fingertec Admin 2',
                'role' => 'Admin',
            ]
        ];
        
        $response_packet = json_encode($fake_users);
    }

    if (!empty($response_packet)) {
        socket_sendto($socket, $response_packet, strlen($response_packet), 0, $client_ip, $client_port);
        echo " -> Sent response packet back.\n\n";
    }
}
socket_close($socket);