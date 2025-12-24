<?php

namespace Burningyolo\LaravelHttpMonitor\Tests\Unit;

use Burningyolo\LaravelHttpMonitor\Commands\CleanupRequestLogsCommand;
use Burningyolo\LaravelHttpMonitor\Commands\ClearAllLogsCommand;
use Burningyolo\LaravelHttpMonitor\Commands\PruneRequestLogsCommand;
use Burningyolo\LaravelHttpMonitor\Commands\ShowStatsCommand;
use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

abstract class CommandTestCase extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            \Burningyolo\LaravelHttpMonitor\RequestTrackerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}

#[CoversClass(ShowStatsCommand::class)]
class CommandTest extends CommandTestCase
{
    #[Test]
    public function it_displays_stats_with_default_days()
    {
        $this->artisan(ShowStatsCommand::class)
            ->expectsOutput('Request Tracker Statistics (Last 7 days)')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_stats_with_custom_days()
    {
        $this->artisan(ShowStatsCommand::class, ['--days' => 30])
            ->expectsOutput('Request Tracker Statistics (Last 30 days)')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_overall_stats_with_no_data()
    {
        $this->artisan(ShowStatsCommand::class)
            ->expectsOutputToContain('Overall Statistics')
            ->expectsOutputToContain('Total Inbound Request')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_overall_stats_with_data()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'country_code' => 'US',
            'city' => 'New York',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/test',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'duration_ms' => 150.5,
            'created_at' => now(),
        ]);

        OutboundRequest::create([
            'url' => 'https://api.example.com',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'method' => 'POST',
            'host' => 'yourmum.com',
            'status_code' => 201,
            'duration_ms' => 250.3,
            'created_at' => now(),
        ]);

        $this->artisan(ShowStatsCommand::class)
            ->expectsOutputToContain('Overall Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_top_ips_with_request_counts()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.100',
            'country_code' => 'US',
            'city' => 'Los Angeles',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            InboundRequest::create([
                'tracked_ip_id' => $ip->id,
                'method' => 'GET',
                'url' => "/api/test{$i}",
                'full_url' => 'http://example.com/api/old',
                'path' => 'abc',
                'status_code' => 200,
                'created_at' => now(),
            ]);
        }

        $this->artisan(ShowStatsCommand::class)
            ->expectsOutputToContain('Top 10 IP Addresses')
            ->expectsOutputToContain('192.168.1.100')
            ->assertSuccessful();
    }
}

#[CoversClass(CleanupRequestLogsCommand::class)]
class CleanupRequestLogsCommandTest extends CommandTestCase
{
    #[Test]
    public function it_deletes_old_inbound_requests()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/old',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now()->subDays(40),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/recent',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan(CleanupRequestLogsCommand::class, ['--days' => 30, '--type' => 'inbound'])
            ->assertSuccessful();

        $this->assertEquals(1, InboundRequest::count());
        $this->assertEquals('/api/recent', InboundRequest::first()->url);
    }

    #[Test]
    public function it_performs_dry_run_without_deleting()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/test',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now()->subDays(40),
        ]);

        $this->artisan(CleanupRequestLogsCommand::class, ['--days' => 30, '--dry-run' => true])
            ->expectsOutputToContain('DRY RUN MODE')
            ->expectsOutputToContain('would be deleted')
            ->assertSuccessful();

        $this->assertEquals(1, InboundRequest::count());
    }

    #[Test]
    public function it_filters_by_status_code()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/error',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 500,
            'created_at' => now()->subDays(40),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/success',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now()->subDays(40),
        ]);

        $this->artisan(CleanupRequestLogsCommand::class, [
            '--days' => 30,
            '--status' => 500,
            '--type' => 'inbound',
        ])->assertSuccessful();

        $this->assertEquals(1, InboundRequest::count());
        $this->assertEquals(200, InboundRequest::first()->status_code);
    }

    #[Test]
    public function it_cleans_orphaned_ips()
    {
        TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $ipWithRequests = TrackedIp::create([
            'ip_address' => '192.168.1.2',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ipWithRequests->id,
            'method' => 'GET',
            'url' => '/api/test',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now(),
        ]);

        $this->artisan(CleanupRequestLogsCommand::class, [
            '--days' => 30,
            '--orphaned-ips' => true,
        ])->assertSuccessful();

        $this->assertEquals(1, TrackedIp::count());
        $this->assertEquals('192.168.1.2', TrackedIp::first()->ip_address);
    }

    #[Test]
    public function it_cleans_both_inbound_and_outbound_with_all_type()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/test',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now()->subDays(40),
        ]);

        OutboundRequest::create([
            'url' => 'https://api.example.com',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'method' => 'POST',
            'status_code' => 200,
            'created_at' => now()->subDays(40),
        ]);

        $this->artisan(CleanupRequestLogsCommand::class, ['--days' => 30, '--type' => 'all'])
            ->assertSuccessful();

        $this->assertEquals(0, InboundRequest::count());
        $this->assertEquals(0, OutboundRequest::count());
    }
}

