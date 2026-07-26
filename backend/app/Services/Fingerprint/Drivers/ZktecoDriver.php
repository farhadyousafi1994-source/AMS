<?php

namespace App\Services\Fingerprint\Drivers;

/**
 * ZKTeco devices. Enrolment/verification run through the local capture bridge
 * (or the vendor SDK) that streams templates to the browser; the server stores
 * and matches templates. Connectivity uses the shared host:port probe.
 */
class ZktecoDriver extends AbstractDriver
{
    public function key(): string { return 'zkteco'; }

    public function label(): string { return 'ZKTeco'; }
}
