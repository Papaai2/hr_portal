<?php

// Include the interface that this class is contracted to implement.
require_once __DIR__ . '/DeviceDriverInterface.php';

// In a real implementation, you would include the manufacturer's library file here.
// For example:
// require_once __DIR__ . '/lib/fingertec/TADK.php';

/**
 * Class FingertecDriver
 *
 * This is the driver implementation for Fingertec hardware.
 * It uses the manufacturer's specific SDK/library to communicate with the device
 * and translates the results into the standardized format required by the
 * DeviceDriverInterface.
 */
class FingertecDriver implements DeviceDriverInterface
{
    /**
     * @var mixed Holds the connection object or resource from the SDK.
     */
    private $connection = null;

    /**
     * {@inheritdoc}
     * This method will contain the specific code to connect to a Fingertec device.
     */
    public function connect(string $ip, int $port, string $key): bool
    {
        // Placeholder Logic for Sprint 1
        // In Sprint 2, you would replace this with actual SDK calls, e.g.:
        // $this->connection = new TADK($ip, $port);
        // return $this->connection->connect($key);
        return true; // Assume success for now
    }

    /**
     * {@inheritdoc}
     * This method will contain the specific code to disconnect from a Fingertec device.
     */
    public function disconnect(): void
    {
        // Placeholder Logic for Sprint 1
        // In Sprint 2, you would replace this with actual SDK calls, e.g.:
        // if ($this->connection) {
        //     $this->connection->disconnect();
        // }
        $this->connection = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeviceName(): string
    {
        // Placeholder for Sprint 2 implementation
        return "Fingertec Device (Not Implemented)";
    }

    /**
     * {@inheritdoc}
     */
    public function getUsers(): array
    {
        // Placeholder for Sprint 2 implementation
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAttendanceLogs(): array
    {
        // Placeholder for Sprint 2 implementation
        return [];
    }
}

