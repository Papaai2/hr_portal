<?php
// in file: app/core/drivers/lib/fingertec/TAD_PHP_Library.php

class TAD
{
    private $_ip;
    private $_port;
    private $_socket;
    private $_session_id = 0;
    private $_reply_id = 0;
    
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_GET_USER = 1501;
    const ACK_OK = 2000;

    public function __construct($ip, $port = 4370)
    {
        $this->_ip = $ip;
        $this->_port = $port;
    }

    public function isConnected(): bool
    {
        return $this->_session_id > 0;
    }

    public function connect(): bool
    {
        $this->_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$this->_socket) return false;

        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        
        if (!@socket_connect($this->_socket, $this->_ip, $this->_port)) {
            @socket_close($this->_socket);
            return false;
        }

        // Perform a real handshake: send a connect command and wait for a reply.
        $this->_send_command(self::CMD_CONNECT);
        $response = @socket_read($this->_socket, 1024);
        
        if (strlen($response ?? '') < 8) {
            $this->disconnect();
            return false;
        }

        $header = unpack('vcommand/vchecksum/vsession_id/vreply_id', $response);

        if ($header['command'] === self::ACK_OK) {
            $this->_session_id = $header['session_id'];
            return true;
        }

        $this->disconnect();
        return false;
    }

    public function disconnect(): void
    {
        if (is_resource($this->_socket)) {
            if ($this->isConnected()) {
                $this->_send_command(self::CMD_EXIT);
            }
            @socket_close($this->_socket);
        }
        $this->_socket = null;
        $this->_session_id = 0;
    }
    
    public function getUsers()
    {
        if (!$this->isConnected()) return false;

        $this->_send_command(self::CMD_GET_USER);
        $response_data = @socket_read($this->_socket, 4096);

        if (empty($response_data)) return [];

        $payload = substr($response_data, 8);
        $decoded_data = json_decode($payload, true);
        
        return is_array($decoded_data) ? $decoded_data : [];
    }
    
    private function _send_command($command, $data = '')
    {
        $this->_reply_id++;
        $buf = pack('vvvv', $command, 0, $this->_session_id, $this->_reply_id) . $data;
        @socket_send($this->_socket, $buf, strlen($buf), 0);
    }

    // Stubs for other interface methods
    public function getVersion() { return "TAD Library v2.0 (Real)"; }
    public function addUser(array $data) { return false; }
    public function updateUser(string $employee_code, array $userData) { return false; }
    public function deleteUser(string $employee_code) { return false; }
    public function getAttendanceLogs() { return []; }
}