<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Request Tracking
    |--------------------------------------------------------------------------
    |
    | Enable or disable the rrequest tracking functionality.
    |
    */
    'enabled' => env('REQUEST_TRACKER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Track Inbound Requests
    |--------------------------------------------------------------------------
    |
    | Enable or disable tracking of incoming HTTP requests.
    |
    */
    'track_inbound' => env('REQUEST_TRACKER_INBOUND', true),

    /*
    |--------------------------------------------------------------------------
    | Track Outbound Requests
    |--------------------------------------------------------------------------
    |
    | Enable or disable tracking of outgoing HTTP requests.
    |
    */
    'track_outbound' => env('REQUEST_TRACKER_OUTBOUND', true),

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | Paths that should not be tracked (e.g., health checks, assets).
    |
    */
    'excluded_paths' => [
        'telescope*',
        'horizon*',
        '_debugbar*',
        'admin*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Store Request Body
    |--------------------------------------------------------------------------
    |
    | Whether to store the request/response body.
    |
    */
    'store_body' => env('REQUEST_TRACKER_STORE_BODY', false),

    /*
    |--------------------------------------------------------------------------
    | Store Headers
    |--------------------------------------------------------------------------
    |
    | Whether to store request/response headers.
    |
    */
    'store_headers' => env('REQUEST_TRACKER_STORE_HEADERS', true),

    /*
    |--------------------------------------------------------------------------
    | Excluded Outbound Hosts
    |--------------------------------------------------------------------------
    |
    | Hosts that should not be tracked for outbound requests.
    | 
    |
    */
    'excluded_outbound_hosts' => [
        'localhost',
        '127.0.0.1',
        
    ],
];