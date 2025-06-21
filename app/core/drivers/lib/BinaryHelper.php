<?php
// in file: app/core/drivers/lib/BinaryHelper.php
// FINAL, STABLE, AND SYMMETRICAL VERSION

class BinaryHelper
{
    /**
     * Creates a full ZKTeco protocol packet including the header and data.
     */
    public static function createHeader(int $command, int $sessionId, int $replyId, string $data = ''): string
    {
        // The core packet data used for checksum calculation
        $buf = pack('vvvv', $command, $sessionId, $replyId, strlen($data)) . $data;
        $checksum = self::calculateChecksum($buf);

        // The final packet with the fixed start marker and calculated checksum
        $header = pack('vvvv', 1, $checksum, $sessionId, $replyId);

        return "\x50\x50\x82\x7d" . $header . $buf;
    }

    /**
     * Parses the header from a ZKTeco request/response packet.
     * This is now robust and correctly matches the createHeader structure.
     */
    public static function parseHeader(string $data): ?array
    {
        if (strlen($data) < 16) {
            return null; // Not enough data for a full header
        }
        
        // Unpack the fixed header part of the ZK protocol
        $header_format = 'vstart_marker/vunknown/vsize/vchecksum/vsession_id/vreply_id/vcommand';
        $header = unpack($header_format, substr($data, 0, 14));

        return $header !== false ? $header : null;
    }
    
    /**
     * Calculates the ZKTeco checksum for a given data buffer.
     */
    private static function calculateChecksum(string $data): int
    {
        $checksum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i = $i + 2) {
            $value = ord($data[$i]) + (isset($data[$i + 1]) ? ord($data[$i + 1]) << 8 : 0);
            $checksum += $value;
        }
        return ($checksum % 65536);
    }
    
    /**
     * Parses the user data payload from a ZKTeco device.
     */
    public static function parseZkUserData(string $data): array
    {
        if (empty($data)) return [];
        $users = [];
        
        // This check is important as real device payloads have this prefix.
        if (strpos($data, "\x01\x00\x00\x00") === 0) {
            $data = substr($data, 4);
        }

        $record_size = 72;
        
        while (strlen($data) >= $record_size) {
            $record = substr($data, 0, $record_size);
            
            $unpack_format = 'vpin/cprivilege/a8password/a24name/a8cardno/cgroup/a24timezones/a4pin2';
            $userdata = unpack($unpack_format, $record);

            if ($userdata === false) {
                error_log("BinaryHelper: unpack() failed for a user record. Skipping.");
                $data = substr($data, $record_size);
                continue;
            }
            
            $employee_code = $userdata['pin'];
            $raw_name = $userdata['name'] ?? '';
            $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $raw_name));
            
            if ($employee_code > 0 && !empty($name)) {
                $users[] = [
                    'employee_code' => $employee_code,
                    'user_id' => $employee_code,
                    'name' => $name,
                    'role' => ($userdata['privilege'] == 14) ? 'Admin' : 'User',
                    'privilege' => ($userdata['privilege'] == 14) ? 'Admin' : 'User',
                    'card_id' => intval(trim($userdata['cardno'] ?? '')),
                    'group_id' => $userdata['group'],
                ];
            }
            
            $data = substr($data, $record_size);
        }
        return $users;
    }
}