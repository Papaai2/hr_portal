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
        // Return a generic name if not connected, or actual if connected
        return $this->isConnected ? "FingerTec Device ({$this->host}:{$this->port})" : "FingerTec (Not Connected)";
    }

    protected function sendCommand(string $command, ?string $data = null, bool $expectResponse = true) {
        if (!$this->isConnected()) throw new Exception("Not connected. Call connect() first.");
        if (!is_resource($this->connection)) throw new Exception("Connection resource is not valid.");
        
        $cmdString = $command . ($data !== null ? '=' . $data : '') . $this->config['line_ending'];
        if ($this->config['debug']) $this->logInfo("Sending: " . trim($cmdString));
        
        // Ensure data is fully written to the socket
        $bytesWritten = fwrite($this->connection, $cmdString, strlen($cmdString));
        if ($bytesWritten === false || $bytesWritten < strlen($cmdString)) {
            throw new Exception("Failed to send command. Only sent {$bytesWritten} of " . strlen($cmdString) . " bytes.");
        }
        
        return $expectResponse ? $this->readResponse() : true;
    }
    
    protected function readResponse(): string {
        if (!is_resource($this->connection)) throw new Exception("Connection lost, cannot read response.");
        
        $response = '';
        $startTime = microtime(true);
        $buffer = '';
        
        while ((microtime(true) - $startTime) < $this->config['timeout']) {
            $read = [$this->connection];
            $write = null;
            $except = null;
            $selectResult = stream_select($read, $write, $except, 0, 500000); // 0.5 second timeout for select
            
            if ($selectResult === false) {
                throw new Exception("Error waiting for response on socket.");
            } elseif ($selectResult === 0) {
                // Timeout, no data yet, continue loop
                continue;
            } else {
                // Data is available to read
                $chunk = fgets($this->connection, 4096);
                if ($chunk === false) {
                    throw new Exception("Error reading from socket.");
                }
                $buffer .= $chunk;
                
                // Check for termination sequence (e.g., "OK\r\n")
                if (strpos($buffer, $this->config['line_ending'] . 'OK' . $this->config['line_ending']) !== false || strpos($buffer, 'OK') !== false) {
                    break;
                }
            }
        }

        if ($this->config['debug']) $this->logInfo("Received: " . trim($buffer));
        
        // Remove trailing "OK" and any extra newlines/tabs from the end of the data
        $cleanedResponse = preg_replace('/(\r?\n)?OK(\r?\n)?$/', '', trim($buffer));
        return trim($cleanedResponse);
    }
    
    public function getUsers(): array {
        try {
            // Fingertec's DATA QUERY user returns data in the format "PIN=X\tName=Y\tPri=Z" followed by OK
            $response = $this->sendCommand('DATA QUERY user');
            return $this->parseUserData($response);
        } catch (Exception $e) {
            $this->logError("Failed to get users: " . $e->getMessage());
            return [];
        }
    }
    
    protected function parseUserData(string $rawData): array {
        $users = [];
        $lines = explode($this->config['line_ending'], $rawData);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'DATA') === 0 || strpos($line, 'OK') === 0) continue;
            
            $userData = [];
            $parts = explode("\t", $line); // Split by tab
            foreach ($parts as $part) {
                $keyValue = explode('=', $part, 2);
                if (count($keyValue) === 2) {
                    $userData[$keyValue[0]] = $keyValue[1];
                }
            }

            if (!empty($userData['PIN'])) {
                $users[] = [
                    'user_id' => trim($userData['PIN']),
                    'name' => trim($userData['Name'] ?? 'N/A'),
                    'privilege' => trim($userData['Pri'] ?? 'User'),
                    // Assuming default values or N/A if not provided by device
                    'card_id' => $userData['Card'] ?? 'N/A', 
                    'group_id' => $userData['Group'] ?? 'N/A',
                ];
            }
        }
        return $users;
    }

    public function getAttendanceLogs(): array {
        try {
            $response = $this->sendCommand('DATA QUERY attlog'); // Fingertec specific command
            return $this->parseAttendanceData($response);
        } catch (Exception $e) {
            $this->logError("Failed to get attendance logs: " . $e->getMessage());
            return [];
        }
    }

    private function parseAttendanceData(string $rawData): array {
        $logs = [];
        $lines = explode($this->config['line_ending'], $rawData);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'DATA') === 0 || strpos($line, 'OK') === 0) continue;
            
            $logData = [];
            $parts = explode("\t", $line); // Split by tab
            foreach ($parts as $part) {
                $keyValue = explode('=', $part, 2);
                if (count($keyValue) === 2) {
                    $logData[$keyValue[0]] = $keyValue[1];
                }
            }

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
            $privilege = $userData['privilege'] ?? 0; // 0 for user, 14 for admin (FingerTec specific)
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
        // We'll attempt to delete and then add.
        try {
            // Attempt to delete first to ensure a clean update, though some devices allow direct update
            // We ignore the return value of delete, as add will overwrite.
            $this->deleteUser($userId); 
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