#[CoversClass(ClearAllLogsCommand::class)]
class ClearAllLogsCommandTest extends CommandTestCase
{
    #[Test]
    public function it_clears_all_logs_with_force_flag()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/test',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now(),
        ]);

        OutboundRequest::create([
            'url' => 'https://api.example.com',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'method' => 'POST',
            'status_code' => 200,
            'created_at' => now(),
        ]);

        $this->artisan(ClearAllLogsCommand::class, ['--type' => 'all', '--force' => true])
            ->expectsOutputToContain('Clearing logs...')
            ->assertSuccessful();

        $this->assertEquals(0, InboundRequest::count());
        $this->assertEquals(0, OutboundRequest::count());
        $this->assertEquals(0, TrackedIp::count());
    }

    #[Test]
    public function it_clears_only_inbound_requests()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/test',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now(),
        ]);

        OutboundRequest::create([
            'url' => 'https://api.example.com',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'method' => 'POST',
            'status_code' => 200,
            'created_at' => now(),
        ]);

        $this->artisan(ClearAllLogsCommand::class, ['--type' => 'inbound', '--force' => true])
            ->assertSuccessful();

        $this->assertEquals(0, InboundRequest::count());
        $this->assertEquals(1, OutboundRequest::count());
        $this->assertEquals(1, TrackedIp::count());
    }

    #[Test]
    public function it_requires_confirmation_without_force_flag()
    {
        $this->artisan(ClearAllLogsCommand::class, ['--type' => 'all'])
            ->expectsQuestion('Are you absolutely sure you want to continue?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_double_confirmation()
    {
        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/test',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now(),
        ]);

        $this->artisan(ClearAllLogsCommand::class, ['--type' => 'all'])
            ->expectsQuestion('Are you absolutely sure you want to continue?', true)
            ->expectsQuestion('This is your last chance. Really delete?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        $this->assertEquals(1, InboundRequest::count());
    }

    #[Test]
    public function it_rejects_invalid_type()
    {
        $this->artisan(ClearAllLogsCommand::class, ['--type' => 'invalid'])
            ->expectsOutput('Invalid type specified')
            ->assertFailed();
    }
}

#[CoversClass(PruneRequestLogsCommand::class)]
class PruneRequestLogsCommandTest extends CommandTestCase
{
    #[Test]
    public function it_prunes_based_on_retention_settings()
    {
        Config::set('request-tracker.retention.inbound_days', 30);
        Config::set('request-tracker.retention.outbound_days', 60);

        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/old',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now()->subDays(40),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/recent',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now()->subDays(20),
        ]);

        $this->artisan(PruneRequestLogsCommand::class, ['--force' => true])
            ->assertSuccessful();

        $this->assertEquals(1, InboundRequest::count());
        $this->assertEquals('/api/recent', InboundRequest::first()->url);
    }

    #[Test]
    public function it_fails_when_no_retention_configured()
    {
        Config::set('request-tracker.retention.inbound_days', null);
        Config::set('request-tracker.retention.outbound_days', null);

        $this->artisan(PruneRequestLogsCommand::class, ['--force' => true])
            ->expectsOutputToContain('No retention settings configured')
            ->assertFailed();
    }

    #[Test]
    public function it_requires_confirmation_without_force()
    {
        Config::set('request-tracker.retention.inbound_days', 30);

        $this->artisan(PruneRequestLogsCommand::class)
            ->expectsQuestion('Do you want to continue?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_prunes_inbound_and_outbound_separately()
    {
        Config::set('request-tracker.retention.inbound_days', 30);
        Config::set('request-tracker.retention.outbound_days', 60);

        $ip = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        InboundRequest::create([
            'tracked_ip_id' => $ip->id,
            'method' => 'GET',
            'url' => '/api/test',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'status_code' => 200,
            'created_at' => now()->subDays(40),
        ]);

        OutboundRequest::create([
            'url' => 'https://api.example.com',
            'full_url' => 'http://example.com/api/old',
            'path' => 'abc',
            'method' => 'POST',
            'status_code' => 200,
            'created_at' => now()->subDays(50),
        ]);

        $this->artisan(PruneRequestLogsCommand::class, ['--force' => true])
            ->assertSuccessful();

        $this->assertEquals(0, InboundRequest::count());
        $this->assertEquals(1, OutboundRequest::count());
    }

    #[Test]
    public function it_displays_retention_settings_before_pruning()
    {
        Config::set('request-tracker.retention.inbound_days', 30);
        Config::set('request-tracker.retention.outbound_days', 60);

        $this->artisan(PruneRequestLogsCommand::class)
            ->expectsOutputToContain('Inbound requests: 30 days')
            ->expectsOutputToContain('Outbound requests: 60 days')
            ->expectsQuestion('Do you want to continue?', false)
            ->assertSuccessful();
    }
}
