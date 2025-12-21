<?php

namespace Burningyolo\LaravelHttpMonitor\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Burningyolo\LaravelHttpMonitor\Jobs\FetchIpGeoDataJob;

class TrackInboundRequest
{
    public function handle(Request $request, Closure $next)
    {
        if (
            !Config::get('request-tracker.enabled', true) ||
            !Config::get('request-tracker.track_inbound', true)
        ) {
            return $next($request);
        }

        foreach (Config::get('request-tracker.excluded_paths', []) as $pattern) {
            if (Str::is($pattern, $request->path())) {
                return $next($request);
            }
        }

        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000;

        try {
            $this->logRequest($request, $response, $duration);
        } catch (\Throwable $e) {
            Log::warning('Failed to track inbound request', [
                'error' => $e->getMessage(),
                'url'   => $request->fullUrl(),
            ]);
        }

        return $response;
    }

    protected function logRequest(Request $request, $response, float $duration): void
    {
        $trackedIp = null;

        if ($ip = $request->ip()) {
            $trackedIp = TrackedIp::getOrCreateFromIp($ip);
            
            // Dispatch geo data fetch job if enabled and needed
            $this->dispatchGeoDataFetch($trackedIp, $ip);
        }

        $route = Route::current();

        $isStreamed =
            $response instanceof StreamedResponse ||
            $response instanceof BinaryFileResponse;

        InboundRequest::create([
            'tracked_ip_id'    => $trackedIp?->id,
            'method'           => $request->method(),
            'url'              => $request->url(),
            'full_url'         => $request->fullUrl(),
            'path'             => $request->path(),
            'query_string'     => $request->getQueryString(),
            'headers'          => Config::get('request-tracker.store_headers')
                ? $request->headers->all()
                : null,
            'request_body'     => Config::get('request-tracker.store_body')
                ? $request->getContent()
                : null,
            'status_code'      => method_exists($response, 'getStatusCode')
                ? $response->getStatusCode()
                : null,
            'response_headers' => Config::get('request-tracker.store_headers')
                ? $response->headers->all()
                : null,
            'response_body'    => (
                Config::get('request-tracker.store_body') && !$isStreamed
            )
                ? $response->getContent()
                : null,
            'duration_ms'      => round($duration),
            'user_id'          => Auth::id(),
            'user_type'        => Auth::check() ? get_class(Auth::user()) : null,
            'session_id'       => Session::getId(),
            'user_agent'       => $request->userAgent(),
            'referer'          => $request->header('referer'),
            'route_name'       => $route?->getName(),
            'controller_action'=> $route?->getActionName(),
        ]);
    }

    /**
     * Dispatch geo data fetch job if needed
     */
    protected function dispatchGeoDataFetch(TrackedIp $trackedIp, string $ip): void
    {
        // Check if geo fetching is enabled
        if (!Config::get('request-tracker.fetch_geo_data', true)) {
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
            FetchIpGeoDataJob::dispatch($trackedIp->id);
        } else {
            try {
                (new FetchIpGeoDataJob($trackedIp->id))->handle();
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