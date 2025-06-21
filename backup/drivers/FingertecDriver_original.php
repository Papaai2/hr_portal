<?php
// in file: app/core/drivers/FingertecDriver.php
// FINAL REVERTED VERSION

require_once __DIR__ . '/DeviceDriverInterface.php';
require_once __DIR__ . '/lib/fingertec/TAD_PHP_Library.php';

class FingertecDriver implements DeviceDriverInterface
{
    private ?TAD $tad = null;
    private bool $is_connected = false;

    public function connect(string $ip, int $port, ?string $key = '0'): bool
    {
        $this->tad = new TAD($ip, $port, (int)$key);
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
    
    public function getDeviceName(): string
    {
         return $this->is_connected ? 'Fingertec Device' : 'N/A';
    }

    public function getUsers(): array
    {
        return $this->is_connected ? $this->tad->getUsers() : [];
    }

    public function getAttendanceLogs(): array
    {
        return $this->is_connected ? [] : [];
    }
}