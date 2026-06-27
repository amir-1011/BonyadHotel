<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Under maintenance (env toggle)
    |--------------------------------------------------------------------------
    |
    | When UNDER_MAINTENANCE=true in .env, all requests show a maintenance page.
    | maintenance-toggle.php writes storage/framework/site_maintenance.json instead
    | of editing .env (avoids php artisan serve restarts on local dev).
    |
    */

    'env_enabled' => filter_var(env('UNDER_MAINTENANCE', false), FILTER_VALIDATE_BOOLEAN),

];
