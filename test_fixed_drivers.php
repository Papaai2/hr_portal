<?php
/**
 * Test script for the fixed biometric device drivers
 * Tests both fake and real device connections
 */
require_once 'EnhancedDriverFramework.php';
require_once 'FingertecDriver.php';
require_once 'ZKTecoDriver.php';
function testDriver($driverClass, $deviceName, $fakeIp, $realIp = null) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Testing $deviceName Driver\n";
    echo str_repeat("=", 60) . "\n";
    
    // Test with fake device
    echo "\n--- Testing with Fake Device ($fakeIp) ---\n";
    
    try {
        $driver = new $driverClass([
            'debug' => true,
            'timeout' => 10
        ]);
        
        // Test connection
        echo "Connecting to fake device...\n";
        if ($driver->connect($fakeIp, 4370)) {
            echo "✓ Connection successful\n";
            
            // Test device info
            echo "Getting device info...\n";
            $deviceInfo = $driver->getDeviceInfo();
            echo "✓ Device: " . $deviceInfo['manufacturer'] . " " . $deviceInfo['model'] . "\n";
            echo "✓ Firmware: " . $deviceInfo['firmware'] . "\n";
            
            // Test users
            echo "Getting users...\n";
            $users = $driver->getUsers();
            echo "✓ Found " . count($users) . " users\n";
            if (!empty($users)) {
                echo "  Sample user: " . $users[0]['name'] . " (ID: " . $users[0]['user_id'] . ")\n";
            }
            
            // Test attendance
            echo "Getting attendance logs...\n";
            $attendance = $driver->getAttendanceLogs();
            echo "✓ Found " . count($attendance) . " attendance records\n";
            if (!empty($attendance)) {
                echo "  Sample record: User " . $attendance[0]['user_id'] . " at " . $attendance[0]['timestamp'] . "\n";
            }
            
            // Test device status
            if (method_exists($driver, 'getDeviceStatus')) {
                echo "Getting device status...\n";
                $status = $driver->getDeviceStatus();
                echo "✓ Status: " . $status['connection_status'] . "\n";
                echo "✓ Users: " . $status['user_count'] . ", Attendance: " . $status['attendance_count'] . "\n";
            }
            
            $driver->disconnect();
            echo "✓ Disconnected successfully\n";
            
        } else {
            echo "✗ Failed to connect to fake device\n";
            echo "Error: " . $driver->getLastError() . "\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Exception during fake device test: " . $e->getMessage() . "\n";
    }
    
    // Test with real device (if IP provided)
    if ($realIp) {
        echo "\n--- Testing with Real Device ($realIp) ---\n";
        
        try {
            $driver = new $driverClass([
                'debug' => false,
                'timeout' => 15,
                'retry_attempts' => 3
            ]);
            
            echo "Connecting to real device...\n";
            if ($driver->connect($realIp, 4370)) {
                echo "✓ Connection successful\n";
                
                // Basic tests for real device
                $deviceInfo = $driver->getDeviceInfo();
                echo "✓ Device: " . $deviceInfo['manufacturer'] . " " . $deviceInfo['model'] . "\n";
                
                $users = $driver->getUsers();
                echo "✓ Found " . count($users) . " users\n";
                
                $driver->disconnect();
                echo "✓ Disconnected successfully\n";
                
            } else {
                echo "! Could not connect to real device (this is normal if no device is present)\n";
                echo "  Error: " . $driver->getLastError() . "\n";
            }
            
        } catch (Exception $e) {
            echo "! Exception during real device test: " . $e->getMessage() . "\n";
            echo "  (This is normal if no real device is connected)\n";
        }
    }
}
function testFramework() {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Testing Enhanced Driver Framework\n";
    echo str_repeat("=", 60) . "\n";
    
    // Test interface exists
    if (interface_exists('DeviceDriverInterface')) {
        echo "✓ DeviceDriverInterface exists\n";
    } else {
        echo "✗ DeviceDriverInterface missing\n";
    }
    
    // Test base class exists
    if (class_exists('EnhancedBaseDriver')) {
        echo "✓ EnhancedBaseDriver class exists\n";
    } else {
        echo "✗ EnhancedBaseDriver class missing\n";
    }
    
    // Test driver classes exist
    if (class_exists('FingertecDriver')) {
        echo "✓ FingertecDriver class exists\n";
    } else {
        echo "✗ FingertecDriver class missing\n";
    }
    
    if (class_exists('ZKTecoDriver')) {
        echo "✓ ZKTecoDriver class exists\n";
    } else {
        echo "✗ ZKTecoDriver class missing\n";
    }
}
// Run tests
echo "Biometric Device Driver Test Suite\n";
echo "=================================\n";
testFramework();
// Test FingerTec Driver
testDriver('FingertecDriver', 'FingerTec', '127.0.0.1', '192.168.1.201');
// Test ZKTeco Driver  
testDriver('ZKTecoDriver', 'ZKTeco', 'localhost', '192.168.1.202');
echo "\n" . str_repeat("=", 60) . "\n";
echo "Test Suite Complete\n";
echo str_repeat("=", 60) . "\n";
?>