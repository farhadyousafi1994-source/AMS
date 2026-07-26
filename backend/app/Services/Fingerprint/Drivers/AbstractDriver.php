<?php

namespace App\Services\Fingerprint\Drivers;

use App\Models\FingerprintDevice;
use App\Services\Fingerprint\Contracts\FingerprintDriver;

/** Shared helpers; brand drivers override what they support. */
abstract class AbstractDriver implements FingerprintDriver
{
    public function detect(): array
    {
        return [];
    }

    /** Default network reachability check for host:port devices. */
    public function test(FingerprintDevice $device): array
    {
        if (! $device->host) {
            return ['online' => false, 'message' => 'No host configured', 'latency_ms' => null];
        }
        $start = microtime(true);
        $conn = @fsockopen($device->host, (int) ($device->port ?: 4370), $errno, $errstr, 1.5);
        $ms = (int) round((microtime(true) - $start) * 1000);
        if ($conn) {
            fclose($conn);

            return ['online' => true, 'message' => 'Reachable', 'latency_ms' => $ms];
        }

        return ['online' => false, 'message' => $errstr ?: 'Unreachable', 'latency_ms' => $ms];
    }

    public function capture(FingerprintDevice $device): array
    {
        // Real capture happens in the local bridge/SDK; the server never touches
        // the sensor directly. Brands that need the bridge say so clearly.
        throw new \RuntimeException($this->label().' capture requires the local device bridge to be running.');
    }

    public function match(string $candidate, string $stored): bool
    {
        // Templates are compared by the same normalisation used at capture.
        return hash_equals(trim($stored), trim($candidate));
    }
}
