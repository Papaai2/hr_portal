<?php
// in file: app/core/services/AttendanceService.php

require_once __DIR__ . '/../database.php';

/**
 * Class AttendanceService
 *
 * This service class encapsulates all the business logic related to handling
 * attendance data for both PUSH (cloud) and PULL (local) models.
 */
class AttendanceService
{
    /**
     * @var PDO The database connection object.
     */
    private $pdo;

    /**
     * AttendanceService constructor.
     */
    public function __construct()
    {
        // Use the global $pdo variable established in the bootstrap process.
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Saves an array of standardized attendance logs to the database,
     * checking for duplicates to prevent redundant entries.
     *
     * @param array $logs An array of log entries, each conforming to the standard format.
     * @param int|null $deviceId The ID of the device (known in PULL mode, null in PUSH mode).
     * @return array An associative array with counts of 'success', 'failed', and 'duplicate' insertions.
     */
    public function saveStandardizedLogs(array $logs, ?int $deviceId = null): array
    {
        if (empty($logs)) {
            return ['success' => 0, 'failed' => 0, 'duplicates' => 0];
        }

        // Prepare statements for checking existence and inserting new logs.
        $stmt_check = $this->pdo->prepare(
            "SELECT COUNT(*) FROM attendance_logs WHERE employee_code = ? AND punch_time = ?"
        );
        $stmt_insert = $this->pdo->prepare(
            "INSERT INTO attendance_logs (device_id, employee_code, punch_time, punch_state, work_code) VALUES (?, ?, ?, ?, ?)"
        );

        $results = ['success' => 0, 'failed' => 0, 'duplicates' => 0];

        foreach ($logs as $log) {
            // In PUSH mode, the device ID must be looked up from the device's serial number.
            // For now, we will assume it's passed or handle it in the next task.
            $currentDeviceId = $deviceId; 

            // Basic validation to ensure we have the essential data points.
            if (!isset($log['employee_code']) || !isset($log['punch_time'])) {
                 $results['failed']++;
                 continue;
            }
            
            try {
                // 1. Check for duplicates
                $stmt_check->execute([$log['employee_code'], $log['punch_time']]);
                if ($stmt_check->fetchColumn() > 0) {
                    $results['duplicates']++;
                    continue;
                }

                // 2. Insert the new log
                $success = $stmt_insert->execute([
                    $currentDeviceId,
                    $log['employee_code'],
                    $log['punch_time'],
                    $log['punch_state'],
                    $log['work_code'] ?? null // Use null if work_code isn't set
                ]);

                if ($success) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (PDOException $e) {
                error_log("AttendanceService Error: " . $e->getMessage());
                $results['failed']++;
                continue;
            }
        }

        return $results;
    }
}