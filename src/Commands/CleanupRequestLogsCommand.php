<?php

namespace Burningyolo\LaravelHttpMonitor\Commands;

use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Console\Command;

class CleanupRequestLogsCommand extends Command
{
    protected $signature = 'request-tracker:cleanup
                            {--days=30 : Delete records older than this many days}
                            {--type=all : Type of requests to clean (all, inbound, outbound)}
                            {--status= : Only delete requests with specific status code}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--orphaned-ips : Also cleanup orphaned IP records}';

    protected $description = 'Clean up old request logs from the database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $type = $this->option('type');
        $status = $this->option('status');
        $dryRun = $this->option('dry-run');
        $cleanupOrphanedIps = $this->option('orphaned-ips');

        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up records older than {$days} days (before {$cutoffDate->toDateTimeString()})");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No records will actually be deleted');
        }

        $totalDeleted = 0;

        // Clean inbound requests
        if (in_array($type, ['all', 'inbound'])) {
            $deleted = $this->cleanupInboundRequests($cutoffDate, $status, $dryRun);
            $totalDeleted += $deleted;
            $this->info("Inbound requests: {$deleted} records ".($dryRun ? 'would be ' : '').'deleted');
        }

        // Clean outbound requests
        if (in_array($type, ['all', 'outbound'])) {
            $deleted = $this->cleanupOutboundRequests($cutoffDate, $status, $dryRun);
            $totalDeleted += $deleted;
            $this->info("Outbound requests: {$deleted} records ".($dryRun ? 'would be ' : '').'deleted');
        }

        // Clean orphaned IPs
        if ($cleanupOrphanedIps && ! $dryRun) {
            $deleted = $this->cleanupOrphanedIps();
            $this->info("Orphaned IPs: {$deleted} records deleted");
        } elseif ($cleanupOrphanedIps && $dryRun) {
            $count = $this->countOrphanedIps();
            $this->info("Orphaned IPs: {$count} records would be deleted");
        }

        $this->newLine();
        $this->info("Total: {$totalDeleted} records ".($dryRun ? 'would be ' : '').'deleted');

        if ($dryRun) {
            $this->warn('Run without --dry-run to actually delete the records');
        }

        return self::SUCCESS;
    }

    protected function cleanupInboundRequests($cutoffDate, $status, bool $dryRun): int
    {
        $query = InboundRequest::where('created_at', '<', $cutoffDate);

        if ($status) {
            $query->where('status_code', $status);
        }

        if ($dryRun) {
            return $query->count();
        }

        return $query->delete();
    }

    protected function cleanupOutboundRequests($cutoffDate, $status, bool $dryRun): int
    {
        $query = OutboundRequest::where('created_at', '<', $cutoffDate);

        if ($status) {
            $query->where('status_code', $status);
        }

        if ($dryRun) {
            return $query->count();
        }

        return $query->delete();
    }

    protected function cleanupOrphanedIps(): int
    {
        return TrackedIp::whereDoesntHave('inboundRequests')
            ->whereDoesntHave('outboundRequests')
            ->delete();
    }

    protected function countOrphanedIps(): int
    {
        return TrackedIp::whereDoesntHave('inboundRequests')
            ->whereDoesntHave('outboundRequests')
            ->count();
    }
}
