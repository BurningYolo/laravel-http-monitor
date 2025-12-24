<?php

namespace Burningyolo\LaravelHttpMonitor\Notifications;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordNotifier
{
    protected ?string $webhookUrl;

    protected bool $isDiscordEnabled;

    public function __construct()
    {
        $this->webhookUrl = Config::get('request-tracker.discord.webhook_url');
        $this->isDiscordEnabled = Config::get('request-tracker.discord.enabled', true);
    }

    public function send(string $message): bool
    {
        if (empty($this->webhookUrl) || ! $this->isDiscordEnabled) {
            Log::debug('Discord webhook not configured or is Disabled, skipping notification', [
                'message' => $message,
            ]);

            return false;
        }

        try {
            $response = Http::timeout(10) // prevent hanging forever
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->webhookUrl, [
                    'content' => $message, // Discord uses 'content' (not 'text')
                ]);
            /** @var \Illuminate\Http\Client\Response $response */
            if ($response->successful()) {
                Log::info('Discord stats notification sent successfully', [
                    'status' => $response->status(),
                    'message' => substr($message, 0, 120).'...',
                ]);

                return true;
            }

            // Log non-200 responses
            Log::warning('Discord notification failed - non-successful response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'message' => substr($message, 0, 100).'...',
            ]);

            return false;

        } catch (RequestException $e) {
            Log::error('Discord notification HTTP request failed', [
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => $e->response?->body(),
                'message' => substr($message, 0, 100).'...',
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('Unexpected error sending Discord notification', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message' => substr($message, 0, 100).'...',
            ]);

            return false;
        }
    }
}
