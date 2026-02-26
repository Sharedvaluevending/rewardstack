<?php

namespace Tests\Unit;

use App\Services\AveryDpoService;
use Tests\TestCase;

class AveryDpoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['printkits.merge_column' => 'qr_url']);
        config(['printkits.sizes' => [
            'round_2in' => [
                'name' => '2" Round',
                'bundle_filename' => 'round-2in.avery',
            ],
        ]]);
        config(['printkits.bundle_base_url' => 'https://example.com/bundles']);
        config(['avery.merge_direct_url' => 'https://services.print.avery.com/merge']);
        config(['avery.consumer' => 'test-consumer']);
        config(['avery.deployment_id' => 'US_en']);
        config(['avery.profile' => 'WePrint']);
        config(['avery.back_url' => null]);
    }

    public function test_merge_column_returns_config_value(): void
    {
        config(['printkits.merge_column' => 'custom_column']);
        $service = new AveryDpoService();
        $this->assertSame('custom_column', $service->mergeColumn());
    }

    public function test_bundle_url_for_size_returns_full_url(): void
    {
        $service = new AveryDpoService();
        $url = $service->bundleUrlForSize('round_2in');
        $this->assertSame('https://example.com/bundles/round-2in.avery', $url);
    }

    public function test_bundle_url_for_size_uses_app_url_when_bundle_base_empty(): void
    {
        config(['printkits.bundle_base_url' => '']);
        config(['app.url' => 'https://app.test']);
        $service = new AveryDpoService();
        $url = $service->bundleUrlForSize('round_2in');
        $this->assertSame('https://app.test/avery/bundles/round-2in.avery', $url);
    }

    public function test_bundle_url_for_size_throws_for_unknown_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown sticker size: invalid');
        $service = new AveryDpoService();
        $service->bundleUrlForSize('invalid');
    }

    public function test_build_single_column_csv_includes_header_and_rows(): void
    {
        $service = new AveryDpoService();
        $csv = $service->buildSingleColumnCsv('qr_url', ['https://a.com', 'https://b.com']);
        $this->assertStringContainsString('qr_url', $csv);
        $this->assertStringContainsString('https://a.com', $csv);
        $this->assertStringContainsString('https://b.com', $csv);
        $lines = explode("\n", $csv);
        $this->assertCount(3, $lines);
    }

    public function test_build_single_column_csv_escapes_commas(): void
    {
        $service = new AveryDpoService();
        $csv = $service->buildSingleColumnCsv('col', ['a,b']);
        $this->assertStringContainsString('"a,b"', $csv);
    }

    public function test_build_csv_supports_multiple_columns(): void
    {
        $service = new AveryDpoService();
        $csv = $service->buildCsv([
            [
                'qr_url' => 'https://example.com/s/abc',
                'qr_image_url' => 'https://example.com/storage/qr1.png',
            ],
        ], ['qr_url', 'qr_image_url']);

        $this->assertStringContainsString('qr_url,qr_image_url', $csv);
        $this->assertStringContainsString('https://example.com/s/abc', $csv);
        $this->assertStringContainsString('https://example.com/storage/qr1.png', $csv);
    }

    public function test_direct_merge_action_url_includes_query_params(): void
    {
        $service = new AveryDpoService();
        $url = $service->directMergeActionUrl();
        $this->assertStringStartsWith('https://services.print.avery.com/merge', $url);
        $this->assertStringContainsString('deploymentId=US_en', $url);
        $this->assertStringContainsString('consumer=test-consumer', $url);
        $this->assertStringContainsString('profile=WePrint', $url);
    }

    public function test_direct_merge_action_url_includes_back_url_when_set(): void
    {
        config(['avery.back_url' => 'https://back.example.com']);
        $service = new AveryDpoService();
        $url = $service->directMergeActionUrl();
        $this->assertStringContainsString('backUrl=', $url);
    }

    public function test_build_auto_post_html_contains_action_and_hidden_fields(): void
    {
        $service = new AveryDpoService();
        $html = $service->buildAutoPostHtml('https://avery.com/merge', [
            'mergeDataFormat' => 'csv',
            'mergeData' => 'url1,url2',
        ]);
        $this->assertStringContainsString('action="https://avery.com/merge"', $html);
        $this->assertStringContainsString('name="mergeDataFormat"', $html);
        $this->assertStringContainsString('value="csv"', $html);
        $this->assertStringContainsString('name="mergeData"', $html);
        $this->assertStringContainsString('Opening Avery Design & Print', $html);
        $this->assertStringContainsString('<button type="submit" class="btn">Continue to Avery</button>', $html);
        $this->assertStringContainsString('<button type="submit" class="btn" formtarget="_blank"', $html);
        $this->assertStringContainsString('This order is already paid. You can retry this step anytime from your Print Kit orders.', $html);
        $this->assertStringNotContainsString('<a class="btn" href="#"', $html);
    }
}
