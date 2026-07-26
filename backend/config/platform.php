<?php

return [

    /*
     | The single, immutable root identity of the SaaS platform. Platform-critical
     | operations (tenant/branch provisioning, licensing, global settings, …) are
     | reserved to this account and cannot be granted through roles or permissions.
     */
    'owner_email' => env('PLATFORM_OWNER_EMAIL', 'support@briskcodes.com'),

    /*
     | Branch creation/deletion is reserved to the Platform Owner by default. A
     | tenant only gains it if the Platform Owner flips branch_self_service on for
     | that organization.
     */
    'branch_self_service_default' => false,
];
