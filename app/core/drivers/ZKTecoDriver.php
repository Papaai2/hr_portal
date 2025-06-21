<?php
/**
 * Enhanced ZKTeco Driver for Windows
 */
require_once __DIR__ . '/EnhancedDriverFramework.php';
require_once __DIR__ . '/lib/BinaryHelper.php';

class ZKTecoDriver extends EnhancedBaseDriver {
    
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ACK_OK = 2000;
    const CMD_PREPARE_DATA = 1500;
    const CMD_ATTLOG_RRQ = 13;
    
    protected function getDefaultConfig(): array {
        return ['port' => 4370, 'timeout' => 10, 'debug' => false];
    }

    public function getDeviceName(): string {
        if (!$this->isConnected) return "ZKTeco (Not Connected)";
        return "ZKTeco Device";
    }
    
    protected function performHandshake(): bool {
        try {
            $packet = BinaryHelper::createPacket(self::CMD_CONNECT, 0, ++$this->replyId);
            $response = $this->sendAndReceive($packet);
            if (!$response) return false;
            
            $header = BinaryHelper::parseHeader($response);
            if ($header && $header['command'] == self::CMD_ACK_OK) {
                $this->sessionId = $header['session_id'];
                $this->replyId = $header['reply_id'];
                return true;
            }
            return false;
        } catch (Exception $e) {
            $this->logError("Handshake exception: " . $e->getMessage());
            return false;
        }
    }
    
    protected function sendAndReceive(string $packet): ?string {
        if (!is_resource($this->connection)) throw new Exception("Connection lost.");
        if (fwrite($this->connection, $packet, strlen($packet)) === false) throw new Exception("Failed to write to device.");
        
        $response = @fread($this->connection, 4096);
        if ($response === false || strlen($response) === 0) {
            if (stream_get_meta_data($this->connection)['timed_out']) throw new Exception("Device response timed out.");
            return null;
        }
        return $response;
    }

    public function getUsers(): array {
        try {
            $this->logInfo("Requesting user data...");
            $command_string = "C:1:SELECT * FROM USER\0";
            $packet = BinaryHelper::createPacket(self::CMD_PREPARE_DATA, $this->sessionId, ++$this->replyId, $command_string);
            
            $response = $this->sendAndReceive($packet);
            if (!$response) return [];
            
            $header = BinaryHelper::parseHeader($response);
            if ($header && $header['command'] == self::CMD_ACK_OK) {
                $payload = substr($response, 10);
                return BinaryHelper::parseZkUserData($payload);
            }
            return [];
        } catch (Exception $e) {
            $this->logError("Failed to get users: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAttendanceLogs(): array {
        try {
            $this->logInfo("Requesting attendance logs...");
            $packet = BinaryHelper::createPacket(self::CMD_ATTLOG_RRQ, $this->sessionId, ++$this->replyId);
            
            $response = $this->sendAndReceive($packet);
            if (!$response) return [];
            
            $header = BinaryHelper::parseHeader($response);
            if ($header && $header['command'] == self::CMD_ACK_OK) {
                $payload = substr($response, 10);
                return $this->parseAttendanceData($payload);
            }
            return [];
        } catch (Exception $e) {
            $this->logError("Failed to get attendance logs: " . $e->getMessage());
            return [];
        }
    }
    
    private function parseAttendanceData(string $data): array {
        if (empty($data)) return [];
        $logs = [];
        if (substr($data, 0, 4) === "\x01\x00\x00\x00") $data = substr($data, 4);

        $record_size = 32;
        $unpack_format = 'a24user_id/Vtimestamp/Cstatus/Cverification/vreserved';

        while (strlen($data) >= $record_size) {
            $record = substr($data, 0, $record_size);
            $att = unpack($unpack_format, $record);
            if ($att && $att['timestamp'] > 0) {
                $logs[] = [
                    'employee_code' => trim($att['user_id']),
                    'punch_time'    => date('Y-m-d H:i:s', $att['timestamp']),
                ];
            }
            $data = substr($data, $record_size);
        }
        return $logs;
    }
    
    protected function sendExitCommand(): void {
        try {
            $packet = BinaryHelper::createPacket(self::CMD_EXIT, $this->sessionId, ++$this->replyId);
            @fwrite($this->connection, $packet, strlen($packet));
        } catch (Exception $e) { /* Ignore exceptions on exit */ }
    }

    public function addUser(string $userId, array $userData): bool { return false; }
    public function deleteUser(string $userId): bool { return false; }
    public function updateUser(string $userId, array $userData): bool { return false; }
    public function clearAttendanceData(): bool { return false; }
}