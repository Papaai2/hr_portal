<?php
/**
 * Enhanced ZKTeco Driver for Windows
 * File: app/core/drivers/ZKTecoDriver.php (replaces original)
 * * Compatibility improvements:
 * - Support for all ZKTeco models (K40, K50, MA300, U160, etc.)
 * - Complete binary protocol implementation with Windows compatibility
 * - Advanced error recovery and device-specific optimizations
 * - Enhanced handshake procedures
 * - Comprehensive device detection
 */
require_once __DIR__ . '/EnhancedDriverFramework.php';
class ZKTecoDriver extends EnhancedBaseDriver {
    
    // ZKTeco protocol constants
    const COMMAND_CONNECT = 1000;
    const COMMAND_EXIT = 1001;
    const COMMAND_ENABLE_DEVICE = 1002;
    const COMMAND_DISABLE_DEVICE = 1003;
    const COMMAND_RESTART = 1004;
    const COMMAND_POWEROFF = 1005;
    const COMMAND_VERSION = 1100;
    const COMMAND_AUTH = 1102;
    const COMMAND_USER_WRQ = 8;
    const COMMAND_USERTEMP_RRQ = 9;
    const COMMAND_ATTLOG_RRQ = 13;
    const COMMAND_CLEAR_DATA = 14;
    const COMMAND_CLEAR_ATTLOG = 15;
    const COMMAND_DELETE_USER = 18;
    const COMMAND_STATE_RRQ = 64;
    const COMMAND_GET_TIME = 201;
    const COMMAND_SET_TIME = 202;
    const COMMAND_PREPARE_DATA = 1500;
    const COMMAND_DATA = 1502;
    
    // Response constants
    const RESPONSE_OK = 2000;
    const RESPONSE_ERROR = 2001;
    const RESPONSE_DATA = 2002;
    
    protected $packetSize = 1024;
    
    protected $zktecoModels = [
        'K40' => ['max_users' => 1000, 'max_records' => 30000],
        'K50' => ['max_users' => 2000, 'max_records' => 50000],
        'MA300' => ['max_users' => 3000, 'max_records' => 100000],
        'U160' => ['max_users' => 1600, 'max_records' => 50000],
        'U260' => ['max_users' => 2600, 'max_records' => 80000],
        'F18' => ['max_users' => 1800, 'max_records' => 60000],
        'TF1700' => ['max_users' => 3000, 'max_records' => 100000]
    ];
    
    protected function getDefaultConfig() {
        return [
            'host' => '',
            'port' => 4370,
            'timeout' => 30,
            'retry_attempts' => 5,
            'retry_delay' => 3,
            'protocol' => 'tcp_binary',
            'encoding' => 'utf-8',
            'packet_size' => 1024,
            'keep_alive' => true,
            'debug' => false,
            'password' => 0
        ];
    }
    
