<?php
// in file: fake_device_server.php
// DEBUGGING VERSION

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ob_implicit_flush();

require_once __DIR__ . '/app/core/drivers/lib/BinaryHelper.php';

$ip = '127.0.0.1';
$port = 8099;

function get_fake_fingertec_user_payload(): string {
    // Return a fake user payload as a binary string (example data)
    // You should replace this with the actual payload structure as needed
    return pack('A8A24A8A8A8', '12345678', 'John Doe', '00000001', 'admin', 'active');
}

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$socket || !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) || !socket_bind($socket, $ip, $port) || !socket_listen($socket)) {
    die("Failed to set up the fake Fingertec server socket on port {$port}.\nError: " . socket_strerror(socket_last_error()) . "\n");
}

echo "Fake Fingertec Server listening on tcp://{$ip}:{$port}\nPress Ctrl+C to stop.\n\n";

do {
    $client_socket = socket_accept($socket);
    if (!$client_socket) continue;

    echo "[Fingertec Server] New client connected.\n";
    $session_id = rand(100, 1000);
    $client_connected = true;
    $client_buffer = '';

    do {
        $chunk = @socket_read($client_socket, 4096, PHP_BINARY_READ);
        if ($chunk === false) {
             echo " -> [DEBUG] socket_read returned false. Closing connection.\n";
            $client_connected = false;
            break;
        }
        if ($chunk !== '') {
            echo " -> [DEBUG] Received " . strlen($chunk) . " bytes.\n";
            $client_buffer .= $chunk;
        }

        // Only try to process if we have data
        if (strlen($client_buffer) > 0) {
            echo " -> [DEBUG] Processing buffer of size " . strlen($client_buffer) . ".\n";
            while (strlen($client_buffer) >= 8) {
                echo " -> [DEBUG] Buffer has enough for a header. Entering process loop.\n";
                $packet_len = 8;
                $header = BinaryHelper::parseHeader($client_buffer);
                
                if (!$header) {
                    echo " -> [DEBUG] ERROR: Failed to parse header.\n";
                    $client_buffer = '';
                    break;
                }

                $command_id = $header['command'];
                echo " -> [DEBUG] Parsed Command ID: {$command_id}\n";
                
                if ($command_id === 1500) { // CMD_PREPARE_DATA
                    $payload = substr($client_buffer, 8);
                    $payload_end = strpos($payload, "\0");
                    if ($payload_end !== false) {
                        $packet_len += $payload_end + 1;
                    }
                }

                if (strlen($client_buffer) < $packet_len) {
                    echo " -> [DEBUG] Incomplete packet. Waiting for more data.\n";
                    break; 
                }
                
                $response_packet = '';
                switch ($command_id) {
                    case 1000: // CMD_CONNECT
                        $response_packet = BinaryHelper::createHeader(2000, $session_id, $header['reply_id']);
                        break;
                    case 1500: // CMD_PREPARE_DATA
                        $user_data = get_fake_fingertec_user_payload();
                        $data_response = BinaryHelper::createHeader(1502, $session_id, $header['reply_id'], $user_data);
                        $final_ack = BinaryHelper::createHeader(2000, $session_id, $header['reply_id']);
                        $response_packet = $data_response . $final_ack;
                        break;
                    case 1001: // CMD_EXIT
                        $response_packet = BinaryHelper::createHeader(2000, $session_id, $header['reply_id']);
                        $client_connected = false;
                        break;
                    default:
                        $response_packet = BinaryHelper::createHeader(2000, $session_id, $header['reply_id']);
                        break;
                }

                if (!empty($response_packet)) {
                    socket_write($client_socket, $response_packet, strlen($response_packet));
                    echo " -> [DEBUG] Sent response packet.\n";
                }
                
                $client_buffer = substr($client_buffer, $packet_len);
                echo " -> [DEBUG] Sliced buffer. Remaining size: " . strlen($client_buffer) . ".\n";
                
                if (!$client_connected) break;
            }
        }
        usleep(50000); // Prevent high CPU usage
    } while ($client_connected);

    @socket_close($client_socket);
    echo "[Fingertec Server] Client connection loop ended.\n\n";
} while (true);

socket_close($socket);