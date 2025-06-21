<?php
/**
 * Enhanced Biometric Driver Framework for Windows
 */
require_once __DIR__ . '/DeviceDriverInterface.php';

abstract class EnhancedBaseDriver implements DeviceDriverInterface {
    protected string $host = '';
    protected int $port = 4370;
    protected int $timeout = 10;
    protected $connection = null;
    protected string $lastError = '';
    protected bool $isConnected = false;
    protected array $config = [];
    protected int $retryAttempts = 1;
    protected int $retryDelay = 1;
    protected int $sessionId = 0;
    protected int $replyId = 0;
    protected string $logPath = '';

    public function __construct(array $config = []) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->host = $this->config['host'] ?? '';
        $this->port = $this->config['port'] ?? 4370;
        $this->timeout = $this->config['timeout'] ?? 10;
        $this->retryAttempts = $this->config['retry_attempts'] ?? 1;
        $this->logPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }
    }

    abstract protected function getDefaultConfig(): array;

    public function ping(string $ip, int $port): bool {
        $socket = @fsockopen($ip, $port, $errno, $errstr, 2);
        if ($socket) {
            @fclose($socket);
            return true;
        }
        return false;
    }

    public function connect(string $ip, int $port, ?string $key = null): bool {
        $this->host = $ip;
        $this->port = $port;
        if ($key !== null) $this->config['key'] = $key;
        
        $this->logInfo("Attempting to connect to {$ip}:{$port}...");

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $this->connection = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout);
                if (!$this->connection) {
                    throw new Exception("Socket connection failed: {$errstr} ({$errno})");
                }
                
                stream_set_timeout($this->connection, $this->timeout);
                stream_set_blocking($this->connection, true);

                if (method_exists($this, 'performHandshake')) {
                    if ($this->performHandshake()) {
                        $this->isConnected = true; // CRITICAL: Set to true only after successful handshake
                        $this->logInfo("Connection and handshake successful.");
                        return true;
                    }
                    $this->logError("Handshake failed after connection was established.");
                    @fclose($this->connection);
                    $this->connection = null;
                } else {
                    $this->isConnected = true;
                    return true;
                }
            } catch (Exception $e) {
                $this->logError("Connection attempt {$attempt} failed: " . $e->getMessage());
                if ($attempt < $this->retryAttempts) sleep($this->retryDelay);
            }
        }
        
        $this->isConnected = false;
        $this->lastError = "All connection attempts failed for {$ip}:{$port}.";
        return false;
    }

    public function disconnect(): void {
        if ($this->isConnected && is_resource($this->connection)) {
            if (method_exists($this, 'sendExitCommand')) {
                try {
                    $this->sendExitCommand();
                } catch (Exception $e) {
                    $this->logError("Exception during polite disconnect: " . $e->getMessage());
                }
            }
            @fclose($this->connection);
        }
        $this->connection = null;
        $this->isConnected = false;
        $this->logInfo("Disconnected from device {$this->host}");
    }
    
    public function setConfig(array $config): void { /* ... */ }
    public function getLastError(): string { return $this->lastError; }
    public function isConnected(): bool { return $this->isConnected; }
    protected function logInfo(string $message): void { /* ... */ }
    protected function logError(string $message): void { /* ... */ }
    private function log(string $level, string $message): void { /* ... */ }
    
    abstract public function getUsers(): array;
    abstract public function getAttendanceLogs(): array;
    abstract public function addUser(string $userId, array $userData): bool;
    abstract public function deleteUser(string $userId): bool;
    abstract public function updateUser(string $userId, array $userData): bool;
    abstract public function clearAttendanceData(): bool;
    abstract public function getDeviceName(): string;
}