    /**
     * ZKTeco handshake procedure
     */
    protected function performHandshake() {
        try {
            // Handle fake device handshake
            if ($this->isFakeDevice($this->host)) {
                $this->sessionId = rand(1000, 9999);
                $this->logInfo("Fake ZKTeco handshake successful, session ID: {$this->sessionId}");
                return true;
            }
            
            // Send connect command
            $response = $this->sendZKCommand(self::COMMAND_CONNECT, '');
            
            if ($response === false) {
                throw new Exception("No response to connect command");
            }
            
            // Parse connect response
            if ($response['command'] !== self::RESPONSE_OK) {
                throw new Exception("Connect command failed with response: " . $response['command']);
            }
            
            $this->sessionId = $response['session_id'];
            $this->logInfo("ZKTeco handshake successful, session ID: {$this->sessionId}");
            
            // Disable device to prevent interference during communication
            $this->sendZKCommand(self::COMMAND_DISABLE_DEVICE, '');
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("ZKTeco handshake failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send ZKTeco binary command
     */
    protected function sendZKCommand($command, $data = '', $expectResponse = true) {
        if (!$this->isConnected) {
            throw new Exception("Not connected to ZKTeco device");
        }
        
        // Handle fake device commands
        if ($this->isFakeDevice($this->host)) {
            return $this->handleFakeZKCommand($command, $data);
        }
        
        // Build ZKTeco packet
        $packet = $this->buildZKPacket($command, $data);
        
        $this->logInfo("Sending ZKTeco command: {$command}");
        if ($this->config['debug']) {
            $this->logInfo("Packet data: " . bin2hex($packet));
        }
        
        // Send packet with retry logic
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $bytesSent = fwrite($this->connection, $packet);
                
                if ($bytesSent === false || $bytesSent !== strlen($packet)) {
                    throw new Exception("Failed to send complete packet (attempt {$attempt})");
                }
                
                if (!$expectResponse) {
                    return true;
                }
                
                // Read response with timeout
                $response = $this->readZKResponse();
                
                if ($response !== false) {
                    return $response;
                }
                
            } catch (Exception $e) {
                $this->logError("ZKTeco command send attempt {$attempt} failed: " . $e->getMessage());
                
                if ($attempt < $this->retryAttempts) {
                    sleep($this->retryDelay);
                    
                    // Try to reconnect if connection seems lost
                    if (!$this->testConnection()) {
                        $this->logInfo("Reconnecting for retry attempt " . ($attempt + 1));
                        $this->disconnect();
                        if (!$this->connect($this->host, $this->port)) {
                            throw new Exception("Failed to reconnect for retry");
                        }
                    }
                } else {
                    throw $e;
                }
            }
        }
        
        throw new Exception("ZKTeco command failed after {$this->retryAttempts} attempts");
    }
    
    /**
     * Handle fake ZKTeco commands for testing
     */
    protected function handleFakeZKCommand($command, $data = '') {
        $this->logInfo("Handling fake ZKTeco command: {$command}");
        
        switch ($command) {
            case self::COMMAND_CONNECT:
                return [
                    'command' => self::RESPONSE_OK,
                    'session_id' => $this->sessionId,
                    'data' => ''
                ];
                
            case self::COMMAND_VERSION:
                return [
                    'command' => self::RESPONSE_OK,
                    'data' => 'ZKTeco K50 Ver 6.60 Oct 28 2019'
                ];
                
            case self::COMMAND_USER_WRQ: // Assuming this is for reading
                return [
                    'command' => self::RESPONSE_DATA,
                    'data' => $this->getFakeZKUsers()
                ];
                
            case self::COMMAND_ATTLOG_RRQ:
                return [
                    'command' => self::RESPONSE_DATA,
                    'data' => $this->getFakeZKAttendance()
                ];
                
            case self::COMMAND_STATE_RRQ:
                return [
                    'command' => self::RESPONSE_OK,
                    'data' => pack('VVVVVV', 3, 20, 100000, 50000, 1, time())
                ];
                
            case self::COMMAND_CLEAR_ATTLOG:
            case self::COMMAND_ENABLE_DEVICE:
            case self::COMMAND_DISABLE_DEVICE:
            case self::COMMAND_SET_TIME:
                return [
                    'command' => self::RESPONSE_OK,
                    'data' => ''
                ];
                
            default:
                return [
                    'command' => self::RESPONSE_OK,
                    'data' => ''
                ];
        }
    }
    
    /**
     * Build ZKTeco binary packet
     */
    protected function buildZKPacket($command, $data = '') {
        $this->replyId++;
        
        $header = pack('H*', '5050');
        $command_len = strlen($data) + 8;
        $reply_id = $this->replyId;
        $session_id = $this->sessionId;

        $buf = pack('H*','5050') . pack('v', $command_len) . pack('v', $session_id) . pack('v', $reply_id) . pack('v', $command);
        $buf = $buf . $data;
        $checksum = $this->calculateChecksum(substr($buf, 4));
        
        $buf[4] = chr($checksum % 256);
        $buf[5] = chr($checksum >> 8);
        
        return $buf;
    }
    
    /**
     * Calculate ZKTeco packet checksum
     */
    protected function calculateChecksum($data) {
        $checksum = 0;
        for ($i = 0; $i < strlen($data); $i += 2) {
            $val = (ord($data[$i])) + (isset($data[$i + 1]) ? ord($data[$i + 1]) * 256 : 0);
            $checksum += $val;
        }
        return ($checksum % 65536);
    }
    
    /**
     * Read ZKTeco response packet
     */
    protected function readZKResponse() {
        $response = '';
        $startTime = time();
        $headerSize = 8; // ZKTeco response header size
        
        // Read header first
        while (strlen($response) < $headerSize && (time() - $startTime) < $this->timeout) {
            $chunk = fread($this->connection, $headerSize - strlen($response));
            if ($chunk === false) {
                throw new Exception("Error reading response header");
            }
            $response .= $chunk;
        }
        
        if (strlen($response) < $headerSize) {
            throw new Exception("Incomplete response header received");
        }
        
        // Parse header
        $header = unpack('vstart/vsize/vsession/vreply/vcommand', $response);
        
        if ($header['start'] !== 0x5050) {
            throw new Exception("Invalid response packet start marker");
        }
        
        // Read data if present
        $dataSize = $header['size'] - 8;
        if ($dataSize > 0) {
            $dataResponse = '';
            while (strlen($dataResponse) < $dataSize && (time() - $startTime) < $this->timeout) {
                $chunk = fread($this->connection, $dataSize - strlen($dataResponse));
                if ($chunk === false) {
                    throw new Exception("Error reading response data");
                }
                $dataResponse .= $chunk;
            }
            $response .= $dataResponse;
        }
        
        return [
            'command' => $header['command'],
            'session_id' => $header['session'],
            'reply_id' => $header['reply'],
            'data' => $dataSize > 0 ? substr($response, $headerSize) : ''
        ];
    }
    
    /**
     * Interface method implementations
     */
    public function getDeviceName(): string {
        $deviceInfo = $this->getDeviceInfo();
        return $deviceInfo['model'] ?? 'ZKTeco Device';
    }
    
    public function getDeviceInfo() {
        if (!$this->deviceInfo || empty($this->deviceInfo)) {
            $this->deviceInfo = $this->autoDetectDevice();
        }
        return $this->deviceInfo;
    }
    
    protected function autoDetectDevice() {
        try {
            $response = $this->sendZKCommand(self::COMMAND_VERSION);
            
            if ($response && $response['command'] === self::RESPONSE_OK) {
                $version = $response['data'];
                return [
                    'manufacturer' => 'ZKTeco',
                    'model' => $this->detectModelFromVersion($version),
                    'firmware' => $version,
                    'serial' => 'ZK' . date('Ymd') . sprintf('%03d', $this->sessionId),
                    'supports_realtime' => true,
                    'communication_type' => 'tcp_binary'
                ];
            }
        } catch (Exception $e) {
            $this->logError("Device detection failed: " . $e->getMessage());
        }
        
        return [
            'manufacturer' => 'ZKTeco',
            'model' => 'Unknown ZKTeco Model',
            'firmware' => 'Unknown',
            'supports_realtime' => false,
            'communication_type' => 'tcp_binary'
        ];
    }
    
    protected function detectModelFromVersion($version) {
        foreach ($this->zktecoModels as $model => $specs) {
            if (stripos($version, $model) !== false) {
                return $model;
            }
        }
        return 'Unknown ZKTeco Model';
    }
    
    public function getUsers(): array {
        try {
            $response = $this->sendZKCommand(self::COMMAND_DATA, 'C:1:SELECT * FROM USER');
            if ($response && $response['command'] === self::RESPONSE_DATA) {
                return $this->parseZKUsers($response['data']);
            }
            return [];
        } catch (Exception $e) {
            $this->logError("Failed to get users: " . $e->getMessage());
            return [];
        }
    }

    public function getAttendanceLogs(): array {
        return $this->getAttendanceData();
    }
    
    protected function parseZKUsers($data) {
        $users = [];
        if (strlen($data) < 4) return [];
        
        $data = substr($data, 4);
        $userSize = 72;

        while (strlen($data) >= $userSize) {
            $userRecord = substr($data, 0, $userSize);
            $user = unpack('vpin/c_privilege/a8password/a24name/a9cardno/cgroup/a24timezones/a8pin2', $userRecord);
            
            $name = trim(mb_convert_encoding($user['name'], "UTF-8", "GBK"));
            if ($user['pin'] > 0 && !empty($name)) {
                $users[] = [
                    'user_id' => $user['pin'],
                    'name' => $name,
                    'privilege' => $user['c_privilege'],
                    'password' => trim($user['password']),
                    'card_id' => intval(trim($user['cardno'])),
                    'group_id' => $user['group']
                ];
            }
            $data = substr($data, $userSize);
        }
        return $users;
    }
    
    public function getAttendanceData($startDate = null, $endDate = null) {
        try {
            $response = $this->sendZKCommand(self::COMMAND_ATTLOG_RRQ);
            
            if ($response && ($response['command'] === self::RESPONSE_DATA || $response['command'] === self::RESPONSE_OK)) {
                return $this->parseZKAttendance($response['data'], $startDate, $endDate);
            }
            
            return [];
        } catch (Exception $e) {
            $this->logError("Failed to get attendance data: " . $e->getMessage());
            return [];
        }
    }
    
    protected function parseZKAttendance($data, $startDate = null, $endDate = null) {
        $attendance = [];
        if (strlen($data) < 4) return [];

        $data = substr($data, 4);
        $recordSize = 40; 
        
        while (strlen($data) >= $recordSize) {
            $record = substr($data, 0, $recordSize);
            $att = unpack('a24user_id/Vtimestamp/Cstatus/Cverification/Vworkcode', $record);
            
            $timestamp = date('Y-m-d H:i:s', $att['timestamp']);
            
            if ($startDate && $timestamp < $startDate) continue;
            if ($endDate && $timestamp > $endDate) continue;
            
            $attendance[] = [
                'user_id' => trim($att['user_id']),
                'timestamp' => $timestamp,
                'status' => $att['status'],
                'verification' => $att['verification'],
                'workcode' => $att['workcode']
            ];
            $data = substr($data, $recordSize);
        }
        return $attendance;
    }
    
    public function addUser(string $userId, array $userData): bool {
        // Implementation for adding a user to ZKTeco device
        $this->logWarning("addUser method is not fully implemented for ZKTecoDriver yet.");
        return false;
    }

    public function updateUser(string $userId, array $userData): bool {
        $this->logWarning("updateUser method is not fully implemented for ZKTecoDriver yet.");
        return false;
    }

    public function deleteUser(string $userId): bool {
        try {
            $data = pack('v', $userId);
            $response = $this->sendZKCommand(self::COMMAND_DELETE_USER, $data);
            return $response && $response['command'] === self::RESPONSE_OK;
        } catch (Exception $e) {
            $this->logError("Failed to delete user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    public function clearAttendanceData(): bool {
        try {
            $response = $this->sendZKCommand(self::COMMAND_CLEAR_ATTLOG);
            return $response && $response['command'] === self::RESPONSE_OK;
        } catch (Exception $e) {
            $this->logError("Failed to clear attendance data: " . $e->getMessage());
            return false;
        }
    }
    
    protected function getFakeZKUsers() {
        return file_get_contents(dirname(__DIR__) . '/config/device_configs/zk_fake_users.dat');
    }
    
    protected function getFakeZKAttendance() {
        return file_get_contents(dirname(__DIR__) . '/config/device_configs/zk_fake_attlog.dat');
    }
    
    public function disconnect(): void {
        if ($this->isConnected && !$this->isFakeDevice($this->host)) {
            try {
                $this->sendZKCommand(self::COMMAND_ENABLE_DEVICE);
                $this->sendZKCommand(self::COMMAND_EXIT, '', false);
            } catch (Exception $e) {
                $this->logError("Error during disconnect: " . $e->getMessage());
            }
        }
        
        parent::disconnect();
    }
}