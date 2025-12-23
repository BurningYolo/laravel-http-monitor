<?php

namespace Burningyolo\LaravelHttpMonitor\Commands;

use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class PruneRequestLogsCommand extends Command
{
    protected $signature = 'request-tracker:prune
                            {--force : Force the operation without confirmation}';

    protected $description = 'Prune request logs based on retention settings in config';

    public function handle(): int
    {
        $inboundRetention = Config::get('request-tracker.retention.inbound_days');
        $outboundRetention = Config::get('request-tracker.retention.outbound_days');

        if (! $inboundRetention && ! $outboundRetention) {
            $this->warn('No retention settings configured. Set retention.inbound_days or retention.outbound_days in config.');

            return self::FAILURE;
        }

        $this->info('Pruning request logs based on retention settings:');

        if ($inboundRetention) {
            $this->line("  - Inbound requests: {$inboundRetention} days");
        }

        if ($outboundRetention) {
            $this->line("  - Outbound requests: {$outboundRetention} days");
        }

        if (! $this->option('force') && ! $this->confirm('Do you want to continue?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $totalDeleted = 0;

        // Prune inbound requests
        if ($inboundRetention) {
            $cutoffDate = now()->subDays($inboundRetention);
            $deleted = InboundRequest::where('created_at', '<', $cutoffDate)->delete();
            $totalDeleted += $deleted;
            $this->info("Deleted {$deleted} inbound requests older than {$inboundRetention} days");
        }

        // Prune outbound requests
        if ($outboundRetention) {
            $cutoffDate = now()->subDays($outboundRetention);
            $deleted = OutboundRequest::where('created_at', '<', $cutoffDate)->delete();
            $totalDeleted += $deleted;
            $this->info("Deleted {$deleted} outbound requests older than {$outboundRetention} days");
        }

        $this->newLine();
        $this->info("Total records deleted: {$totalDeleted}");

        return self::SUCCESS;
    }
}
