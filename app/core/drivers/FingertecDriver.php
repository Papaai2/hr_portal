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
            'timeout' => 30,
            'line_ending' => "\r\n",
            'debug' => false,
        ];
    }
    
    /**
     * getDeviceName() was missing, causing a fatal error.
     * It has been re-implemented to provide device identification.
     */
    public function getDeviceName(): string {
        try {
            // Fingertec devices often return model info with a general INFO command
            // A more advanced implementation could parse this response for the exact model.
            return "FingerTec Device";
        } catch (Exception $e) {
            $this->logError("Could not get device name: " . $e->getMessage());
        }
        return "FingerTec Device";
    }

    protected function sendCommand(string $command, ?string $data = null, bool $expectResponse = true) {
        if (!$this->isConnected()) {
            throw new Exception("Not connected to FingerTec device.");
        }
        
        $cmdString = $command;
        if ($data !== null) {
            $cmdString .= '=' . $data;
        }
        $cmdString .= $this->config['line_ending'];

        $this->logInfo("Sending command: {$command}");
        if ($this->config['debug']) $this->logInfo("Raw command data: " . $cmdString);
        
        if (fwrite($this->connection, $cmdString) === false) {
            throw new Exception("Failed to send command to device.");
        }
        
        if (!$expectResponse) return true;
        
        return $this->readResponse();
    }
    
    protected function readResponse(): string {
        $response = '';
        while (!feof($this->connection)) {
            $chunk = fgets($this->connection, 2048);
            if ($chunk === false) {
                $meta = stream_get_meta_data($this->connection);
                if ($meta['timed_out']) throw new Exception("Device response timed out.");
                break;
            }
            $response .= $chunk;
            if (trim($chunk) === "OK" || trim($chunk) === "") break;
        }

        if (empty(trim($response))) $this->logError("Received empty response from device.");
        if ($this->config['debug']) $this->logInfo("Raw response: " . $response);
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
            if (empty($line) || strpos($line, 'DATA') === 0 || strpos($line, 'OK') === 0 || strpos($line, 'PIN') === 0) continue;
            
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
            // This is a placeholder until the attendance data parser is fully implemented for Fingertec
            return []; 
        } catch (Exception $e) {
            $this->logError("Failed to get attendance logs: " . $e->getMessage());
            return [];
        }
    }

    // --- Dummy implementations for other interface methods ---
    public function addUser(string $userId, array $userData): bool { $this->logError("AddUser not implemented"); return false; }
    public function deleteUser(string $userId): bool { $this->logError("DeleteUser not implemented"); return false; }
    public function updateUser(string $userId, array $userData): bool { $this->logError("UpdateUser not implemented"); return false; }
    public function clearAttendanceData(): bool { $this->logError("ClearAttendanceData not implemented"); return false; }
}