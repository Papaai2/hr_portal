<?php
// in file: app/core/drivers/lib/zkteco/ZKTeco.php
// FINAL REVERTED VERSION (with corrected disconnect method)

require_once __DIR__ . '/../BinaryHelper.php';

class ZKTeco
{
    private ?string $_ip;
    private ?int $_port;
    private $_socket;
    private int $_session_id = 0;
    private int $_reply_id = 0;

    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_PREPARE_DATA = 1500;
    const ACK_OK = 2000;

    public function __construct(string $ip, int $port = 4370)
    {
        $this->_ip = $ip;
        $this->_port = $port;
        $this->_socket = false;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function connect(): bool
    {
        $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->_socket) {
            return false;
        }
        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
        if (!@socket_connect($this->_socket, $this->_ip, $this->_port)) {
            $this->disconnect();
            return false;
        }
        $this->_reply_id = 0;
        $packet = BinaryHelper::createHeader(self::CMD_CONNECT, 0, $this->_reply_id);
        if (!@socket_write($this->_socket, $packet, strlen($packet))) {
            $this->disconnect();
            return false;
        }
        $response = @socket_read($this->_socket, 1024);
        if (strlen($response ?? '') < 8) {
            $this->disconnect();
            return false;
        }
        $header = BinaryHelper::parseHeader($response);
        if (($header['command'] ?? 0) === self::ACK_OK) {
            $this->_session_id = $header['session_id'];
            return true;
        }
        $this->disconnect();
        return false;
    }

    public function disconnect(): void
    {
        // THE FIX IS HERE: Correctly checks for and closes the $_socket property.
        if (is_resource($this->_socket)) {
            @socket_close($this->_socket);
            $this->_socket = null;
        }
    }

    public function getUser(): array
    {
        if (!$this->_socket) return [];
        $this->_reply_id++;
        $command_string = 'C:1:SELECT * FROM USER';
        $packet = BinaryHelper::createHeader(self::CMD_PREPARE_DATA, $this->_session_id, $this->_reply_id, $command_string);
        @socket_write($this->_socket, $packet, strlen($packet));
        $response = @socket_read($this->_socket, 8192);
        if (strlen($response ?? '') > 8) {
            $header = BinaryHelper::parseHeader($response);
            if ($header && $header['command'] === self::ACK_OK) {
                $payload = substr($response, 8);
                return BinaryHelper::parseZkUserData($payload);
            }
        }
        return [];
    }
}