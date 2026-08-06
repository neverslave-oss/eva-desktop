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
        'url' => env('KERNEL_CENTRAL_URL', 'https://kernel-central.neverslave.com'),
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
        'id' => env('KERNEL_DEVICE_ID', 0),
        'token_path' => env('KERNEL_DEVICE_TOKEN_PATH', storage_path('app/device-token.txt')),
        'health_interval_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb (kernel-central WebSocket)
    |--------------------------------------------------------------------------
    |
    | Connection settings for kernel-central's Reverb server. The desktop
    | device uses these to establish a persistent WebSocket tunnel.
    |
    */
    'reverb' => [
        'host' => env('REVERB_HOST', ''),
        'port' => (int) env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'wss'),
        'app_key' => env('REVERB_APP_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | kernel-evolving (local agent)
    |--------------------------------------------------------------------------
    |
    | The local kernel-evolving instance that runs on port 8779. The message
    | forwarder relays incoming WS messages to this endpoint.
    |
    */
    'evolving' => [
        'url' => env('KERNEL_EVOLVING_URL', 'http://localhost:8779'),
        'timeout' => (int) env('KERNEL_EVOLVING_TIMEOUT', 30),
    ],
];
