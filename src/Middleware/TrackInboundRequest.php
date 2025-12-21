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
}
