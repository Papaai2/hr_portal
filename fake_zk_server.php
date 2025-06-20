<?php
// in file: fake_zk_server.php
// FINAL VERSION 3: Corrected to handle the get user command.

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ob_implicit_flush();

require_once __DIR__ . '/app/core/drivers/lib/BinaryHelper.php';

$ip = '127.0.0.1';
$port = 4370;

function get_fake_zk_user_payload(): string
{
    // This function creates a fake binary payload of user data.
    $payload = '';
    // User 1: Pin 1, Name 'ZK-User-A', Role 'User'
    $payload .= pack('v', 1) . str_pad('ZK-User-A', 28, "\0") . pack('C', 0) . str_pad('', 23, "\0");
    // User 2: Pin 2, Name 'ZK-Admin-B', Role 'Admin'
    $payload .= pack('v', 2) . str_pad('ZK-Admin-B', 28, "\0") . pack('C', 14) . str_pad('', 23, "\0");
    return $payload;
}

// --- Constants ---
const CMD_CONNECT = 1000;
const CMD_EXIT = 1001;
const CMD_PREPARE_DATA = 1500; // This is the command for requesting data
const ACK_OK = 2000;

// --- Server Setup ---
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$socket || !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) || !socket_bind($socket, $ip, $port) || !socket_listen($socket)) {
    die("Could not set up the fake ZKTeco server socket on port {$port}.\nError: " . socket_strerror(socket_last_error()) . "\n");
}

echo "Fake ZKTeco Server listening on tcp://{$ip}:{$port}\nPress Ctrl+C to stop.\n\n";

while (true) {
    $client_socket = socket_accept($socket);
    if ($client_socket === false) continue;

    socket_getpeername($client_socket, $client_ip, $client_port);
    echo "[ZKTeco Server] Accepted connection from {$client_ip}:{$client_port}\n";

    $session_id = rand(1000, 9999);
    $client_connected = true;
    $client_buffer = '';

    do {
        $chunk = @socket_read($client_socket, 2048, PHP_BINARY_READ);
        if ($chunk === false || ($chunk === '' && $client_buffer === '')) {
            $client_connected = false;
            break;
        }
        if ($chunk !== '') {
            $client_buffer .= $chunk;
        }

        while (strlen($client_buffer) >= 8) {
            $packet_len = strlen($client_buffer); // Assume we process the whole buffer
            $header = BinaryHelper::parseHeader($client_buffer);
            
            if (!$header) { break; }

            $command_id = $header['command'];
            echo " -> Parsed Command ID: {$command_id}\n";
            $response_packet = '';

            switch ($command_id) {
                case CMD_CONNECT:
                    $response_packet = BinaryHelper::createHeader(ACK_OK, $session_id, $header['reply_id']);
                    break;
                
                // THIS IS THE FIX: Handle the command to prepare/request data
                case CMD_PREPARE_DATA:
                    $user_data = get_fake_zk_user_payload();
                    // The device responds with the user data directly.
                    $response_packet = BinaryHelper::createHeader(ACK_OK, $session_id, $header['reply_id'], $user_data);
                    break;
                
                case CMD_EXIT:
                    $response_packet = BinaryHelper::createHeader(ACK_OK, $session_id, $header['reply_id']);
                    $client_connected = false;
                    break;

                default:
                    $response_packet = BinaryHelper::createHeader(ACK_OK, $session_id, $header['reply_id']);
                    break;
            }

            if (!empty($response_packet)) {
                socket_write($client_socket, $response_packet, strlen($response_packet));
                echo " -> Sent response.\n";
            }
            
            $client_buffer = substr($client_buffer, $packet_len);
            if (!$client_connected) break;
        }
        usleep(50000);
    } while ($client_connected);

    @socket_close($client_socket);
    echo "[ZKTeco Server] Closed client connection.\n\n";
}

socket_close($socket);