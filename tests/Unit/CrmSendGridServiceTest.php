<?php

namespace Tests\Unit;

use App\Services\CrmSendGridService;
use Tests\TestCase;

class CrmSendGridServiceTest extends TestCase
{
    public function test_is_configured_returns_false_when_api_key_empty(): void
    {
        config(['services.sendgrid.api_key' => '']);

        $service = new CrmSendGridService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_when_api_key_set(): void
    {
        config(['services.sendgrid.api_key' => 'SG.test-key']);

        $service = new CrmSendGridService();
        $this->assertTrue($service->isConfigured());
    }
}
