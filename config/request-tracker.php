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
    | Routes Enabled
    |--------------------------------------------------------------------------
    |
    | These Routes will be registered to view the dashboard and Views, if you don't want front-end then you can disable these routes
    |
    */
    'routes_enabled' => env('REQUEST_TRACKER_ROUTES_ENABLED', true),

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
    | Whether to store the request/response body. Recommended disabled cuz this will eat memory
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
    | Maximum Body Size
    |--------------------------------------------------------------------------
    |
    | Maximum size (in bytes) for request/response bodies to store.
    | Bodies larger than this will be truncated with a notice.
    |
    | Default: 65536 (64 KB)
    |
    */
    'max_body_size' => env('REQUEST_TRACKER_MAX_BODY_SIZE', 65536),

    /*
    |--------------------------------------------------------------------------
    | Omit Body Fields
    |--------------------------------------------------------------------------
    |
    | Sensitive fields that should be omitted from request/response bodies.
    | These fields will be replaced with '***OMITTED***' in stored data.
    |
    |
    */
    'omit_body_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'old_password',
        'token',
        'api_key',
        'api_secret',
        'secret',
        'secret_key',
        'access_token',
        'refresh_token',
        'bearer_token',
        'auth_token',
        'credit_card',
        'card_number',
        'card_cvv',
        'cvv',
        'cvc',
        'ssn',
        'social_security',
        'pin',
        'pin_code',
        'private_key',
        'encryption_key',
        'oauth_token',
        'oauth_secret',
    ],

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
    | - 'ip-api' (default)
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

    /*
    |--------------------------------------------------------------------------
    | Data Retention Settings
    |--------------------------------------------------------------------------
    |
    | Configure how long to keep request logs before they can be pruned.
    | Set to null to disable automatic pruning for that type.
    |
    */

    'retention' => [
        // Number of days to keep inbound request logs
        'inbound_days' => env('REQUEST_TRACKER_RETENTION_INBOUND', 7),

        // Number of days to keep outbound request logs
        'outbound_days' => env('REQUEST_TRACKER_RETENTION_OUTBOUND', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Send Stats Notifications to Discord & Slack
    |--------------------------------------------------------------------------
    |
    | Send Notificaitons about website stats to Discord & Slack
    | Change the Avatar URL and Bot Name as per your preference
    |
    |
    */

    'discord' => [
        'webhook_url' => env('REQUEST_TRACKER_DISCORD_WEBHOOK_URL', null),
        'enabled' => env('REQUEST_TRACKER_DISCORD_NOTIFICATIONS_ENABLED', false),
        'bot_name' => env('REQUEST_TRACKER_DISCORD_BOT_NAME', 'Laravel HTTP Monitor'),
        'avatar_url' => env('REQUEST_TRACKER_DISCORD_AVATAR_URL', 'https://avatars.githubusercontent.com/u/81748439'),

    ],

    'slack' => [
        'webhook_url' => env('REQUEST_TRACKER_SLACK_WEBHOOK_URL', null),
        'enabled' => env('REQUEST_TRACKER_SLACK_NOTIFICATIONS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications Enabled Fields
    |--------------------------------------------------------------------------
    |
    | Congigure this to enable/disable fields to be included in the notifications going to discord & Slack
    |
    |
    */
    'notifications' => [
        'enabled_fields' => [
            'total_inbound' => true,
            'total_outbound' => true,
            'successful_outbound' => true,
            'failed_outbound' => true,
            'avg_response_time' => true,
            'unique_ips' => true,
            'last_24h_activity' => true,
            'ratio_success_failure' => true,
            'top_endpoints' => true,
            'top_ips' => true,
        ],
    ],

];
