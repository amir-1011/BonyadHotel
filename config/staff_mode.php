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

    /*
    |--------------------------------------------------------------------------
    | Staff session idle timeout (minutes)
    |--------------------------------------------------------------------------
    |
    | Admin and host accounts are logged out after this many minutes without
    | activity. Simple users (no staff role) are not affected.
    |
    */

    'session_timeout_minutes' => (int) env('STAFF_SESSION_TIMEOUT_MINUTES', 180),

];
