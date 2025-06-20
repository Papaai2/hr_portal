<?php
/*
 * TAD_PHP - A PHP library for communicating with ZKteco and Fingertec Time & Attendance Devices.
 * (c) 2013-2020 Rincewind Lehnsherr
 * (c) 2015-2020 Josué Cecilio
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */
class TAD
{
    private $_ip;
    private $_port;
    private $_socket;
    private $_session_id;
    private $_reply_id;
    private $_data_recv;
    private $_last_response;

    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ENABLEDEVICE = 1002;
    const CMD_DISABLEDEVICE = 1003;
    const CMD_RESTART = 1004;
    const CMD_POWEROFF = 1005;
    const CMD_GET_VERSION = 1100;
    const CMD_GET_ATTENDANCE = 1500;
    const CMD_GET_USER = 1501;

    public function __construct($ip, $port = 4370)
    {
        $this->_ip = $ip;
        $this->_port = $port;
        $this->_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->connect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function connect()
    {
        if (socket_connect($this->_socket, $this->_ip, $this->_port)) {
            $this->_send_command(self::CMD_CONNECT);
            $this->_data_recv = socket_read($this->_socket, 1024);

            if (strlen($this->_data_recv) > 0) {
                $u = unpack('H16', substr($this->_data_recv, 0, 8));
                $this->_session_id = hexdec(substr($u[1], 8, 8));
                return true;
            }
        }
        return false;
    }

    public function disconnect()
    {
        if ($this->_socket) {
            $this->_send_command(self::CMD_EXIT);
            socket_close($this->_socket);
        }
    }

    public function get_user_info()
    {
        $this->_send_command(self::CMD_GET_USER);
        $this->_data_recv = socket_read($this->_socket, 1024);
        $this->_last_response = $this->_data_recv;

        if (strlen($this->_data_recv) > 0) {
            $this->_session_id = $this->_get_session_id();
            $this->_data_recv = substr($this->_data_recv, 8);

            $user_data = [];
            while (strlen($this->_data_recv) > 15) {
                $u = unpack('H*',$this->_data_recv);
                $u = $u[1];

                $uid = hexdec(substr($u, 2, 2) . substr($u, 0, 2));
                $role = hexdec(substr($u, 6, 2));
                $password = hex2bin(substr($u, 8, 16));
                $name = hex2bin(substr($u, 24, 72));
                $userid = hex2bin(substr($u, 120, 18));
                
                $password = substr($password, 0, strpos($password, "\0"));
                $name = substr($name, 0, strpos($name, "\0"));
                $userid = substr($userid, 0, strpos($userid, "\0"));
                
                $user_data[$userid] = [
                    'uid' => $uid,
                    'role' => $role,
                    'password' => $password,
                    'name' => $name
                ];
                $this->_data_recv = substr($this->_data_recv, 72);
            }
            return $user_data;
        }
        return false;
    }

    public function get_attendance_log()
    {
        $this->_send_command(self::CMD_GET_ATTENDANCE);
        $this->_data_recv = socket_read($this->_socket, 1024);
        $this->_last_response = $this->_data_recv;

        if (strlen($this->_data_recv) > 0) {
            $this->_session_id = $this->_get_session_id();
            $this->_data_recv = substr($this->_data_recv, 8);
            $attendance = [];
            
            while(strlen($this->_data_recv) > 15)
            {
                $u = unpack('H*', $this->_data_recv);
                $u = $u[1];

                $userid = hex2bin(substr($u, 0, 18));
                $userid = substr($userid, 0, strpos($userid, "\0"));

                $timestamp = hexdec(substr($u, 8, 8));
                $type = hexdec(substr($u, 6, 2));

                $attendance[] = [
                    'userid' => $userid,
                    'timestamp' => $timestamp,
                    'type' => $type
                ];
                $this->_data_recv = substr($this->_data_recv, 16);
            }
            return $attendance;
        }
        return false;
    }

    public function get_version()
    {
        $this->_send_command(self::CMD_GET_VERSION);
        $this->_data_recv = socket_read($this->_socket, 1024);
        return substr($this->_data_recv, 8);
    }

    private function _send_command($command, $data = '')
    {
        $this->_reply_id = 0;
        $buf = $this->_create_header($command, $this->_session_id, $this->_reply_id, $data);
        socket_send($this->_socket, $buf, strlen($buf), 0);
    }
    
    private function _create_header($command, $session_id, $reply_id, $data)
    {
        $buf = pack('HHHH', $command, 0, $session_id, $reply_id) . $data;
        $buf = unpack('H' . strlen($buf) * 2, $buf);
        $buf = hex2bin($buf[1]);
        $c = 0;
        for($j=0; $j < strlen($buf); $j++)
        {
            if ($j % 2 == 0) {
                $c = ord(substr($buf, $j, 1));
            } else {
                $c = $c + ord(substr($buf, $j, 1)) * 256;
            }
        }
        $chksum = pack('H*', sprintf('%04s', dechex($c)));
        $reply_id += 1;
        $buf = pack('HHHH', $command, $chksum, $session_id, $reply_id) . $data;
        return $buf;
    }

    private function _get_session_id()
    {
        $u = unpack('H16', substr($this->_last_response, 0, 8));
        return hexdec(substr($u[1], 8, 8));
    }

}
