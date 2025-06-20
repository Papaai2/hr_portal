<?php
// in file: app/core/drivers/lib/BinaryHelper.php
// FINAL, COMPLETE, HARDWARE-READY VERSION

class BinaryHelper
{
    /**
     * Creates a command packet with a standardized header using little-endian byte order.
     *
     * @param int $command The command ID.
     * @param int $sessionId The session ID.
     * @param int $replyId The reply ID.
     * @param string $data The optional payload data.
     * @return string The fully formed binary packet.
     */
    public static function createHeader(int $command, int $sessionId, int $replyId, string $data = ''): string
    {
        // Using 'v' for unsigned short (16-bit, little-endian) is more reliable.
        $header_without_checksum = pack('vvvv', $command, 0, $sessionId, $replyId);
        $checksum = self::calculateChecksum($header_without_checksum . $data);
        return pack('vvvv', $command, $checksum, $sessionId, $replyId) . $data;
    }

    /**
     * Parses the 8-byte header from a device's response packet.
     *
     * @param string $data The raw response from the device.
     * @return array|null An associative array with header fields, or null if the packet is too short.
     */
    public static function parseHeader(string $data): ?array
    {
        if (strlen($data) < 8) return null;
        return unpack('vcommand/vchecksum/vsession_id/vreply_id', substr($data, 0, 8));
    }

    /**
     * Calculates the 16-bit checksum for a data packet.
     *
     * @param string $data The binary data string.
     * @return int The calculated checksum.
     */
    private static function calculateChecksum(string $data): int
    {
        $checksum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i = $i + 2) {
            $value = ord($data[$i]) + (isset($data[$i + 1]) ? ord($data[$i + 1]) << 8 : 0);
            $checksum += $value;
        }
        return ~($checksum & 0xFFFF) & 0xFFFF;
    }

    /**
     * Parses real user data from a ZKTeco device payload.
     * @param string $data The raw binary payload from the device.
     * @return array A list of users.
     */
    public static function parseZkUserData(string $data): array
    {
        if (empty($data)) return [];
        
        $users = [];
        // ZKTeco user record format can vary, this is a common one (72 bytes)
        $record_size = 72;
        while (strlen($data) >= $record_size) {
            $record = substr($data, 0, $record_size);
            // This unpack format is based on common ZK protocol reverse engineering
            $userdata = unpack('vpin/cprivilege/a24name/a8password/a4card_number', $record);
            
            $employee_code = $userdata['pin'];
            $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $userdata['name']));

            if ($employee_code > 0 && !empty($name)) {
                $users[] = [
                    'employee_code' => $employee_code,
                    'name' => $name,
                    'role' => ($userdata['privilege'] == 14) ? 'Admin' : 'User'
                ];
            }
            $data = substr($data, $record_size);
        }
        return $users;
    }

    /**
     * Parses real user data from a Fingertec device payload.
     * @param string $data The raw binary payload from the device.
     * @return array A list of users.
     */
    public static function parseFingertecUserData(string $data): array
    {
        if (empty($data)) return [];
        $users = [];
        // Fingertec user record is typically 72 bytes
        $record_size = 72;
        while (strlen($data) >= $record_size) {
            $recData = substr($data, 0, $record_size);
            $rec = unpack('H4pin/cprivilege/a8password/a24name', $recData);

            $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $rec['name']));

            if (!empty($name)) {
                $users[] = [
                    'employee_code' => hexdec($rec['pin']),
                    'name' => $name,
                    'role' => ($rec['privilege'] === 14) ? 'Admin' : 'User',
                ];
            }
            $data = substr($data, $record_size);
        }
        return $users;
    }
}