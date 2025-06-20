<?php

require_once __DIR__ . '/DeviceDriverInterface.php';
require_once __DIR__ . '/lib/fingertec/TAD_PHP_Library.php';

class ZKTecoDriver implements DeviceDriverInterface
{
    private ?TAD $connection = null;

    public function connect(string $ip, int $port, ?string $key = null): bool
    {
        try {
            // Suppress constructor errors, we will check the connection state
            $this->connection = @new TAD($ip, $port);
            // The TAD library connects in the constructor, but we can check the result.
            // A simple way to verify is to try a basic command.
            if ($this->connection && $this->connection->get_version()) {
                return true;
            }
        } catch (Exception $e) {
            // Connection failed
            error_log("ZKTeco connection failed for IP {$ip}: " . $e->getMessage());
        }
        $this->connection = null;
        return false;
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

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
                    
                    // Map device-specific status to standardized punch_state
                    $punch_state = $this->mapDeviceStatusToPunchState($log['type']);

                    $standardizedLogs[] = [
                        'employee_code' => (string)$log['userid'],
                        // Convert timestamp to a standard database-friendly format
                        'punch_time'    => date('Y-m-d H:i:s', $log['timestamp']),
                        // Use the newly mapped, standardized punch_state
                        'punch_state'   => $punch_state
                    ];
                }
            }
        }
        return $standardizedLogs;
    }

    public function getUsers(): array
    {
        if (!$this->connection) {
            return [];
        }
        // This should call a method in the TAD library to get user info
        // and then standardize it.
        return $this->connection->get_user_info();
    }

    /**
     * Maps the raw status code from a device to a simple In (0) or Out (1).
     *
     * @param int $deviceStatus The raw status code from the device.
     * @return int The standardized punch_state code (0 for In, 1 for Out).
     */
    private function mapDeviceStatusToPunchState(int $deviceStatus): int
    {
        // List of device codes that count as an "OUT" punch:
        // 1 = Check-Out, 2 = Break-Out, 5 = Overtime-Out
        $out_states = [1, 2, 5];

        if (in_array($deviceStatus, $out_states, true)) {
            // If the status is any kind of "Out", map it to Standard Check-Out.
            return 1;
        } else {
            // Otherwise, any other status (0, 3, 4, and any unknowns)
            // will be mapped to a standard Check-In.
            return 0;
        }
    }
}