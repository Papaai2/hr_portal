<?php
// in file: fake_zk_server.php
// FINAL, COMPLETE, DYNAMIC VERSION

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

require_once __DIR__ . '/app/core/drivers/lib/BinaryHelper.php';

$ip = '127.0.0.1';
$port = 8100; // Fake ZKTeco server port for testing
$user_db_file = __DIR__ . '/tmp/zk_users.json';

function load_users(string $file): array {
    if (!file_exists($file)) {
        return [
            '1' => ['pin' => 1, 'privilege' => 14, 'name' => 'ZK Admin (Default)', 'password' => '123', 'card_number' => ''],
            '2' => ['pin' => 2, 'privilege' => 0, 'name' => 'ZK User (Default)', 'password' => '', 'card_number' => '']
        ];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save_users(string $file, array $users): void {
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

function get_user_payload(array $users): string {
    $payload = '';
    foreach ($users as $user) {
        $record = pack('vca24a8a4', $user['pin'], $user['privilege'], $user['name'], $user['password'], $user['card_number']);
        $payload .= str_pad($record, 72, "\0");
    }
    return $payload;
}

echo "Fake ZKTeco Server (Dynamic) listening on tcp://{$ip}:{$port}\n";
$users = load_users($user_db_file);
save_users($user_db_file, $users);
echo count($users) . " users loaded into memory.\n\n";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $ip, $port);
socket_listen($socket);

const CMD_CONNECT = 1000;
const CMD_EXIT = 1001;
const CMD_PREPARE_DATA = 1500;
const ACK_OK = 2000;
const CMD_USER_WRQ = 8;

while (true) {
    $client_socket = socket_accept($socket);
    echo "Accepted connection.\n";
    $session_id = rand(1000, 9999);

    do {
        $buffer = @socket_read($client_socket, 8192, PHP_BINARY_READ);
        if ($buffer === false || empty($buffer)) {
            break;
        }
        
        $header = BinaryHelper::parseHeader($buffer);
        if (!$header) continue;

        $command_id = $header['command'];
        $response_packet = '';

        switch ($command_id) {
            case CMD_CONNECT:
                $response_packet = BinaryHelper::createHeader(ACK_OK, $session_id, $header['reply_id']);
                break;
            case CMD_PREPARE_DATA:
                $users = load_users($user_db_file);
                $response_packet = BinaryHelper::createHeader(ACK_OK, $session_id, $header['reply_id'], get_user_payload($users));
                break;
            case CMD_USER_WRQ:
                $user_data_raw = substr($buffer, 8);
                $user_data = unpack('vpin/cprivilege/a24name', $user_data_raw);
                $users = load_users($user_db_file);
                $users[$user_data['pin']] = ['pin' => $user_data['pin'], 'privilege' => $user_data['privilege'], 'name' => trim($user_data['name']), 'password' => '', 'card_number' => ''];
                save_users($user_db_file, $users);
                echo "User {$user_data['pin']} synced. Total users: " . count($users) . "\n";
                $response_packet = BinaryHelper::createHeader(ACK_OK, $session_id, $header['reply_id']);
                break;
            case CMD_EXIT:
                $response_packet = BinaryHelper::createHeader(ACK_OK, $session_id, $header['reply_id']);
                break;
        }

        if ($response_packet) {
            socket_write($client_socket, $response_packet, strlen($response_packet));
        }
        if ($command_id === CMD_EXIT) break;

    } while (true);
    echo "Closed connection.\n\n";
    @socket_close($client_socket);
}