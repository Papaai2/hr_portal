<?php
// in file: app/core/drivers/lib/fingertec/TAD_PHP_Library.php
// FINAL, COMPLETE, DYNAMIC VERSION

require_once __DIR__ . '/../BinaryHelper.php';

class TAD
{
    private ?string $_ip;
    private ?int $_port;
    private $_socket; // Can be a resource or false
    private int $_session_id = 0;
    private int $_reply_id = 0;
    private bool $_is_connected = false;

    // Constants for commands
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ACK_OK = 2000;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1502;
    const CMD_USER_WRQ = 8; // Write User Record

    public function __construct(string $ip, int $port = 80)
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
        if ($this->_is_connected) {
            return true;
        }

        $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->_socket === false) {
            error_log("TAD socket_create() failed: " . socket_strerror(socket_last_error()));
            return false;
        }

        // Set a timeout for receiving data to prevent hangs
        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 10, "usec" => 0]);
        
        if (!@socket_connect($this->_socket, $this->_ip, $this->_port)) {
            error_log("TAD socket_connect() failed: " . socket_strerror(socket_last_error($this->_socket)));
            $this->disconnect();
            return false;
        }

        $this->_reply_id = 0;
        $this->_session_id = 0;
        $packet = BinaryHelper::createHeader(self::CMD_CONNECT, 0, 0);

        if (@socket_write($this->_socket, $packet, strlen($packet)) === false) {
            error_log("TAD socket_write() failed on connect.");
            $this->disconnect();
            return false;
        }

        $response = @socket_read($this->_socket, 1024);
        if ($response === false || strlen($response) < 8) {
            error_log("TAD failed to read connection response.");
            $this->disconnect();
            return false;
        }

        $header = BinaryHelper::parseHeader($response);
        if (!$header || $header['command'] != self::CMD_ACK_OK) {
            error_log("TAD connection rejected by device.");
            $this->disconnect();
            return false;
        }

        $this->_session_id = $header['session_id'];
        $this->_is_connected = true;
        return true;
    }

    public function disconnect(): void
    {
        if ($this->_is_connected && is_resource($this->_socket)) {
            try {
                $packet = BinaryHelper::createHeader(self::CMD_EXIT, $this->_session_id, ++$this->_reply_id);
                @socket_write($this->_socket, $packet, strlen($packet));
            } finally {
                socket_close($this->_socket);
            }
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
        if (!$this->isConnected()) return '';
        $this->_reply_id++;
        $packet = BinaryHelper::createHeader(self::CMD_PREPARE_DATA, $this->session_id, $this->reply_id, $command_string . "\0");
        
        if (@socket_write($this->_socket, $packet, strlen($packet)) === false) return '';
        
        // Read all data until socket is closed by the device or timeout
        $response_buffer = '';
        while($chunk = @socket_read($this->_socket, 65535)) {
            $response_buffer .= $chunk;
        }

        if (strlen($response_buffer) < 16) return '';
        
        // Remove header and footer from data chunk
        return substr($response_buffer, 8, -8); 
    }

    public function getUsers(): array
    {
        // This command is specific to ZKTeco and might not work on Fingertec
        // For Fingertec, you'd use their text-based protocol.
        // This library seems to be using the ZKTeco binary protocol.
        $payload = $this->downloadData('C:1:SELECT * FROM USER');
        return BinaryHelper::parseZkUserData($payload);
    }
}