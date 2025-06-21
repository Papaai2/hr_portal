<?php
/**
 * Enhanced FingerTec Driver for Windows
 * File: app/core/drivers/FingertecDriver.php (replaces original)
 * 
 * Compatibility improvements:
 * - Support for all FingerTec models (TA100, TA200, TA300, R2, R3, etc.)
 * - Enhanced ASCII protocol handling with Windows compatibility
 * - Advanced error recovery and device-specific optimizations
 * - Real-time event handling
 * - Comprehensive device detection
 */
require_once __DIR__ . '/EnhancedDriverFramework.php';
require_once __DIR__ . '/lib/fingertec/TAD_PHP_Library.php'; // Add this line
class FingertecDriver extends EnhancedBaseDriver {
    
    // FingerTec model specifications
    protected $fingertecModels = [
        'TA100' => ['port' => 4370, 'protocol' => 'tcp', 'max_users' => 1000],
        'TA200' => ['port' => 4370, 'protocol' => 'tcp', 'max_users' => 2000],
        'TA300' => ['port' => 4370, 'protocol' => 'tcp', 'max_users' => 3000],
        'R2' => ['port' => 4370, 'protocol' => 'tcp', 'max_users' => 2000],
        'R3' => ['port' => 4370, 'protocol' => 'tcp', 'max_users' => 3000],
        'TimeLine100' => ['port' => 4370, 'protocol' => 'tcp', 'max_users' => 5000],
        'eSSL' => ['port' => 4370, 'protocol' => 'tcp', 'max_users' => 10000]
    ];
    
    // FingerTec command set
    protected $commands = [
        'INFO' => 'INFO',
        'GET_VERSION' => 'GET DeviceInfo.ProductName',
        'GET_SERIAL' => 'GET DeviceInfo.SerialNumber',
        'GET_USER_COUNT' => 'GET UserCount',
        'GET_ATTENDANCE_COUNT' => 'GET AttendanceCount',
        'GET_USERS' => 'DATA QUERY userinfo',
        'GET_ATTENDANCE' => 'DATA QUERY attlog',
        'CLEAR_ATTENDANCE' => 'DATA DELETE attlog',
        'ENABLE_REALTIME' => 'REG_EVENT',
        'DISABLE_REALTIME' => 'UNREG_EVENT',
        'RESTART' => 'RESTART',
        'SET_TIME' => 'SET DateTime'
    ];
    
    protected function getDefaultConfig() {
        return [
            'host' => '192.168.1.201',
            'port' => 4370,
            'timeout' => 30,
            'retry_attempts' => 5,
            'retry_delay' => 3,
            'protocol' => 'tcp',
            'encoding' => 'utf-8',
            'line_ending' => "\r\n",
            'response_timeout' => 10,
            'chunk_size' => 1024,
            'keep_alive' => true,
            'debug' => false
        ];
    }
    
    // Enhanced command sending with FingerTec protocol
    protected function sendCommand($command, $data = null, $expectResponse = true) {
        if (!$this->isConnected) {
            throw new Exception("Not connected to FingerTec device");
        }
        
        // Build FingerTec command format
        $cmd = $this->buildFingertecCommand($command, $data);
        
        $this->logInfo("Sending command: {$command}");
        if ($this->config['debug']) {
            $this->logInfo("Command data: " . $cmd);
        }
        
        // Send command with retry logic
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $bytesSent = fwrite($this->connection, $cmd);
                
                if ($bytesSent === false || $bytesSent === 0) {
                    throw new Exception("Failed to send command (attempt {$attempt})");
                }
                
                if (!$expectResponse) {
                    return true;
                }
                
                // Read response
                $response = $this->readResponse();
                
                if ($response !== false) {
                    return $this->parseResponse($response);
                }
                
            } catch (Exception $e) {
                $this->logError("Command send attempt {$attempt} failed: " . $e->getMessage());
                
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
        
        throw new Exception("Command failed after {$this->retryAttempts} attempts");
    }
    
    // Build FingerTec specific command format
    protected function buildFingertecCommand($command, $data = null) {
        $cmd = $command;
        
        if ($data !== null) {
            if (is_array($data)) {
                $cmd .= ' ' . implode(' ', $data);
            } else {
                $cmd .= ' ' . $data;
            }
        }
        
        // Add FingerTec line ending
        $cmd .= $this->config['line_ending'];
        
        return $cmd;
    }
    
    // Enhanced response reading with timeout and error handling
    protected function readResponse() {
        $response = '';
        $startTime = time();
        
        while (time() - $startTime < $this->config['response_timeout']) {
            if (feof($this->connection)) {
                break;
            }
            
            $chunk = fread($this->connection, $this->config['chunk_size']);
            
            if ($chunk === false) {
                throw new Exception("Error reading response from device");
            }
            
            if ($chunk === '') {
                usleep(100000); // 100ms
                continue;
            }
            
            $response .= $chunk;
            
            // Check if we have a complete response
            if ($this->isCompleteResponse($response)) {
                break;
            }
        }
        
        if (empty($response)) {
            throw new Exception("No response received from device within timeout");
        }
        
        if ($this->config['debug']) {
            $this->logInfo("Raw response: " . $response);
        }
        
        return $response;
    }
    
    // Check if response is complete
    protected function isCompleteResponse($response) {
        $endMarkers = ["\r\n", "\n", "OK\r\n", "FAIL\r\n", "END\r\n"];
        
        foreach ($endMarkers as $marker) {
            if (substr($response, -strlen($marker)) === $marker) {
                return true;
            }
        }
        
        return false;
    }
    
