<?php

namespace Burningyolo\LaravelHttpMonitor\Commands;

use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Console\Command;

class ClearAllLogsCommand extends Command
{
    protected $signature = 'request-tracker:clear
                            {--type=all : What to clear (all, inbound, outbound, ips)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clear all request tracking logs (DANGER: This cannot be undone!)';

    public function handle(): int
    {
        $type = $this->option('type');

        $message = match ($type) {
            'inbound' => 'This will delete ALL inbound request logs',
            'outbound' => 'This will delete ALL outbound request logs',
            'ips' => 'This will delete ALL tracked IP records',
            'all' => 'This will delete ALL request logs and IP records',
            default => 'Invalid type specified',
        };

        if (! in_array($type, ['all', 'inbound', 'outbound', 'ips'])) {
            $this->error($message);

            return self::FAILURE;
        }

        $this->warn($message);
        $this->error('⚠️Operaton cannot be undonee');
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('Are you absolutely sure you want to continue?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            // Double confirmation for safety
            if (! $this->confirm('This is your last chance. Really delete?', false)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Clearing logs...');
        $totalDeleted = 0;

        if (in_array($type, ['all', 'inbound'])) {
            $count = InboundRequest::count();
            InboundRequest::truncate();
            $totalDeleted += $count;
            $this->line("Cleared {$count} inbound requests");
        }

        if (in_array($type, ['all', 'outbound'])) {
            $count = OutboundRequest::count();
            OutboundRequest::truncate();
            $totalDeleted += $count;
            $this->line(" Cleared {$count} outbound requests");
        }

        if (in_array($type, ['all', 'ips'])) {
            $count = TrackedIp::count();
            TrackedIp::truncate();
            $totalDeleted += $count;
            $this->line("Cleared {$count} tracked IPs");
        }

        $this->newLine();
        $this->info("Total records cleared: {$totalDeleted}");

        return self::SUCCESS;
    }
}
