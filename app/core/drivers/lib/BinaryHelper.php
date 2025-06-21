<?php
// in file: app/core/drivers/lib/BinaryHelper.php
// FINAL, COMPLETE VERSION

class BinaryHelper
{
    public static function createHeader(int $command, int $sessionId, int $replyId, string $data = ''): string
    {
        $header_without_checksum = pack('vvvv', $command, 0, $sessionId, $replyId);
        $checksum = self::calculateChecksum($header_without_checksum . $data);
        return pack('vvvv', $command, $checksum, $sessionId, $replyId) . $data;
    }

    public static function parseHeader(string $data): ?array
    {
        if (strlen($data) < 8) return null;
        return unpack('vcommand/vchecksum/vsession_id/vreply_id', substr($data, 0, 8));
    }

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

    public static function parseZkUserData(string $data): array
    {
        if (empty($data)) return [];
        $users = [];
        $record_size = 72;
        while (strlen($data) >= $record_size) {
            $record = substr($data, 0, $record_size);
            $userdata = unpack('vpin/cprivilege/a24name', $record);
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

    public static function parseFingertecUserData(string $data): array
    {
        if (empty($data)) return [];
        $users = [];
        $record_size = 72;
        while (strlen($data) >= $record_size) {
            $recData = substr($data, 0, $record_size);
            $rec = unpack('H4pin/cprivilege/a24name', $recData);
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