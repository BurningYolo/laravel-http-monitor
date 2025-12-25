<?php

namespace Burningyolo\LaravelHttpMonitor\Commands;

use Burningyolo\LaravelHttpMonitor\Notifications\StatsNotifier;
use Illuminate\Console\Command;

class SendStatsToWebhooksCommand extends Command
{
    protected $signature = 'request-tracker:send-stats';

    protected $description = 'Send request stats to Slack and Discord';

    public function handle(StatsNotifier $notifier)
    {
        $sentChannels = $notifier->sendStats();

        if (empty($sentChannels)) {
            $this->warn('No notifications were sent. Please enable Slack or Discord and set webhook URLs.');

            return;
        }

        $this->info(
            'Stats sent via: '.implode(', ', array_map('ucfirst', $sentChannels))
        );
    }
}
