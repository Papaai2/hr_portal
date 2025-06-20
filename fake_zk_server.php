<?php
// in file: fake_zk_server.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$ip = '127.0.0.1';
$port = 4371; // Use a different port to avoid conflicts

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$socket) die("Could not create TCP socket\n");

socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

if (!socket_bind($socket, $ip, $port)) die("Could not bind to TCP address {$ip}:{$port}\n");
if (!socket_listen($socket)) die("Could not listen on TCP socket\n");

echo "Fake ZKTeco Server (TCP) listening on tcp://{$ip}:{$port}\nPress Ctrl+C to stop.\n\n";

const CMD_CONNECT = 1000;
const CMD_EXIT = 1001;
const CMD_VERSION = 1100;
const CMD_GET_USER = 1501; // Command to get users
const ACK_OK = 2000;

while (true) {
    $client_socket = socket_accept($socket);
    if ($client_socket === false) continue;
    
    socket_getpeername($client_socket, $client_ip, $client_port);
    echo "Accepted a new TCP connection from {$client_ip}:{$client_port}\n";

    $session_id = rand(1000, 9999);
    $client_connected = true;

    do {
        $buffer = @socket_read($client_socket, 1024);
        if ($buffer === false || strlen($buffer) < 8) {
            $client_connected = false;
            break;
        }

        echo " -> Received " . strlen($buffer) . " bytes. HEX: " . bin2hex($buffer) . "\n";
        
        $header = unpack('vcommand/vchecksum/vsession_id/vreply_id', $buffer);
        $command_id = $header['command'];
        $reply_id = $header['reply_id'];

        echo " -> Parsed Command ID: {$command_id}\n";
        
        $response_packet = '';

        switch ($command_id) {
            case CMD_CONNECT:
                echo " -> Action: Responding to Connection Request.\n";
                $response_packet = pack('vvvv', ACK_OK, 0, $session_id, $reply_id);
                break;
            
            case CMD_VERSION:
                echo " -> Action: Responding with Fake Version.\n";
                $header_response = pack('vvvv', ACK_OK, 0, $session_id, $reply_id);
                $payload = "Ver 7.80 (FakeTCP)";
                $response_packet = $header_response . $payload;
                break;

            case CMD_GET_USER:
                echo " -> Action: Responding with Fake ZKTeco User List.\n";
                $header_response = pack('vvvv', ACK_OK, 0, $session_id, $reply_id);
                $fake_users = [
                    ['employee_code' => 'ZK-001', 'name' => 'ZKTeco User A', 'role' => 'User'],
                    ['employee_code' => 'ZK-002', 'name' => 'ZKTeco Admin B', 'role' => 'Admin']
                ];
                $payload = json_encode($fake_users);
                $response_packet = $header_response . $payload;
                break;

            case CMD_EXIT:
                echo " -> Action: Received Exit command. Closing connection.\n";
                $client_connected = false;
                break;

            default:
                echo " -> Action: Unknown Command. Sending generic ACK.\n";
                $response_packet = pack('vvvv', ACK_OK, 0, $session_id, $reply_id);
                break;
        }

        if (!empty($response_packet)) {
            socket_write($client_socket, $response_packet, strlen($response_packet));
            echo " -> Sent response packet back.\n";
        }

    } while ($client_connected);
    
    @socket_close($client_socket);
    echo "Closed client connection.\n\n";
}

socket_close($socket);