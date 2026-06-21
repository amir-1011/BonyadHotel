<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform service commission (developer wallet)
    |--------------------------------------------------------------------------
    |
    | 5% of each commissionable transaction, capped per line item.
    |
    */
    'percentage' => (int) env('PLATFORM_COMMISSION_PERCENTAGE', 5),
    'cap'        => (int) env('PLATFORM_COMMISSION_CAP', 50_000),
];
