<?php
/**
 * Device Driver Interface
 * Provides standard methods for hardware device communication
 * Compatible with both Fingertec and ZKTeco devices
 */

if (!interface_exists('DeviceDriverInterface')) {
    interface DeviceDriverInterface
    {
        /**
         * Establishes a full connection to the device.
         */
        public function connect(string $ip, int $port, ?string $key = null): bool;

        /**
         * Disconnects from the device.
         */
        public function disconnect(): void;

        /**
         * Performs a quick check to see if the device is reachable.
         * This should be a lightweight operation with a very short timeout.
         */
        public function ping(string $ip, int $port): bool;

        /**
         * Retrieves all users from the device.
         */
        public function getUsers(): array;

        /**
         * Retrieves all attendance logs from the device.
         */
        public function getAttendanceLogs(): array;

        /**
         * Adds a new user to the device.
         */
        public function addUser(string $userId, array $userData): bool;

        /**
         * Deletes a user from the device.
         */
        public function deleteUser(string $userId): bool;

        /**
         * Updates an existing user's data.
         */
        public function updateUser(string $userId, array $userData): bool;
        
        /**
         * Clears all attendance data from the device.
         */
        public function clearAttendanceData(): bool;
    }
}