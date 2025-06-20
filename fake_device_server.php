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

const CMD_CONNECT = 1000;
const CMD_GET_USER = 1501;
const ACK_OK = 2000;

while (true) {
    $buffer = '';
    $client_ip = '';
    $client_port = 0;
    $bytes_received = socket_recvfrom($socket, $buffer, 2048, 0, $client_ip, $client_port);

    if (!$bytes_received || $bytes_received < 8) continue;
    
    echo "Received " . $bytes_received . " bytes. Command: ";
    
    $header = unpack('vcommand/vchecksum/vsession_id/vreply_id', $buffer);
    $command_id = $header['command'];
    $reply_id = $header['reply_id'];
    $session_id = $header['session_id'];

    echo "{$command_id}\n";
    
    $response_packet = '';
    
    switch ($command_id) {
        case CMD_CONNECT:
            echo " -> Action: Responding to Connection Request.\n";
            $response_packet = pack('vvvv', ACK_OK, 0, rand(1000,9999), $reply_id);
            break;

        case CMD_GET_USER:
            echo " -> Action: Responding with Fake User List.\n";
            $header_response = pack('vvvv', ACK_OK, 0, $session_id, $reply_id);
            $fake_users = [
                ['employee_code' => 'FG-001', 'name' => 'Fingertec User 1', 'role' => 'User'],
                ['employee_code' => 'FG-002', 'name' => 'Fingertec Admin 2', 'role' => 'Admin']
            ];
            $payload = json_encode($fake_users);
            $response_packet = $header_response . $payload;
            break;
    }

    if (!empty($response_packet)) {
        socket_sendto($socket, $response_packet, strlen($response_packet), 0, $client_ip, $client_port);
        echo " -> Sent response packet back.\n\n";
    }
}
socket_close($socket);