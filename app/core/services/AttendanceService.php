<?php
// in file: app/core/services/AttendanceService.php
require_once __DIR__ . '/../drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/../drivers/EnhancedDriverFramework.php';
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
     * and flags it if it appears to be a violation (e.g., a late in, early out).
     * It also implements a new rule: if a punch occurs within 3 minutes of the last valid punch, it's ignored.
     *
     * @param string $employeeCode The Employee Code from the machine/log.
     * @param string $punchTime The timestamp of the punch.
     * @param int $deviceId The ID of the device sending the punch.
     * @return boolean True on successful save, false otherwise (including ignored punches).
     */
    public function processPunch(string $employeeCode, string $punchTime, int $deviceId): bool
    {
        $punchDateTime = new DateTime($punchTime);
        $punchDate = $punchDateTime->format('Y-m-d');
        
        // Get user and shift details for the day
        $userShift = $this->getUserAndShiftDetails($employeeCode, $punchDate);
        $shift = $userShift['shift'] ?? null;
        $expectedState = self::PUNCH_IN; // Default for first punch of the day
        $violation = null;
        $calculatedExpectedIn = null;
        $calculatedExpectedOut = null;

        // Get the last VALID punch to determine the next logical state and apply 3-minute ignore rule.
        $lastValidPunch = $this->getLastValidPunchForDay($employeeCode, $punchDate);
        
        if ($lastValidPunch) {
            $secondsSinceLast = $punchDateTime->getTimestamp() - (new DateTime($lastValidPunch['punch_time']))->getTimestamp();

            // NEW RULE: If punch occurs within 3 minutes (180 seconds) of the last valid punch, ignore it.
            if ($secondsSinceLast < 180) { 
                // Punch ignored: do not save it, return false.
                return false; 
            }

            // If not ignored, determine the next logical state (alternating from last valid punch)
            $expectedState = ($lastValidPunch['punch_state'] == self::PUNCH_IN) ? self::PUNCH_OUT : self::PUNCH_IN;
            
            // Note: The previous 'double_punch' check (secondsSinceLast < 60) is now superseded
            // by the more aggressive 3-minute ignore rule.
        }
        // else: This is the first punch for the day, so expectedState remains PUNCH_IN (0)

        // Apply shift rules if a shift is assigned to the user
        if ($shift) {
            $shiftStartTime = new DateTime($punchDate . ' ' . $shift['start_time']);
            $shiftEndTime = new DateTime($punchDate . ' ' . $shift['end_time']);
            // Adjust end time for night shifts: if the shift crosses midnight, the end time is on the next day
            if ($shift['is_night_shift'] && $shiftEndTime < $shiftStartTime) {
                $shiftEndTime->modify('+1 day');
            }
            // Store the expected times from the shift
            $calculatedExpectedIn = $shiftStartTime->format('H:i:s');
            $calculatedExpectedOut = $shiftEndTime->format('H:i:s');

            // Check for late in or early out based on shift times and grace periods
            if ($expectedState == self::PUNCH_IN) {
                $graceInLimit = clone $shiftStartTime;
                $graceInLimit->modify('+' . $shift['grace_period_in'] . ' minutes');
                if ($punchDateTime > $graceInLimit) {
                    $violation = 'late_in'; // Punch-in after grace period
                }
            } elseif ($expectedState == self::PUNCH_OUT) {
                $graceOutLimit = clone $shiftEndTime;
                $graceOutLimit->modify('-' . $shift['grace_period_out'] . ' minutes');
                if ($shift['is_night_shift']) {
                    if ($punchDateTime < $graceOutLimit) {
                         $violation = 'early_out'; // Punch-out before grace period
                    }
                } else {
                    if ($punchDateTime < $graceOutLimit) {
                        $violation = 'early_out'; // Punch-out before grace period
                    }
                }
            }
        }
        
        return $this->savePunch($employeeCode, $punchTime, $expectedState, $deviceId, $violation, $calculatedExpectedIn, $calculatedExpectedOut);
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
     * Fetches the last valid punch for a given employee on a specific date.
     * A punch is considered "valid" if its status is explicitly 'valid'.
     *
     * @param string $employeeCode
     * @param string $punchDate
     * @return array|null The last valid punch record, or null if none exists.
     */
    private function getLastValidPunchForDay(string $employeeCode, string $punchDate): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM attendance_logs 
             WHERE employee_code = ? AND DATE(punch_time) = ? AND status = 'valid'
             ORDER BY punch_time DESC 
             LIMIT 1"
        );
        $stmt->execute([$employeeCode, $punchDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Fetches user ID and their assigned shift details for a given employee code and date.
     *
     * @param string $employeeCode
     * @param string $date The date in Y-m-d format.
     * @return array|null An associative array containing user and shift details, or null if not found.
     */
    private function getUserAndShiftDetails(string $employeeCode, string $date): ?array
    {
        // For simplicity, we assume one shift per user. For complex rotating shifts,
        // you'd need a user_shifts table with date ranges.
        $sql = "SELECT u.id AS user_id, s.shift_name, s.start_time, s.end_time, s.grace_period_in, 
                       s.grace_period_out, s.break_start_time, s.break_end_time, s.is_night_shift
                FROM users u
                LEFT JOIN shifts s ON u.shift_id = s.id
                WHERE u.employee_code = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$employeeCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return [
                'user_id' => $result['user_id'],
                'shift' => [
                    'shift_name' => $result['shift_name'],
                    'start_time' => $result['start_time'],
                    'end_time' => $result['end_time'],
                    'grace_period_in' => $result['grace_period_in'],
                    'grace_period_out' => $result['grace_period_out'],
                    'break_start_time' => $result['break_start_time'],
                    'break_end_time' => $result['break_end_time'],
                    'is_night_shift' => (bool)$result['is_night_shift']
                ]
            ];
        }
        return null;
    }

    /**
     * Saves a validated punch record into the database.
     * The status will be 'valid' if no violation, or 'invalid' if a violation occurred.
     *
     * @param string $employeeCode
     * @param string $punchTime
     * @param integer $punchState
     * @param integer $deviceId
     * @param string|null $violationType
     * @param string|null $expectedInTime The expected punch-in time based on shift.
     * @param string|null $expectedOutTime The expected punch-out time based on shift.
     * @return boolean True on success, false on failure.
     */
    private function savePunch(string $employeeCode, string $punchTime, int $punchState, int $deviceId, ?string $violationType, ?string $expectedInTime, ?string $expectedOutTime): bool
    {
        // Use 'valid' or 'invalid' status based on violation type
        $status = ($violationType === null) ? 'valid' : 'invalid';
        $sql = "INSERT INTO attendance_logs (employee_code, punch_time, punch_state, device_id, status, violation_type, expected_in, expected_out) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$employeeCode, $punchTime, $punchState, $deviceId, $status, $violationType, $expectedInTime, $expectedOutTime]);
        } catch (PDOException $e) {
            error_log("Failed to save punch: " . $e->getMessage());
            return false;
        }
    }
}