<?php
// fake_zk_server.php - Simulates a real ZKTeco device

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

require_once __DIR__ . '/app/core/drivers/lib/BinaryHelper.php';

$ip = '127.0.0.1';
$port = 8100;

// Simulate device storage
$users = [
    '1' => ['user_id' => '1', 'name' => 'ZK Admin (Fake)', 'privilege' => 14, 'password' => '123', 'card_id' => 0, 'group_id' => 0],
    '2' => ['user_id' => '2', 'name' => 'ZK User (Fake)', 'privilege' => 0, 'password' => '', 'card_id' => 0, 'group_id' => 0],
];
$attendance_logs = [];

function get_user_payload(array $users_data): string {
    $payload = "\x01\x00\x00\x00"; // ZKTeco specific prefix
    foreach ($users_data as $user) {
        $payload .= str_pad(pack(
            'vCa8a24a8c', // PIN, Privilege, Password, Name, CardNo, Group
            (int)$user['user_id'],
            (int)$user['privilege'],
            str_pad($user['password'], 8, "\0"),
            str_pad($user['name'], 24, "\0"),
            str_pad((string)$user['card_id'], 8, "\0"),
            (int)($user['group_id'] ?? 0)
        ), 72, "\0"); // ZKTeco user record size is 72 bytes
    }
    return $payload;
}

function get_attendance_payload(array $logs_data): string {
    $payload = "\x01\x00\x00\x00"; // ZKTeco specific prefix
    foreach ($logs_data as $log) {
        $payload .= pack(
            'a24VCV', // user_id, timestamp, status, verification, reserved
            str_pad($log['employee_code'], 24, "\0"),
            strtotime($log['punch_time']),
            (int)($log['status'] ?? 0),
            (int)($log['verification'] ?? 1),
            (int)($log['reserved'] ?? 0)
        );
    }
    return $payload;
}

echo "Fake ZKTeco Server (Final) listening on tcp://{$ip}:{$port}\n";
echo "Simulating dynamic user and attendance data.\n\n";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $ip, $port);
socket_listen($socket);

const CMD_CONNECT = 1000;
const CMD_EXIT = 1001;
const CMD_PREPARE_DATA = 1500;
const CMD_ATTLOG_RRQ = 13;
const ACK_OK = 2000;
const CMD_USER_WRQ = 8; // Write User Record (Add/Update)
const CMD_CLEAR_ATTLOG = 1009; // Clear Attendance Logs
const CMD_DELETE_USER = 1004; // Delete User

while ($client = socket_accept($socket)) {
    echo "Accepted connection.\n";
    $session_id = rand(1000, 9999);
    $reply_id = 0;
    $keep_alive = true;

    while ($keep_alive) {
        $buffer = @socket_read($client, 4096, PHP_BINARY_READ);
        if ($buffer === false || $buffer === "") {
            echo "Client disconnected or no more data.\n";
            $keep_alive = false;
            break;
        }

        $header = BinaryHelper::parseHeader($buffer);
        if (!$header) {
            echo "Invalid header received. Closing connection.\n";
            $keep_alive = false;
            break;
        }

        $reply_id = $header['reply_id'];
        $command = $header['command'];
        $response_packet = '';

        echo "Received command: " . $command . " (Reply ID: {$reply_id})\n";

        switch ($command) {
            case CMD_CONNECT:
                echo "Handshake request received. Sending ACK.\n";
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
                break;
            case CMD_PREPARE_DATA:
                echo "User data request received. Sending payload.\n";
                global $users; // Access global users array
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id, get_user_payload($users));
                break;
            case CMD_ATTLOG_RRQ:
                echo "Attendance log request received. Sending payload.\n";
                global $attendance_logs; // Access global attendance_logs array
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id, get_attendance_payload($attendance_logs));
                break;
            case CMD_USER_WRQ:
                echo "User write request received.\n";
                $payload = substr($buffer, 10); // Data starts after the full header
                $user_data = unpack('vpin/cprivilege/a8password/a24name/a8cardno/cgroup', $payload);
                
                if ($user_data) {
                    $user_id = $user_data['pin'];
                    $users[$user_id] = [
                        'user_id' => $user_id,
                        'name' => trim(preg_replace('/[\x00-\x1F\x7F]/', '', $user_data['name'])),
                        'privilege' => $user_data['privilege'],
                        'password' => trim($user_data['password']),
                        'card_id' => intval(trim($user_data['cardno'])),
                        'group_id' => $user_data['group'],
                    ];
                    echo "User {$user_id} added/updated in fake storage.\n";
                    $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
                } else {
                    echo "Failed to parse user data for write.\n";
                    // Send an error ACK if parsing fails
                    $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id); // ZKTeco often sends ACK_OK even for failures, or a specific error code
                }
                break;
            case CMD_DELETE_USER:
                echo "Delete user request received.\n";
                $payload = substr($buffer, 10);
                $user_id_to_delete = unpack('V', $payload)[1];
                if (isset($users[$user_id_to_delete])) {
                    unset($users[$user_id_to_delete]);
                    echo "User {$user_id_to_delete} deleted from fake storage.\n";
                    $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
                } else {
                    echo "User {$user_id_to_delete} not found for deletion.\n";
                    $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
                }
                break;
            case CMD_CLEAR_ATTLOG:
                echo "Clear attendance log request received.\n";
                $attendance_logs = []; // Clear all logs
                echo "Attendance logs cleared in fake storage.\n";
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
                break;
            case CMD_EXIT:
                echo "Received exit command. Closing connection.\n";
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id);
                $keep_alive = false;
                break;
            default:
                echo "Unknown command: " . $command . "\n";
                $response_packet = BinaryHelper::createPacket(ACK_OK, $session_id, $reply_id); // Default ACK for unknown commands
                break;
        }

        if ($response_packet) {
            @socket_write($client, $response_packet, strlen($response_packet));
        }
    }
    @socket_close($client);
    echo "Closed connection.\n\n";
}