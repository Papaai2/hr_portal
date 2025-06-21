<?php
// in file: app/core/drivers/lib/BinaryHelper.php

class BinaryHelper
{
    /**
     * Creates a ZKTeco protocol header.
     *
     * @param integer $command The command code.
     * @param integer $sessionId The session ID from the device.
     * @param integer $replyId The reply counter.
     * @param string $data The payload data.
     * @return string The full packet with header and data.
     */
    public static function createHeader(int $command, int $sessionId, int $replyId, string $data = ''): string
    {
        $command_len = strlen($data) + 8;
        
        // The header part that is used for checksum calculation
        $buf = pack('vvvv', $command_len, $sessionId, $replyId, $command);
        $checksum = self::calculateChecksum($buf . $data);
        
        // The final packet with the checksum included
        $header = pack('H*', '5050') . pack('v', $command_len) . pack('v', $checksum) . pack('v', $sessionId) . pack('v', $replyId) . pack('v', $command);
        
        return $header . $data;
    }

    /**
     * Parses the header from a ZKTeco response packet.
     *
     * @param string $data The raw response data.
     * @return array|null The parsed header or null on failure.
     */
    public static function parseHeader(string $data): ?array
    {
        if (strlen($data) < 8) return null;
        return unpack('vcommand/vchecksum/vsession_id/vreply_id', substr($data, 8, 8));
    }
    
    /**
     * Calculates the ZKTeco checksum for a given data buffer.
     * NOTE: This is a known algorithm for these devices.
     *
     * @param string $data The data to checksum.
     * @return integer The calculated checksum.
     */
    private static function calculateChecksum(string $data): int
    {
        $checksum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i = $i + 2) {
            $val = ord($data[$i]) + (isset($data[$i + 1]) ? ord($data[$i + 1]) * 256 : 0);
            $checksum += $val;
            if ($checksum > 65535) {
                $checksum -= 65536;
            }
        }
        return ($checksum ^ 0xFFFF) + 1;
    }
    
    /**
     * Parses the user data payload from a ZKTeco device.
     *
     * @param string $data The raw user data payload.
     * @return array An array of parsed users.
     */
    public static function parseZkUserData(string $data): array
    {
        if (empty($data)) return [];
        $users = [];
        // The first 4 bytes are a header for the data chunk.
        if (strlen($data) > 4) {
            $data = substr($data, 4);
        }

        $record_size = 72; // Each user record is typically 72 bytes
        while (strlen($data) >= $record_size) {
            $record = substr($data, 0, $record_size);
            // Unpack the relevant fields from the user record
            $userdata = unpack('vpin/cprivilege/a8password/a24name/a9cardno/cgroup/a24timezones/a8pin2', $record);
            
            $employee_code = $userdata['pin'];
            // Clean the name field from null bytes and other non-printable characters
            $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $userdata['name']));
            
            if ($employee_code > 0 && !empty($name)) {
                $users[] = [
                    'employee_code' => $employee_code,
                    'user_id' => $employee_code, // for consistency
                    'name' => $name,
                    'role' => ($userdata['privilege'] == 14) ? 'Admin' : 'User', // 14 is a common admin level
                    'privilege' => ($userdata['privilege'] == 14) ? 'Admin' : 'User',
                    'card_id' => intval(trim($userdata['cardno'])),
                    'group_id' => $userdata['group'],
                ];
            }
            $data = substr($data, $record_size);
        }
        return $users;
    }
}