<?php
// in file: app/core/drivers/DeviceDriverInterface.php
// FINAL REVERTED VERSION

interface DeviceDriverInterface
{
    /**
     * Establishes a connection to the hardware device.
     */
    public function connect(string $ip, int $port, ?string $key): bool;

    /**
     * Terminates the connection to the hardware device.
     */
    public function disconnect(): void;

    /**
     * Retrieves the device's name or model number.
     */
    public function getDeviceName(): string;

    /**
     * Retrieves a list of all users registered on the device.
     */
    public function getUsers(): array;

    /**
     * Retrieves all new attendance logs from the device.
     */
    public function getAttendanceLogs(): array;
}