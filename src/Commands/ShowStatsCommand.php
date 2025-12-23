<?php

namespace Burningyolo\LaravelHttpMonitor\Commands;

use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowStatsCommand extends Command
{
    protected $signature = 'request-tracker:stats
                            {--days=7 : Show stats for the last N days}';

    protected $description = 'Display statistics about tracked requests';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Request Tracker Statistics (Last {$days} days)");
        $this->newLine();

        // Overall stats
        $this->displayOverallStats();
        $this->newLine();

        // Recent stats
        $this->displayRecentStats($cutoffDate, $days);
        $this->newLine();

        // Top IPs
        $this->displayTopIps($cutoffDate, $days);
        $this->newLine();

        // Status code distribution
        $this->displayStatusDistribution($cutoffDate, $days);

        return self::SUCCESS;
    }

    protected function displayOverallStats(): void
    {
        $this->line('<fg=cyan>Overall Statistics</>');

        $totalInbound = InboundRequest::count();
        $totalOutbound = OutboundRequest::count();
        $totalIps = TrackedIp::count();
        $ipsWithGeo = TrackedIp::whereNotNull('country_code')->count();

        $oldestInbound = InboundRequest::oldest()->first();
        $oldestOutbound = OutboundRequest::oldest()->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Inbound Request', number_format($totalInbound)],
                ['Total Outbound Requests', number_format($totalOutbound)],
                ['Unique IP Addresse', number_format($totalIps)],
                ['IPs with Geo Data', number_format($ipsWithGeo).' ('.($totalIps > 0 ? round(($ipsWithGeo / $totalIps) * 100, 1) : 0).'%)'],
                ['Oldest Inbound Record', $oldestInbound ? $oldestInbound->created_at->diffForHumans() : 'N/A'],
                ['Oldest Outbound Record', $oldestOutbound ? $oldestOutbound->created_at->diffForHumans() : 'N/A'],
            ]
        );
    }

    protected function displayRecentStats($cutoffDate, int $days): void
    {
        $this->line("<fg=cyan>Recent Activity (Last {$days} days)</>");

        $recentInbound = InboundRequest::where('created_at', '>=', $cutoffDate)->count();
        $recentOutbound = OutboundRequest::where('created_at', '>=', $cutoffDate)->count();
        $recentIps = TrackedIp::where('first_seen_at', '>=', $cutoffDate)->count();

        $avgInboundDuration = InboundRequest::where('created_at', '>=', $cutoffDate)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        $avgOutboundDuration = OutboundRequest::where('created_at', '>=', $cutoffDate)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Inbound Requests', number_format($recentInbound)],
                ['Outbound Requests', number_format($recentOutbound)],
                ['New IPs', number_format($recentIps)],
                ['Avg Inbound Duration', $avgInboundDuration ? round($avgInboundDuration, 2).' ms' : 'N/A'],
                ['Avg Outbound Duration', $avgOutboundDuration ? round($avgOutboundDuration, 2).' ms' : 'N/A'],
            ]
        );
    }

    protected function displayTopIps($cutoffDate, int $days): void
    {
        $this->line("<fg=cyan>Top 10 IP Addresses (Last {$days} days)</>");
        $topIps = DB::table('tracked_ips')
            ->select(
                'tracked_ips.ip_address',
                'tracked_ips.country_code',
                'tracked_ips.city',
                DB::raw('COUNT(inbound_requests.id) as request_count')
            )
            ->leftJoin('inbound_requests', 'tracked_ips.id', '=', 'inbound_requests.tracked_ip_id')
            ->where('inbound_requests.created_at', '>=', $cutoffDate)
            ->groupBy('tracked_ips.id', 'tracked_ips.ip_address', 'tracked_ips.country_code', 'tracked_ips.city')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get();

        if ($topIps->isEmpty()) {
            $this->line('No data available');

            return;
        }

        $this->table(
            ['IP Address', 'Country', 'City', 'Requests'],
            $topIps->map(fn ($ip) => [
                $ip->ip_address,
                $ip->country_code ?? 'N/A',
                $ip->city ?? 'N/A',
                number_format($ip->request_count),
            ])
        );
    }

    protected function displayStatusDistribution($cutoffDate, int $days): void
    {
        $this->line("<fg=cyan>HTTP Status Distribution (Last {$days} days)</>");

        $inboundStats = InboundRequest::where('created_at', '>=', $cutoffDate)
            ->whereNotNull('status_code')
            ->select('status_code', DB::raw('COUNT(*) as count'))
            ->groupBy('status_code')
            ->orderBy('status_code')
            ->get();

        if ($inboundStats->isEmpty()) {
            $this->line('No data available');

            return;
        }

        $this->line('<fg=green>Inbound Requests:</>');
        $this->table(
            ['Status Code', 'Count', 'Percentage'],
            $inboundStats->map(function ($stat) use ($inboundStats) {
                $total = $inboundStats->sum('count');
                $percentage = ($stat->count / $total) * 100;

                return [
                    $this->colorizeStatusCode($stat->status_code),
                    number_format($stat->count),
                    round($percentage, 1).'%',
                ];
            })
        );
    }

    protected function colorizeStatusCode($code): string
    {
        return match (true) {
            $code >= 200 && $code < 300 => "<fg=green>{$code}</>",
            $code >= 300 && $code < 400 => "<fg=yellow>{$code}</>",
            $code >= 400 && $code < 500 => "<fg=red>{$code}</>",
            $code >= 500 => "<fg=red;options=bold>{$code}</>",
            default => (string) $code,
        };
    }
}
