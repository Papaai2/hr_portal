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
        if (!$this->isConnected()) throw new Exception("Not connected.");
        
        $cmdString = $command . ($data !== null ? '=' . $data : '') . $this->config['line_ending'];
        if ($this->config['debug']) $this->logInfo("Sending: " . trim($cmdString));
        if (fwrite($this->connection, $cmdString) === false) throw new Exception("Failed to send command.");
        
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
        if ($this->config['debug']) $this->logInfo("Received: " . trim($response));
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

    public function getAttendanceLogs(): array {
        try {
            $response = $this->sendCommand('DATA QUERY attlog', 'PIN,DateTime,Status');
            return $this->parseAttendanceData($response);
        } catch (Exception $e) {
            $this->logError("Failed to get attendance logs: " . $e->getMessage());
            return [];
        }
    }

    private function parseAttendanceData(string $rawData): array {
        $logs = [];
        foreach (explode("\r\n", $rawData) as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'DATA') === 0 || strpos($line, 'OK') === 0) continue;
            parse_str(str_replace("\t", "&", $line), $logData);
            if (!empty($logData['PIN']) && !empty($logData['DateTime'])) {
                $logs[] = [
                    'employee_code' => trim($logData['PIN']),
                    'punch_time'    => trim($logData['DateTime']),
                ];
            }
        }
        return $logs;
    }

    public function addUser(string $userId, array $userData): bool {
        $this->logInfo("Adding user {$userId} to FingerTec device...");
        try {
            $name = $userData['name'] ?? '';
            $privilege = $userData['privilege'] ?? 0; // 0 for user, 1 for admin (FingerTec specific)
            $password = $userData['password'] ?? ''; // Optional
            $card_id = $userData['card_id'] ?? ''; // Optional

            $data = "PIN={$userId}\tName={$name}\tPri={$privilege}";
            if (!empty($password)) $data .= "\tPass={$password}";
            if (!empty($card_id)) $data .= "\tCard={$card_id}";

            $response = $this->sendCommand('DATA ADD user', $data);
            return strpos($response, 'OK') !== false;
        } catch (Exception $e) {
            $this->logError("Failed to add user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function deleteUser(string $userId): bool {
        $this->logInfo("Deleting user {$userId} from FingerTec device...");
        try {
            $response = $this->sendCommand('DATA DELETE user', "PIN={$userId}");
            return strpos($response, 'OK') !== false;
        } catch (Exception $e) {
            $this->logError("Failed to delete user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function updateUser(string $userId, array $userData): bool {
        $this->logInfo("Updating user {$userId} on FingerTec device...");
        // FingerTec often handles updates as a re-add with the same PIN.
        // We'll attempt to delete and then add, or just add if delete fails/isn't needed.
        try {
            // Attempt to delete first to ensure a clean update, though some devices allow direct update
            $this->deleteUser($userId); // Ignore result, as add will overwrite
            return $this->addUser($userId, $userData);
        } catch (Exception $e) {
            $this->logError("Failed to update user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function clearAttendanceData(): bool {
        $this->logInfo("Clearing attendance data from FingerTec device...");
        try {
            $response = $this->sendCommand('CLEAR LOG');
            return strpos($response, 'OK') !== false;
        } catch (Exception $e) {
            $this->logError("Failed to clear attendance data: " . $e->getMessage());
            return false;
        }
    }
}