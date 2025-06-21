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
    const CMD_VERSION = 1100;
    const CMD_PREPARE_DATA = 1500;
    const CMD_ATTLOG_RRQ = 13;
    
    protected function getDefaultConfig(): array {
        return ['port' => 4370, 'timeout' => 10, 'debug' => false];
    }

    public function getDeviceName(): string {
        if (!$this->isConnected) return "ZKTeco (Not Connected)";
        try {
            $packet = BinaryHelper::createHeader(self::CMD_VERSION, $this->sessionId, ++$this->replyId);
            $response = $this->sendPacketAndGetResponse($packet);
            if ($response) {
                $header = BinaryHelper::parseHeader($response);
                if ($header && $header['command'] == self::CMD_ACK_OK) {
                    return "ZKTeco Device (FW: " . trim(substr($response, 16)) . ")";
                }
            }
        } catch (Exception $e) {
            $this->logError("Could not get device name: " . $e->getMessage());
        }
        return "ZKTeco Device";
    }
    
    protected function performHandshake(): bool {
        try {
            $packet = BinaryHelper::createHeader(self::CMD_CONNECT, 0, 0);
            $response = $this->sendPacketAndGetResponse($packet);
            if ($response === null) {
                $this->logError("Handshake failed: No response from device (timeout).");
                return false;
            }
            $header = BinaryHelper::parseHeader($response);
            if ($header && $header['command'] == self::CMD_ACK_OK) {
                $this->sessionId = $header['session_id'];
                return true;
            }
            $this->logError("Handshake failed. Device returned unexpected data.");
            return false;
        } catch (Exception $e) {
            $this->logError("Handshake exception: " . $e->getMessage());
            return false;
        }
    }
    
    protected function sendPacketAndGetResponse(string $packet, int $size = 8192): ?string {
        if (!is_resource($this->connection)) throw new Exception("Connection lost before sending packet.");
        if (fwrite($this->connection, $packet, strlen($packet)) === false) {
            throw new Exception("Failed to write packet to device.");
        }
        $response = @fread($this->connection, $size);
        if ($response === false || strlen($response) === 0) {
            if (stream_get_meta_data($this->connection)['timed_out']) throw new Exception("Device response timed out.");
            return null;
        }
        if ($this->config['debug']) $this->logInfo("Raw Response HEX: " . bin2hex($response));
        return $response;
    }

    protected function sendExitCommand(): void {
        $packet = BinaryHelper::createHeader(self::CMD_EXIT, $this->sessionId, ++$this->replyId);
        @fwrite($this->connection, $packet, strlen($packet));
    }

    public function getUsers(): array { /* ... unchanged ... */ return []; }
    public function getAttendanceLogs(): array { /* ... unchanged ... */ return []; }
    public function addUser(string $userId, array $userData): bool { /* ... */ return false; }
    public function deleteUser(string $userId): bool { /* ... */ return false; }
    public function updateUser(string $userId, array $userData): bool { /* ... */ return false; }
    public function clearAttendanceData(): bool { /* ... */ return false; }
}