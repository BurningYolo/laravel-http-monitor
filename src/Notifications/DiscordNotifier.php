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
        $this->isDiscordEnabled = Config::get('request-tracker.discord.enabled', false);
    }

    public function send(array $stats): bool
    {
        if (empty($this->webhookUrl) || ! $this->isDiscordEnabled) {
            Log::debug('Discord webhook not configured or disabled, skipping notification');

            return false;
        }

        try {
            $embed = $this->buildEmbed($stats);

            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->webhookUrl, [
                    'username' => Config::get('request-tracker.discord.bot_name', 'HTTP Monitor Bot'),
                    'avatar_url' => Config::get('request-tracker.discord.avatar_url', null),
                    'embeds' => [$embed],
                ]);
            /** @var \Illuminate\Http\Client\Response $response */
            if ($response->successful()) {
                Log::info('Discord stats notification sent successfully', [
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::warning('Discord notification failed - non-successful response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (RequestException $e) {
            Log::error('Discord notification HTTP request failed', [
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => $e->response?->body(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('Unexpected error sending Discord notification', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Build Discord embed structure
     */
    protected function buildEmbed(array $stats): array
    {
        $fields = [];
        $description = "Here's your HTTP monitoring summary for today.";

        // Build fields based on enabled configurations
        if (! empty($stats['total_inbound'])) {
            $fields[] = [
                'name' => 'Total Inbound Requests',
                'value' => number_format($stats['total_inbound']),
                'inline' => true,
            ];
        }

        if (! empty($stats['total_outbound'])) {
            $fields[] = [
                'name' => 'Total Outbound Requests',
                'value' => number_format($stats['total_outbound']),
                'inline' => true,
            ];
        }

        if (! empty($stats['unique_ips'])) {
            $fields[] = [
                'name' => 'Unique IP Addresses',
                'value' => number_format($stats['unique_ips']),
                'inline' => true,
            ];
        }

        if (isset($stats['successful_outbound'])) {
            $fields[] = [
                'name' => 'Successful Outbound',
                'value' => number_format($stats['successful_outbound']),
                'inline' => true,
            ];
        }

        if (isset($stats['failed_outbound'])) {
            $fields[] = [
                'name' => 'Failed Outbound',
                'value' => number_format($stats['failed_outbound']),
                'inline' => true,
            ];
        }

        if (isset($stats['avg_response_time'])) {
            $fields[] = [
                'name' => 'Average Response Time',
                'value' => $stats['avg_response_time'].'ms',
                'inline' => true,
            ];
        }

        if (isset($stats['ratio_success_failure'])) {
            $fields[] = [
                'name' => 'Success Rate',
                'value' => $stats['ratio_success_failure'].'%',
                'inline' => true,
            ];
        }

        if (! empty($stats['last_24h_activity'])) {
            $fields[] = [
                'name' => 'Total Requests in Last 24h',
                'value' => number_format($stats['last_24h_activity']),
                'inline' => true,
            ];
        }

        // Top Endpoints
        if (! empty($stats['top_endpoints'])) {
            $endpointsList = collect($stats['top_endpoints'])
                ->map(fn ($item) => "`{$item['endpoint']}` - {$item['count']} requests")
                ->take(5)
                ->join("\n");

            $fields[] = [
                'name' => 'Top Endpoints',
                'value' => $endpointsList ?: 'No data',
                'inline' => false,
            ];
        }

        // Top IPs
        if (! empty($stats['top_ips'])) {
            $ipsList = collect($stats['top_ips'])
                ->map(fn ($item) => "`{$item['ip']}` - {$item['count']} requests")
                ->take(5)
                ->join("\n");

            $fields[] = [
                'name' => 'Top IP Addresses',
                'value' => $ipsList ?: 'No data',
                'inline' => false,
            ];
        }

        return [
            'title' => 'HTTP Monitor Daily Stats',
            'description' => $description,
            'color' => 5814783, // Blue color
            'fields' => $fields,
            'timestamp' => now()->toIso8601String(),
            'footer' => [
                'text' => 'Laravel HTTP Monitor',
            ],
            'author' => [
                'name' => 'Created by BurningYolo',
                'url' => 'https://github.com/BurningYolo/laravel-http-monitor',
                'icon_url' => 'https://avatars.githubusercontent.com/u/81748439',
            ],
        ];
    }
}
