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

    /**
     * Send a rich block message to Slack
     */
    public function send(array $stats): bool
    {
        if (empty($this->webhookUrl) || ! $this->isSlackEnabled) {
            Log::debug('Slack webhook not configured or disabled, skipping notification');

            return false;
        }

        try {
            $blocks = $this->buildBlocks($stats);

            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->webhookUrl, [
                    'blocks' => $blocks,
                ]);
            /** @var \Illuminate\Http\Client\Response $response */
            if ($response->successful() || $response->status() === 200) {
                Log::info('Slack stats notification sent successfully', [
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::warning('Slack notification failed - unexpected response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (RequestException $e) {
            Log::error('Slack notification HTTP request failed', [
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => $e->response?->body(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('Unexpected error sending Slack notification', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Build Slack blocks structure
     */
    protected function buildBlocks(array $stats): array
    {
        $blocks = [];

        // Header
        $blocks[] = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => 'HTTP Monitor Daily Stats',
                'emoji' => true,
            ],
        ];

        // Description
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "Here's your HTTP monitoring summary for today.",
            ],
        ];

        $blocks[] = ['type' => 'divider'];

        // Build fields section
        $fields = [];

        if (! empty($stats['total_inbound'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Total Inbound*\n".number_format($stats['total_inbound']),
            ];
        }

        if (! empty($stats['total_outbound'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Total Outbound*\n".number_format($stats['total_outbound']),
            ];
        }

        if (isset($stats['successful_outbound'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Successful Outbound*\n".number_format($stats['successful_outbound']),
            ];
        }

        if (isset($stats['failed_outbound'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Failed Outbound*\n".number_format($stats['failed_outbound']),
            ];
        }

        if (isset($stats['avg_response_time'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Avg Response Time*\n".$stats['avg_response_time'].'ms',
            ];
        }

        if (! empty($stats['unique_ips'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Unique IPs*\n".number_format($stats['unique_ips']),
            ];
        }

        if (isset($stats['ratio_success_failure'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Success Rate*\n".$stats['ratio_success_failure'].'%',
            ];
        }

        if (! empty($stats['last_24h_activity'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Total Requests in Last 24h*\n".number_format($stats['last_24h_activity']),
            ];
        }

        // Add fields block if we have any
        if (! empty($fields)) {
            $blocks[] = [
                'type' => 'section',
                'fields' => $fields,
            ];
        }

        // Top Endpoints
        if (! empty($stats['top_endpoints'])) {
            $blocks[] = ['type' => 'divider'];

            $endpointsList = collect($stats['top_endpoints'])
                ->map(fn ($item) => "• `{$item['endpoint']}` - {$item['count']} requests")
                ->take(5)
                ->join("\n");

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Top Endpoints*\n".($endpointsList ?: 'No data'),
                ],
            ];
        }

        // Top IPs
        if (! empty($stats['top_ips'])) {
            $ipsList = collect($stats['top_ips'])
                ->map(fn ($item) => "• `{$item['ip']}` - {$item['count']} requests")
                ->take(5)
                ->join("\n");

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Top IP Addresses*\n".($ipsList ?: 'No data'),
                ],
            ];
        }

        // Footer
        $blocks[] = ['type' => 'divider'];
        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => ' '.now()->format('Y-m-d H:i:s').' | Laravel HTTP Monitor',
                ],
            ],
        ];

        return $blocks;
    }
}
