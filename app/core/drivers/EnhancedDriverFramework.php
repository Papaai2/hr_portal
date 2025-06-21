<?php
/**
 * Enhanced Biometric Driver Framework for Windows
 * File: app/core/drivers/EnhancedDriverFramework.php
 */

require_once __DIR__ . '/DeviceDriverInterface.php';

abstract class EnhancedBaseDriver implements DeviceDriverInterface {
    protected string $host = '';
    protected int $port = 4370;
    protected int $timeout = 30;
    protected $socket = null;
    protected $connection = null; // Can be a socket resource or stream
    protected string $lastError = '';
    protected bool $isConnected = false;
    protected array $deviceInfo = [];
    protected array $config = [];
    protected int $retryAttempts = 3;
    protected int $retryDelay = 2; // seconds
    protected int $sessionId = 0;
    protected int $replyId = 0;
    protected string $logPath = '';
    
    public function __construct(array $config = []) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->host = $this->config['host'] ?? '';
        $this->port = $this->config['port'] ?? 4370;
        $this->timeout = $this->config['timeout'] ?? 30;
        $this->retryAttempts = $this->config['retry_attempts'] ?? 3;
        $this->retryDelay = $this->config['retry_delay'] ?? 2;
        $this->logPath = $this->config['log_path'] ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }
    }

    abstract protected function getDefaultConfig(): array;

    public function ping(string $ip, int $port): bool {
        // Ping uses a very short timeout to avoid delaying the UI.
        $quickTimeout = 2;
        $socket = @fsockopen($ip, $port, $errno, $errstr, $quickTimeout);
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
        
        $this->logInfo("Attempting to connect to {$ip}:{$port} with timeout {$this->timeout}s and {$this->retryAttempts} attempts.");
        if ($this->isFakeDevice($ip)) {
            return $this->connectToFakeDevice($ip, $port);
        }

        // Try different connection methods for wider compatibility.
        $connectionMethods = [
            'stream_socket' => [$this, 'connectViaStreamSocket'],
            'fsockopen' => [$this, 'connectViaFsockopen'],
        ];

        foreach ($connectionMethods as $methodName => $callback) {
            $this->logInfo("Attempting connection via {$methodName}");
            for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
                try {
                    if (call_user_func($callback)) {
                        $this->isConnected = true;
                        $this->logInfo("Connected successfully via {$methodName} on attempt {$attempt}.");

                        // After connecting, perform the device-specific handshake.
                        if (method_exists($this, 'performHandshake')) {
                            if ($this->performHandshake()) {
                                return true; // Handshake successful
                            }
                            $this->logError("Handshake failed after successful connection via {$methodName}.");
                        } else {
                            // If no handshake method, assume connection is sufficient.
                            if ($this->verifyConnection()) {
                                return true;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->logError("Connection attempt {$attempt} via {$methodName} failed: " . $e->getMessage());
                    if ($attempt < $this->retryAttempts) {
                        sleep($this->retryDelay);
                    }
                }
            }
        }
        $this->logError("All connection methods failed for {$ip}:{$port}.");
        $this->lastError = "Unable to connect to device. All connection methods failed.";
        return false;
    }
    
    protected function setStreamTimeouts(): void {
        if (is_resource($this->connection) || (is_object($this->connection) && get_resource_type($this->connection) === 'stream')) {
            stream_set_timeout($this->connection, $this->timeout);
            stream_set_blocking($this->connection, true);
        }
    }

    protected function connectViaStreamSocket(): bool {
        $this->connection = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout);
        if (!$this->connection) {
            throw new Exception("Stream Socket connection failed: {$errstr} ({$errno})");
        }
        $this->socket = $this->connection; // For backward compatibility if needed
        $this->setStreamTimeouts(); // CRITICAL FIX: Set timeout for all subsequent reads/writes
        return true;
    }

    protected function connectViaFsockopen(): bool {
        $this->connection = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->connection) {
            throw new Exception("Fsockopen connection failed: {$errstr} ({$errno})");
        }
        $this->socket = $this->connection;
        $this->setStreamTimeouts(); // CRITICAL FIX: Set timeout for all subsequent reads/writes
        return true;
    }

    protected function isFakeDevice($ip): bool {
        return in_array(strtolower($ip), ['0.0.0.0', 'fake', 'test', 'localhost', '127.0.0.1']);
    }

    protected function connectToFakeDevice($ip, $port): bool {
        $this->logInfo("Connecting to MOCK device at {$ip}:{$port}");
        $this->socket = fopen('php://memory', 'r+');
        $this->connection = $this->socket;
        $this->isConnected = true;
        $this->deviceInfo = [
            'manufacturer' => get_class($this),
            'model' => 'Fake Device',
            'firmware' => '1.0.0',
            'serial' => 'FAKE' . date('Ymd')
        ];
        $this->logInfo("Successfully connected to MOCK device.");
        return true;
    }

    protected function verifyConnection(): bool {
        if (!$this->connection || $this->isFakeDevice($this->host)) return true;
        try {
            if (stream_get_meta_data($this->connection)['eof']) {
                $this->logError("Connection verification failed: socket EOF");
                $this->lastError = "Connection closed by the device.";
                return false;
            }
            return true;
        } catch (Exception $e) {
            $this->logError("Connection verification failed: " . $e->getMessage());
            $this->lastError = "Connection verification failed.";
            return false;
        }
    }

    public function disconnect(): void {
        if ($this->connection && is_resource($this->connection)) {
            @fclose($this->connection);
        }
        $this->socket = null;
        $this->connection = null;
        $this->isConnected = false;
        $this->sessionId = 0;
        $this->replyId = 0;
        $this->logInfo("Disconnected from device {$this->host}");
    }
    
    public function setConfig(array $config): void {
        $this->config = array_merge($this->config, $config);
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function getLastError(): string { return $this->lastError; }
    public function isConnected(): bool { return $this->isConnected; }
    protected function logInfo(string $message): void { $this->log('INFO', $message); }
    protected function logError(string $message): void { $this->log('ERROR', $message); $this->lastError = $message; }
    private function log(string $level, string $message): void {
        $logFile = $this->logPath . '/device_driver_' . date('Y-m-d') . '.log';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] [{$level}] [{$this->host}] {$message}" . PHP_EOL;
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    // Abstract methods to be implemented by child classes
    abstract public function getUsers(): array;
    abstract public function getAttendanceLogs(): array;
    abstract public function addUser(string $userId, array $userData): bool;
    abstract public function deleteUser(string $userId): bool;
    abstract public function updateUser(string $userId, array $userData): bool;
    abstract public function clearAttendanceData(): bool;
}