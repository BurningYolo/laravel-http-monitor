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
        $notifier->sendStats();
        $this->info('Stats sent successfully.');
    }
}