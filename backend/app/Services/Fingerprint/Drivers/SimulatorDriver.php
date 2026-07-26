<?php

namespace App\Services\Fingerprint\Drivers;

use App\Models\FingerprintDevice;
use Illuminate\Support\Str;

/**
 * Fully working software device — no hardware needed. Capture returns a stable
 * per-enrolment template so enrol + verify round-trips end to end. Ideal for
 * testing, demos and environments without a physical reader.
 */
class SimulatorDriver extends AbstractDriver
{
    public function key(): string { return 'simulator'; }

    public function label(): string { return 'Software Simulator'; }

    public function detect(): array
    {
        return [[
            'name' => 'Virtual Fingerprint Reader',
            'brand' => 'simulator',
            'model' => 'SIM-1',
            'connection' => 'simulator',
            'serial' => 'SIM-'.strtoupper(Str::random(6)),
        ]];
    }

    public function test(FingerprintDevice $device): array
    {
        return ['online' => true, 'message' => 'Simulator ready', 'latency_ms' => 1];
    }

    public function capture(FingerprintDevice $device): array
    {
        // A "scan" from the single virtual finger. The template is stable so
        // enrol → verify round-trips end to end (a real sensor discriminates
        // between people; the simulator represents one demo finger). A realistic
        // quality score varies each scan.
        return ['template' => 'SIMTPL:VIRTUAL-FINGER', 'quality' => random_int(60, 99)];
    }
}
