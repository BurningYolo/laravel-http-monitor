<?php

namespace Burningyolo\LaravelHttpMonitor\Http;

use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Burningyolo\LaravelHttpMonitor\Support\BodyProcessor;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;

class OutboundRequestMiddleware
{
    /**
     * SINGLE shared middleware instance.
     * Required so withoutMiddleware() works correctly.
     */
    public static function handle(): Closure
    {
        static $middleware;

        if ($middleware) {
            return $middleware;
        }

        return $middleware = function (callable $handler) {
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

    protected static function logRequest(
        RequestInterface $request,
        $response,
        float $duration,
        $error = null
    ): void {
        if (
            ! Config::get('request-tracker.enabled', true) ||
            ! Config::get('request-tracker.track_outbound', true)
        ) {
            return;
        }

        try {
            $uri = $request->getUri();
            $host = $uri->getHost();

            $excludedHosts = Config::get('request-tracker.excluded_outbound_hosts', []);
            if (in_array($host, $excludedHosts, true)) {
                return;
            }

            $ip = gethostbyname($host);
            $trackedIp = null;

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $trackedIp = TrackedIp::getOrCreateFromIp($ip);
            }

            $triggeredBy = self::getTriggeredBy(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12)
            );

            $requestBody = null;
            if (Config::get('request-tracker.store_body')) {
                $rawBody = (string) $request->getBody();
                $requestBody = BodyProcessor::process($rawBody);
            }

            $responseBody = null;
            if ($response && Config::get('request-tracker.store_body')) {
                $rawResponseBody = (string) $response->getBody();
                $responseBody = BodyProcessor::process($rawResponseBody);
            }

            OutboundRequest::create([
                'tracked_ip_id' => $trackedIp?->id,
                'method' => $request->getMethod(),
                'url' => (string) $uri,
                'host' => $host,
                'full_url' => (string) $uri,
                'path' => $uri->getPath() ?: '/',
                'query_string' => $uri->getQuery() ?: null,
                'headers' => Config::get('request-tracker.store_headers')
                    ? $request->getHeaders()
                    : null,
                'request_body' => $requestBody,
                'status_code' => $response?->getStatusCode(),
                'response_headers' => $response && Config::get('request-tracker.store_headers')
                    ? $response->getHeaders()
                    : null,
                'response_body' => $responseBody,
                'duration_ms' => round($duration, 2),
                'user_id' => Auth::id(),
                'user_type' => Auth::check() ? get_class(Auth::user()) : null,
                'triggered_by' => $triggeredBy,
                'successful' => $error === null,
                'error_message' => $error ? (string) $error : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Outbound request tracking failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function getTriggeredBy(array $backtrace): ?string
    {
        foreach ($backtrace as $trace) {
            if (! isset($trace['class'])) {
                continue;
            }

            foreach (['Controller', 'Job', 'Command'] as $type) {
                if (str_contains($trace['class'], $type)) {
                    return "{$type}: {$trace['class']}";
                }
            }
        }

        return null;
    }
}
