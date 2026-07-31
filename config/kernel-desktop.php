<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kernel Central
    |--------------------------------------------------------------------------
    |
    | The central server that manages device pairing, message relay, and
    | WebSocket tunnels. The desktop app registers itself as a device
    | during the setup wizard.
    |
    */
    'central' => [
        'url' => env('KERNEL_CENTRAL_URL', 'https://kernel-central.neverslave.dev'),
        'api_prefix' => '/api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Identity
    |--------------------------------------------------------------------------
    |
    | After pairing, the device receives a Sanctum token from kernel-central.
    | This token is stored here and used for heartbeat and message relay.
    |
    */
    'device' => [
        'name' => env('KERNEL_DEVICE_NAME', 'kernel-desktop'),
        'type' => 'desktop',
        'token_path' => env('KERNEL_DEVICE_TOKEN_PATH', storage_path('app/device-token.txt')),
        'health_interval_seconds' => 30,
    ],
];
