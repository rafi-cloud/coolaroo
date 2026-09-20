<?php

namespace Tests\Feature\Services;

use App\Exceptions\AiUnavailableException;
use App\Services\AiMenuService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiMenuServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai.api_key' => 'test-key']);
    }

    public function test_chat_returns_the_decoded_response_on_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'hello']]],
            ], 200),
        ]);

        $result = (new AiMenuService())->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('hello', $result['choices'][0]['message']['content']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://models.github.ai/inference/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === config('services.ai.model')
                && $request['messages'] === [['role' => 'user', 'content' => 'hi']];
        });
    }

    public function test_chat_throws_when_api_key_is_not_configured(): void
    {
        config(['services.ai.api_key' => null]);
        Http::fake();

        $this->expectException(AiUnavailableException::class);

        (new AiMenuService())->chat([['role' => 'user', 'content' => 'hi']]);

        Http::assertNothingSent();
    }

    public function test_chat_throws_busy_exception_on_provider_rate_limit(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->expectException(AiUnavailableException::class);

        (new AiMenuService())->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_throws_busy_exception_on_provider_server_error(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->expectException(AiUnavailableException::class);

        (new AiMenuService())->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_throws_busy_exception_on_timeout(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->expectException(AiUnavailableException::class);

        (new AiMenuService())->chat([['role' => 'user', 'content' => 'hi']]);
    }
}
