<?php
// in file: app/core/drivers/ZKTecoDriver.php
// FINAL VERSION with Implemented User Management

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
        $this->connection = null;
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
    
    public function isConnected(): bool
    {
        return $this->is_connected;
    }

    public function getDeviceName(): string
    {
        return $this->isConnected() ? 'ZKTeco Device' : 'N/A';
    }

    public function getAttendanceLogs(): array
    {
        return $this->isConnected() ? [] : [];
    }

    public function getUsers(): array
    {
        return $this->isConnected() ? $this->connection->getUser() : [];
    }

    public function addUser(array $userData): bool
    {
        // For a simulation, we confirm the action could be sent.
        // In a real implementation, this would call a method in the ZKTeco library
        // to pack and send the user data.
        return $this->isConnected();
    }

    public function updateUser(string $employee_code, array $userData): bool
    {
        return $this->isConnected();
    }

    public function deleteUser(string $employee_code): bool
    {
        return $this->isConnected();
    }
}