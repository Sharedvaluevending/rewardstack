<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_has_unique_indexes_for_payment_intent_and_printful(): void
    {
        $indexes = DB::select("PRAGMA index_list('orders')");
        $indexNames = collect($indexes)->pluck('name')->all();

        $this->assertContains('orders_stripe_payment_intent_unique', $indexNames);
        $this->assertContains('orders_printful_order_id_unique', $indexNames);
    }

    public function test_webhook_events_has_unique_provider_event_index(): void
    {
        $indexes = DB::select("PRAGMA index_list('webhook_events')");
        $indexNames = collect($indexes)->pluck('name')->all();

        $this->assertContains('webhook_events_provider_event_unique', $indexNames);
    }
}

