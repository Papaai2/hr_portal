<?php

/**
 * Interface DeviceDriverInterface
 *
 * This interface defines the contract that all hardware device drivers must adhere to.
 * It ensures that the main application can interact with any supported hardware
 * in a standardized way, without needing to know the specific implementation details
 * of the device's communication protocol.
 */
interface DeviceDriverInterface
{
    /**
     * Establishes a connection to the hardware device.
     *
     * @param string $ip The IP address of the device.
     * @param int $port The communication port of the device.
     * @param string $key The communication key or password for the device (if any).
     * @return bool True on successful connection, false otherwise.
     */
    public function connect(string $ip, int $port, string $key): bool;

    /**
     * Terminates the connection to the hardware device.
     */
    public function disconnect(): void;

    /**
     * Retrieves the device's name or model number.
     *
     * @return string The name of the device. Returns an empty string on failure.
     */
    public function getDeviceName(): string;

    /**
     * Retrieves a list of all users registered on the device.
     *
     * @return array An array of users. Each user should be an associative array
     * with a standardized format, e.g.:
     * [
     * ['employee_code' => '101', 'name' => 'John Doe', 'role' => 'User'],
     * ['employee_code' => '102', 'name' => 'Jane Smith', 'role' => 'Admin']
     * ]
     */
    public function getUsers(): array;

    /**
     * Retrieves all new attendance logs from the device.
     *
     * @return array An array of attendance logs. Each log should be an associative array
     * with a standardized format, e.g.:
     * [
     * ['employee_code' => '101', 'punch_time' => '2023-10-27 09:00:00', 'punch_state' => 0],
     * ['employee_code' => '101', 'punch_time' => '2023-10-27 17:30:00', 'punch_state' => 1]
     * ]
     */
    public function getAttendanceLogs(): array;
}
