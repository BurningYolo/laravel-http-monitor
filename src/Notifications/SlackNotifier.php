<?php

namespace Burningyolo\LaravelHttpMonitor\Notifications;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNotifier
{
    protected ?string $webhookUrl;

    protected bool $isSlackEnabled;

    public function __construct()
    {
        $this->webhookUrl = Config::get('request-tracker.slack.webhook_url');
        $this->isSlackEnabled = Config::get('request-tracker.slack.enabled', false);
    }

    public function send(string $message): bool
    {
        if (empty($this->webhookUrl) || ! $this->isSlackEnabled) {
            Log::debug('Slack webhook not configured or is Disabled, skipping notification', [
                'message' => $message,
            ]);

            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->webhookUrl, [
                    'text' => $message, // Slack still uses 'text' for simple messages
                ]);
            /** @var \Illuminate\Http\Client\Response $response */
            if ($response->successful() || $response->status() === 200) {
                Log::info('Slack stats notification sent successfully', [
                    'status' => $response->status(),
                    'message' => substr($message, 0, 120).'...',
                ]);

                return true;
            }

            // Slack often returns 200 even on error, but let's be safe
            Log::warning('Slack notification failed - unexpected response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'message' => substr($message, 0, 100).'...',
            ]);

            return false;

        } catch (RequestException $e) {
            Log::error('Slack notification HTTP request failed', [
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => $e->response?->body(),
                'message' => substr($message, 0, 100).'...',
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('Unexpected error sending Slack notification', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message' => substr($message, 0, 100).'...',
            ]);

            return false;
        }
    }
}
