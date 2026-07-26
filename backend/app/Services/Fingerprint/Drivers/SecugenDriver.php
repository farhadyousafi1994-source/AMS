<?php

namespace App\Services\Fingerprint\Drivers;

/**
 * SecuGen devices. Enrolment/verification run through the local capture bridge
 * (or the vendor SDK) that streams templates to the browser; the server stores
 * and matches templates. Connectivity uses the shared host:port probe.
 */
class SecugenDriver extends AbstractDriver
{
    public function key(): string { return 'secugen'; }

    public function label(): string { return 'SecuGen'; }
}
