<?php
/**
 * Enhanced ZKTeco Driver for Windows
 * File: app/core/drivers/ZKTecoDriver.php
 */
require_once __DIR__ . '/EnhancedDriverFramework.php';
require_once __DIR__ . '/lib/BinaryHelper.php';

class ZKTecoDriver extends EnhancedBaseDriver {
    
    // ZKTeco protocol constants from TAD/ZKTeco library
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ENABLEDEVICE = 1002;
    const CMD_DISABLEDEVICE = 1003;
    const CMD_ACK_OK = 2000;
    const CMD_ACK_ERROR = 2001;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1502;
    const CMD_USER_WRQ = 8;
    const CMD_ATTLOG_RRQ = 13; // Request attendance records
    
    protected function getDefaultConfig(): array {
        return [
            'port' => 4370,
            'timeout' => 30, // Overall connection timeout
            'response_timeout' => 15, // Single command response timeout
            'password' => 0, // Device communication key
            'debug' => false
        ];
    }
    
    protected function performHandshake(): bool {
        try {
            $this->logInfo("Performing ZKTeco handshake...");
            $packet = BinaryHelper::createHeader(self::CMD_CONNECT, 0, 0);
            $this->sendPacket($packet);
            
            $response = $this->readResponse();
            $header = BinaryHelper::parseHeader($response);

            if ($header && $header['command'] == self::CMD_ACK_OK) {
                $this->sessionId = $header['session_id'];
                $this->logInfo("Handshake successful. Session ID: {$this->sessionId}");
                return true;
            }
            $this->logError("Handshake failed. Invalid response header.");
            return false;
        } catch (Exception $e) {
            $this->logError("Handshake exception: " . $e->getMessage());
            return false;
        }
    }
    
    protected function sendPacket(string $packet): void {
        if (fwrite($this->connection, $packet, strlen($packet)) === false) {
            throw new Exception("Failed to write packet to device.");
        }
    }
    
    protected function readResponse(int $size = 8192): string {
        $response = '';
        $bytesRead = 0;
        
        // Header is 8 bytes
        $headerData = @fread($this->connection, 8);
        if ($headerData === false || strlen($headerData) < 8) {
            $meta = stream_get_meta_data($this->connection);
            if ($meta['timed_out']) throw new Exception("Device response timed out while reading header.");
            throw new Exception("Failed to read response header from device.");
        }

        $header = BinaryHelper::parseHeader($headerData);
        if (!$header) throw new Exception("Invalid response header.");

        $response = $headerData;
        $dataSize = unpack('vsize', substr($headerData, 2, 2))['size'];
        $payloadSize = $dataSize - 8;

        if ($payloadSize > 0) {
            $payloadData = '';
            while (strlen($payloadData) < $payloadSize) {
                $chunk = @fread($this->connection, $payloadSize - strlen($payloadData));
                if ($chunk === false || strlen($chunk) === 0) {
                    $meta = stream_get_meta_data($this->connection);
                     if ($meta['timed_out']) throw new Exception("Device response timed out while reading payload.");
                    break; // Error or connection closed
                }
                $payloadData .= $chunk;
            }
            $response .= $payloadData;
        }

        if ($this->config['debug']) {
            $this->logInfo("Raw Response HEX: " . bin2hex($response));
        }

        return $response;
    }

    public function getUsers(): array {
        if ($this->isFakeDevice($this->host)) return BinaryHelper::parseZkUserData($this->getFakeZKUsers());
        
        try {
            $this->logInfo("Requesting user data from device...");
            $command_string = 'C:1:SELECT * FROM USER';
            $packet = BinaryHelper::createHeader(self::CMD_PREPARE_DATA, $this->sessionId, ++$this->replyId, $command_string);
            $this->sendPacket($packet);
            $response = $this->readResponse();
            $header = BinaryHelper::parseHeader($response);

            if ($header && $header['command'] == self::CMD_ACK_OK) {
                $payload = substr($response, 8);
                return BinaryHelper::parseZkUserData($payload);
            }
            $this->logError("Failed to get users. Device returned command: " . ($header['command'] ?? 'N/A'));
            return [];
        } catch (Exception $e) {
            $this->logError("Failed to get users: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAttendanceLogs(): array {
        if ($this->isFakeDevice($this->host)) return []; // Implement fake logs if needed
        try {
            $this->logInfo("Requesting attendance logs from device...");
            $packet = BinaryHelper::createHeader(self::CMD_ATTLOG_RRQ, $this->sessionId, ++$this->replyId);
            $this->sendPacket($packet);
            $response = $this->readResponse(65535); // Allow larger response for logs
            $header = BinaryHelper::parseHeader($response);

            if ($header && $header['command'] == self::CMD_ACK_OK) {
                $payload = substr($response, 8);
                // Requires a new parser in BinaryHelper for attendance logs.
                // return BinaryHelper::parseZkAttendanceData($payload);
                return []; // Placeholder until parser is created
            }
            return [];
        } catch (Exception $e) {
            $this->logError("Failed to get attendance logs: " . $e->getMessage());
            return [];
        }
    }
    
    protected function getFakeZKUsers(): string {
        // Simulates the raw binary data packet for users
        $userData1 = pack('vca24', 1, 0, 'Fake User 1'); // pin, privilege, name
        $userData1 = str_pad($userData1, 72, "\0");
        $userData2 = pack('vca24', 2, 14, 'Fake Admin'); // pin, privilege, name
        $userData2 = str_pad($userData2, 72, "\0");
        return $userData1 . $userData2;
    }

    public function disconnect(): void {
        if ($this->isConnected() && !$this->isFakeDevice($this->host)) {
            try {
                $packet = BinaryHelper::createHeader(self::CMD_EXIT, $this->sessionId, ++$this->replyId);
                $this->sendPacket($packet);
            } catch (Exception $e) {
                $this->logError("Exception during ZKTeco disconnect: " . $e->getMessage());
            }
        }
        parent::disconnect();
    }
    
    // Dummy implementations for other interface methods
    public function addUser(string $userId, array $userData): bool { $this->logError("AddUser not implemented for ZKTecoDriver"); return false; }
    public function deleteUser(string $userId): bool { $this->logError("DeleteUser not implemented for ZKTecoDriver"); return false; }
    public function updateUser(string $userId, array $userData): bool { $this->logError("UpdateUser not implemented for ZKTecoDriver"); return false; }
    public function clearAttendanceData(): bool { $this->logError("ClearAttendanceData not implemented for ZKTecoDriver"); return false; }
}