<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Some runtime env checks in the app (rate limiting, fallbacks) depend on environment.
        // In this repo, tests may run with APP_ENV not reliably set to "testing" (e.g. .env=production).
        // Force it here so tests are deterministic.
        $this->app->detectEnvironment(fn () => 'testing');
        config()->set('app.env', 'testing');

        // Override config that may come from dev .env (QUEUE_CONNECTION=redis, CACHE_DRIVER=redis, etc.)
        // so tests run consistently in dev folders without a dedicated .env.testing.
        config()->set('queue.default', 'sync');
        config()->set('cache.default', 'array');
        config()->set('mail.default', 'array');
        config()->set('session.driver', 'array');
    }
}
