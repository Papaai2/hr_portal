<?php
// in file: fake_device_server.php
// A more realistic TCP server that understands the binary handshake.

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ob_implicit_flush();

$ip = '127.0.0.1';
$port = 8099; // Using a distinct port for Fingertec testing.

// --- Helper to create a response packet ---
function create_response_header($command, $session_id, $reply_id) {
    return pack('HHHH', $command, 0, $session_id, $reply_id);
}

// --- FAKE DATA ---
$fake_users_json = json_encode([
    ['employee_code' => 'FT-001', 'name' => 'Fingertec TCP User 1', 'role' => 'User'],
    ['employee_code' => 'FT-002', 'name' => 'Fingertec TCP Admin 2', 'role' => 'Admin']
]);

$fake_attendance_json = json_encode([
    ['employee_code' => 'FT-001', 'timestamp' => date('Y-m-d H:i:s', strtotime('-5 hours')), 'type' => 'punch-in'],
    ['employee_code' => 'FT-001', 'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')), 'type' => 'punch-out']
]);
// --- END FAKE DATA ---

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$socket || !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) || !socket_bind($socket, $ip, $port) || !socket_listen($socket)) {
    die("Failed to set up the fake server socket.\n");
}

echo "Fake Fingertec TCP Server listening on tcp://{$ip}:{$port}\nPress Ctrl+C to stop.\n\n";

do {
    $client_socket = socket_accept($socket);
    if (!$client_socket) continue;

    echo "New client connected.\n";
    $session_id = rand(100, 1000);
    $client_connected = true;

    do {
        $buffer = @socket_read($client_socket, 1024, PHP_BINARY_READ);
        if ($buffer === false || $buffer === '') {
            $client_connected = false;
            break;
        }

        // --- FIX ---
        // This check ensures we don't try to unpack an incomplete packet, which causes the warnings.
        if (strlen($buffer) < 8) {
            continue;
        }

        // Unpack the header from the client's request
        $header = unpack('H4command/H4checksum/H4sessionId/H4replyId', substr($buffer, 0, 8));
        $command_id = hexdec($header['command']);
        $reply_id = hexdec($header['replyId']);
        
        echo "Received command ID: {$command_id}\n";
        $response_packet = '';

        switch ($command_id) {
            case 1000: // CMD_CONNECT
                $response_packet = create_response_header(2000, $session_id, $reply_id); // Respond with ACK_OK
                break;
            
            case 1500: // CMD_PREPARE_DATA
                $data_payload = substr($buffer, 8);
                
                if (strpos($data_payload, 'GET_USERS') !== false) {
                    echo " -> Client requested user data.\n";
                    $response_packet = create_response_header(1502, $session_id, $reply_id) . $fake_users_json;
                    $response_packet .= create_response_header(2000, $session_id, $reply_id);
                } elseif (strpos($data_payload, 'GET_ATTENDANCE') !== false) {
                    echo " -> Client requested attendance data.\n";
                    $response_packet = create_response_header(1502, $session_id, $reply_id) . $fake_attendance_json;
                    $response_packet .= create_response_header(2000, $session_id, $reply_id);
                }
                break;

            case 1001: // CMD_EXIT
                $response_packet = create_response_header(2000, $session_id, $reply_id);
                $client_connected = false;
                break;
        }

        if (!empty($response_packet)) {
            socket_write($client_socket, $response_packet, strlen($response_packet));
            echo " -> Sent response.\n";
        }

    } while ($client_connected);

    socket_close($client_socket);
    echo "Client disconnected.\n\n";

} while (true);

socket_close($socket);