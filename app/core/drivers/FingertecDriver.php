<?php
// in file: app/core/drivers/FingertecDriver.php

require_once __DIR__ . '/DeviceDriverInterface.php';

class FingertecDriver implements DeviceDriverInterface
{
    private $tad;
    private $is_connected = false;

    public function connect(string $ip, int $port, $com_key = 0): bool
    {
        require_once __DIR__ . '/lib/fingertec/TAD_PHP_Library.php';
        
        $this->tad = new TAD($ip, $port, (int)$com_key);
        $this->is_connected = $this->tad->connect();
        
        return $this->is_connected;
    }

    public function disconnect(): void
    {
        if ($this->tad && $this->is_connected) {
            $this->tad->disconnect();
            $this->is_connected = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->is_connected;
    }

    public function getUsers(): array
    {
        if (!$this->isConnected()) {
            return [];
        }
        return $this->tad->getUsers();
    }

    public function getAttendanceLogs(): array
    {
        if (!$this->isConnected()) {
            return [];
        }
        return $this->tad->getAttendanceLogs();
    }
    
    public function getDeviceName(): string
    {
         if (!$this->isConnected()) {
            return 'N/A';
        }
        return 'Fingertec Device';
    }
    
    public function getVersion(): string
    {
        return $this->tad ? $this->tad->getVersion() : 'N/A';
    }

    public function addUser(array $data): bool
    {
        return false;
    }

    public function updateUser(string $employee_code, array $userData): bool
    {
        return false;
    }

    public function deleteUser(string $employee_code): bool
    {
        return false;
    }
}