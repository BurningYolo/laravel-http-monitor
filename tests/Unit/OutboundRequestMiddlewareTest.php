<?php

namespace Burningyolo\LaravelHttpMonitor\Tests\Feature;

use Burningyolo\LaravelHttpMonitor\Http\OutboundRequestMiddleware;
use Burningyolo\LaravelHttpMonitor\Models\OutboundRequest;
use Burningyolo\LaravelHttpMonitor\Models\TrackedIp;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;

class OutboundRequestMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Set default config
        Config::set('request-tracker.enabled', true);
        Config::set('request-tracker.track_outbound', true);
        Config::set('request-tracker.store_body', true);
        Config::set('request-tracker.store_headers', true);
        Config::set('request-tracker.max_body_size', 65536);
        Config::set('request-tracker.omit_body_fields', ['password', 'api_key']);
        Config::set('request-tracker.excluded_outbound_hosts', []);
    }

    protected function createMockClient(array $responses = []): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(OutboundRequestMiddleware::handle());

        return new Client(['handler' => $handlerStack]);
    }

    /** @test */
    public function it_tracks_outbound_requests()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], json_encode(['status' => 'success'])),
        ]);

        $response = $client->get('https://api.example.com/users');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, OutboundRequest::count());

        $tracked = OutboundRequest::first();
        $this->assertEquals('GET', $tracked->method);
        $this->assertEquals('api.example.com', $tracked->host);
        $this->assertStringContainsString('/users', $tracked->url);
    }

    /** @test */
    public function it_does_not_track_when_disabled()
    {
        Config::set('request-tracker.enabled', false);

        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $client->get('https://api.example.com/test');

        $this->assertEquals(0, OutboundRequest::count());
    }

    /** @test */
    public function it_does_not_track_outbound_when_disabled()
    {
        Config::set('request-tracker.track_outbound', false);

        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $client->get('https://api.example.com/test');

        $this->assertEquals(0, OutboundRequest::count());
    }

    /** @test */
    public function it_excludes_configured_hosts()
    {
        Config::set('request-tracker.excluded_outbound_hosts', ['localhost', 'internal-api.com']);

        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
            new GuzzleResponse(200, [], 'OK'),
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $client->get('http://localhost:8000/test');
        $client->get('https://internal-api.com/data');
        $client->get('https://external-api.com/data');

        $this->assertEquals(1, OutboundRequest::count());
        $this->assertEquals('external-api.com', OutboundRequest::first()->host);
    }

    /** @test */
    public function it_omits_sensitive_fields_from_request_body()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], json_encode(['success' => true])),
        ]);

        $client->post('https://api.example.com/login', [
            'json' => [
                'username' => 'john',
                'password' => 'secret123',
                'email' => 'john@example.com',
            ],
        ]);

        $tracked = OutboundRequest::first();
        $body = json_decode($tracked->request_body, true);

        $this->assertEquals('john', $body['username']);
        $this->assertEquals('***OMITTED***', $body['password']);
        $this->assertEquals('john@example.com', $body['email']);
    }

    /** @test */
    public function it_omits_sensitive_fields_from_response_body()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], json_encode([
                'user' => 'john',
                'api_key' => 'secret-key-123',
                'email' => 'john@example.com',
            ])),
        ]);

        $client->get('https://api.example.com/user');

        $tracked = OutboundRequest::first();
        $body = json_decode($tracked->response_body, true);

        $this->assertEquals('john', $body['user']);
        $this->assertEquals('***OMITTED***', $body['api_key']);
        $this->assertEquals('john@example.com', $body['email']);
    }

    /** @test */
    public function it_truncates_large_request_bodies()
    {
        Config::set('request-tracker.max_body_size', 100);

        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $largeData = str_repeat('a', 200);
        $client->post('https://api.example.com/upload', [
            'body' => $largeData,
        ]);

        $tracked = OutboundRequest::first();
        $this->assertStringEndsWith('... [truncated]', $tracked->request_body);
        $this->assertLessThanOrEqual(100 + strlen('... [truncated]'), strlen($tracked->request_body));
    }

    /** @test */
    public function it_truncates_large_response_bodies()
    {
        Config::set('request-tracker.max_body_size', 100);

        $largeResponse = str_repeat('x', 200);
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], $largeResponse),
        ]);

        $client->get('https://api.example.com/large');

        $tracked = OutboundRequest::first();
        $this->assertStringEndsWith('... [truncated]', $tracked->response_body);
    }

    /** @test */
    public function it_tracks_status_code()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(404, [], 'Not Found'),
        ]);

        $response = $client->get('https://api.example.com/missing', [
            'http_errors' => false,
        ]);

        $tracked = OutboundRequest::first();
        $this->assertEquals(404, $tracked->status_code);
    }

    /** @test */
    public function it_tracks_request_duration()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $client->get('https://api.example.com/test');

        $tracked = OutboundRequest::first();
        $this->assertGreaterThan(0, $tracked->duration_ms);
    }

    /** @test */
    public function it_marks_failed_requests()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(500, [], 'Internal Server Error'),
        ]);

        try {
            $client->get('https://api.example.com/error');
        } catch (\Exception $e) {
            // Expected to throw
        }

        $tracked = OutboundRequest::first();
        $this->assertEquals(500, $tracked->status_code);
    }

    /** @test */
    public function it_handles_network_errors()
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'https://api.example.com/test')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(OutboundRequestMiddleware::handle());
        $client = new Client(['handler' => $handlerStack]);

        try {
            $client->get('https://api.example.com/test');
        } catch (\Exception $e) {
            // Expected
        }

        $tracked = OutboundRequest::first();
        $this->assertFalse($tracked->successful);
        $this->assertNotNull($tracked->error_message);
        $this->assertStringContainsString('Connection refused', $tracked->error_message);
    }

    /** @test */
    public function it_stores_headers_when_enabled()
    {
        Config::set('request-tracker.store_headers', true);

        $client = $this->createMockClient([
            new GuzzleResponse(200, ['X-Custom' => 'value'], 'OK'),
        ]);

        $client->get('https://api.example.com/test', [
            'headers' => ['User-Agent' => 'TestBot/1.0'],
        ]);

        $tracked = OutboundRequest::first();
        $this->assertNotNull($tracked->headers);
        $this->assertNotNull($tracked->response_headers);
    }

    /** @test */
    public function it_does_not_store_headers_when_disabled()
    {
        Config::set('request-tracker.store_headers', false);

        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $client->get('https://api.example.com/test');

        $tracked = OutboundRequest::first();
        $this->assertNull($tracked->headers);
        $this->assertNull($tracked->response_headers);
    }

    /** @test */
    public function it_stores_body_when_enabled()
    {
        Config::set('request-tracker.store_body', true);

        $client = $this->createMockClient([
            new GuzzleResponse(200, [], json_encode(['data' => 'test'])),
        ]);

        $client->post('https://api.example.com/data', [
            'json' => ['key' => 'value'],
        ]);

        $tracked = OutboundRequest::first();
        $this->assertNotNull($tracked->request_body);
        $this->assertNotNull($tracked->response_body);
    }

    /** @test */
    public function it_does_not_store_body_when_disabled()
    {
        Config::set('request-tracker.store_body', false);

        $client = $this->createMockClient([
            new GuzzleResponse(200, [], json_encode(['data' => 'test'])),
        ]);

        $client->post('https://api.example.com/data', [
            'json' => ['key' => 'value'],
        ]);

        $tracked = OutboundRequest::first();
        $this->assertNull($tracked->request_body);
        $this->assertNull($tracked->response_body);
    }

    /** @test */
    public function it_tracks_post_requests_with_json()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(201, [], json_encode(['id' => 1])),
        ]);

        $client->post('https://api.example.com/users', [
            'json' => [
                'name' => 'John',
                'password' => 'secret',
            ],
        ]);

        $tracked = OutboundRequest::first();
        $this->assertEquals('POST', $tracked->method);
        $this->assertEquals(201, $tracked->status_code);

        $body = json_decode($tracked->request_body, true);
        $this->assertEquals('John', $body['name']);
        $this->assertEquals('***OMITTED***', $body['password']);
    }

    /** @test */
    public function it_tracks_put_requests()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], json_encode(['updated' => true])),
        ]);

        $client->put('https://api.example.com/users/1', [
            'json' => ['name' => 'Jane'],
        ]);

        $tracked = OutboundRequest::first();
        $this->assertEquals('PUT', $tracked->method);
    }

    /** @test */
    public function it_tracks_delete_requests()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(204, [], ''),
        ]);

        $client->delete('https://api.example.com/users/1');

        $tracked = OutboundRequest::first();
        $this->assertEquals('DELETE', $tracked->method);
        $this->assertEquals(204, $tracked->status_code);
    }

    /** @test */
    public function it_tracks_query_strings()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $client->get('https://api.example.com/users?page=1&limit=10');

        $tracked = OutboundRequest::first();
        $this->assertEquals('page=1&limit=10', $tracked->query_string);
    }

    /** @test */
    public function it_resolves_host_ip_address()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $client->get('https://api.example.com/test');

        $tracked = OutboundRequest::first();

        if ($tracked->tracked_ip_id) {
            $trackedIp = TrackedIp::find($tracked->tracked_ip_id);
            $this->assertNotNull($trackedIp);
            $this->assertNotEmpty($trackedIp->ip);
        }
    }

    /** @test */
    public function it_handles_nested_sensitive_data()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], json_encode(['success' => true])),
        ]);

        $client->post('https://api.example.com/auth', [
            'json' => [
                'user' => [
                    'name' => 'John',
                    'credentials' => [
                        'password' => 'secret123',
                        'api_key' => 'key-abc-123',
                    ],
                ],
            ],
        ]);

        $tracked = OutboundRequest::first();
        $body = json_decode($tracked->request_body, true);

        $this->assertEquals('John', $body['user']['name']);
        $this->assertEquals('***OMITTED***', $body['user']['credentials']['password']);
        $this->assertEquals('***OMITTED***', $body['user']['credentials']['api_key']);
    }

    /** @test */
    public function it_handles_plain_text_bodies()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'Plain text response'),
        ]);

        $client->post('https://api.example.com/webhook', [
            'body' => 'Plain text data',
        ]);

        $tracked = OutboundRequest::first();
        $this->assertEquals('Plain text data', $tracked->request_body);
        $this->assertEquals('Plain text response', $tracked->response_body);
    }

    /** @test */
    public function it_handles_empty_response_body()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(204, [], ''),
        ]);

        $client->delete('https://api.example.com/resource/1');

        $tracked = OutboundRequest::first();
        $this->assertNull($tracked->response_body);
    }

    /** @test */
    public function it_tracks_path_correctly()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'OK'),
        ]);

        $client->get('https://api.example.com/v1/users/123/profile');

        $tracked = OutboundRequest::first();
        $this->assertEquals('/v1/users/123/profile', $tracked->path);
    }

    /** @test */
    public function it_handles_concurrent_requests()
    {
        $client = $this->createMockClient([
            new GuzzleResponse(200, [], 'Response 1'),
            new GuzzleResponse(200, [], 'Response 2'),
            new GuzzleResponse(200, [], 'Response 3'),
        ]);

        $client->get('https://api.example.com/endpoint1');
        $client->get('https://api.example.com/endpoint2');
        $client->get('https://api.example.com/endpoint3');

        $this->assertEquals(3, OutboundRequest::count());
    }
}
