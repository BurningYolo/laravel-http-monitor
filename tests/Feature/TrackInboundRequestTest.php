<?php

namespace Burningyolo\LaravelHttpMonitor\Tests\Feature;

use Burningyolo\LaravelHttpMonitor\Middleware\TrackInboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\InboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(TrackInboundRequest::class)]
class TrackInboundRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Set default config
        Config::set('request-tracker.enabled', true);
        Config::set('request-tracker.track_inbound', true);
        Config::set('request-tracker.store_body', true);
        Config::set('request-tracker.store_headers', true);
        Config::set('request-tracker.max_body_size', 65536);
        Config::set('request-tracker.omit_body_fields', ['password', 'token']);
        Config::set('request-tracker.excluded_paths', []);
        Config::set('request-tracker.fetch_geo_data', false);
    }

    #[Test]
    public function it_tracks_inbound_requests()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, InboundRequest::count());

        $tracked = InboundRequest::first();
        $this->assertEquals('GET', $tracked->method);
        $this->assertEquals('/test', $tracked->path);
    }

    #[Test]
    public function it_does_not_track_when_disabled()
    {
        Config::set('request-tracker.enabled', false);

        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');

        $middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(0, InboundRequest::count());
    }

    #[Test]
    public function it_does_not_track_inbound_when_disabled()
    {
        Config::set('request-tracker.track_inbound', false);

        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');

        $middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(0, InboundRequest::count());
    }

    #[Test]
    public function it_excludes_paths_matching_patterns()
    {
        Config::set('request-tracker.excluded_paths', ['admin*', 'telescope*']);

        $middleware = new TrackInboundRequest;

        // Should be excluded
        $request1 = Request::create('/admin/users', 'GET');
        $middleware->handle($request1, fn ($req) => new Response('OK', 200));

        $request2 = Request::create('/telescope/requests', 'GET');
        $middleware->handle($request2, fn ($req) => new Response('OK', 200));

        // Should be tracked
        $request3 = Request::create('/api/users', 'GET');
        $middleware->handle($request3, fn ($req) => new Response('OK', 200));

        $this->assertEquals(1, InboundRequest::count());
        $this->assertEquals('/api/users', InboundRequest::first()->path);
    }

    #[Test]
    public function it_omits_sensitive_fields_from_request_body()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/login', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'john',
                'password' => 'secret123',
                'email' => 'john@example.com',
            ])
        );

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $body = json_decode($tracked->request_body, true);

        $this->assertEquals('john', $body['username']);
        $this->assertEquals('***OMITTED***', $body['password']);
        $this->assertEquals('john@example.com', $body['email']);
    }

    #[Test]
    public function it_omits_sensitive_fields_from_response_body()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/api/user', 'GET');

        $middleware->handle($request, function ($req) {
            return new Response(json_encode([
                'id' => 1,
                'name' => 'John',
                'token' => 'secret-token-123',
            ]), 200, ['Content-Type' => 'application/json']);
        });

        $tracked = InboundRequest::first();
        $body = json_decode($tracked->response_body, true);

        $this->assertEquals(1, $body['id']);
        $this->assertEquals('John', $body['name']);
        $this->assertEquals('***OMITTED***', $body['token']);
    }

    #[Test]
    public function it_truncates_large_request_bodies()
    {
        Config::set('request-tracker.max_body_size', 100);

        $middleware = new TrackInboundRequest;
        $largeBody = str_repeat('a', 200);
        $request = Request::create('/test', 'POST', [], [], [], [], $largeBody);

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertStringEndsWith('... [truncated]', $tracked->request_body);
        $this->assertLessThanOrEqual(100 + strlen('... [truncated]'), strlen($tracked->request_body));
    }

    #[Test]
    public function it_stores_headers_when_enabled()
    {
        Config::set('request-tracker.store_headers', true);

        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'TestBot/1.0');
        $request->headers->set('X-Custom-Header', 'custom-value');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertNotNull($tracked->headers);
        $this->assertIsArray($tracked->headers);
    }

    #[Test]
    public function it_does_not_store_headers_when_disabled()
    {
        Config::set('request-tracker.store_headers', false);

        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertNull($tracked->headers);
    }

    #[Test]
    public function it_stores_body_when_enabled()
    {
        Config::set('request-tracker.store_body', true);

        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['data' => 'test'])
        );

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertNotNull($tracked->request_body);
    }

    #[Test]
    public function it_does_not_store_body_when_disabled()
    {
        Config::set('request-tracker.store_body', false);

        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['data' => 'test'])
        );

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertNull($tracked->request_body);
        $this->assertNull($tracked->response_body);
    }

    #[Test]
    public function it_tracks_response_status_code()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');

        $middleware->handle($request, fn ($req) => new Response('Not Found', 404));

        $tracked = InboundRequest::first();
        $this->assertEquals(404, $tracked->status_code);
    }

    #[Test]
    public function it_tracks_request_duration()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');

        $middleware->handle($request, function ($req) {
            usleep(10000); // Sleep 10ms

            return new Response('OK', 200);
        });

        $tracked = InboundRequest::first();
        $this->assertGreaterThan(0, $tracked->duration_ms);
    }

    #[Test]
    public function it_tracks_ip_address()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertNotNull($tracked->tracked_ip_id);

        $trackedIp = TrackedIp::find($tracked->tracked_ip_id);
        $this->assertEquals('192.168.1.100', $trackedIp->ip_address);
    }

    #[Test]
    public function it_tracks_user_agent()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertEquals('Mozilla/5.0', $tracked->user_agent);
    }

    #[Test]
    public function it_tracks_referer()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');
        $request->headers->set('Referer', 'https://google.com');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertEquals('https://google.com', $tracked->referer);
    }

    #[Test]
    public function it_handles_form_data_correctly()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'POST', [
            'username' => 'john',
            'password' => 'secret',
        ]);
        $request->headers->set('Content-Type', 'application/x-www-form-urlencoded');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $body = json_decode($tracked->request_body, true);

        $this->assertEquals('john', $body['username']);
        $this->assertEquals('***OMITTED***', $body['password']);
    }

    #[Test]
    public function it_handles_query_strings()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test?page=1&limit=10', 'GET');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();

        // Parse the query string into an array
        parse_str($tracked->query_string, $params);

        // Assert the parameters match expected values
        $this->assertEquals([
            'page' => '1',
            'limit' => '10',
        ], $params);
    }

    #[Test]
    public function it_tracks_full_url()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('https://example.com/api/users?page=1', 'GET');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $this->assertStringContainsString('/api/users', $tracked->full_url);
        $this->assertStringContainsString('page=1', $tracked->full_url);
    }

    #[Test]
    public function it_does_not_fail_on_missing_response_methods()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');

        // Create a mock response without getStatusCode method
        $mockResponse = new class
        {
            public function __call($method, $args)
            {
                return null;
            }
        };

        $middleware->handle($request, fn ($req) => $mockResponse);

        $tracked = InboundRequest::first();
        $this->assertNull($tracked->status_code);
    }

    #[Test]
    public function it_handles_json_content_type()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/api/data', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['key' => 'value', 'password' => 'secret'])
        );

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $body = json_decode($tracked->request_body, true);

        $this->assertEquals('value', $body['key']);
        $this->assertEquals('***OMITTED***', $body['password']);
    }

    #[Test]
    public function it_handles_multipart_form_data()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/upload', 'POST', [
            'name' => 'John',
            'token' => 'abc123',
        ]);
        $request->headers->set('Content-Type', 'multipart/form-data');

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $body = json_decode($tracked->request_body, true);

        $this->assertEquals('John', $body['name']);
        $this->assertEquals('***OMITTED***', $body['token']);
    }

    #[Test]
    public function it_continues_tracking_even_if_exception_occurs()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/test', 'GET');

        try {
            $middleware->handle($request, function ($req) {
                throw new \Exception('Test exception');
            });
        } catch (\Exception $e) {
            // Exception expected
        }

        // Should still track the request even though handler threw exception
        $this->assertEquals(1, InboundRequest::count());
    }

    #[Test]
    public function it_handles_nested_sensitive_data()
    {
        $middleware = new TrackInboundRequest;
        $request = Request::create('/api/update', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'user' => [
                    'name' => 'John',
                    'auth' => [
                        'password' => 'secret123',
                        'token' => 'token123',
                    ],
                ],
                'settings' => [
                    'theme' => 'dark',
                ],
            ])
        );

        $middleware->handle($request, fn ($req) => new Response('OK', 200));

        $tracked = InboundRequest::first();
        $body = json_decode($tracked->request_body, true);

        $this->assertEquals('John', $body['user']['name']);
        $this->assertEquals('***OMITTED***', $body['user']['auth']['password']);
        $this->assertEquals('***OMITTED***', $body['user']['auth']['token']);
        $this->assertEquals('dark', $body['settings']['theme']);
    }
}
