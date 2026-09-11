<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Refah PCPOS Agent (Windows PC at each accommodation)
    |--------------------------------------------------------------------------
    |
    | The Laravel backend reaches the on-site Windows PC over Tailscale, then
    | the Agent talks to the POS on the local modem LAN. Defaults match
    | «مستند جامع Agent ارتباط با POS» and culr.txt.
    |
    */
    'enabled' => (bool) env('PCPOS_ENABLED', true),

    'agent_path' => env('PCPOS_AGENT_PATH', '/pcpos'),

    'default_agent_port' => (int) env('PCPOS_AGENT_PORT', 8088),

    'default_pos_port' => (int) env('PCPOS_POS_PORT', 1362),

    'purchase_code' => env('PCPOS_PR', '000000'),

    'currency' => env('PCPOS_CURRENCY', '364'),

    /*
    | Guest card swipe can take well over a minute. Keep this above nginx /
    | php-fpm proxy_read_timeout as well.
    */
    'timeout_seconds' => (int) env('PCPOS_TIMEOUT_SECONDS', 180),

    'connect_timeout_seconds' => (int) env('PCPOS_CONNECT_TIMEOUT_SECONDS', 15),
];
