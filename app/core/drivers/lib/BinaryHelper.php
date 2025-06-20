<?php
// in file: app/core/drivers/lib/BinaryHelper.php

class BinaryHelper
{
    /**
     * Calculates the checksum for a data packet.
     */
    private static function calculateChecksum(string $data): int
    {
        $checksum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i = $i + 2) {
            $lsb = ord($data[$i]);
            $msb = ($i + 1 < $len) ? ord($data[$i + 1]) : 0;
            $checksum += $lsb + ($msb << 8);
        }
        return ~($checksum & 0xFFFF) & 0xFFFF;
    }

    /**
     * Creates a command packet with a standardized header and a valid checksum.
     */
    public static function createHeader(int $command, int $sessionId, int $replyId, string $data = ''): string
    {
        // First, pack the header with a placeholder checksum (0)
        $header_without_checksum = pack('HHHH', $command, 0, $sessionId, $replyId);
        
        // Calculate the checksum on the entire packet (header + data)
        $checksum = self::calculateChecksum($header_without_checksum . $data);
        
        // Now, pack the final header with the correct checksum
        return pack('HHHH', $command, $checksum, $sessionId, $replyId) . $data;
    }

    /**
     * Parses the header from a device's response packet.
     */
    public static function parseHeader(string $data): ?array
    {
        if (strlen($data) < 8) {
            return null;
        }
        return unpack('H4command/H4checksum/H4sessionId/H4replyId', substr($data, 0, 8));
    }

    /**
     * Parses attendance log data from a raw binary string.
     */
    public static function parseAttendanceData(string $data): array
    {
        if (empty($data)) {
            return [];
        }

        $attData = substr($data, 8);
        $records = [];
        
        while (strlen($attData) >= 40) {
            $rec = unpack('a24user_id/a1/a1/a1/a1/a4timestamp/a1/a1/a7', $attData);

            $userId = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $rec['user_id']));
            $timestamp = self::decodeTimestamp(substr($attData, 28, 4));

            if ($userId && $timestamp) {
                 $records[] = [
                    'employee_code' => $userId,
                    'timestamp' => $timestamp,
                    'status' => (int)ord(substr($attData, 32, 1)),
                    'type' => 'punch'
                ];
            }
            $attData = substr($attData, 40);
        }
        
        return $records;
    }
    
    /**
     * Decodes the packed timestamp format used by many devices.
     */
    public static function decodeTimestamp(string $data): ?string
    {
        if(strlen($data) < 4) return null;
        $time = unpack('I', $data)[1];
        if ($time === 0) return null;

        $second = $time % 60;
        $minute = ($time >> 6) % 60;
        $hour = ($time >> 12) % 60;
        $day = ($time >> 17) % 31 + 1;
        $month = ($time >> 22) % 12 + 1;
        $year = floor($time / 512 / 60 / 24 / 365) + 2000;
        
        return date('Y-m-d H:i:s', mktime($hour, $minute, $second, $month, $day, $year));
    }
    
    /**
     * Parses user data from a raw binary string.
     */
    public static function parseUserData(string $data): array
    {
        if (empty($data)) {
            return [];
        }

        $userData = substr($data, 8);
        $records = [];
        
        while (strlen($userData) >= 72) {
            $recData = substr($userData, 0, 72);
            $rec = unpack('H2pin/a1privilege/a8password/a24name/a1card/a4group/a32tz/a1pin2', $recData);
            
            $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $rec['name']));
            
            if(!empty($name)){
                $records[] = [
                    'employee_code' => hexdec($rec['pin']),
                    'name' => $name,
                    'role' => (int)ord($rec['privilege']) === 14 ? 'Admin' : 'User',
                    'password' => trim($rec['password']),
                ];
            }
            $userData = substr($userData, 72);
        }
        
        return $records;
    }
}