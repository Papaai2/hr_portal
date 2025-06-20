<?php
// in file: app/core/drivers/FingertecDriver.php

require_once __DIR__ . '/DeviceDriverInterface.php';
require_once __DIR__ . '/lib/fingertec/TAD_PHP_Library.php';

class FingertecDriver implements DeviceDriverInterface
{
    private ?TAD $tad = null;

    public function connect(string $ip, int $port, ?string $com_key = '0'): bool
    {
        $this->tad = new TAD($ip, $port, (int)$com_key);
        return $this->tad->connect();
    }

    public function disconnect(): void
    {
        if ($this->tad && $this->tad->isConnected()) {
            $this->tad->disconnect();
        }
    }

    public function isConnected(): bool
    {
        return $this->tad ? $this->tad->isConnected() : false;
    }

    public function getDeviceName(): string
    {
        return $this->isConnected() ? 'Fingertec Device (' . $this->tad->getVersion() . ')' : 'N/A';
    }

    public function getUsers(): array
    {
        return $this->isConnected() ? $this->tad->getUsers() : [];
    }

    public function getAttendanceLogs(): array
    {
        // This is a stub for now, as log parsing is complex
        return $this->isConnected() ? $this->tad->getAttendanceLogs() : [];
    }
}