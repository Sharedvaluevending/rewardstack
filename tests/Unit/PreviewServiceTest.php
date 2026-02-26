<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Business;
use App\Services\PreviewService;
use App\Services\PrintfulService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generate_preview_throws_when_no_custom_elements(): void
    {
        $printful = Mockery::mock(PrintfulService::class);
        $service = new PreviewService($printful);
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 19.99,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No custom elements to overlay');

        $service->generatePreview($product, null, null, null, []);
    }

    public function test_constructor_accepts_printful_service(): void
    {
        $printful = Mockery::mock(PrintfulService::class);
        $service = new PreviewService($printful);
        $this->assertInstanceOf(PreviewService::class, $service);
    }
}
