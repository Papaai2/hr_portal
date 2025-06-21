<?php
// in file: app/core/drivers/ZKTecoDriver.php
// FINAL REVERTED VERSION

require_once __DIR__ . '/lib/zkteco/ZKTeco.php'; 
require_once __DIR__ . '/DeviceDriverInterface.php';

class ZKTecoDriver implements DeviceDriverInterface
{
    private ?ZKTeco $connection = null;
    private bool $is_connected = false;

    public function connect(string $ip, int $port, ?string $key = null): bool
    {
        try {
            $this->connection = new ZKTeco($ip, $port);
            $this->is_connected = $this->connection->connect();
            return $this->is_connected; 
        } catch (Exception $e) {
            error_log("ZKTeco connection failed for IP {$ip}: " . $e->getMessage());
        }
        $this->is_connected = false;
        return false;
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->disconnect();
            $this->connection = null;
            $this->is_connected = false;
        }
    }
    
    public function getDeviceName(): string
    {
        return $this->is_connected ? 'ZKTeco Device' : 'N/A';
    }

    public function getAttendanceLogs(): array
    {
        return $this->is_connected ? [] : [];
    }

    public function getUsers(): array
    {
        return $this->is_connected ? $this->connection->getUser() : [];
    }
}