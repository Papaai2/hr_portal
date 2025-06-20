<?php
// in file: app/core/drivers/DeviceDriverInterface.php
// FINAL CORRECTED VERSION

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
     * @param string|null $key The communication key or password for the device (if any).
     * @return bool True on successful connection, false otherwise.
     */
    public function connect(string $ip, int $port, ?string $key): bool;

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
     * with a standardized format.
     */
    public function getUsers(): array;

    /**
     * Retrieves all new attendance logs from the device.
     *
     * @return array An array of attendance logs in a standardized format.
     */
    public function getAttendanceLogs(): array;

    /**
     * Adds a new user to the device.
     *
     * @param array $userData Associative array containing user data, e.g.,
     * ['employee_code' => '103', 'name' => 'Peter Jones', 'password' => '', 'card' => '']
     * @return bool True on success, false on failure.
     */
    public function addUser(array $userData): bool;

    /**
     * Updates an existing user's information on the device.
     *
     * @param string $employee_code The employee code of the user to update.
     * @param array $userData Associative array with the data to update.
     * @return bool True on success, false on failure.
     */
    public function updateUser(string $employee_code, array $userData): bool;

    /**
     * Deletes a user from the device.
     *
     * @param string $employee_code The employee code of the user to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteUser(string $employee_code): bool;
}