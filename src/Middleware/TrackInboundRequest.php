<?php

namespace Burningyolo\LaravelHttpMonitor\Middleware; 

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config; 
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Session; 

class TrackInboundRequest
{
    public function handle(Request $request, Closure $next)
    {
        if (!Config::get('request-tracker.enabled') || !Config::get('request-tracker.track_inbound')) {
            return $next($request);
        }

        // Check excluded paths
        foreach (Config::get('request-tracker.excluded_paths', []) as $pattern) {
            if (Str::is($pattern, $request->path())) {
                return $next($request);
            }
        }

        $startTime = microtime(true);
        
        $response = $next($request);
        
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        try {
            $this->logRequest($request, $response, $duration);
        } catch (\Exception $e) {
            // Fail silently to not break the application
             Log::error('Failed to track inbound request', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }

    protected function logRequest($request, $response, float $duration): void
    {
        $ip = $request->ip();
        $trackedIp = TrackedIp::getOrCreateFromIp($ip);

        $route = Route::current();

        InboundRequest::create([
            'tracked_ip_id' => $trackedIp->id,
            'method' => $request->method(),
            'url' => $request->url(),
            'full_url' => $request->fullUrl(),
            'path' => $request->path(),
            'query_string' => $request->getQueryString(),
            'headers' => Config::get('request-tracker.store_headers') ? $request->headers->all() : null,
            'request_body' => Config::get('request-tracker.store_body') ? $request->getContent() : null,
            'status_code' => $response->getStatusCode(),
            'response_headers' => Config::get('request-tracker.store_headers') ? $this->getResponseHeaders($response) : null,
            'response_body' => Config::get('request-tracker.store_body') ? $response->getContent() : null,
            'duration_ms' => round($duration),
            'user_id' => Auth::id(), 
            'user_type' => Auth::check()? get_class(Auth::user()) : null,
            'session_id' => Session::getId(), 
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'route_name' => $route?->getName(),
            'controller_action' => $route?->getActionName(),
        ]);
    }

    protected function getResponseHeaders($response): array
    {
        $headers = [];
        foreach ($response->headers->all() as $key => $values) {
            $headers[$key] = $values;
        }
        return $headers;
    }
}