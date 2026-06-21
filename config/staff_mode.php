<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staff-only mode (temporary)
    |--------------------------------------------------------------------------
    |
    | When enabled, all public user-facing routes redirect to /admin/login.
    | Only super_admin and host roles can authenticate via the staff login.
    | Set STAFF_ONLY_MODE=false in .env to restore the normal user UI.
    |
    */

    'enabled' => env('STAFF_ONLY_MODE', false),

];
