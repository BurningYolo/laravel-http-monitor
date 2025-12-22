<?php

namespace Burningyolo\LaravelHttpMonitor\Tests\Unit;

use Burningyolo\LaravelHttpMonitor\Support\BodyProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(BodyProcessor::class)]
class BodyProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set default config values
        Config::set('request-tracker.max_body_size', 65536);
        Config::set('request-tracker.omit_body_fields', [
            'password',
            'token',
            'api_key',
            'credit_card',
        ]);
    }

    #[Test]
    public function it_returns_null_for_empty_body()
    {
        $result = BodyProcessor::process(null);
        $this->assertNull($result);

        $result = BodyProcessor::process('');
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_plain_text_unchanged_when_under_limit()
    {
        $body = 'This is a plain text body';
        $result = BodyProcessor::process($body);

        $this->assertEquals($body, $result);
    }

    #[Test]
    public function it_truncates_large_plain_text_body()
    {
        Config::set('request-tracker.max_body_size', 100);

        $body = str_repeat('a', 200);
        $result = BodyProcessor::process($body);

        $this->assertEquals(100, strlen($result) - strlen('... [truncated]'));
        $this->assertStringEndsWith('... [truncated]', $result);
    }

    #[Test]
    public function it_omits_sensitive_fields_from_json()
    {
        $body = json_encode([
            'username' => 'your mum',
            'password' => 'gottem',
            'email' => 'yourmum@example.com',
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('your mum', $decoded['username']);
        $this->assertEquals('***OMITTED***', $decoded['password']);
        $this->assertEquals('yourmum@example.com', $decoded['email']);
    }

    #[Test]
    public function it_omits_nested_sensitive_fields()
    {
        $body = json_encode([
            'user' => [
                'name' => 'John',
                'credentials' => [
                    'password' => 'secret123',
                    'api_key' => 'key-123',
                ],
            ],
            'settings' => [
                'theme' => 'dark',
            ],
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('John', $decoded['user']['name']);
        $this->assertEquals('***OMITTED***', $decoded['user']['credentials']['password']);
        $this->assertEquals('***OMITTED***', $decoded['user']['credentials']['api_key']);
        $this->assertEquals('dark', $decoded['settings']['theme']);
    }

    #[Test]
    public function it_is_case_insensitive_when_matching_fields()
    {
        $body = json_encode([
            'Password' => 'secret1',
            'PASSWORD' => 'secret2',
            'PaSsWoRd' => 'secret3',
            'user_password' => 'secret4',
            'API_KEY' => 'key123',
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('***OMITTED***', $decoded['Password']);
        $this->assertEquals('***OMITTED***', $decoded['PASSWORD']);
        $this->assertEquals('***OMITTED***', $decoded['PaSsWoRd']);
        $this->assertEquals('***OMITTED***', $decoded['user_password']);
        $this->assertEquals('***OMITTED***', $decoded['API_KEY']);
    }

    #[Test]
    public function it_matches_partial_field_names()
    {
        $body = json_encode([
            'user_password' => 'secret1',
            'new_password' => 'secret2',
            'password_confirmation' => 'secret3',
            'bearer_token' => 'token123',
            'api_key_primary' => 'key123',
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('***OMITTED***', $decoded['user_password']);
        $this->assertEquals('***OMITTED***', $decoded['new_password']);
        $this->assertEquals('***OMITTED***', $decoded['password_confirmation']);
        $this->assertEquals('***OMITTED***', $decoded['bearer_token']);
        $this->assertEquals('***OMITTED***', $decoded['api_key_primary']);
    }

    #[Test]
    public function it_handles_invalid_json_gracefully()
    {
        $body = '{invalid json content';
        $result = BodyProcessor::process($body);

        $this->assertEquals($body, $result);
    }

    #[Test]
    public function it_truncates_large_json_after_processing()
    {
        Config::set('request-tracker.max_body_size', 100);

        $largeData = [
            'username' => str_repeat('a', 50),
            'email' => str_repeat('b', 50),
            'data' => str_repeat('c', 50),
        ];

        $body = json_encode($largeData);
        $result = BodyProcessor::process($body);

        $this->assertLessThanOrEqual(100 + strlen('... [truncated]'), strlen($result));
        $this->assertStringEndsWith('... [truncated]', $result);
    }

    #[Test]
    public function it_processes_form_data_from_request_object()
    {
        $request = Request::create('/test', 'POST', [
            'username' => 'john',
            'password' => 'secret123',
            'email' => 'john@example.com',
        ]);

        $request->headers->set('Content-Type', 'application/x-www-form-urlencoded');

        $result = BodyProcessor::process($request->getContent(), $request);
        $decoded = json_decode($result, true);

        $this->assertEquals('john', $decoded['username']);
        $this->assertEquals('***OMITTED***', $decoded['password']);
        $this->assertEquals('john@example.com', $decoded['email']);
    }

    #[Test]
    public function it_processes_json_from_request_object()
    {
        $data = [
            'username' => 'john',
            'password' => 'secret123',
            'token' => 'abc123',
        ];

        $request = Request::create('/test', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $result = BodyProcessor::process($request->getContent(), $request);
        $decoded = json_decode($result, true);

        $this->assertEquals('john', $decoded['username']);
        $this->assertEquals('***OMITTED***', $decoded['password']);
        $this->assertEquals('***OMITTED***', $decoded['token']);
    }

    #[Test]
    public function it_handles_empty_json_object()
    {
        $body = json_encode([]);
        $result = BodyProcessor::process($body);

        $this->assertEquals('[]', $result);
    }

    #[Test]
    public function it_handles_json_with_numeric_keys()
    {
        $body = json_encode([
            'items' => [
                ['name' => 'Item 1', 'password' => 'secret'],
                ['name' => 'Item 2', 'api_key' => 'key123'],
            ],
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('Item 1', $decoded['items'][0]['name']);
        $this->assertEquals('***OMITTED***', $decoded['items'][0]['password']);
        $this->assertEquals('Item 2', $decoded['items'][1]['name']);
        $this->assertEquals('***OMITTED***', $decoded['items'][1]['api_key']);
    }

    #[Test]
    public function it_preserves_non_string_values()
    {
        $body = json_encode([
            'username' => 'john',
            'age' => 25,
            'active' => true,
            'score' => 99.5,
            'tags' => ['admin', 'user'],
            'password' => 'secret123',
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('john', $decoded['username']);
        $this->assertEquals(25, $decoded['age']);
        $this->assertTrue($decoded['active']);
        $this->assertEquals(99.5, $decoded['score']);
        $this->assertEquals(['admin', 'user'], $decoded['tags']);
        $this->assertEquals('***OMITTED***', $decoded['password']);
    }

    #[Test]
    public function it_uses_custom_omit_fields_from_config()
    {
        Config::set('request-tracker.omit_body_fields', [
            'ssn',
            'tax_id',
        ]);

        $body = json_encode([
            'name' => 'John',
            'password' => 'secret123', // This should NOT be omitted now
            'ssn' => '123-45-6789',
            'tax_id' => 'TAX123',
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('John', $decoded['name']);
        $this->assertEquals('secret123', $decoded['password']); // Not in custom list
        $this->assertEquals('***OMITTED***', $decoded['ssn']);
        $this->assertEquals('***OMITTED***', $decoded['tax_id']);
    }

    #[Test]
    public function it_respects_custom_max_body_size()
    {
        Config::set('request-tracker.max_body_size', 50);

        $body = str_repeat('x', 100);
        $result = BodyProcessor::process($body);

        $this->assertStringEndsWith('... [truncated]', $result);
        $this->assertEquals(50, strlen($result) - strlen('... [truncated]'));
    }

    #[Test]
    public function it_handles_deeply_nested_structures()
    {
        $body = json_encode([
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'password' => 'deep_secret',
                            'username' => 'john',
                        ],
                    ],
                ],
            ],
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('***OMITTED***', $decoded['level1']['level2']['level3']['level4']['password']);
        $this->assertEquals('john', $decoded['level1']['level2']['level3']['level4']['username']);
    }

    #[Test]
    public function it_handles_xml_content_as_plain_text()
    {
        $body = '<?xml version="1.0"?><root><user>John</user></root>';
        $result = BodyProcessor::process($body);

        $this->assertEquals($body, $result);
    }

    #[Test]
    public function it_handles_multipart_form_data()
    {
        $request = Request::create('/test', 'POST', [
            'name' => 'John',
            'password' => 'secret',
        ]);

        $request->headers->set('Content-Type', 'multipart/form-data');

        $result = BodyProcessor::process($request->getContent(), $request);
        $decoded = json_decode($result, true);

        $this->assertEquals('John', $decoded['name']);
        $this->assertEquals('***OMITTED***', $decoded['password']);
    }

    #[Test]
    public function get_omitted_fields_returns_config_value()
    {
        Config::set('request-tracker.omit_body_fields', ['password', 'token']);

        $fields = BodyProcessor::getOmittedFields();

        $this->assertEquals(['password', 'token'], $fields);
    }

    #[Test]
    public function get_max_body_size_returns_config_value()
    {
        Config::set('request-tracker.max_body_size', 32768);

        $size = BodyProcessor::getMaxBodySize();

        $this->assertEquals(32768, $size);
    }

    #[Test]
    public function it_handles_unicode_characters()
    {
        $body = json_encode([
            'username' => 'ジョン',
            'password' => 'パスワード',
            'message' => '你好世界',
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('ジョン', $decoded['username']);
        $this->assertEquals('***OMITTED***', $decoded['password']);
        $this->assertEquals('你好世界', $decoded['message']);
    }

    #[Test]
    public function it_handles_special_characters_in_values()
    {
        $body = json_encode([
            'username' => 'john@example.com',
            'password' => 'p@$$w0rd!#%',
            'url' => 'https://example.com/path?query=1&other=2',
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('john@example.com', $decoded['username']);
        $this->assertEquals('***OMITTED***', $decoded['password']);
        $this->assertEquals('https://example.com/path?query=1&other=2', $decoded['url']);
    }

    #[Test]
    public function it_handles_null_values_in_json()
    {
        $body = json_encode([
            'username' => 'john',
            'password' => null,
            'email' => null,
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        $this->assertEquals('john', $decoded['username']);
        $this->assertEquals('***OMITTED***', $decoded['password']); // Still omits even if null
        $this->assertNull($decoded['email']);
    }

    #[Test]
    public function it_does_not_omit_integer_keys()
    {
        $body = json_encode([
            'users' => [
                ['name' => 'John', 'password' => 'secret1'],
                ['name' => 'Jane', 'password' => 'secret2'],
            ],
        ]);

        $result = BodyProcessor::process($body);
        $decoded = json_decode($result, true);

        // Numeric array keys should not be checked for omission
        $this->assertIsArray($decoded['users']);
        $this->assertCount(2, $decoded['users']);
    }
}
