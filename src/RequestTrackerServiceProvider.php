<?php 

namespace Burningyolo\LaravelHttpMonitor;

use Illuminate\Support\ServiceProvider; 
use Burningyolo\LaravelHttpMonitor\Middleware\TrackInboundRequest; 
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;  

class RequestTrackerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => \database_path('migrations'),
        ], 'request-tracker-migrations');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/request-tracker.php' => \config_path('request-tracker.php'),
        ], 'request-tracker-config');

        // Load migrations automatically
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register middleware
        $router = $this->app->make(Router::class);
        $router->pushMiddlewareToGroup('web', TrackInboundRequest::class);
        $router->pushMiddlewareToGroup('api', TrackInboundRequest::class);
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/request-tracker.php', 'request-tracker'
        );

        // Automatically track all outbound HTTP requests
        $this->app->booted(function () {
            if (Config::get('request-tracker.track_outbound', true)) {
                $this->registerGlobalHttpTracking();
            }
        });
    }

    protected function registerGlobalHttpTracking()
    {
        // Register global middleware for all HTTP requests
        \Illuminate\Support\Facades\Http::globalRequestMiddleware(
            \Burningyolo\LaravelHttpMonitor\Http\OutboundRequestMiddleware::middleware()
        );

        // Also register the macro for explicit tracking if needed
        \Illuminate\Support\Facades\Http::macro('tracked', function () {
            return \Illuminate\Support\Facades\Http::withMiddleware(
                \Burningyolo\LaravelHttpMonitor\Http\OutboundRequestMiddleware::middleware()
            );
        });

        // Register macro to skip tracking for specific requests
        \Illuminate\Support\Facades\Http::macro('untracked', function () {
            return \Illuminate\Support\Facades\Http::withoutMiddleware(
                \Burningyolo\LaravelHttpMonitor\Http\OutboundRequestMiddleware::class
            );
        });
    }
}