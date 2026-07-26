<?php

namespace App\Services\Fingerprint\Drivers;

use App\Models\FingerprintDevice;

/**
 * Platform biometrics via WebAuthn (laptop/phone fingerprint sensors). The
 * browser performs the ceremony; the stored "template" is the credential id.
 */
class WebauthnDriver extends AbstractDriver
{
    public function key(): string { return 'webauthn'; }

    public function label(): string { return 'Platform Biometric (WebAuthn)'; }

    public function test(FingerprintDevice $device): array
    {
        return ['online' => true, 'message' => 'Handled in-browser', 'latency_ms' => 0];
    }
}
