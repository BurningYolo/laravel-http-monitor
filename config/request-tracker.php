<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Request Tracking
    |--------------------------------------------------------------------------
    |
    | Enable or disable the request tracking functionality.
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

    /*
    |--------------------------------------------------------------------------
    | Fetch Geo Data
    |--------------------------------------------------------------------------
    |
    | Enable or disable fetching geographic data for IP addresses.
    |
    */
    'fetch_geo_data' => env('REQUEST_TRACKER_FETCH_GEO', true),

    /*
    |--------------------------------------------------------------------------
    | Geo Data Queue
    |--------------------------------------------------------------------------
    |
    | The queue to use for geo data fetching jobs.
    |
    */
    'geo_queue' => env('REQUEST_TRACKER_GEO_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Geo Data Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds for geo data API requests.
    |
    */
    'geo_timeout' => env('REQUEST_TRACKER_GEO_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Geo Data Async Dispatch
    |--------------------------------------------------------------------------
    |
    | Whether to dispatch geo data fetching asynchronously (via queue)
    | or synchronously (blocks request). Recommended: true
    |
    */
    'geo_dispatch_async' => env('REQUEST_TRACKER_GEO_ASYNC', true),

    /*
    |--------------------------------------------------------------------------
    | Geo Provider
    |--------------------------------------------------------------------------
    |
    | The geo IP provider to use for fetching location data.
    |
    | Available providers:
    | - 'ip-api' (defualt)
    | - 'ipinfo'
    | - 'ipapi'
    |
    */
    'geo_provider' => env('REQUEST_TRACKER_GEO_PROVIDER', 'ip-api'),

    /*
    |--------------------------------------------------------------------------
    | IPInfo Token
    |--------------------------------------------------------------------------
    |
    | API token for ipinfo.io provider.
    | Required if using 'ipinfo' as geo_provider.
    |
    */
    'ipinfo_token' => env('IPINFO_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | IPAPI Key
    |--------------------------------------------------------------------------
    |
    | API key for ipapi.co provider.
    |
    |
    */
    'ipapi_key' => env('IPAPI_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Skip Geo Fetch for IPs
    |--------------------------------------------------------------------------
    |
    | IP addresses that should not trigger geo data fetching.
    | Private and reserved IPs are automatically skipped.
    |
    */
    'skip_geo_for_ips' => [
        '127.0.0.1',
        'localhost',
        '::1',
    ],
];
