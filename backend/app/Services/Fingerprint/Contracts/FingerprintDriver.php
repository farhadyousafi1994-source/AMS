<?php

namespace App\Services\Fingerprint\Contracts;

use App\Models\FingerprintDevice;

/**
 * Device-agnostic contract every fingerprint brand implements. Real hardware
 * (ZKTeco, DigitalPersona, SecuGen…) is reached through a local capture bridge
 * or the platform WebAuthn API; the Simulator implements the full flow so the
 * system is testable without hardware. Add a brand by dropping in one class.
 */
interface FingerprintDriver
{
    /** Machine key, e.g. "zkteco". */
    public function key(): string;

    /** Human label, e.g. "ZKTeco". */
    public function label(): string;

    /** Probe for reachable devices of this brand (auto-detect). */
    public function detect(): array;

    /** Connectivity test for one configured device: [online, message, latency_ms]. */
    public function test(FingerprintDevice $device): array;

    /** Live-scan a finger and return an opaque template: [template, quality]. */
    public function capture(FingerprintDevice $device): array;

    /** 1:1 verification of a freshly captured template against a stored one. */
    public function match(string $candidate, string $stored): bool;
}
