<?php
/**
 * Enhanced Biometric Driver Framework for Windows
 * File: app/core/drivers/EnhancedDriverFramework.php
 */

require_once __DIR__ . '/DeviceDriverInterface.php';

abstract class EnhancedBaseDriver implements DeviceDriverInterface {
    protected $host = '';
    protected $port = 4370;
    protected $timeout = 30;
    protected $socket = null;
    protected $connection = null;
    protected $lastError = '';
    protected $isConnected = false;
    protected $deviceInfo = [];
    protected $errorLog = [];
    protected $config = [];
    protected $retryAttempts = 3;
    protected $retryDelay = 2;
    protected $sessionId = 0;
    protected $replyId = 0;
    protected $logPath = '';
    protected $configPath = '';

    public function __construct($config = []) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->host = $this->config['host'] ?? '';
        $this->port = $this->config['port'] ?? 4370;
        $this->timeout = $this->config['timeout'] ?? 30;
        $this->retryAttempts = $this->config['retry_attempts'] ?? 5;
        $this->retryDelay = $this->config['retry_delay'] ?? 2;
        $this->logPath = $this->config['log_path'] ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'logs';
        $this->configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'device_configs';
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }
    }

    abstract protected function getDefaultConfig();

    public function ping(string $ip, int $port): bool {
        $quickTimeout = 1;
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
        if ($this->isFakeDevice($ip)) return $this->connectToFakeDevice($ip, $port);

        $connectionMethods = [
            'tcp_socket' => [$this, 'connectViaTCPSocket'],
            'fsockopen' => [$this, 'connectViaFsockopen'],
            'stream_socket' => [$this, 'connectViaStreamSocket']
        ];

        foreach ($connectionMethods as $method => $callback) {
            $this->logInfo("Attempting connection via {$method}");
            for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
                try {
                    if (call_user_func($callback)) {
                        $this->isConnected = true;
                        $this->logInfo("Connected successfully via {$method} on attempt {$attempt}");
                        if (method_exists($this, 'performHandshake')) {
                            if ($this->performHandshake()) return true;
                        } else {
                            if ($this->verifyConnection()) return true;
                        }
                    }
                } catch (Exception $e) {
                    $this->logError("Connection attempt {$attempt} via {$method} failed: " . $e->getMessage());
                    if ($attempt < $this->retryAttempts) sleep($this->retryDelay);
                }
            }
        }
        $this->logError("All connection methods failed for {$ip}:{$port}");
        return false;
    }

    protected function isFakeDevice($ip) { return in_array($ip, ['0.0.0.0']) || strpos($ip, 'fake') !== false || strpos($ip, 'test') !== false; }

    protected function connectToFakeDevice($ip, $port) {
        $this->logInfo("Connecting to MOCK device at {$ip}:{$port}");
        $this->socket = fopen('php://memory', 'r+');
        $this->connection = $this->socket;
        $this->isConnected = true;
        $this->deviceInfo = ['manufacturer' => get_class($this), 'model' => 'Fake Device', 'firmware' => '1.0.0', 'serial' => 'FAKE' . date('Ymd'), 'supports_realtime' => true, 'communication_type' => 'fake'];
        $this->logInfo("Successfully connected to MOCK device");
        return true;
    }

    protected function connectViaTCPSocket() {
        $context = stream_context_create(['socket' => ['so_keepalive' => true]]);
        $this->socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) throw new Exception("TCP Socket connection failed: {$errstr} ({$errno})");
        $this->connection = $this->socket; stream_set_blocking($this->socket, 1); return true;
    }

    protected function connectViaFsockopen() {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) throw new Exception("Fsockopen connection failed: {$errstr} ({$errno})");
        $this->connection = $this->socket; return true;
    }

    protected function connectViaStreamSocket() {
        $context = stream_context_create();
        $this->socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) throw new Exception("Stream socket connection failed: {$errstr} ({$errno})");
        $this->connection = $this->socket; return true;
    }

    protected function verifyConnection() {
        if (!$this->socket || $this->isFakeDevice($this->host)) return true;
        try {
            if (stream_get_meta_data($this->socket)['eof']) { $this->logError("Connection verification failed: socket EOF"); return false; }
            return true;
        } catch (Exception $e) { $this->logError("Connection verification failed: " . $e->getMessage()); return false; }
    }

    // FIXED: Added the missing testConnection method back.
    protected function testConnection() {
        if (!$this->isConnected || !$this->socket) {
            return false;
        }
        if ($this->isFakeDevice($this->host)) {
            return true;
        }
        $meta = stream_get_meta_data($this->socket);
        return !$meta['eof'];
    }

    public function disconnect(): void {
        if ($this->socket) { @fclose($this->socket); $this->socket = null; $this->connection = null; }
        $this->isConnected = false; $this->sessionId = 0; $this->replyId = 0;
        $this->logInfo("Disconnected from device {$this->host}");
    }
    
    public function setConfig($config) {
        $this->config = array_merge($this->config, $config);
        $this->host = $this->config['host'] ?? $this->host;
        $this->port = $this->config['port'] ?? $this->port;
        $this->timeout = $this->config['timeout'] ?? $this->timeout;
        $this->retryAttempts = $this->config['retry_attempts'] ?? $this->retryAttempts;
        $this->retryDelay = $this->config['retry_delay'] ?? $this->retryDelay;
    }

    public function getDeviceName(): string { if (!$this->isConnected) return 'Not Connected'; if ($this->isFakeDevice($this->host)) return 'Fake Device'; return 'Connected Device'; }
    public function getUsers(): array { if ($this->isFakeDevice($this->host)) return $this->getFakeUsers(); return []; }
    public function getAttendanceLogs(): array { if ($this->isFakeDevice($this->host)) return $this->getFakeAttendanceLogs(); return []; }
    protected function getFakeUsers() { return [['user_id' => '1', 'name' => 'Mock User 1']]; }
    protected function getFakeAttendanceLogs() { return [['user_id' => '1', 'timestamp' => date('Y-m-d H:i:s')]]; }
    protected function log($level, $message) { $logEntry = "[" . date('Y-m-d H:i:s') . "] [{$level}] {$message}" . PHP_EOL; @file_put_contents($this->logPath . '/device_driver.log', $logEntry, FILE_APPEND); }
    protected function logInfo($message) { $this->log('INFO', $message); }
    protected function logError($message) { $this->log('ERROR', $message); $this->lastError = $message; }
    public function getLastError() { return $this->lastError; }
    public function isConnected() { return $this->isConnected; }

    abstract public function addUser(string $userId, array $userData): bool;
    abstract public function deleteUser(string $userId): bool;
    abstract public function updateUser(string $userId, array $userData): bool;
    abstract public function clearAttendanceData(): bool;
}