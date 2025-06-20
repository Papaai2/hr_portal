<?php

require_once __DIR__ . '/lib/fingertec/TAD_PHP_Library.php';

class ZKTecoDriver implements DeviceDriverInterface
{
    private ?TAD $connection = null;

    public function connect(string $ip, int $port, ?string $key = null): bool
    {
        try {
            $this->connection = @new TAD($ip, $port);
            if ($this->connection && $this->connection->get_version()) {
                return true;
            }
        } catch (Exception $e) {
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

    public function getDeviceName(): string
    {
        if (!$this->connection) {
            return '';
        }
        $version = $this->connection->get_version();
        return is_string($version) ? trim($version) : 'ZKTeco Device';
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
                if (isset($log['userid'], $log['timestamp'], $log['type'])) {
                    $punch_state = $this->mapDeviceStatusToPunchState($log['type']);
                    $standardizedLogs[] = [
                        'employee_code' => (string)$log['userid'],
                        'punch_time'    => date('Y-m-d H:i:s', $log['timestamp']),
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
        $rawUsers = $this->connection->get_user_info();
        if (!is_array($rawUsers)) {
            return [];
        }

        $standardizedUsers = [];
        foreach ($rawUsers as $employeeCode => $user) {
             // Role mapping: 0=User, >0=Some form of Admin (e.g., 14 for Super Admin).
            $role_text = ($user['role'] > 0) ? 'Admin' : 'User';
            $standardizedUsers[] = [
                'employee_code' => (string)$employeeCode,
                'name'          => $user['name'],
                'role'          => $role_text
            ];
        }
        return $standardizedUsers;
    }
    
    private function mapDeviceStatusToPunchState(int $deviceStatus): int
    {
        $out_states = [1, 2, 5];
        if (in_array($deviceStatus, $out_states, true)) {
            return 1;
        } else {
            return 0;
        }
    }
}