# Laravel HTTP Monitor

A Laravel package for tracking and monitoring both inbound and outbound HTTP requests with IP tracking & optional geographical data.

## Requirements

- PHP 8.0 or higher
- Laravel 9.x, 10.x, or 11.x

## Installation

Install the package via Composer:

```bash
composer require burningyolo/laravel-http-monitor
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=request-tracker-config
```

Publish migrations:

```bash
php artisan vendor:publish --tag=request-tracker-migrations
```

Optionally, publish the views for customization:

```bash
php artisan vendor:publish --tag=http-monitor-views
```

## Configuration

The configuration file `config/request-tracker.php` provides extensive options for customizing the package behavior:

### Basic Settings

```php
// Enable/disable the entire package
'enabled' => true,

// Track inbound requests
'track_inbound' => true,

// Track outbound requests
'track_outbound' => true,

// Store request/response headers
'store_headers' => true,

// Store request/response bodies
'store_body' => true,
```

### Exclusions

```php
// Exclude specific paths from inbound tracking
'excluded_paths' => [
    'horizon/*',
    'telescope/*',
    '_debugbar/*',
],

// Exclude specific hosts from outbound tracking
'excluded_outbound_hosts' => [
    'localhost',
    '127.0.0.1',
],

// These fields will be stored as ***omitted***
'omit_body_fields' => [
        'password',
        'password_confirmation',
        .......
    ],
```

### Automatic Tracking

Once installed, the package automatically tracks:

- All inbound requests to `web` and `api` middleware groups
- All outbound HTTP requests made via Laravel's HTTP client

### Dashboard

Access the built-in dashboard by visiting:

```
/http-monitor
```

The dashboard provides:

- Overview of recent requests
- Filtering by type, status, date range
- Detailed request/response inspection
- Geographic distribution of requests
- Performance metrics

## Artisan Commands

### View Statistics

Display statistics about tracked requests:

```bash
php artisan request-tracker:stats

# Show stats for last 30 days
php artisan request-tracker:stats --days=30
```

### Cleanup Old Logs

Remove old request logs based on custom criteria:

```bash
# Delete records older than 30 days
php artisan request-tracker:cleanup --days=30

# Clean only inbound requests
php artisan request-tracker:cleanup --type=inbound

# Clean by status code
php artisan request-tracker:cleanup --status=404

# Preview without deleting
php artisan request-tracker:cleanup --dry-run

# Also remove orphaned IP records
php artisan request-tracker:cleanup --orphaned-ips
```

### Prune Based on Retention Policy

Automatically prune logs according to retention settings in config:

```bash
php artisan request-tracker:prune

# Skip confirmation
php artisan request-tracker:prune --force
```

### Clear All Logs

Remove all tracked data (use with caution):

```bash
# Clear everything
php artisan request-tracker:clear

# Clear specific type
php artisan request-tracker:clear --type=inbound
php artisan request-tracker:clear --type=outbound
php artisan request-tracker:clear --type=ips

# Skip confirmation
php artisan request-tracker:clear --force
```

### Accessing Tracked Data

Query tracked requests using the provided models:

```php

use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;

```

## Database Schema

### inbound_requests

Stores incoming HTTP requests with columns for method, URL, headers, body, status code, duration, user information, and more.

### outbound_requests

Stores outgoing HTTP requests with similar structure plus fields for tracking what triggered the request and success/failure status.

### tracked_ips

Stores unique IP addresses with optional geolocation data including country, region, city, coordinates, timezone, ISP, and organization.

## Some Considerations

- Enable `geo_dispatch_async` to fetch geolocation data in background jobs
- Use `excluded_paths` to skip tracking of static assets and admin panels
- Consider disabling body storage for high-traffic applications

## License

This package is open-source software licensed under the MIT license.

## Credits

Developed by [Burningyolo](https://github.com/burningyolo)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

If you encounter any issues or have questions, please open an issue on GitHub.
