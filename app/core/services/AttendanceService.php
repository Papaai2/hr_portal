<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database.php';

/**
 * Class AttendanceService
 *
 * This service class encapsulates all the business logic related to handling
 * attendance data.
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
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    /**
     * Saves an array of standardized attendance logs to the database,
     * checking for duplicates to prevent redundant entries.
     *
     * @param array $logs An array of log entries, each conforming to the standard format.
     * @param int $deviceId The ID of the device from which the logs were fetched.
     * @return array An associative array with counts of 'success', 'failed', and 'duplicate' insertions.
     */
    public function saveStandardizedLogs(array $logs, int $deviceId): array
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
            try {
                // 1. Check for duplicates
                $stmt_check->execute([$log['employee_code'], $log['punch_time']]);
                if ($stmt_check->fetchColumn() > 0) {
                    $results['duplicates']++;
                    continue;
                }

                // 2. Insert the new log
                $success = $stmt_insert->execute([
                    $deviceId,
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
                // Log error if needed: error_log($e->getMessage());
                $results['failed']++;
                continue;
            }
        }

        return $results;
    }
}
