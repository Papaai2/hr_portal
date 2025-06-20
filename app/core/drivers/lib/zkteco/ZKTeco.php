<?php
// in file: app/core/drivers/lib/zkteco/ZKTeco.php

class ZKTeco
{
    private $ip;
    private $port;
    private $socket;
    public $session_id = 0;
    private $_reply_id = 0;

    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_VERSION = 1100;
    const CMD_GET_USER = 1501; // Command to get users
    const USHRT_MAX = 65535;
    const ACK_OK = 2000;

    public function __construct(string $ip, int $port = 4370)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    public function connect(): bool
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) return false;

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 2, 'usec' => 0));
        
        if (!@socket_connect($this->socket, $this->ip, $this->port)) {
            @socket_close($this->socket);
            $this->socket = null;
            return false;
        }

        $this->_reply_id = 0;
        $header = $this->create_header(self::CMD_CONNECT, 0, $this->_reply_id, '');
        
        @socket_send($this->socket, $header, strlen($header), 0);
        
        $response = @socket_read($this->socket, 1024);
        if (strlen($response ?? '') < 8) {
            $this->disconnect();
            return false;
        }
        
        $header_data = unpack('vcommand/vchecksum/vsession_id/vreply_id', $response);
        if (($header_data['command'] ?? 0) === self::ACK_OK) {
            $this->session_id = $header_data['session_id'];
            return true;
        }
        $this->disconnect();
        return false;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
             if ($this->session_id > 0) {
                 $header = $this->create_header(self::CMD_EXIT, $this->session_id, $this->_reply_id + 1, '');
                 @socket_send($this->socket, $header, strlen($header), 0);
             }
             @socket_close($this->socket);
             $this->socket = null;
        }
    }
    
    public function getVersion(): string
    {
        if(!$this->socket || $this->session_id == 0) return '';
        $this->_reply_id++;
        $header = $this->create_header(self::CMD_VERSION, $this->session_id, $this->_reply_id, '');
        @socket_send($this->socket, $header, strlen($header), 0);
        $response = @socket_read($this->socket, 1024);
        
        if (strlen($response ?? '') >= 8) {
             $header_data = unpack('vcommand', $response);
             if ($header_data['command'] === self::ACK_OK) {
                return substr($response, 8);
             }
        }
        return '';
    }
    
    public function getUser(): array
    {
        if (!$this->socket || $this->session_id == 0) return [];

        $this->_reply_id++;
        $header = $this->create_header(self::CMD_GET_USER, $this->session_id, $this->_reply_id, '');
        @socket_send($this->socket, $header, strlen($header), 0);
        $response = @socket_read($this->socket, 4096);

        if (strlen($response ?? '') >= 8) {
            $header_data = unpack('vcommand', $response);
            if ($header_data['command'] === self::ACK_OK) {
                $payload = substr($response, 8);
                $decoded_data = json_decode($payload, true);
                return is_array($decoded_data) ? $decoded_data : [];
            }
        }
        return [];
    }
    
    // Stubs for future implementation
    public function getAttendance(): array { return []; }
    public function setUser(int $uid, string $userid, string $name, string $password = '', int $role = 0): bool { return false; }
    public function removeUser(int $uid): bool { return false; }
    
    private function create_header($command, $session_id, $reply_id, $data)
    {
        $buf = pack('vvvv', $command, 0, $session_id, $reply_id) . $data;
        $checksum = 0;
        $len = strlen($buf);
        for ($i = 0; $i < $len; $i = $i + 2) {
            $checksum += ord($buf[$i]) + (ord($buf[$i + 1]) << 8);
        }
        $checksum = ~($checksum & 0xFFFF);
        $buf = pack('vvvv', $command, $checksum, $session_id, $reply_id) . $data;
        return $buf;
    }
}