<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Test site mode
    |--------------------------------------------------------------------------
    |
    | When enabled, staff (admin/host) see a notice after logging in that
    | clarifies this is a test environment separate from production.
    | Set TEST_SITE_MODE=true in .env to activate.
    |
    */

    'enabled' => env('TEST_SITE_MODE', false),

];
