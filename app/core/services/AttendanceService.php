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
     * @param integer $userId The ID of the user punching.
     * @param string $punchTime The timestamp of the punch.
     * @return boolean True on successful save, false otherwise.
     */
    public function processPunch(int $userId, string $punchTime): bool
    {
        $punchDate = (new DateTime($punchTime))->format('Y-m-d');
        
        // Get the last VALID punch to determine the next logical state.
        $lastValidPunch = $this->getLastValidPunchForDay($userId, $punchDate);
        
        $expectedState = self::PUNCH_IN; // By default, the first punch of the day is IN.
        $violation = null;

        if ($lastValidPunch) {
            // A previous valid punch exists, so we expect the opposite state.
            $expectedState = ($lastValidPunch['punch_state'] == self::PUNCH_IN) ? self::PUNCH_OUT : self::PUNCH_IN;

            // VIOLATION CHECK 1: Is this new punch happening too soon after the last one?
            $secondsSinceLast = (new DateTime($punchTime))->getTimestamp() - (new DateTime($lastValidPunch['punch_time']))->getTimestamp();
            if ($secondsSinceLast < 60) { // Cooldown of 1 minute.
                $violation = 'double_punch';
            }
        }
        
        // A nightly or weekly script could be added to run more complex violation checks,
        // such as finding an IN without a matching OUT for a whole day and flagging it 'missing_out_punch'.

        return $this->savePunch($userId, $punchTime, $expectedState, $violation);
    }

    /**
     * Saves a batch of standardized logs from a device by processing each one.
     *
     * @param array $logs An array of log entries.
     * @return integer The number of logs successfully saved.
     */
    public function saveStandardizedLogs(array $logs): int
    {
        $savedCount = 0;
        foreach ($logs as $log) {
            // Ensure required keys exist before processing
            if (isset($log['employee_code']) && isset($log['punch_time'])) {
                if ($this->processPunch((int)$log['employee_code'], $log['punch_time'])) {
                    $savedCount++;
                }
            }
        }
        return $savedCount;
    }

    /**
     * Fetches the last non-error punch for a given user on a specific date.
     * This is used to determine the logical sequence of punches.
     *
     * @param integer $userId
     * @param string $punchDate
     * @return array|null The last valid punch record, or null if none exists.
     */
    private function getLastValidPunchForDay(int $userId, string $punchDate): ?array
    {
        // Notice we only look for punches that are NOT already flagged as an error.
        $stmt = $this->pdo->prepare(
            "SELECT * FROM attendance_logs 
             WHERE user_id = ? AND DATE(punch_time) = ? AND status != 'error'
             ORDER BY punch_time DESC 
             LIMIT 1"
        );
        $stmt->execute([$userId, $punchDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Saves a validated punch record into the database, including its status and any violation type.
     *
     * @param integer $userId
     * @param string $punchTime
     * @param integer $punchState
     * @param string|null $violationType
     * @param integer|null $deviceId (Kept for future use)
     * @return boolean True on success, false on failure.
     */
    private function savePunch(int $userId, string $punchTime, int $punchState, ?string $violationType, ?int $deviceId = null): bool
    {
        // If there's a violation, the status is 'error'. Otherwise, it's 'unprocessed' and needs to be paired later.
        $status = ($violationType === null) ? 'unprocessed' : 'error';

        $sql = "INSERT INTO attendance_logs (user_id, punch_time, punch_state, device_id, status, violation_type) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId, $punchTime, $punchState, $deviceId, $status, $violationType]);
        } catch (PDOException $e) {
            // Log error, maybe duplicate punch attempt if you have a unique constraint
            error_log("Failed to save punch: " . $e->getMessage());
            return false;
        }
    }
}