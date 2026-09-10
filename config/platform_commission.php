<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform service commission (developer wallet)
    |--------------------------------------------------------------------------
    |
    | Fixed amount per confirmed accommodation booking (manual / online).
    | Standalone manual service sales (booking_source manual_service) use
    | percentage + cap below. Program (اردو), credit (اعتباری), and medical
    | accommodation bookings are exempt.
    |
    */
    'fixed_amount' => (int) env('PLATFORM_COMMISSION_FIXED_AMOUNT', 50_000),

    /*
    |--------------------------------------------------------------------------
    | Legacy percentage model (historical entries only)
    |--------------------------------------------------------------------------
    */
    'percentage' => (int) env('PLATFORM_COMMISSION_PERCENTAGE', 5),
    'cap'        => (int) env('PLATFORM_COMMISSION_CAP', 50_000),
];
