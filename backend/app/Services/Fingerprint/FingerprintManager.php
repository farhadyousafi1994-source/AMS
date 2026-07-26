<?php

namespace App\Services\Fingerprint;

use App\Models\FingerprintDevice;
use App\Services\Fingerprint\Contracts\FingerprintDriver;
use App\Services\Fingerprint\Drivers\DigitalPersonaDriver;
use App\Services\Fingerprint\Drivers\MantraDriver;
use App\Services\Fingerprint\Drivers\SecugenDriver;
use App\Services\Fingerprint\Drivers\SimulatorDriver;
use App\Services\Fingerprint\Drivers\WebauthnDriver;
use App\Services\Fingerprint\Drivers\ZktecoDriver;

/**
 * Resolves the right driver for a device/brand. Registering a new brand is one
 * line here plus its driver class — nothing else in the system changes.
 */
class FingerprintManager
{
    /** @return array<string, class-string<FingerprintDriver>> */
    public static function registry(): array
    {
        return [
            'simulator' => SimulatorDriver::class,
            'zkteco' => ZktecoDriver::class,
            'digitalpersona' => DigitalPersonaDriver::class,
            'secugen' => SecugenDriver::class,
            'mantra' => MantraDriver::class,
            'webauthn' => WebauthnDriver::class,
        ];
    }

    public function driver(string $brand): FingerprintDriver
    {
        $class = self::registry()[$brand] ?? SimulatorDriver::class;

        return new $class;
    }

    public function forDevice(FingerprintDevice $device): FingerprintDriver
    {
        return $this->driver($device->brand);
    }

    /** Brand catalogue for the settings UI. */
    public function brands(): array
    {
        return collect(self::registry())->map(fn ($class) => [
            'key' => (new $class)->key(),
            'label' => (new $class)->label(),
        ])->values()->all();
    }
}
