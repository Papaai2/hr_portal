<?php
/**
 * Enhanced FingerTec Driver for Windows
 */
require_once __DIR__ . '/EnhancedDriverFramework.php';

class FingertecDriver extends EnhancedBaseDriver {
    
    protected function getDefaultConfig(): array {
        return ['port' => 4370, 'timeout' => 15, 'line_ending' => "\r\n", 'debug' => false];
    }
    
    public function getDeviceName(): string {
        if (!$this->isConnected) return "FingerTec (Not Connected)";
        return "FingerTec Device";
    }

    protected function sendCommand(string $command, ?string $data = null, bool $expectResponse = true) {
        if (!$this->isConnected()) throw new Exception("Not connected to FingerTec device.");
        
        $cmdString = $command . ($data !== null ? '=' . $data : '') . $this->config['line_ending'];

        $this->logInfo("Sending command: {$command}");
        if ($this->config['debug']) $this->logInfo("Raw command data: " . $cmdString);
        
        if (fwrite($this->connection, $cmdString) === false) throw new Exception("Failed to send command to device.");
        
        return $expectResponse ? $this->readResponse() : true;
    }
    
    protected function readResponse(): string {
        $response = '';
        $startTime = time();

        while (time() - $startTime < $this->config['timeout']) {
            if (feof($this->connection)) break;
            $chunk = fgets($this->connection, 2048);
            if ($chunk === false) break;
            $response .= $chunk;
            if (strpos($response, 'OK') !== false) break;
        }
        
        if (empty(trim($response))) $this->logError("Received empty or no response from device.");
        if ($this->config['debug']) $this->logInfo("Raw response: " . $response);
        
        return trim($response);
    }
    
    public function getUsers(): array {
        try {
            $response = $this->sendCommand('DATA QUERY user', 'PIN,Name,Pri');
            return $this->parseUserData($response);
        } catch (Exception $e) {
            $this->logError("Failed to get users: " . $e->getMessage());
            return [];
        }
    }
    
    protected function parseUserData(string $rawData): array {
        $users = [];
        foreach (explode("\r\n", $rawData) as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'DATA') === 0 || strpos($line, 'OK') === 0) continue;
            
            parse_str(str_replace("\t", "&", $line), $userData);
            
            if (!empty($userData['PIN'])) {
                $users[] = [
                    'user_id' => trim($userData['PIN']),
                    'name' => trim($userData['Name'] ?? 'N/A'),
                    'privilege' => trim($userData['Pri'] ?? 'User'),
                    'card_id' => 'N/A', 'group_id' => 'N/A',
                ];
            }
        }
        return $users;
    }

    public function getAttendanceLogs(): array { return []; }
    public function addUser(string $userId, array $userData): bool { return false; }
    public function deleteUser(string $userId): bool { return false; }
    public function updateUser(string $userId, array $userData): bool { return false; }
    public function clearAttendanceData(): bool { return false; }
}