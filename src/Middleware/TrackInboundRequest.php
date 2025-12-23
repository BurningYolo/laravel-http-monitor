<?php

namespace Burningyolo\LaravelHttpMonitor\Middleware;

use Burningyolo\LaravelHttpMonitor\Jobs\FetchIpGeoDataJob;
use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Burningyolo\LaravelHttpMonitor\Support\BodyProcessor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrackInboundRequest
{
    public function handle(Request $request, Closure $next)
    {
        if (
            ! Config::get('request-tracker.enabled', true) ||
            ! Config::get('request-tracker.track_inbound', true)
        ) {
            return $next($request);
        }

        foreach (Config::get('request-tracker.excluded_paths', []) as $pattern) {
            if (Str::is($pattern, $request->path())) {
                return $next($request);
            }
        }

        $startTime = microtime(true);
        $response = null;

        try {
            $response = $next($request);

            return $response;
        } finally {
            // Track request even if exception occurs
            $duration = (microtime(true) - $startTime) * 1000;

            try {
                $this->logRequest($request, $response, $duration);
            } catch (\Throwable $e) {
                Log::warning('Failed to track inbound request', [
                    'error' => $e->getMessage(),
                    'url' => $request->fullUrl(),
                ]);
            }
        }
    }

    protected function logRequest(Request $request, $response, float $duration): void
    {
        $trackedIp = null;

        if ($ip = $request->ip()) {
            $trackedIp = TrackedIp::getOrCreateFromIp($ip);
            $this->dispatchGeoDataFetch($trackedIp, $ip);
        }

        $route = Route::current();

        $isStreamed =
            $response instanceof StreamedResponse ||
            $response instanceof BinaryFileResponse;

        $requestBody = null;
        if (Config::get('request-tracker.store_body')) {
            try {
                $rawBody = $request->getContent();
                $requestBody = BodyProcessor::process($rawBody, $request);
            } catch (\Throwable $e) {
                $requestBody = null;
            }
        }

        $responseBody = null;
        if (Config::get('request-tracker.store_body') && ! $isStreamed && $response) {
            try {
                if (method_exists($response, 'getContent')) {
                    $rawResponseBody = $response->getContent();
                    $responseBody = BodyProcessor::process($rawResponseBody);
                }
            } catch (\Throwable $e) {
                $responseBody = null;
            }
        }

        $path = $request->path();
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        InboundRequest::create([
            'tracked_ip_id' => $trackedIp?->getKey(),
            'method' => $request->method(),
            'url' => $request->url(),
            'full_url' => $request->fullUrl(),
            'path' => $path,
            'query_string' => $request->getQueryString(),
            'headers' => (Config::get('request-tracker.store_headers') && property_exists($request, 'headers'))
                ? $request->headers->all()
                : null,
            'request_body' => $requestBody,
            'status_code' => ($response && method_exists($response, 'getStatusCode'))
                ? $response->getStatusCode()
                : null,
            'response_headers' => ($response && Config::get('request-tracker.store_headers') && property_exists($response, 'headers'))
                ? $response->headers->all()
                : null,
            'response_body' => $responseBody,
            'duration_ms' => round($duration),
            'user_id' => Auth::id(),
            'user_type' => Auth::check() ? get_class(Auth::user()) : null,
            'session_id' => Session::getId(),
            'user_agent' => method_exists($request, 'userAgent') ? $request->userAgent() : null,
            'referer' => method_exists($request, 'header') ? $request->header('referer') : null,
            'route_name' => $route?->getName(),
            'controller_action' => $route?->getActionName(),
        ]);
    }

    /**
     * Dispatch geo data fetch job if needed
     */
    protected function dispatchGeoDataFetch(TrackedIp $trackedIp, string $ip): void
    {
        // Check if geo fetching is enabled
        if (! Config::get('request-tracker.fetch_geo_data', true)) {
            return;
        }

        // Skip if already has geo data
        if ($trackedIp->hasGeoData()) {
            return;
        }

        // Skip certain IPs (localhost, private IPs, etc.)
        if ($this->shouldSkipGeoFetch($ip)) {
            return;
        }

        // Dispatch job or fetch synchronously based on config
        if (Config::get('request-tracker.geo_dispatch_async', true)) {
            FetchIpGeoDataJob::dispatch($trackedIp->getKey());
        } else {
            try {
                (new FetchIpGeoDataJob($trackedIp->getKey()))->handle();
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch geo data synchronously', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check if IP should be skipped for geo fetching
     */
    protected function shouldSkipGeoFetch(string $ip): bool
    {
        // Check configured skip list
        $skipList = Config::get('request-tracker.skip_geo_for_ips', []);
        if (in_array($ip, $skipList)) {
            return true;
        }

        // Skip private/reserved IP ranges (10.x.x.x, 172.16-31.x.x, 192.168.x.x, 127.x.x.x, etc.)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return false;
    }
}