    // Enhanced response parsing
    protected function parseResponse($response) {
        $response = trim($response);
        
        if (strpos($response, 'OK') === 0) {
            return $this->parseSuccessResponse($response);
        } elseif (strpos($response, 'FAIL') === 0) {
            throw new Exception("Device returned error: " . $response);
        } elseif (strpos($response, 'DATA') === 0) {
            return $this->parseDataResponse($response);
        } else {
            return $response;
        }
    }
    
    protected function parseSuccessResponse($response) {
        if (preg_match('/OK\s+(.+)/', $response, $matches)) {
            return trim($matches[1]);
        }
        return true;
    }
    
    protected function parseDataResponse($response) {
        $lines = explode("\n", $response);
        $data = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === 'END') {
                continue;
            }
            
            if (strpos($line, 'DATA') === 0) {
                continue;
            }
            
            $data[] = $this->parseDataLine($line);
        }
        
        return $data;
    }
    
    protected function parseDataLine($line) {
        $fields = explode("\t", $line);
        
        if (count($fields) >= 3) {
            return [
                'user_id' => $fields[0] ?? '',
                'name' => $fields[1] ?? '',
                'privilege' => $fields[2] ?? '',
                'password' => $fields[3] ?? '',
                'card_id' => $fields[4] ?? '',
                'group_id' => $fields[5] ?? '',
                'timezone' => $fields[6] ?? '',
                'verification' => $fields[7] ?? ''
            ];
        }
        
        return $line;
    }
    
    // Device detection methods
    protected function detectViaVersion() {
        try {
            $version = $this->sendCommand($this->commands['GET_VERSION']);
            $serial = $this->sendCommand($this->commands['GET_SERIAL']);
            
            return [
                'manufacturer' => 'FingerTec',
                'model' => $this->detectModelFromVersion($version),
                'firmware' => $version,
                'serial' => $serial,
                'supports_realtime' => true,
                'communication_type' => 'tcp'
            ];
        } catch (Exception $e) {
            $this->logError("Version detection failed: " . $e->getMessage());
            return false;
        }
    }
    
    protected function detectModelFromVersion($version) {
        foreach ($this->fingertecModels as $model => $specs) {
            if (stripos($version, $model) !== false) {
                return $model;
            }
        }
        return 'Unknown FingerTec Model';
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
            return $this->sendCommand($this->commands['GET_USERS']);
        } catch (Exception $e) {
            $this->logError("Failed to get users: " . $e->getMessage());
            return [];
        }
    }
    
    public function addUser($userId, $userData) {
        try {
            $userString = implode("\t", [
                $userId,
                $userData['name'] ?? '',
                $userData['privilege'] ?? '0',
                $userData['password'] ?? '',
                $userData['card_id'] ?? '',
                $userData['group_id'] ?? '1',
                $userData['timezone'] ?? '1',
                $userData['verification'] ?? '15'
            ]);
            
            return $this->sendCommand('DATA UPDATE userinfo', $userString);
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
            return $this->sendCommand('DATA DELETE userinfo', "WHERE userid={$userId}");
        } catch (Exception $e) {
            $this->logError("Failed to delete user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAttendanceData($startDate = null, $endDate = null) {
        try {
            $command = $this->commands['GET_ATTENDANCE'];
            
            if ($startDate && $endDate) {
                $command .= " WHERE LogDate>='{$startDate}' AND LogDate<='{$endDate}'";
            }
            
            $rawData = $this->sendCommand($command);
            return $this->parseAttendanceData($rawData);
        } catch (Exception $e) {
            $this->logError("Failed to get attendance data: " . $e->getMessage());
            return [];
        }
    }
    
    protected function parseAttendanceData($rawData) {
        if (!is_array($rawData)) {
            return [];
        }
        
        $attendanceRecords = [];
        
        foreach ($rawData as $record) {
            if (is_string($record)) {
                $fields = explode("\t", $record);
                if (count($fields) >= 4) {
                    $attendanceRecords[] = [
                        'user_id' => $fields[0],
                        'timestamp' => $fields[1],
                        'status' => $fields[2],
                        'verification' => $fields[3],
                        'workcode' => $fields[4] ?? '0'
                    ];
                }
            }
        }
        
        return $attendanceRecords;
    }
    
    public function clearAttendanceData() {
        try {
            return $this->sendCommand($this->commands['CLEAR_ATTENDANCE']);
        } catch (Exception $e) {
            $this->logError("Failed to clear attendance data: " . $e->getMessage());
            return false;
        }
    }
    
    public function getDeviceStatus() {
        try {
            $info = $this->getDeviceInfo();
            $users = $this->sendCommand($this->commands['GET_USER_COUNT']);
            $attendance = $this->sendCommand($this->commands['GET_ATTENDANCE_COUNT']);
            
            return [
                'device_info' => $info,
                'user_count' => intval($users),
                'attendance_count' => intval($attendance),
                'connection_status' => $this->isConnected ? 'Connected' : 'Disconnected',
                'last_communication' => date('Y-m-d H:i:s')
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
                $datetime = date('Y-m-d H:i:s');
            }
            
            return $this->sendCommand($this->commands['SET_TIME'], $datetime);
        } catch (Exception $e) {
            $this->logError("Failed to set date/time: " . $e->getMessage());
            return false;
        }
    }
    
    // Enable real-time events
    public function enableRealTimeEvents() {
        try {
            return $this->sendCommand($this->commands['ENABLE_REALTIME']);
        } catch (Exception $e) {
            $this->logError("Failed to enable real-time events: " . $e->getMessage());
            return false;
        }
    }
    
    // Disable real-time events
    public function disableRealTimeEvents() {
        try {
            return $this->sendCommand($this->commands['DISABLE_REALTIME']);
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
            // Clear existing data
            $this->sendCommand('DATA DELETE userinfo', 'WHERE userid>0');
            
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
}
?>