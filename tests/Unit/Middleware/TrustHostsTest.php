<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\TrustHosts;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    public function test_hosts_returns_array(): void
    {
        $middleware = new TrustHosts(app());
        $hosts = $middleware->hosts();
        $this->assertIsArray($hosts);
        $this->assertNotEmpty($hosts);
    }
}
