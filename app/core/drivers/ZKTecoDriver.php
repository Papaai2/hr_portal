<?php
// in file: app/core/drivers/ZKTecoDriver.php

require_once __DIR__ . '/lib/zkteco/ZKTeco.php'; 
require_once __DIR__ . '/DeviceDriverInterface.php';

class ZKTecoDriver implements DeviceDriverInterface
{
    private ?ZKTeco $connection = null;

    public function connect(string $ip, int $port, ?string $key = null): bool
    {
        try {
            $this->connection = new ZKTeco($ip, $port);
            if ($this->connection->connect()) {
                return true;
            }
        } catch (Exception $e) {
            error_log("ZKTeco connection failed for IP {$ip}: " . $e->getMessage());
        }
        $this->connection = null;
        return false;
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    public function getDeviceName(): string
    {
        return $this->connection ? $this->connection->getVersion() : 'ZKTeco Device';
    }

    public function getAttendanceLogs(): array
    {
        return $this->connection ? $this->connection->getAttendance() : [];
    }

    public function getUsers(): array
    {
        return $this->connection ? $this->connection->getUser() : [];
    }

    public function addUser(array $userData): bool
    {
        // This is a stub for a real implementation
        return false;
    }

    public function updateUser(string $employee_code, array $userData): bool
    {
        // This is a stub for a real implementation
        return false;
    }

    public function deleteUser(string $employee_code): bool
    {
         // This is a stub for a real implementation
        return false;
    }
}