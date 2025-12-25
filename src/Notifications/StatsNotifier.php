<?php

namespace Burningyolo\LaravelHttpMonitor\Notifications;

use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class StatsNotifier
{
    protected SlackNotifier $slack;

    protected DiscordNotifier $discord;

    public function __construct(SlackNotifier $slack, DiscordNotifier $discord)
    {
        $this->slack = $slack;
        $this->discord = $discord;
    }

    /**
     * Gather stats and send notifications
     */
    public function sendStats(): array
    {
        $stats = $this->gatherStats();
        $sentChannels = [];

        if (
            Config::get('request-tracker.slack.enabled', false) &&
            filled(Config::get('request-tracker.slack.webhook_url'))
        ) {
            $this->slack->send($stats);
            $sentChannels[] = 'slack';
        }

        if (
            Config::get('request-tracker.discord.enabled', false) &&
            filled(Config::get('request-tracker.discord.webhook_url'))
        ) {
            $this->discord->send($stats);
            $sentChannels[] = 'discord';
        }

        return $sentChannels;
    }

    /**
     * Gather all statistics based on configuration
     */
    protected function gatherStats(): array
    {
        $stats = [];
        $enabledFields = Config::get('request-tracker.notifications.enabled_fields', []);

        // Total Inbound
        if ($enabledFields['total_inbound'] ?? false) {
            $stats['total_inbound'] = InboundRequest::count();
        }

        // Total Outbound
        if ($enabledFields['total_outbound'] ?? false) {
            $stats['total_outbound'] = OutboundRequest::count();
        }

        // Successful Outbound
        if ($enabledFields['successful_outbound'] ?? false) {
            $stats['successful_outbound'] = OutboundRequest::where('successful', true)->count();
        }

        // Failed Outbound
        if ($enabledFields['failed_outbound'] ?? false) {
            $stats['failed_outbound'] = OutboundRequest::where('successful', false)->count();
        }

        // Average Response Time
        if ($enabledFields['avg_response_time'] ?? false) {
            $avg = OutboundRequest::avg('duration_ms');
            $stats['avg_response_time'] = $avg ? round($avg, 2) : 0;
        }

        // Unique IPs
        if ($enabledFields['unique_ips'] ?? false) {
            $stats['unique_ips'] = TrackedIp::count();
        }

        // Last 24h Activity
        if ($enabledFields['last_24h_activity'] ?? false) {
            $stats['last_24h_activity'] = InboundRequest::where(
                'created_at',
                '>=',
                now()->subDay()
            )->count() + OutboundRequest::where(
                'created_at',
                '>=',
                now()->subDay()
            )->count();
        }

        // Success/Failure Ratio
        if ($enabledFields['ratio_success_failure'] ?? false) {
            $total = OutboundRequest::count();
            $successful = OutboundRequest::where('successful', true)->count();

            if ($total > 0) {
                $stats['ratio_success_failure'] = round(($successful / $total) * 100, 2);
            } else {
                $stats['ratio_success_failure'] = 0;
            }
        }

        // Top Endpoints
        if ($enabledFields['top_endpoints'] ?? false) {
            $stats['top_endpoints'] = $this->getTopEndpoints();
        }

        // Top IPs
        if ($enabledFields['top_ips'] ?? false) {
            $stats['top_ips'] = $this->getTopIps();
        }

        return $stats;
    }

    /**
     * Get top requested endpoints
     */
    protected function getTopEndpoints(int $limit = 5): array
    {
        return InboundRequest::select('path', DB::raw('COUNT(*) as count'))
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'endpoint' => $item->path,
                'count' => $item->count,
            ])
            ->toArray();
    }

    /**
     * Get top requesting IPs
     */
    protected function getTopIps(int $limit = 5): array
    {
        return TrackedIp::select('ip_address', DB::raw('COUNT(*) as count'))
            ->groupBy('ip_address')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'ip' => $item->ip_address,
                'count' => $item->count,
            ])
            ->toArray();
    }
}
