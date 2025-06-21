<?php
// fake_zk_server.php - Simulates a real ZKTeco device

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

require_once __DIR__ . '/app/core/drivers/lib/BinaryHelper.php';

$ip = '127.0.0.1';
$port = 8100;

function get_user_payload(): string {
    // FIXED: The 'pack' format now includes all fields for a full 72-byte record,
    // and the arguments are passed in the correct order to match the parser.
    // Format: pin(v), privilege(c), password(a8), name(a24), cardno(a8), group(c), timezones(a24), pincode2(a4)
    $format = 'vca8a24a8ca24a4';

    $user1_data = pack($format, 1, 14, '123', 'ZK Admin (Fake)', '1001', '1', '', '');
    $user2_data = pack($format, 2, 0, '', 'ZK User (Fake)', '1002', '1', '', '');

    return "\x01\x00\x00\x00" . $user1_data . $user2_data;
}

echo "Fake ZKTeco Server (Rewritten) listening on tcp://{$ip}:{$port}\n";
echo "2 fake users loaded into memory.\n\n";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $ip, $port);
socket_listen($socket);

const CMD_CONNECT = 1000;
const CMD_EXIT = 1001;
const CMD_PREPARE_DATA = 1500;
const ACK_OK = 2000;

while ($client_socket = socket_accept($socket)) {
    echo "Accepted connection.\n";
    $session_id = rand(1000, 9999);

    do {
        $buffer = @socket_read($client_socket, 1024, PHP_BINARY_READ);
        if ($buffer === false || empty($buffer)) {
            break;
        }
        
        $header = BinaryHelper::parseHeader($buffer);
        if (!$header) continue;

        $response_packet = '';
        $reply_id = $header['reply_id'];

        switch ($header['command']) {
            case CMD_CONNECT:
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
                break;
            case CMD_PREPARE_DATA:
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id, get_user_payload());
                break;
            case CMD_EXIT:
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
                break;
        }

        if ($response_packet) {
            socket_write($client_socket, $response_packet, strlen($response_packet));
        }
        if ($header['command'] === CMD_EXIT) break;

    } while (true);
    echo "Closed connection.\n\n";
    @socket_close($client_socket);
}