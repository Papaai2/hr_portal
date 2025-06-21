<?php
// DeviceDriverInterface.php
// Complete version with all required methods
// Prevent multiple declarations
if (!interface_exists('DeviceDriverInterface')) {
/**
* Device Driver Interface
* Provides standard methods for hardware device communication
*/
interface DeviceDriverInterface
{
/**
* Establishes a connection to the hardware device.
* @param string $ip The device IP address
* @param int $port The device port number  
* @param string|null $key Optional authentication key
* @return bool True if connection successful, false otherwise
*/
public function connect(string $ip, int $port, ?string $key): bool;
/**
* Terminates the connection to the hardware device.
* @return void
*/
public function disconnect(): void;
/**
* Retrieves the device's name or model number.
* @return string The device name/model
*/
public function getDeviceName(): string;
/**
* Retrieves a list of all users registered on the device.
* @return array Array of user data
*/
public function getUsers(): array;
/**
* Retrieves all new attendance logs from the device.
* @return array Array of attendance log entries
*/
public function getAttendanceLogs(): array;
/**
* Adds a new user to the device.
* @param string $userId The user ID/employee code
* @param array $userData User data (name, password, role, etc.)
* @return bool True if user added successfully, false otherwise
*/
public function addUser(string $userId, array $userData): bool;
/**
* Deletes a user from the device.
* @param string $userId The user ID/employee code to delete
* @return bool True if user deleted successfully, false otherwise
*/
public function deleteUser(string $userId): bool;
/**
* Updates an existing user on the device.
* @param string $userId The user ID/employee code to update
* @param array $userData Updated user data
* @return bool True if user updated successfully, false otherwise
*/
public function updateUser(string $userId, array $userData): bool;
/**
* Clears all attendance data from the device.
* @return bool True if data cleared successfully, false otherwise
*/
public function clearAttendanceData(): bool;
}
}