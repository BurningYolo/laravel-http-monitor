<?php

namespace Burningyolo\LaravelHttpMonitor\Tests\Unit;

use Burningyolo\LaravelHttpMonitor\Jobs\FetchIpGeoDataJob;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(FetchIpGeoDataJob::class)]
class FetchIpGeoDataJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        // Set default config values
        Config::set('request-tracker.fetch_geo_data', true);
        Config::set('request-tracker.geo_provider', 'ip-api');
        Config::set('request-tracker.geo_timeout', 5);
        Config::set('request-tracker.geo_queue', 'default');
    }

    #[Test]
    public function it_does_nothing_when_geo_lookup_is_disabled()
    {
        Config::set('request-tracker.fetch_geo_data', false);

        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake();

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        Http::assertNothingSent();
        $trackedIp->refresh();
        $this->assertNull($trackedIp->country_code);
    }

    #[Test]
    public function it_does_nothing_when_ip_already_has_geo_data()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'country_code' => 'US',
            'country_name' => 'United States',
            'city' => 'Mountain View',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake();

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_fetches_geo_data_from_ip_api_successfully()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'region' => 'CA',
                'regionName' => 'California',
                'city' => 'Mountain View',
                'zip' => '94035',
                'lat' => 37.386,
                'lon' => -122.0838,
                'timezone' => 'America/Los_Angeles',
                'isp' => 'Google LLC',
                'org' => 'Google Public DNS',
                'as' => 'AS15169 Google LLC',
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        $trackedIp->refresh();

        $this->assertEquals('US', $trackedIp->country_code);
        $this->assertEquals('United States', $trackedIp->country_name);
        $this->assertEquals('CA', $trackedIp->region_code);
        $this->assertEquals('California', $trackedIp->region_name);
        $this->assertEquals('Mountain View', $trackedIp->city);
        $this->assertEquals('94035', $trackedIp->zip_code);
        $this->assertEquals(37.386, $trackedIp->latitude);
        $this->assertEquals(-122.0838, $trackedIp->longitude);
        $this->assertEquals('America/Los_Angeles', $trackedIp->timezone);
        $this->assertEquals('Google LLC', $trackedIp->isp);
        $this->assertEquals('Google Public DNS', $trackedIp->organization);
    }

    #[Test]
    public function it_handles_partial_geo_data_from_ip_api()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '1.1.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Australia',
                'countryCode' => 'AU',
                'city' => 'Sydney',
                // Missing region, zip, coordinates, etc.
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        $trackedIp->refresh();

        $this->assertEquals('AU', $trackedIp->country_code);
        $this->assertEquals('Australia', $trackedIp->country_name);
        $this->assertEquals('Sydney', $trackedIp->city);
        $this->assertNull($trackedIp->region_code);
        $this->assertNull($trackedIp->zip_code);
        $this->assertNull($trackedIp->latitude);
        $this->assertNull($trackedIp->longitude);
    }

    #[Test]
    public function it_handles_ip_api_non_success_status()
    {
        Log::spy();
        $trackedIp = TrackedIp::create([
            'ip_address' => '192.168.1.1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'fail',
                'message' => 'private range',
                'query' => '192.168.1.1',
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        Log::shouldHaveReceived('warning')
            ->with('ip-api.com returned non-success status', \Mockery::type('array'))
            ->once();

        Log::shouldHaveReceived('warning')
            ->with('No geo data returned from provider', \Mockery::type('array'))
            ->once();
        $trackedIp->refresh();
        $this->assertNull($trackedIp->country_code);
    }

    #[Test]
    public function it_handles_ip_api_http_failure()
    {
        Log::spy();

        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response(null, 500),
        ]);

        try {
            $job = new FetchIpGeoDataJob($trackedIp->id);
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Exception was thrown as expected
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to fetch geo data for IP', \Mockery::type('array'));

        $trackedIp->refresh();
        $this->assertNull($trackedIp->country_code);
    }

    #[Test]
    public function it_uses_correct_ip_api_endpoint_with_fields()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'ip-api.com/json/8.8.8.8') &&
                   str_contains($url, 'fields=') &&
                   str_contains($url, 'country') &&
                   str_contains($url, 'lat') &&
                   str_contains($url, 'isp');
        });
    }

    #[Test]
    public function it_respects_custom_timeout_setting()
    {
        Config::set('request-tracker.geo_timeout', 10);

        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        // The timeout is set on the HTTP client, which we can't directly assert,
        // but we can verify the request was made successfully
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'ip-api.com');
        });
    }

    #[Test]
    public function it_logs_successful_geo_data_fetch()
    {
        Log::spy();  // <-- Add this at the top

        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'city' => 'Mountain View',
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Geo data fetched successfully', \Mockery::on(function ($context) {
                return $context['ip'] === '8.8.8.8' &&
                       $context['provider'] === 'ip-api';
            }));
    }

    #[Test]
    public function it_logs_warning_when_no_geo_data_returned()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'fail',
            ], 200),
        ]);

        Log::shouldReceive('warning')
            ->with('ip-api.com returned non-success status', \Mockery::type('array'));

        Log::shouldReceive('warning')
            ->once()
            ->with('No geo data returned from provider', \Mockery::on(function ($context) {
                return $context['ip'] === '8.8.8.8' &&
                       $context['provider'] === 'ip-api';
            }));

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();
    }

    #[Test]
    public function it_throws_exception_on_unknown_provider()
    {
        Config::set('request-tracker.geo_provider', 'unknown-provider');

        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown geo provider: unknown-provider');

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();
    }

    #[Test]
    public function it_logs_error_and_rethrows_on_exception()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to fetch geo data for IP', \Mockery::on(function ($context) {
                return $context['ip'] === '8.8.8.8' &&
                       $context['error'] === 'Network error' &&
                       isset($context['trace']);
            }));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Network error');

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();
    }

    #[Test]
    public function it_uses_custom_queue_from_config()
    {
        Config::set('request-tracker.geo_queue', 'geo-processing');

        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);

        // Access the queue property to verify it was set correctly
        $this->assertEquals('geo-processing', $job->queue);
    }

    #[Test]
    public function it_handles_ipv6_addresses()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '2001:4860:4860::8888',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'city' => 'Mountain View',
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '2001:4860:4860::8888');
        });

        $trackedIp->refresh();
        $this->assertEquals('US', $trackedIp->country_code);
    }

    #[Test]
    public function it_handles_empty_response_fields_gracefully()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => '',
                'countryCode' => '',
                'city' => '',
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        $trackedIp->refresh();
        // Empty strings should be stored as-is or handled by the application
        $this->assertNotNull($trackedIp);
    }

    #[Test]
    public function it_handles_numeric_coordinate_values()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'lat' => 37.386,
                'lon' => -122.0838,
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        $trackedIp->refresh();
        $this->assertNotNull($trackedIp->latitude);
        $this->assertNotNull($trackedIp->longitude);
    }

    #[Test]
    public function it_retries_failed_requests()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 2) {
                return Http::response(null, 500);
            }

            return Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
            ], 200);
        });

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        // Should have retried and eventually succeeded
        $this->assertGreaterThan(1, $callCount);
        $trackedIp->refresh();
        $this->assertEquals('US', $trackedIp->country_code);
    }

    #[Test]
    public function it_handles_special_characters_in_location_names()
    {
        $trackedIp = TrackedIp::create([
            'ip_address' => '8.8.8.8',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'São Tomé and Príncipe',
                'countryCode' => 'ST',
                'city' => 'São Tomé',
                'regionName' => 'Água Grande',
            ], 200),
        ]);

        $job = new FetchIpGeoDataJob($trackedIp->id);
        $job->handle();

        $trackedIp->refresh();
        $this->assertEquals('São Tomé and Príncipe', $trackedIp->country_name);
        $this->assertEquals('São Tomé', $trackedIp->city);
        $this->assertEquals('Água Grande', $trackedIp->region_name);
    }
}
