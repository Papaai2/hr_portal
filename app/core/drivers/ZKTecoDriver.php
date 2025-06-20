<?php

// Include the interface that this class is contracted to implement.
require_once __DIR__ . '/DeviceDriverInterface.php';

// Include the same library as the Fingertec driver, as it supports both brands.
require_once __DIR__ . '/lib/fingertec/TAD_PHP_Library.php';

/**
 * Class ZKTecoDriver
 *
 * This is the driver implementation for ZKTeco hardware.
 * It leverages the same underlying TAD_PHP_Library as the Fingertec driver,
 * showcasing the reusability of our architecture. It translates results into
 * the standardized format required by the DeviceDriverInterface.
 */
class ZKTecoDriver implements DeviceDriverInterface
{
    /**
     * @var TAD Holds the connection object from the TAD_PHP_Library.
     */
    private $connection = null;

    /**
     * {@inheritdoc}
     * This method uses the TAD_PHP_Library to connect to a ZKTeco device.
     */
    public function connect(string $ip, int $port, string $key): bool
    {
        try {
            $this->connection = new TAD($ip, $port);
            return ($this->connection !== null);
        } catch (Exception $e) {
            // In a production app, you would log this error to a file.
            // error_log("ZKTeco connection failed for IP {$ip}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     * This method uses the TAD_PHP_Library to disconnect from a ZKTeco device.
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->disconnect();
        }
        $this->connection = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeviceName(): string
    {
        if ($this->connection) {
            try {
                $versionInfo = $this->connection->get_version();
                return empty($versionInfo) ? "ZKTeco Device" : trim($versionInfo);
            } catch (Exception $e) {
                return "ZKTeco Device (Error)";
            }
        }
        return "ZKTeco Device (Not Connected)";
    }

    /**
     * {@inheritdoc}
     * Fetches users from a ZKTeco device and standardizes the data format.
     */
    public function getUsers(): array
    {
        if (!$this->connection) {
            return [];
        }

        $standardizedUsers = [];
        $rawUsers = $this->connection->get_user_info();

        if (is_array($rawUsers)) {
            foreach ($rawUsers as $userid => $data) {
                $standardizedUsers[] = [
                    'employee_code' => (string)$userid,
                    'name'          => $data['name'],
                    // Standardizing role: ZKTeco devices often use '14' for an admin user.
                    'role'          => ($data['role'] == 14) ? 'Admin' : 'User'
                ];
            }
        }

        return $standardizedUsers;
    }

    /**
     * {@inheritdoc}
     * Fetches attendance logs from a ZKTeco device and standardizes the data format.
     */
    public function getAttendanceLogs(): array
    {
        if (!$this->connection) {
            return [];
        }

        $standardizedLogs = [];
        $rawLogs = $this->connection->get_attendance_log();

        if (is_array($rawLogs)) {
            foreach ($rawLogs as $log) {
                // Ensure we have the necessary keys before processing
                if (isset($log['userid'], $log['timestamp'], $log['type'])) {
                    $standardizedLogs[] = [
                        'employee_code' => (string)$log['userid'],
                        // Convert timestamp to a standard database-friendly format
                        'punch_time'    => date('Y-m-d H:i:s', $log['timestamp']),
                        // The 'type' from the library directly maps to our punch_state
                        'punch_state'   => $log['type']
                    ];
                }
            }
        }
        
        return $standardizedLogs;
    }
}
