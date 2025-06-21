<?php
// in file: app/core/drivers/lib/zkteco/ZKTeco.php
// FINAL CORRECTED VERSION

require_once __DIR__ . '/../BinaryHelper.php';

class ZKTeco
{
    private ?string $_ip;
    private ?int $_port;
    private $_socket; // Can be a resource or null
    private int $_session_id = 0;
    private int $_reply_id = 0;
    private bool $_is_connected = false;

    // Constants for commands
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_PREPARE_DATA = 1500;
    const ACK_OK = 2000;
    const CMD_ACK_DATA = 1502;

    public function __construct(string $ip, int $port = 4370)
    {
        $this->_ip = $ip;
        $this->_port = $port;
        $this->_socket = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function connect(): bool
    {
        if ($this->_is_connected) {
            return true;
        }

        $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->_socket === false) {
            error_log("ZKTeco socket_create() failed: " . socket_strerror(socket_last_error()));
            return false;
        }

        // Set a timeout for receiving data to prevent hangs
        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);

        if (!@socket_connect($this->_socket, $this->_ip, $this->_port)) {
            error_log("ZKTeco socket_connect() failed: " . socket_strerror(socket_last_error($this->_socket)));
            $this->disconnect();
            return false;
        }

        $this->_reply_id = 0;
        $packet = BinaryHelper::createHeader(self::CMD_CONNECT, 0, $this->_reply_id);

        if (@socket_write($this->_socket, $packet, strlen($packet)) === false) {
            error_log("ZKTeco socket_write() on connect failed.");
            $this->disconnect();
            return false;
        }
        
        $response = @socket_read($this->_socket, 1024);
        if ($response === false || strlen($response) < 8) {
            error_log("ZKTeco failed to read connection response.");
            $this->disconnect();
            return false;
        }
        
        $header = BinaryHelper::parseHeader($response);
        if ($header && $header['command'] === self::ACK_OK) {
            $this->_session_id = $header['session_id'];
            $this->_is_connected = true;
            return true;
        }
        
        error_log("ZKTeco connection rejected by device.");
        $this->disconnect();
        return false;
    }

    public function disconnect(): void
    {
        if ($this->_is_connected && is_resource($this->_socket)) {
            try {
                // Politely tell the device we are disconnecting.
                $packet = BinaryHelper::createHeader(self::CMD_EXIT, $this->_session_id, ++$this->_reply_id);
                @socket_write($this->_socket, $packet, strlen($packet));
            } finally {
                // Ensure the socket is always closed.
                @socket_close($this->_socket);
            }
        }
        $this->_socket = null;
        $this->_is_connected = false;
    }

    public function getUser(): array
    {
        if (!$this->_is_connected || !is_resource($this->_socket)) return [];
        
        $this->_reply_id++;
        $command_string = 'C:1:SELECT * FROM USER';
        $packet = BinaryHelper::createHeader(self::CMD_PREPARE_DATA, $this->_session_id, $this->_reply_id, $command_string);
        
        if (@socket_write($this->_socket, $packet, strlen($packet)) === false) {
             error_log("ZKTeco getUser write failed.");
             return [];
        }

        // Read all data chunks from the device
        $response_buffer = '';
        while($chunk = @socket_read($this->_socket, 8192)) {
            $response_buffer .= $chunk;
        }
        
        if (strlen($response_buffer) > 8) {
            $header = BinaryHelper::parseHeader($response_buffer);
            // ZKTeco sends data in a CMD_ACK_DATA packet which contains the actual user data payload
            if ($header && ($header['command'] === self::ACK_OK || $header['command'] === self::CMD_ACK_DATA)) {
                $payload = substr($response_buffer, 16); // Data starts after the full header
                return BinaryHelper::parseZkUserData($payload);
            }
        }
        return [];
    }
}