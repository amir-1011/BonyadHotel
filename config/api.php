<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Token Expiration (days)
    |--------------------------------------------------------------------------
    |
    | Used when issuing Sanctum personal access tokens for mobile/API clients.
    | Set SANCTUM_EXPIRATION in minutes to override with a global limit.
    |
    */

    'token_expiration_days' => (int) env('API_TOKEN_EXPIRATION_DAYS', 30),

];
