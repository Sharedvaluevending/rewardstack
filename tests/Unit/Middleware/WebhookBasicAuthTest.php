<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\WebhookBasicAuth;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebhookBasicAuthTest extends TestCase
{
    public function test_allows_request_when_token_matches(): void
    {
        config(['services.printful.webhook_token' => 'secret123']);
        $middleware = new WebhookBasicAuth;

        $request = Request::create('/webhooks/printful', 'POST');
        $request->headers->set('X-Webhook-Token', 'secret123');

        $next = fn ($r) => response('ok');
        $response = $middleware->handle($request, $next, 'printful');

        $this->assertEquals('ok', $response->getContent());
    }

    public function test_allows_request_when_not_configured_in_non_production(): void
    {
        config(['services.printful.webhook_username' => '', 'services.printful.webhook_password' => '', 'services.printful.webhook_token' => '']);
        $this->app['env'] = 'local';
        $middleware = new WebhookBasicAuth;

        $request = Request::create('/webhooks/printful', 'POST');
        $next = fn ($r) => response('ok');
        $response = $middleware->handle($request, $next, 'printful');

        $this->assertEquals('ok', $response->getContent());
    }

    public function test_returns_401_when_basic_auth_required_but_not_provided(): void
    {
        config([
            'services.printful.webhook_username' => 'user',
            'services.printful.webhook_password' => 'pass',
            'services.printful.webhook_token' => '',
        ]);
        $middleware = new WebhookBasicAuth;

        $request = Request::create('/webhooks/printful', 'POST');
        $next = fn ($r) => response('ok');
        $response = $middleware->handle($request, $next, 'printful');

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"error":"Unauthorized"}', $response->getContent());
    }

    public function test_returns_401_when_basic_auth_wrong_credentials(): void
    {
        config([
            'services.printful.webhook_username' => 'user',
            'services.printful.webhook_password' => 'pass',
            'services.printful.webhook_token' => '',
        ]);
        $middleware = new WebhookBasicAuth;

        $request = Request::create('/webhooks/printful', 'POST');
        $request->headers->set('Authorization', 'Basic ' . base64_encode('wrong:wrong'));
        $next = fn ($r) => response('ok');
        $response = $middleware->handle($request, $next, 'printful');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_allows_request_when_basic_auth_correct(): void
    {
        config([
            'services.printful.webhook_username' => 'user',
            'services.printful.webhook_password' => 'pass',
            'services.printful.webhook_token' => '',
        ]);
        $middleware = new WebhookBasicAuth;

        $request = Request::create('/webhooks/printful', 'POST', [], [], [], [
            'PHP_AUTH_USER' => 'user',
            'PHP_AUTH_PW' => 'pass',
        ]);
        $next = fn ($r) => response('ok');
        $response = $middleware->handle($request, $next, 'printful');

        $this->assertEquals('ok', $response->getContent());
    }
}
