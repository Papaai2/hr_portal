<?php
/**
 * Enhanced FingerTec Driver for Windows
 * File: app/core/drivers/FingertecDriver.php
 */
require_once __DIR__ . '/EnhancedDriverFramework.php';

class FingertecDriver extends EnhancedBaseDriver {
    
    protected function getDefaultConfig(): array {
        return [
            'port' => 4370,
            'timeout' => 30, // seconds
            'response_timeout' => 15, // seconds for a single command
            'line_ending' => "\r\n",
            'debug' => false,
        ];
    }
    
    protected function sendCommand(string $command, ?string $data = null, bool $expectResponse = true) {
        if (!$this->isConnected()) {
            throw new Exception("Not connected to FingerTec device.");
        }
        
        if ($this->isFakeDevice($this->host)) {
            return $this->handleFakeCommand($command);
        }
        
        $cmdString = $command;
        if ($data !== null) {
            $cmdString .= '=' . $data;
        }
        $cmdString .= $this->config['line_ending'];

        $this->logInfo("Sending command: {$command}");
        if ($this->config['debug']) {
            $this->logInfo("Raw command data: " . $cmdString);
        }
        
        if (fwrite($this->connection, $cmdString) === false) {
            throw new Exception("Failed to send command to device.");
        }
        
        if (!$expectResponse) {
            return true;
        }
        
        return $this->readResponse();
    }
    
    protected function readResponse() {
        $response = '';
        // The stream_set_timeout in the framework handles the blocking,
        // so we can use a simpler loop.
        while (!feof($this->connection)) {
            $chunk = fgets($this->connection, 2048); // Read line by line
            if ($chunk === false) {
                $meta = stream_get_meta_data($this->connection);
                if ($meta['timed_out']) {
                    throw new Exception("Device response timed out.");
                }
                break; // Connection closed or error
            }
            $response .= $chunk;
            // FingerTec often ends data blocks with an empty line or OK prompt
            if (trim($chunk) === "OK" || trim($chunk) === "") {
                break;
            }
        }

        if (empty(trim($response))) {
            $this->logError("Received empty response from device.");
            return '';
        }

        if ($this->config['debug']) {
            $this->logInfo("Raw response: " . $response);
        }
        
        return trim($response);
    }
    
    public function getUsers(): array {
        try {
            $response = $this->sendCommand('DATA QUERY user', 'PIN,Name,Pri,Card,Grp,TZ,Verify');
            return $this->parseUserData($response);
        } catch (Exception $e) {
            $this->logError("Failed to get users: " . $e->getMessage());
            return [];
        }
    }
    
    protected function parseUserData(string $rawData): array {
        $users = [];
        $lines = explode("\r\n", $rawData);

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip headers, empty lines, and OK responses
            if (empty($line) || strpos($line, 'DATA') === 0 || strpos($line, 'OK') === 0 || strpos($line, 'PIN') === 0) {
                continue;
            }
            
            // Data is often in "key=value" format, separated by tabs or commas
            $line = str_replace(["\t", ","], "&", $line);
            parse_str($line, $userData);
            
            if (isset($userData['PIN']) && !empty($userData['PIN'])) {
                $users[] = [
                    'user_id' => trim($userData['PIN']),
                    'name' => trim($userData['Name'] ?? 'N/A'),
                    'privilege' => trim($userData['Pri'] ?? 'User'),
                    'card_id' => trim($userData['Card'] ?? 'N/A'),
                    'group_id' => trim($userData['Grp'] ?? 'N/A'),
                ];
            }
        }
        return $users;
    }

    public function getAttendanceLogs(): array {
        try {
            $response = $this->sendCommand('DATA QUERY attlog', 'PIN,DateTime,Status,Verify');
            return $this->parseAttendanceData($response);
        } catch (Exception $e) {
            $this->logError("Failed to get attendance logs: " . $e->getMessage());
            return [];
        }
    }
    
    protected function parseAttendanceData(string $rawData): array {
        $logs = [];
        $lines = explode("\r\n", $rawData);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'DATA') === 0 || strpos($line, 'OK') === 0 || strpos($line, 'PIN') === 0) {
                continue;
            }
            
            $line = str_replace(["\t", ","], "&", $line);
            parse_str($line, $logData);

            if (isset($logData['PIN']) && isset($logData['DateTime'])) {
                $logs[] = [
                    'employee_code' => trim($logData['PIN']),
                    'punch_time' => trim($logData['DateTime']),
                    // Status might need mapping based on device manual
                    'punch_status' => trim($logData['Status'] ?? '0'),
                ];
            }
        }
        return $logs;
    }

    protected function handleFakeCommand(string $command): string {
        $this->logInfo("Handling fake FingerTec command: {$command}");
        if ($command === 'DATA QUERY user') {
            return "OK\r\nPIN=1,Name=Fake User 1,Pri=0\r\nPIN=2,Name=Fake Admin,Pri=14\r\n";
        }
        if ($command === 'DATA QUERY attlog') {
            return "OK\r\nPIN=1,DateTime=2025-06-21 09:00:00,Status=0\r\nPIN=1,DateTime=2025-06-21 17:00:00,Status=1\r\n";
        }
        return "OK";
    }

    // Dummy implementations for other interface methods
    public function addUser(string $userId, array $userData): bool { $this->logError("AddUser not implemented for FingertecDriver"); return false; }
    public function deleteUser(string $userId): bool { $this->logError("DeleteUser not implemented for FingertecDriver"); return false; }
    public function updateUser(string $userId, array $userData): bool { $this->logError("UpdateUser not implemented for FingertecDriver"); return false; }
    public function clearAttendanceData(): bool { $this->logError("ClearAttendanceData not implemented for FingertecDriver"); return false; }
}