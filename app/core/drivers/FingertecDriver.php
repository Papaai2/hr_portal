<?php
// in file: app/core/drivers/FingertecDriver.php

require_once __DIR__ . '/lib/fingertec/TAD_PHP_Library.php';
require_once __DIR__ . '/DeviceDriverInterface.php';

class FingertecDriver implements DeviceDriverInterface
{
    private ?TAD $connection = null;

    public function connect(string $ip, int $port, ?string $key = null): bool
    {
        try {
            $this->connection = new TAD($ip, $port);
            
            // Check the connection status after the handshake in the constructor
            if ($this->connection->isConnected()) {
                return true;
            }
        } catch (Exception $e) {
            error_log("FingerTec connection failed for IP {$ip}: " . $e->getMessage());
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
        return $this->connection ? $this->connection->getVersion() : 'Fingertec Device';
    }

    public function getUsers(): array
    {
        return $this->connection ? $this->connection->getUsers() : [];
    }

    // Stubs, matching the library
    public function getAttendanceLogs(): array { return []; }
    public function addUser(array $userData): bool { return false; }
    public function updateUser(string $employee_code, array $userData): bool { return false; }
    public function deleteUser(string $employee_code): bool { return false; }
}