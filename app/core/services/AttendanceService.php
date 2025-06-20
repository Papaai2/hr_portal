<?php
// in file: app/core/services/AttendanceService.php

class AttendanceService
{
    private PDO $pdo;
    private const PUNCH_IN = 0;
    private const PUNCH_OUT = 1;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Processes a single raw punch. It determines the logical punch state (IN/OUT)
     * and flags it if it appears to be a violation (e.g., a double punch).
     *
     * @param string $employeeCode The Employee Code from the machine/log.
     * @param string $punchTime The timestamp of the punch.
     * @param int $deviceId The ID of the device sending the punch.
     * @return boolean True on successful save, false otherwise.
     */
    public function processPunch(string $employeeCode, string $punchTime, int $deviceId): bool
    {
        $punchDate = (new DateTime($punchTime))->format('Y-m-d');
        
        // Get the last VALID punch to determine the next logical state.
        $lastValidPunch = $this->getLastValidPunchForDay($employeeCode, $punchDate);
        
        $expectedState = self::PUNCH_IN;
        $violation = null;

        if ($lastValidPunch) {
            $expectedState = ($lastValidPunch['punch_state'] == self::PUNCH_IN) ? self::PUNCH_OUT : self::PUNCH_IN;

            $secondsSinceLast = (new DateTime($punchTime))->getTimestamp() - (new DateTime($lastValidPunch['punch_time']))->getTimestamp();
            if ($secondsSinceLast < 60) {
                $violation = 'double_punch';
            }
        }
        
        return $this->savePunch($employeeCode, $punchTime, $expectedState, $deviceId, $violation);
    }

    /**
     * Saves a batch of standardized logs from a device by processing each one.
     *
     * @param array $logs An array of log entries.
     * @param int $deviceId The ID of the device these logs came from.
     * @return integer The number of logs successfully saved.
     */
    public function saveStandardizedLogs(array $logs, int $deviceId): int
    {
        $savedCount = 0;
        foreach ($logs as $log) {
            if (isset($log['employee_code']) && isset($log['punch_time'])) {
                if ($this->processPunch($log['employee_code'], $log['punch_time'], $deviceId)) {
                    $savedCount++;
                }
            }
        }
        return $savedCount;
    }

    /**
     * Fetches the last non-error punch for a given employee on a specific date.
     *
     * @param string $employeeCode
     * @param string $punchDate
     * @return array|null The last valid punch record, or null if none exists.
     */
    private function getLastValidPunchForDay(string $employeeCode, string $punchDate): ?array
    {
        // **FIXED**: Query uses employee_code instead of user_id.
        $stmt = $this->pdo->prepare(
            "SELECT * FROM attendance_logs 
             WHERE employee_code = ? AND DATE(punch_time) = ? AND status != 'error'
             ORDER BY punch_time DESC 
             LIMIT 1"
        );
        $stmt->execute([$employeeCode, $punchDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Saves a validated punch record into the database.
     *
     * @param string $employeeCode
     * @param string $punchTime
     * @param integer $punchState
     * @param integer $deviceId
     * @param string|null $violationType
     * @return boolean True on success, false on failure.
     */
    private function savePunch(string $employeeCode, string $punchTime, int $punchState, int $deviceId, ?string $violationType): bool
    {
        $status = ($violationType === null) ? 'unprocessed' : 'error';

        // **FIXED**: Inserts into employee_code instead of user_id.
        $sql = "INSERT INTO attendance_logs (employee_code, punch_time, punch_state, device_id, status, violation_type) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$employeeCode, $punchTime, $punchState, $deviceId, $status, $violationType]);
        } catch (PDOException $e) {
            error_log("Failed to save punch: " . $e->getMessage());
            return false;
        }
    }
}