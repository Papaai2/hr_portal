<?php
// in file: app/core/drivers/lib/fingertec/TAD_PHP_Library.php

require_once __DIR__ . '/../BinaryHelper.php';

class TAD
{
    private $_ip;
    private $_port;
    private $_socket;
    private $_session_id = 0;
    private $_reply_id = 0;
    private $_is_connected = false;
    private $_com_key = 0;

    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ACK_OK = 2000;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1502; 
    const CMD_AUTH = 1102;

    public function __construct($ip, $port = 4370, $com_key = 0)
    {
        $this->_ip = $ip;
        $this->_port = $port;
        $this->_com_key = $com_key;
        $this->_socket = false;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function connect(): bool
    {
        if ($this->_is_connected) return true;

        $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->_socket) return false;
        
        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 5, "usec" => 0));

        if (!@socket_connect($this->_socket, $this->_ip, $this->_port)) {
            $this->disconnect();
            return false;
        }

        $this->_reply_id = 0;
        $this->_session_id = 0;
        
        $packet = BinaryHelper::createHeader(self::CMD_CONNECT, 0, 0);
        $this->sendAndReceive($packet);
        
        $response = $this->readResponse();
        if (!$response) {
            $this->disconnect();
            return false;
        }
        
        $header = BinaryHelper::parseHeader($response);
        if (!$header || hexdec($header['command']) != self::CMD_ACK_OK) {
            $this->disconnect();
            return false;
        }
        
        $this->_session_id = hexdec($header['sessionId']);
        
        if ($this->_com_key > 0) {
            if (!$this->authorize()) {
                $this->disconnect();
                return false;
            }
        }
        
        $this->_is_connected = true;
        return true;
    }
    
    public function disconnect(): void
    {
        if (is_resource($this->_socket)) {
            if ($this->_is_connected) {
                $packet = BinaryHelper::createHeader(self::CMD_EXIT, $this->_session_id, $this->_reply_id + 1);
                @socket_write($this->_socket, $packet, strlen($packet));
            }
            socket_close($this->_socket);
        }
        $this->_socket = false;
        $this->_is_connected = false;
    }
    
    private function authorize(): bool
    {
        $this->_reply_id++;
        
        $key = str_pad(dechex($this->_com_key), 8, '0', STR_PAD_LEFT);
        $key = str_split($key, 2);
        $key = chr(hexdec($key[3])) . chr(hexdec($key[2])) . chr(hexdec($key[1])) . chr(hexdec($key[0]));
        
        $packet = BinaryHelper::createHeader(self::CMD_AUTH, $this->_session_id, $this->_reply_id, $key);
        $this->sendAndReceive($packet);
        
        $response = $this->readResponse();
        if ($response) {
            $header = BinaryHelper::parseHeader($response);
            return (hexdec($header['command']) == self::CMD_ACK_OK);
        }
        return false;
    }

    private function sendAndReceive($commandPacket) {
        return @socket_write($this->_socket, $commandPacket, strlen($commandPacket));
    }
    
    private function readResponse($max_size = 4096) {
         return @socket_read($this->_socket, $max_size);
    }
    
    private function downloadData(string $command_string)
    {
        $this->_reply_id++;
        $packet = BinaryHelper::createHeader(self::CMD_PREPARE_DATA, $this->_session_id, $this->_reply_id, $command_string);
        $this->sendAndReceive($packet);
        
        $response = $this->readResponse(8192);
        if (!$response) {
            return '';
        }
        
        $payload = substr($response, 8, -8);
        
        return $payload;
    }

    public function getUsers(): array
    {
        if (!$this->isConnected()) return [];

        $payload = $this->downloadData('GET_USERS');
        
        $json_data = @json_decode($payload, true);
        if (is_array($json_data)) {
            return $json_data;
        }
        
        return BinaryHelper::parseUserData($payload);
    }

    public function getAttendanceLogs(): array
    {
        if (!$this->isConnected()) return [];

        $payload = $this->downloadData('GET_ATTENDANCE');
        
        $json_data = @json_decode($payload, true);
        if (is_array($json_data)) {
            return $json_data;
        }
        
        return BinaryHelper::parseAttendanceData($payload);
    }
    
    public function isConnected(): bool
    {
        return $this->_is_connected;
    }
    
    public function getVersion() { return "TAD Library v4.2 (Checksum Ready)"; }
}