<?php

namespace Burningyolo\LaravelHttpMonitor\Jobs;

use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchIpGeoDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $trackedIpId;

    public function __construct(int $trackedIpId)
    {
        $this->trackedIpId = $trackedIpId;

        // Use custom queue if configured
        $queue = Config::get('request-tracker.geo_queue', 'default');
        $this->onQueue($queue);
    }

    public function handle(): void
    {
        // Check if geo lookup is enabled
        if (! Config::get('request-tracker.fetch_geo_data', true)) {
            return;
        }

        $trackedIp = TrackedIp::find($this->trackedIpId);

        if ($trackedIp->hasGeoData()) {
            return;
        }

        try {
            $provider = Config::get('request-tracker.geo_provider', 'ip-api');
            $geoData = $this->fetchFromProvider($provider, $trackedIp->ip_address);

            if ($geoData) {
                $trackedIp->update([
                    'country_code' => $geoData['country_code'] ?? null,
                    'country_name' => $geoData['country'] ?? null,
                    'region_code' => $geoData['region_code'] ?? null,
                    'region_name' => $geoData['region'] ?? null,
                    'city' => $geoData['city'] ?? null,
                    'zip_code' => $geoData['zip'] ?? null,
                    'latitude' => $geoData['latitude'] ?? null,
                    'longitude' => $geoData['longitude'] ?? null,
                    'timezone' => $geoData['timezone'] ?? null,
                    'isp' => $geoData['isp'] ?? null,
                    'organization' => $geoData['organization'] ?? null,
                ]);

                Log::info('Geo data fetched successfully', [
                    'ip' => $trackedIp->ip_address,
                    'provider' => $provider,
                    'location' => $trackedIp->location,
                ]);
            } else {
                Log::warning('No geo data returned from provider', [
                    'ip' => $trackedIp->ip_address,
                    'provider' => $provider,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to fetch geo data for IP', [
                'ip' => $trackedIp->ip_address,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function fetchFromProvider(string $provider, string $ip): ?array
    {
        $timeout = Config::get('request-tracker.geo_timeout', 5);

        return match ($provider) {
            'ip-api' => $this->fetchFromIpApi($ip, $timeout),
            'ipinfo' => $this->fetchFromIpInfo($ip, $timeout),
            'ipapi' => $this->fetchFromIpApi2($ip, $timeout),
            default => throw new \InvalidArgumentException("Unknown geo provider: {$provider}"),
        };
    }

    protected function fetchFromIpApi(string $ip, int $timeout): ?array
    {
        /** @var Response $response */
        $response = Http::timeout($timeout)
            ->retry(2, 100)
            ->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as',
            ]);

        if (! $response->successful()) {
            Log::warning('ip-api.com request failed', [
                'status' => $response->status(),
                'ip' => $ip,
            ]);

            return null;
        }

        $data = $response->json();

        if (($data['status'] ?? '') !== 'success') {
            Log::warning('ip-api.com returned non-success status', [
                'status' => $data['status'] ?? 'unknown',
                'message' => $data['message'] ?? 'No message',
                'ip' => $ip,
            ]);

            return null;
        }

        return [
            'country' => $data['country'] ?? null,
            'country_code' => $data['countryCode'] ?? null,
            'region' => $data['regionName'] ?? null,
            'region_code' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'zip' => $data['zip'] ?? null,
            'latitude' => $data['lat'] ?? null,
            'longitude' => $data['lon'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'isp' => $data['isp'] ?? null,
            'organization' => $data['org'] ?? null,
        ];
    }

    protected function fetchFromIpInfo(string $ip, int $timeout): ?array
    {
        $token = Config::get('request-tracker.ipinfo_token');

        if (! $token) {
            Log::warning('ipinfo.io token not configured');

            return null;
        }

        /** @var Response $response */
        $response = Http::timeout($timeout)
            ->retry(2, 100)
            ->get("https://ipinfo.io/{$ip}", [
                'token' => $token,
            ]);

        if (! $response->successful()) {
            Log::warning('ipinfo.io request failed', [
                'status' => $response->status(),
                'ip' => $ip,
            ]);

            return null;
        }

        $data = $response->json();

        // Check for error responses
        if (isset($data['error'])) {
            Log::warning('ipinfo.io returned error', [
                'error' => $data['error'],
                'ip' => $ip,
            ]);

            return null;
        }

        [$lat, $lon] = isset($data['loc']) ? explode(',', $data['loc']) : [null, null];

        return [
            'country' => $data['country'] ?? null,
            'country_code' => $data['country'] ?? null,
            'region' => $data['region'] ?? null,
            'region_code' => null, // ipinfo doesn't provide region code
            'city' => $data['city'] ?? null,
            'zip' => $data['postal'] ?? null,
            'latitude' => $lat ? (float) $lat : null,
            'longitude' => $lon ? (float) $lon : null,
            'timezone' => $data['timezone'] ?? null,
            'isp' => $data['org'] ?? null,
            'organization' => $data['org'] ?? null,
        ];
    }

    protected function fetchFromIpApi2(string $ip, int $timeout): ?array
    {
        $apiKey = Config::get('request-tracker.ipapi_key');
        $url = $apiKey
            ? "https://ipapi.co/{$ip}/json/?key={$apiKey}"
            : "https://ipapi.co/{$ip}/json/";

        /** @var Response $response */
        $response = Http::timeout($timeout)
            ->retry(2, 100)
            ->get($url);

        if (! $response->successful()) {
            Log::warning('ipapi.co request failed', [
                'status' => $response->status(),
                'ip' => $ip,
            ]);

            return null;
        }

        $data = $response->json();

        // Check for error responses
        if (isset($data['error'])) {
            Log::warning('ipapi.co returned error', [
                'error' => $data['error'],
                'reason' => $data['reason'] ?? 'Unknown',
                'ip' => $ip,
            ]);

            return null;
        }

        return [
            'country' => $data['country_name'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'region' => $data['region'] ?? null,
            'region_code' => $data['region_code'] ?? null,
            'city' => $data['city'] ?? null,
            'zip' => $data['postal'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'isp' => $data['org'] ?? null,
            'organization' => $data['org'] ?? null,
        ];
    }
}
