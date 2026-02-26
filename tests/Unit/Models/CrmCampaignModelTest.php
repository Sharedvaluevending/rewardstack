<?php

namespace Tests\Unit\Models;

use App\Models\CrmCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmCampaignModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('draft', CrmCampaign::STATUS_DRAFT);
        $this->assertSame('scheduled', CrmCampaign::STATUS_SCHEDULED);
        $this->assertSame('sending', CrmCampaign::STATUS_SENDING);
        $this->assertSame('sent', CrmCampaign::STATUS_SENT);
        $this->assertSame('cancelled', CrmCampaign::STATUS_CANCELLED);
    }
}
