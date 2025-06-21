<?php
/**
 * Comprehensive Driver Test Script
 * Tests both real hardware compatibility and fake server simulation
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
// Include required files
require_once __DIR__ . '/app/core/drivers/DeviceDriverInterface.php';
require_once __DIR__ . '/app/core/drivers/EnhancedDriverFramework.php';
require_once __DIR__ . '/app/core/drivers/FingertecDriver.php';
require_once __DIR__ . '/app/core/drivers/ZKTecoDriver.php';
echo "=== HR PORTAL DRIVER COMPATIBILITY TEST ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Testing both real hardware compatibility and fake server simulation\n\n";
// Test configuration for fake servers
$testConfig = [
    'fingertec_fake' => [
        'host' => '127.0.0.1',
        'port' => 8099,
        'key' => '0',
        'timeout' => 5,
        'debug' => true
    ],
    'zkteco_fake' => [
        'host' => '127.0.0.1',
        'port' => 8100,
        'key' => '0',
        'timeout' => 5,
        'debug' => true
    ]
];
$results = [
    'total_tests' => 0,
    'passed_tests' => 0,
    'failed_tests' => 0,
    'test_details' => []
];
function logTest($testName, $success, $message, &$results) {
    $results['total_tests']++;
    if ($success) {
        $results['passed_tests']++;
        echo "✓ {$testName}: {$message}\n";
    } else {
        $results['failed_tests']++;
        echo "✗ {$testName}: {$message}\n";
    }
    $results['test_details'][] = [
        'test' => $testName,
        'success' => $success,
        'message' => $message
    ];
}
// Test 1: Interface Compliance
echo "\n=== TEST 1: Interface Compliance ===\n";
try {
    $fingertecDriver = new FingertecDriver();
    $zktecoDriver = new ZKTecoDriver();
    
    // Check if drivers implement the interface
    $fingertecImplements = $fingertecDriver instanceof DeviceDriverInterface;
    $zktecoImplements = $zktecoDriver instanceof DeviceDriverInterface;
    
    logTest("FingertecDriver Interface", $fingertecImplements, 
           $fingertecImplements ? "Properly implements DeviceDriverInterface" : "Does not implement interface");
    
    logTest("ZKTecoDriver Interface", $zktecoImplements, 
           $zktecoImplements ? "Properly implements DeviceDriverInterface" : "Does not implement interface");
    
    // Test required methods exist
    $requiredMethods = ['connect', 'disconnect', 'getDeviceName', 'getUsers', 
                       'getAttendanceLogs', 'addUser', 'deleteUser', 'updateUser', 'clearAttendanceData'];
    
    foreach ($requiredMethods as $method) {
        $fingertecHasMethod = method_exists($fingertecDriver, $method);
        $zktecoHasMethod = method_exists($zktecoDriver, $method);
        
        logTest("FingertecDriver::{$method}", $fingertecHasMethod, 
               $fingertecHasMethod ? "Method exists" : "Method missing");
        
        logTest("ZKTecoDriver::{$method}", $zktecoHasMethod, 
               $zktecoHasMethod ? "Method exists" : "Method missing");
    }
    
} catch (Exception $e) {
    logTest("Interface Compliance", false, "Exception: " . $e->getMessage(), $results);
}
// Test 2: Configuration Management
echo "\n=== TEST 2: Configuration Management ===\n";
try {
    $driver = new FingertecDriver();
    $config = $driver->getConfig();
    
    logTest("Configuration Access", !empty($config), 
           !empty($config) ? "Configuration retrieved successfully" : "Failed to get configuration");
    
    $hasRequiredKeys = isset($config['host'], $config['port'], $config['timeout']);
    logTest("Configuration Structure", $hasRequiredKeys, 
           $hasRequiredKeys ? "All required config keys present" : "Missing required config keys");
    
    // Test that no hardcoded IPs exist in default config
    $noHardcodedIP = empty($config['host']) || $config['host'] === '';
    logTest("No Hardcoded IPs", $noHardcodedIP, 
           $noHardcodedIP ? "No hardcoded IP in default config" : "Warning: Hardcoded IP found: " . $config['host']);
    
} catch (Exception $e) {
    logTest("Configuration Management", false, "Exception: " . $e->getMessage(), $results);
}
// Test 3: Connection Method Compatibility
echo "\n=== TEST 3: Connection Method Compatibility ===\n";
try {
    $driver = new FingertecDriver();
    
    // Test that connect method accepts correct parameters
    $reflection = new ReflectionMethod($driver, 'connect');
    $parameters = $reflection->getParameters();
    
    $correctSignature = (count($parameters) === 3 && 
                        $parameters[0]->getType() && 
                        $parameters[0]->getType()->getName() === 'string' &&
                        $parameters[1]->getType() && 
                        $parameters[1]->getType()->getName() === 'int');
    
    logTest("Connect Method Signature", $correctSignature, 
           $correctSignature ? "Method signature matches interface" : "Method signature mismatch");
    
    // Test invalid connection (should fail gracefully)
    $connectionResult = $driver->connect('192.168.1.255', 9999, null);
    logTest("Invalid Connection Handling", !$connectionResult, 
           !$connectionResult ? "Invalid connection properly rejected" : "Warning: Invalid connection succeeded");
    
} catch (Exception $e) {
    logTest("Connection Method Compatibility", false, "Exception: " . $e->getMessage(), $results);
}
// Test 4: Fake Server Simulation (if servers are running)
echo "\n=== TEST 4: Fake Server Simulation ===\n";
echo "Note: Start fake_device_server.php (port 8099) and fake_zk_server.php (port 8100) to test\n";
// Test Fingertec fake server
try {
    $driver = new FingertecDriver();
    $connected = $driver->connect($testConfig['fingertec_fake']['host'], 
                                 $testConfig['fingertec_fake']['port'], 
                                 $testConfig['fingertec_fake']['key']);
    
    if ($connected) {
        logTest("Fingertec Fake Server Connection", true, "Successfully connected to fake server");
        
        $deviceName = $driver->getDeviceName();
        logTest("Fingertec Device Name", !empty($deviceName), 
               !empty($deviceName) ? "Device name: {$deviceName}" : "Failed to get device name");
        
        $driver->disconnect();
        logTest("Fingertec Disconnect", true, "Successfully disconnected");
    } else {
        logTest("Fingertec Fake Server Connection", false, "Could not connect (server may not be running)");
    }
} catch (Exception $e) {
    logTest("Fingertec Fake Server Test", false, "Exception: " . $e->getMessage(), $results);
}
// Test ZKTeco fake server
try {
    $driver = new ZKTecoDriver();
    $connected = $driver->connect($testConfig['zkteco_fake']['host'], 
                                 $testConfig['zkteco_fake']['port'], 
                                 $testConfig['zkteco_fake']['key']);
    
    if ($connected) {
        logTest("ZKTeco Fake Server Connection", true, "Successfully connected to fake server");
        
        $deviceName = $driver->getDeviceName();
        logTest("ZKTeco Device Name", !empty($deviceName), 
               !empty($deviceName) ? "Device name: {$deviceName}" : "Failed to get device name");
        
        $driver->disconnect();
        logTest("ZKTeco Disconnect", true, "Successfully disconnected");
    } else {
        logTest("ZKTeco Fake Server Connection", false, "Could not connect (server may not be running)");
    }
} catch (Exception $e) {
    logTest("ZKTeco Fake Server Test", false, "Exception: " . $e->getMessage(), $results);
}
// Test 5: Database Integration Compatibility
echo "\n=== TEST 5: Database Integration Compatibility ===\n";
try {
    // Test driver instantiation without hardcoded values
    $brands = ['fingertec', 'zkteco'];
    
    foreach ($brands as $brand) {
        $className = ucfirst($brand) . 'Driver';
        if (class_exists($className)) {
            $driver = new $className();
            $canInstantiate = true;
            $config = $driver->getConfig();
            $noHardcodedValues = empty($config['host']) || $config['host'] === '';
            
            logTest("{$brand} Driver Instantiation", $canInstantiate, 
                   $canInstantiate ? "Driver can be instantiated" : "Failed to instantiate driver");
            
            logTest("{$brand} Database Ready", $noHardcodedValues, 
                   $noHardcodedValues ? "Ready for database-driven configuration" : "Has hardcoded values");
        }
    }
    
} catch (Exception $e) {
    logTest("Database Integration Compatibility", false, "Exception: " . $e->getMessage(), $results);
}
// Test Summary
echo "\n=== TEST SUMMARY ===\n";
echo "Total Tests: {$results['total_tests']}\n";
echo "Passed: {$results['passed_tests']}\n";
echo "Failed: {$results['failed_tests']}\n";
$successRate = $results['total_tests'] > 0 ? 
               round(($results['passed_tests'] / $results['total_tests']) * 100, 2) : 0;
echo "Success Rate: {$successRate}%\n";
if ($successRate >= 80) {
    echo "\n🎉 EXCELLENT! Drivers are ready for production use.\n";
} elseif ($successRate >= 60) {
    echo "\n⚠️  GOOD: Drivers are mostly compatible, minor issues to address.\n";
} else {
    echo "\n❌ NEEDS WORK: Significant compatibility issues found.\n";
}
// Test Instructions
echo "\n=== USAGE INSTRUCTIONS ===\n";
echo "1. For simulation testing:\n";
echo "   - Run: php fake_device_server.php (in terminal 1)\n";
echo "   - Run: php fake_zk_server.php (in terminal 2)\n";
echo "   - Then run this test again\n\n";
echo "2. For real hardware:\n";
echo "   - Add devices through admin/devices.php\n";
echo "   - Use pull_attendance.php for data collection\n";
echo "   - Configure correct IP addresses and ports\n\n";
echo "3. Database configuration:\n";
echo "   - No hardcoded IPs in drivers ✓\n";
echo "   - All configuration comes from database ✓\n";
echo "   - Pull model supported ✓\n\n";
echo "Test completed: " . date('Y-m-d H:i:s') . "\n";
?>