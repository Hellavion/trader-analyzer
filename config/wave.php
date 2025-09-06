<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Resume Lifetime
    |--------------------------------------------------------------------------
    |
    | Define how long (in seconds) you wish an event stream to persist so it
    | can be resumed after a reconnect. The connection automatically
    | re-establishes with every closed response.
    |
    | * Requires a cache driver to be configured.
    |
    */
    'resume_lifetime' => 300, // 5 минут для восстановления соединения

    /*
    |--------------------------------------------------------------------------
    | Reconnection Time
    |--------------------------------------------------------------------------
    |
    | This value determines how long (in milliseconds) to wait before
    | attempting a reconnect to the server after a connection has been lost.
    |
    */
    'retry' => 3000, // 3 секунды

    /*
    |--------------------------------------------------------------------------
    | Ping
    |--------------------------------------------------------------------------
    |
    | A ping event is automatically sent on every SSE connection request if the
    | last event occurred before the set `frequency` value (in seconds). This
    | ensures the connection remains persistent.
    |
    */
    'ping' => [
        'enable' => true,
        'frequency' => 30,
        'eager_env' => 'local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Path
    |--------------------------------------------------------------------------
    |
    | This path is used to register the necessary routes for establishing the
    | Wave connection, storing presence channel users, and handling simple whisper events.
    |
    */
    'path' => 'wave',

    /*
     |--------------------------------------------------------------------------
     | Route Middleware
     |--------------------------------------------------------------------------
     |
     | Define which middleware Wave should assign to the routes that it registers.
     |
     */
    'middleware' => [
        'web',
        \App\Http\Middleware\HandleCors::class,
    ],

    /*
     |--------------------------------------------------------------------------
     | Auth & Guard
     |--------------------------------------------------------------------------
     |
     | Define the default authentication middleware and guard type for
     | authenticating users for presence channels and whisper events.
     |
     */
    'auth_middleware' => 'auth',
    'guard' => 'web',
];