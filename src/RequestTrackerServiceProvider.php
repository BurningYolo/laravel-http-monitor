<?php

namespace Burningyolo\LaravelHttpMonitor;

use Burningyolo\LaravelHttpMonitor\Commands\CleanupRequestLogsCommand;
use Burningyolo\LaravelHttpMonitor\Commands\ClearAllLogsCommand;
use Burningyolo\LaravelHttpMonitor\Commands\PruneRequestLogsCommand;
use Burningyolo\LaravelHttpMonitor\Commands\SendStatsToWebhooksCommand;
use Burningyolo\LaravelHttpMonitor\Commands\ShowStatsCommand;
use Burningyolo\LaravelHttpMonitor\Http\OutboundRequestMiddleware;
use Burningyolo\LaravelHttpMonitor\Middleware\TrackInboundRequest;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class RequestTrackerServiceProvider extends ServiceProvider
{
    public function boot()
    {

        // Register commands

        $this->commands([
            CleanupRequestLogsCommand::class,
            PruneRequestLogsCommand::class,
            ShowStatsCommand::class,
            ClearAllLogsCommand::class,
            SendStatsToWebhooksCommand::class,

        ]);

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'request-tracker-migrations');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/request-tracker.php' => config_path('request-tracker.php'),
        ], 'request-tracker-config');

        // Publish views
        $this->publishes([
            __DIR__.'/Views' => resource_path('views/vendor/http-monitor'),
        ], 'http-monitor-views');

        // Auto-load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register inbound middleware
        if (Config::get('request-tracker.track_inbound', true)) {
            $router = $this->app->make(Router::class);
            $router->pushMiddlewareToGroup('web', TrackInboundRequest::class);
            $router->pushMiddlewareToGroup('api', TrackInboundRequest::class);
        }

        // Load views
        $this->loadViewsFrom(__DIR__.'/Views', 'http-monitor');

        // Load routes
        if (Config::get('request-tracker.routes_enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/request-tracker.php',
            'request-tracker'
        );

        // Delay until Laravel is fully booted (Laravel 9–11 safe)
        $this->app->booted(function () {
            if (Config::get('request-tracker.track_outbound', true)) {
                $this->registerOutboundTracking();
            }
        });
    }

    protected function registerOutboundTracking(): void
    {
        Http::globalMiddleware(
            OutboundRequestMiddleware::handle()
        );

        // Explicit opt-in
        Http::macro('tracked', function () {
            return Http::withMiddleware(
                OutboundRequestMiddleware::handle()
            );
        });
    }
}
