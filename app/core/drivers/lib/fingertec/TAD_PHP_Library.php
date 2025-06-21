<?php
// in file: app/core/drivers/lib/fingertec/TAD_PHP_Library.php
// FINAL, COMPLETE, DYNAMIC VERSION

require_once __DIR__ . '/../BinaryHelper.php';

class TAD
{
    private $_ip;
    private $_port;
    private $_socket;
    private $_session_id = 0;
    private $_reply_id = 0;
    private $_is_connected = false;

    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ACK_OK = 2000;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1502;
    const CMD_USER_WRQ = 8;

    public function __construct($ip, $port = 80, $com_key = 0)
    {
        $this->_ip = $ip;
        $this->_port = $port;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function connect(): bool
    {
        $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->_socket) return false;
        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 5, "usec" => 0]);
        if (!@socket_connect($this->_socket, $this->_ip, $this->_port)) {
            $this->disconnect();
            return false;
        }
        $this->_reply_id = 0;
        $this->_session_id = 0;
        $packet = BinaryHelper::createHeader(self::CMD_CONNECT, 0, 0);
        if (!@socket_write($this->_socket, $packet, strlen($packet))) {
            $this->disconnect();
            return false;
        }
        $response = @socket_read($this->_socket, 1024);
        if (!$response) {
            $this->disconnect();
            return false;
        }
        $header = BinaryHelper::parseHeader($response);
        if (!$header || $header['command'] != self::CMD_ACK_OK) {
            $this->disconnect();
            return false;
        }
        $this->_session_id = $header['session_id'];
        $this->_is_connected = true;
        return true;
    }

    public function disconnect(): void
    {
        if ($this->_socket && $this->_is_connected) {
            $packet = BinaryHelper::createHeader(self::CMD_EXIT, $this->_session_id, ++$this->_reply_id);
            @socket_write($this->_socket, $packet, strlen($packet));
        }
        if (is_resource($this->_socket)) {
            socket_close($this->_socket);
        }
        $this->_socket = false;
        $this->_is_connected = false;
    }
    
    public function isConnected(): bool
    {
        return $this->_is_connected;
    }

    private function downloadData(string $command_string): string
    {
        $this->_reply_id++;
        $packet = BinaryHelper::createHeader(self::CMD_PREPARE_DATA, $this->_session_id, $this->_reply_id, $command_string . "\0");
        @socket_write($this->_socket, $packet, strlen($packet));
        $response_buffer = @socket_read($this->_socket, 65535);
        if (!$response_buffer || strlen($response_buffer) < 16) return '';
        return substr($response_buffer, 8, -8);
    }

    public function getUsers(): array
    {
        if (!$this->isConnected()) return [];
        $payload = $this->downloadData('GET_USERS');
        return BinaryHelper::parseFingertecUserData($payload);
    }

    public function setUser(array $userData): bool
    {
        if (!$this->isConnected()) return false;
        $this->_reply_id++;
        $hex_pin = sprintf('%04X', (int)$userData['employee_code']);
        $user_packet = pack('H4ca8a24', 
            $hex_pin,
            ($userData['role'] === 'Admin') ? 14 : 0,
            $userData['password'] ?? '',
            $userData['name']
        );
        $user_packet = str_pad($user_packet, 72, "\0"); 
        $packet = BinaryHelper::createHeader(self::CMD_USER_WRQ, $this->_session_id, $this->_reply_id, $user_packet);
        @socket_write($this->_socket, $packet, strlen($packet));
        $response = @socket_read($this->_socket, 1024);
        if ($response) {
            $header = BinaryHelper::parseHeader($response);
            return ($header && $header['command'] === self::CMD_ACK_OK);
        }
        return false;
    }
}