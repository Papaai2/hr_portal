<?php
// in file: app/core/drivers/lib/BinaryHelper.php
// FINAL, REWRITTEN, AND STABLE VERSION

class BinaryHelper
{
    const ZK_START_MARKER = "\x50\x50";
    const ZK_HEADER_SIZE = 8;

    /**
     * Creates a full, valid ZKTeco protocol packet.
     */
    public static function createPacket(int $command, int $sessionId, int $replyId, string $data = ''): string
    {
        // Core header: Command(2), Checksum(2), SessionID(2), ReplyID(2)
        $header = pack('vvvv', $command, 0, $sessionId, $replyId);
        
        $packet_with_data = $header . $data;
        $checksum = self::calculateChecksum($packet_with_data);
        
        // Splice the checksum back into the header
        substr_replace($header, pack('v', $checksum), 2, 2);

        // Final packet: Start Marker(2) + Header(8) + Data(variable)
        return self::ZK_START_MARKER . $header . $data;
    }

    /**
     * Parses the header from a raw ZKTeco packet buffer.
     */
    public static function parseHeader(string $buffer): ?array
    {
        if (strlen($buffer) < self::ZK_HEADER_SIZE + 2) {
            return null;
        }
        if (substr($buffer, 0, 2) !== self::ZK_START_MARKER) {
            return null;
        }
        $format = 'vcommand/vchecksum/vsession_id/vreply_id';
        $header = unpack($format, substr($buffer, 2, self::ZK_HEADER_SIZE));
        return $header !== false ? $header : null;
    }
    
    /**
     * Calculates the ZKTeco checksum.
     */
    private static function calculateChecksum(string $data): int
    {
        $checksum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i += 2) {
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
        
        if (substr($data, 0, 4) === "\x01\x00\x00\x00") {
            $data = substr($data, 4);
        }

        $record_size = 72;
        
        while (strlen($data) >= $record_size) {
            $record = substr($data, 0, $record_size);
            $unpack_format = 'vpin/cprivilege/a8password/a24name/a8cardno/cgroup/a24timezones/a4pin2';
            $userdata = unpack($unpack_format, $record);

            if ($userdata === false) continue;
            
            $employee_code = $userdata['pin'];
            $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $userdata['name'] ?? ''));
            
            if ($employee_code > 0 && !empty($name)) {
                $users[] = [
                    'user_id' => $employee_code,
                    'name' => $name,
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