<?php
// in file: app/core/drivers/DeviceDriverInterface.php

/**
 * Interface DeviceDriverInterface
 *
 * This interface defines the contract that all hardware device drivers must adhere to.
 * It ensures the application can interact with any supported hardware in a standardized way.
 */
interface DeviceDriverInterface
{
    /**
     * Establishes a connection to the hardware device.
     *
     * @param string $ip The IP address of the device.
     * @param int $port The communication port of the device.
     * @param string|null $key The communication key or password for the device.
     * @return bool True on successful connection, false otherwise.
     */
    public function connect(string $ip, int $port, ?string $key): bool;

    /**
     * Terminates the connection to the hardware device.
     */
    public function disconnect(): void;

    /**
     * Checks if the driver is currently connected to a device.
     *
     * @return bool True if connected, false otherwise.
     */
    public function isConnected(): bool;

    /**
     * Retrieves the device's name, model, or version number.
     *
     * @return string The name of the device. Returns an empty string on failure.
     */
    public function getDeviceName(): string;

    /**
     * Retrieves a list of all users registered on the device.
     *
     * The format of each user in the array should be standardized to:
     * ['employee_code' => string, 'name' => string, 'role' => string]
     *
     * @return array An array of users.
     */
    public function getUsers(): array;

    /**
     * Retrieves all new attendance logs from the device.
     *
     * @return array An array of attendance logs in a standardized format.
     */
    public function getAttendanceLogs(): array;
}