<?php

namespace Burningyolo\LaravelHttpMonitor\Http; 

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Support\Facades\Config;

class OutboundRequestMiddleware
{
    public static function middleware(): Closure
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $startTime = microtime(true);

                /** @var PromiseInterface $promise */
                $promise = $handler($request, $options);

                return $promise->then(
                    function ($response) use ($request, $startTime) {
                        $duration = (microtime(true) - $startTime) * 1000;
                        self::logRequest($request, $response, $duration);
                        return $response;
                    },
                    function ($reason) use ($request, $startTime) {
                        $duration = (microtime(true) - $startTime) * 1000;
                        self::logRequest($request, null, $duration, $reason);
                        throw $reason;
                    }
                );
            };
        };
    }

    protected static function logRequest(RequestInterface $request, $response, float $duration, $error = null): void
    {
        if (!Config::get('request-tracker.enabled') || !Config::get('request-tracker.track_outbound')) {
            return;
        }

        try {
            $uri = $request->getUri();
            $host = $uri->getHost();

            // Check if host is excluded
            $excludedHosts = Config::get('request-tracker.excluded_outbound_hosts', []);
            if (in_array($host, $excludedHosts)) {
                return;
            }
            
            // Get IP of the remote host
            $ip = gethostbyname($host);
            $trackedIp = null;
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $trackedIp = TrackedIp::getOrCreateFromIp($ip);
            }

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $triggeredBy = self::getTriggeredBy($backtrace);

            OutboundRequest::create([
                'tracked_ip_id' => $trackedIp?->id,
                'method' => $request->getMethod(),
                'url' => (string) $uri,
                'host' => $host,
                'full_url' => (string) $uri,
                'path' => $uri->getPath(),
                'query_string' => $uri->getQuery(),
                'headers' => Config::get('request-tracker.store_headers') ? self::getRequestHeaders($request) : null,
                'request_body' => Config::get('request-tracker.store_body') ? (string) $request->getBody() : null,
                'status_code' => $response ? $response->getStatusCode() : null,
                'response_headers' => $response && Config::get('request-tracker.store_headers') ? self::getResponseHeaders($response) : null,
                'response_body' => $response && Config::get('request-tracker.store_body') ? (string) $response->getBody() : null,
                'duration_ms' => round($duration),
                'user_id' => Auth::id(),
                'user_type' => Auth::check() ? get_class(Auth::user()) : null,
                'triggered_by' => $triggeredBy,
                'successful' => $error === null,
                'error_message' => $error ? (string) $error : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to track outbound request', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function getRequestHeaders(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = $values;
        }
        return $headers;
    }

    protected static function getResponseHeaders($response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = $values;
        }
        return $headers;
    }

    protected static function getTriggeredBy(array $backtrace): ?string
    {
        foreach ($backtrace as $trace) {
            if (isset($trace['class'])) {
                if (str_contains($trace['class'], 'Controller')) {
                    return 'Controller: ' . $trace['class'];
                }
                if (str_contains($trace['class'], 'Job')) {
                    return 'Job: ' . $trace['class'];
                }
                if (str_contains($trace['class'], 'Command')) {
                    return 'Command: ' . $trace['class'];
                }
            }
        }
        return null;
    }
}