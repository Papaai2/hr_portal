<?php
/**
 * Enhanced ZKTeco Driver for Windows
 * File: app/core/drivers/ZKTecoDriver.php (replaces original)
 * 
 * Compatibility improvements:
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
    
    // Response constants
    const RESPONSE_OK = 2000;
    const RESPONSE_ERROR = 2001;
    const RESPONSE_DATA = 2002;
    
    protected $sessionId = 0;
    protected $replyId = 0;
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
            'host' => '192.168.1.201',
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
    
    // ZKTeco handshake procedure
    protected function performHandshake() {
        try {
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
    
    // Send ZKTeco binary command
    protected function sendZKCommand($command, $data = '', $expectResponse = true) {
        if (!$this->isConnected) {
            throw new Exception("Not connected to ZKTeco device");
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
                        if (!$this->connect($this->config)) {
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
    
    // Build ZKTeco binary packet
    protected function buildZKPacket($command, $data = '') {
        $this->replyId++;
        
        // ZKTeco packet structure:
        // [START:2][CMD:2][CHK:2][SES:2][RPL:2][SIZE:2][DATA:var]
        $packet = pack('SSSSSS', 
            0x5050,           // Start marker
            $command,         // Command code
            0,                // Checksum (calculated later)
            $this->sessionId, // Session ID
            $this->replyId,   // Reply ID
            strlen($data)     // Data size
        );
        
        // Append data if present
        if ($data) {
            $packet .= $data;
        }
        
        // Calculate and update checksum
        $checksum = $this->calculateChecksum($packet);
        $packet = substr($packet, 0, 4) . pack('S', $checksum) . substr($packet, 6);
        
        return $packet;
    }
    
    // Calculate ZKTeco packet checksum
    protected function calculateChecksum($packet) {
        $sum = 0;
        $length = strlen($packet);
        
        for ($i = 0; $i < $length; $i += 2) {
            if ($i + 1 < $length) {
                $word = unpack('S', substr($packet, $i, 2))[1];
                $sum += $word;
            } else {
                $sum += ord($packet[$i]);
            }
        }
        
        return $sum & 0xFFFF;
    }
    
    // Read ZKTeco response packet
    protected function readZKResponse() {
        $headerSize = 12; // ZKTeco header size
        $maxDataSize = 65535;
        
        // Read header first
        $header = $this->readBytes($headerSize);
        if (strlen($header) < $headerSize) {
            throw new Exception("Incomplete header received");
        }
        
        // Parse header
        $parsed = unpack('Sstart/Scmd/Schk/Sses/Srpl/Ssize', $header);
        
        if ($parsed['start'] !== 0x5050) {
            throw new Exception("Invalid packet start marker: " . dechex($parsed['start']));
        }
        
        $dataSize = $parsed['size'];
        if ($dataSize > $maxDataSize) {
            throw new Exception("Data size too large: {$dataSize}");
        }
        
        // Read data if present
        $data = '';
        if ($dataSize > 0) {
            $data = $this->readBytes($dataSize);
            if (strlen($data) < $dataSize) {
                throw new Exception("Incomplete data received");
            }
        }
        
        return [
            'command' => $parsed['cmd'],
            'session_id' => $parsed['ses'],
            'reply_id' => $parsed['rpl'],
            'data' => $data,
            'size' => $dataSize
        ];
    }
    
    // Read exact number of bytes with timeout
    protected function readBytes($length) {
        $data = '';
        $startTime = time();
        
        while (strlen($data) < $length && (time() - $startTime) < $this->config['timeout']) {
            $remaining = $length - strlen($data);
            $chunk = fread($this->connection, $remaining);
            
            if ($chunk === false) {
                throw new Exception("Error reading from socket");
            }
            
            if ($chunk === '') {
                usleep(10000); // 10ms
                continue;
            }
            
            $data .= $chunk;
        }
        
        return $data;
    }
    
    // Device detection methods
    protected function detectViaVersion() {
        try {
            $response = $this->sendZKCommand(self::COMMAND_VERSION, '');
            
            if ($response && $response['command'] === self::RESPONSE_OK) {
                $version = $response['data'];
                
                return [
                    'manufacturer' => 'ZKTeco',
                    'model' => $this->detectModelFromVersion($version),
                    'firmware' => $version,
                    'supports_realtime' => true,
                    'communication_type' => 'tcp_binary'
                ];
            }
        } catch (Exception $e) {
            $this->logError("ZKTeco version detection failed: " . $e->getMessage());
        }
        
        return false;
    }
    
    protected function detectModelFromVersion($version) {
        foreach ($this->zktecoModels as $model => $specs) {
            if (stripos($version, $model) !== false) {
                return $model;
            }
        }
        return 'Unknown ZKTeco Model';
    }
    
    // Implementation of interface methods
    public function getDeviceInfo() {
        if (!$this->deviceInfo) {
            $this->deviceInfo = $this->autoDetectDevice();
        }
        return $this->deviceInfo;
    }
    
    public function getAllUsers() {
        try {
            $response = $this->sendZKCommand(self::COMMAND_USER_WRQ, '');
            
            if ($response && $response['command'] === self::RESPONSE_DATA) {
                return $this->parseUserData($response['data']);
            }
            
            return [];
        } catch (Exception $e) {
            $this->logError("Failed to get users: " . $e->getMessage());
            return [];
        }
    }
    
    protected function parseUserData($data) {
        $users = [];
        $length = strlen($data);
        $offset = 0;
        
        while ($offset < $length) {
            if ($offset + 28 > $length) break;
            
            $record = unpack('Suid/a8name/Cprivilege/a5password/Ccard1/Ccard2/Ccard3/Ccard4/Cgroup/Stzone', 
                            substr($data, $offset, 28));
            
            $users[] = [
                'user_id' => $record['uid'],
                'name' => trim($record['name']),
                'privilege' => $record['privilege'],
                'password' => trim($record['password']),
                'card_id' => sprintf('%02X%02X%02X%02X', 
                    $record['card1'], $record['card2'], $record['card3'], $record['card4']),
                'group_id' => $record['group'],
                'timezone' => $record['tzone']
            ];
            
            $offset += 28;
        }
        
        return $users;
    }
    
    public function addUser($userId, $userData) {
        try {
            // Build ZKTeco user record
            $userRecord = pack('Sa8C a5CCCCCs',
                $userId,
                substr($userData['name'] ?? '', 0, 8),
                $userData['privilege'] ?? 0,
                substr($userData['password'] ?? '', 0, 5),
                0, 0, 0, 0, // Card ID bytes
                $userData['group_id'] ?? 1,
                $userData['timezone'] ?? 1
            );
            
            $response = $this->sendZKCommand(self::COMMAND_USER_WRQ, $userRecord);
            return $response && $response['command'] === self::RESPONSE_OK;
        } catch (Exception $e) {
            $this->logError("Failed to add user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateUser($userId, $userData) {
        return $this->addUser($userId, $userData);
    }
    
    public function deleteUser($userId) {
        try {
            $deleteData = pack('S', $userId);
            $response = $this->sendZKCommand(self::COMMAND_DELETE_USER, $deleteData);
            return $response && $response['command'] === self::RESPONSE_OK;
        } catch (Exception $e) {
            $this->logError("Failed to delete user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAttendanceData($startDate = null, $endDate = null) {
        try {
            $response = $this->sendZKCommand(self::COMMAND_ATTLOG_RRQ, '');
            
            if ($response && $response['command'] === self::RESPONSE_DATA) {
                $records = $this->parseAttendanceData($response['data']);
                
                // Filter by date if specified
                if ($startDate || $endDate) {
                    $records = array_filter($records, function($record) use ($startDate, $endDate) {
                        $recordDate = $record['timestamp'];
                        if ($startDate && $recordDate < $startDate) return false;
                        if ($endDate && $recordDate > $endDate) return false;
                        return true;
                    });
                }
                
                return $records;
            }
            
            return [];
        } catch (Exception $e) {
            $this->logError("Failed to get attendance data: " . $e->getMessage());
            return [];
        }
    }
    
    protected function parseAttendanceData($data) {
        $records = [];
        $length = strlen($data);
        $offset = 0;
        $recordSize = 16; // Standard ZKTeco attendance record size
        
        while ($offset + $recordSize <= $length) {
            $record = unpack('Suid/Ltimestamp/Cstatus/Cverify/Lworkcode/x4', 
                            substr($data, $offset, $recordSize));
            
            $records[] = [
                'user_id' => $record['uid'],
                'timestamp' => date('Y-m-d H:i:s', $record['timestamp']),
                'status' => $record['status'],
                'verification' => $record['verify'],
                'workcode' => $record['workcode']
            ];
            
            $offset += $recordSize;
        }
        
        return $records;
    }
    
    public function clearAttendanceData() {
        try {
            $response = $this->sendZKCommand(self::COMMAND_CLEAR_ATTLOG, '');
            return $response && $response['command'] === self::RESPONSE_OK;
        } catch (Exception $e) {
            $this->logError("Failed to clear attendance data: " . $e->getMessage());
            return false;
        }
    }
    
    public function getDeviceStatus() {
        try {
            $response = $this->sendZKCommand(self::COMMAND_STATE_RRQ, '');
            
            return [
                'device_info' => $this->getDeviceInfo(),
                'connection_status' => $this->isConnected ? 'Connected' : 'Disconnected',
                'session_id' => $this->sessionId,
                'last_communication' => date('Y-m-d H:i:s'),
                'status_data' => $response
            ];
        } catch (Exception $e) {
            $this->logError("Failed to get device status: " . $e->getMessage());
            return [
                'connection_status' => 'Error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function setDateTime($datetime = null) {
        try {
            if (!$datetime) {
                $datetime = time();
            } else {
                $datetime = strtotime($datetime);
            }
            
            $timeData = pack('L', $datetime);
            $response = $this->sendZKCommand(self::COMMAND_SET_TIME, $timeData);
            return $response && $response['command'] === self::RESPONSE_OK;
        } catch (Exception $e) {
            $this->logError("Failed to set date/time: " . $e->getMessage());
            return false;
        }
    }
    
    // Enable real-time events
    public function enableRealTimeEvents() {
        try {
            $response = $this->sendZKCommand(self::COMMAND_ENABLE_DEVICE, '');
            return $response && $response['command'] === self::RESPONSE_OK;
        } catch (Exception $e) {
            $this->logError("Failed to enable real-time events: " . $e->getMessage());
            return false;
        }
    }
    
    // Disable real-time events
    public function disableRealTimeEvents() {
        try {
            $response = $this->sendZKCommand(self::COMMAND_DISABLE_DEVICE, '');
            return $response && $response['command'] === self::RESPONSE_OK;
        } catch (Exception $e) {
            $this->logError("Failed to disable real-time events: " . $e->getMessage());
            return false;
        }
    }
    
    // Get real-time attendance
    public function getRealTimeAttendance() {
        return $this->enableRealTimeEvents();
    }
    
    // Backup device data
    public function backupDevice() {
        try {
            $backup = [
                'device_info' => $this->getDeviceInfo(),
                'users' => $this->getAllUsers(),
                'attendance' => $this->getAttendanceData(),
                'backup_date' => date('Y-m-d H:i:s')
            ];
            
            return $backup;
        } catch (Exception $e) {
            $this->logError("Failed to backup device: " . $e->getMessage());
            return false;
        }
    }
    
    // Restore device data
    public function restoreDevice($backupData) {
        if (!isset($backupData['users'])) {
            throw new Exception("Invalid backup data: missing users");
        }
        
        try {
            // Clear existing users
            $this->sendZKCommand(self::COMMAND_CLEAR_DATA, '');
            
            // Restore users
            foreach ($backupData['users'] as $user) {
                if (is_array($user) && isset($user['user_id'])) {
                    $this->addUser($user['user_id'], $user);
                }
            }
            
            return true;
        } catch (Exception $e) {
            $this->logError("Failed to restore device: " . $e->getMessage());
            return false;
        }
    }
    
    // Enhanced disconnect with proper ZKTeco termination
    public function disconnect() {
        if ($this->isConnected && $this->connection) {
            try {
                // Re-enable device before disconnecting
                $this->sendZKCommand(self::COMMAND_ENABLE_DEVICE, '', false);
                
                // Send exit command
                $this->sendZKCommand(self::COMMAND_EXIT, '', false);
                
            } catch (Exception $e) {
                $this->logError("Error during ZKTeco disconnect: " . $e->getMessage());
            }
        }
        
        parent::disconnect();
    }
}
?>