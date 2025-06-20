<?php
// in file: app/core/drivers/lib/fingertec/TAD_PHP_Library.php

class TAD
{
    private $_ip;
    private $_port;
    private $_socket;
    private $_is_connected = false;

    // Stubs for interface compatibility
    private $_session_id = 0;
    private $_reply_id = 0;

    const CMD_EXIT = 1001;
    const CMD_GET_USER = 1501;

    public function __construct($ip, $port = 4370)
    {
        $this->_ip = $ip;
        $this->_port = $port;
        $this->_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        
        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        socket_set_option($this->_socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);

        $this->_is_connected = $this->connect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }
    
    public function isConnected(): bool
    {
        return $this->_is_connected;
    }

    public function connect(): bool
    {
        // Simplified connection that does not send a handshake packet
        if (@socket_connect($this->_socket, $this->_ip, $this->_port)) {
            $this->_session_id = 1234; 
            return true;
        }
        return false;
    }

    public function disconnect(): void
    {
        if (is_resource($this->_socket)) {
            @socket_close($this->_socket);
        }
        $this->_socket = null;
    }
    
    public function getUsers() // Renamed for clarity, called by FingertecDriver's getUsers
    {
        if (!$this->_is_connected) return false;

        // Send a very simple command string
        $command = "GET_USERS";
        @socket_send($this->_socket, $command, strlen($command), 0);
        
        $response_data = @socket_read($this->_socket, 4096);

        if (empty($response_data)) {
            return [];
        }

        // The fake server sends a JSON string, so we just decode it.
        $decoded_data = json_decode($response_data, true);
        
        return is_array($decoded_data) ? $decoded_data : [];
    }

    // Stubs for other interface methods
    public function getVersion() { return "TAD Library v1.2 (Simplified)"; }
    public function getAttendanceLogs() { return []; }
    public function addUser(array $data) { return false; }
    public function updateUser(string $employee_code, array $userData) { return false; }
    public function deleteUser(string $employee_code) { return false; }
}