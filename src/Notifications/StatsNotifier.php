<?php

namespace Burningyolo\LaravelHttpMonitor\Notifications;

use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Support\Facades\Config;

class StatsNotifier
{
    protected SlackNotifier $slack;

    protected DiscordNotifier $discord;

    public function __construct(SlackNotifier $slack, DiscordNotifier $discord)
    {
        $this->slack = $slack;
        $this->discord = $discord;
    }

    public function sendStats(): void
    {

        $lines = ['📊 *HTTP Monitor Daily Stats*'];

        // 1. Inbound Stats
        if (Config::get('request-tracker.notifications.enabled_fields.total_inbound', true)) {
            $lines[] = 'Total Inbound: '.InboundRequest::count();
        }

        // 2. Outbound Stats
        if (Config::get('request-tracker.notifications.enabled_fields.total_outbound', true)) {
            $lines[] = 'Total Outbound: '.OutboundRequest::count();
        }

        // 3. Success Rate
        if (Config::get('request-tracker.notifications.enabled_fields.successful_outbound', true)) {
            $success = OutboundRequest::where('successful', true)->count();
            $lines[] = "Successful Outbound: {$success}";
        }

        // 4. Performance (Reference your index logic)
        if (Config::get('request-tracker.notifications.enabled_fields.avg_response_time', true)) {
            $avg = round(OutboundRequest::avg('duration_ms'), 2);
            $lines[] = "Avg Response Time: {$avg}ms";
        }

        // 5. IP Tracking
        if (Config::get('request-tracker.notifications.enabled_fields.unique_ips', true)) {
            $lines[] = 'Unique IPs: '.TrackedIp::count();
        }

        $message = implode("\n", $lines);

        // Send to active channels
        $this->slack->send($message);
        $this->discord->send($message);
    }
}
