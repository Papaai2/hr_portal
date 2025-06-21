<?php
// fake_zk_server.php - Simulates a real ZKTeco device

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

require_once __DIR__ . '/app/core/drivers/lib/BinaryHelper.php';

$ip = '127.0.0.1';
$port = 8100;

function get_user_payload(): string {
    $user1_data = str_pad(pack('vca24a8', 1, 14, 'ZK Admin (Fake)', '123'), 72, "\0");
    $user2_data = str_pad(pack('vca24a8', 2, 0, 'ZK User (Fake)', ''), 72, "\0");
    return "\x01\x00\x00\x00" . $user1_data . $user2_data;
}

function get_attendance_payload(): string {
    $time1 = strtotime('2025-06-21 09:01:15');
    $time2 = strtotime('2025-06-21 17:05:30');
    
    // FIXED: Using employee IDs '1' and '2' to match the users from get_user_payload()
    $record1 = pack('a24VCV', '1', $time1, 0, 1, 0); 
    $record2 = pack('a24VCV', '2', $time2, 1, 1, 0);

    return "\x01\x00\x00\x00" . $record1 . $record2;
}


echo "Fake ZKTeco Server (Final) listening on tcp://{$ip}:{$port}\n";
echo "2 fake users and 2 fake attendance logs loaded.\n\n";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $ip, $port);
socket_listen($socket);

const CMD_CONNECT = 1000;
const CMD_EXIT = 1001;
const CMD_PREPARE_DATA = 1500;
const CMD_ATTLOG_RRQ = 13;
const ACK_OK = 2000;

while ($client = socket_accept($socket)) {
    echo "Accepted connection.\n";
    $session_id = rand(1000, 9999);
    $reply_id = 0;
    
    $buffer = @socket_read($client, 1024, PHP_BINARY_READ);
    if (!$buffer) { @socket_close($client); continue; }

    $header = BinaryHelper::parseHeader($buffer);
    if (!$header) { @socket_close($client); continue; }

    $reply_id = $header['reply_id'];
    $response_packet = '';
    
    if ($header['command'] === CMD_CONNECT) {
        echo "Handshake request received. Sending ACK.\n";
        $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
        socket_write($client, $response_packet, strlen($response_packet));
        
        $buffer = @socket_read($client, 1024, PHP_BINARY_READ);
        if (!$buffer) { @socket_close($client); continue; }
        $header = BinaryHelper::parseHeader($buffer);
        if (!$header) { @socket_close($client); continue; }
        $reply_id = $header['reply_id'];
    }

    switch ($header['command']) {
        case CMD_PREPARE_DATA:
            echo "User data request received. Sending payload.\n";
            $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id, get_user_payload());
            break;
        case CMD_ATTLOG_RRQ:
            echo "Attendance log request received. Sending payload.\n";
            $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id, get_attendance_payload());
            break;
        case CMD_EXIT:
            $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
            break;
    }

    if ($response_packet) socket_write($client, $response_packet, strlen($response_packet));
    
    echo "Closing connection.\n\n";
    @socket_close($client);
}