<?php 

namespace Burningyolo\LaravelHttpMonitor\Notifications; 

use Burningyolo\LaravelHttpMonitor\Models\TrackedIp; 
use Burningyolo\LaravelHttpMonitor\Models\InboundRequest; 
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest; 

class StatsNotifier 
{
    protected SlackNotifier $slackNotifier; 
    protected DiscordNotifier $discordNotifier; 

    public function __construct(SlackNotifier $slack, DiscordNotifier $discord)
    {
        $this->slackNotifier = $slack;
        $this->discordNotifier = $discord;
    }

    public function sendStats(): void
    {
        //Gather Stats 
        $inboundCount = InboundRequest::count();
        $outboundCount = OutboundRequest::count();  
        $ipsCount = TrackedIp::count(); 

        $message = "Request Tracker Stats:\n";
        $message .= "Total Inbound Requests: {$inboundCount}\n";
        $message .= "Total Outbound Requests: {$outboundCount}\n";
        $message .= "Total Tracked IPs: {$ipsCount}\n";
        
        $this->slackNotifier->send($message);
        $this->discordNotifier->send($message);
    }

}