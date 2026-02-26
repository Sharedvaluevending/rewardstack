<?php

namespace Tests\Unit;

use App\Services\DeepSeekAIService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekAIServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.deepseek.api_key' => '']);
        config(['services.deepseek.api_url' => 'https://api.deepseek.com/v1/chat/completions']);
    }

    public function test_is_configured_returns_false_when_api_key_empty(): void
    {
        config(['services.deepseek.api_key' => '']);
        $service = new DeepSeekAIService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_when_api_key_set(): void
    {
        config(['services.deepseek.api_key' => 'sk-test-key']);
        $service = new DeepSeekAIService();
        $this->assertTrue($service->isConfigured());
    }

    public function test_generate_insight_throws_when_not_configured(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DeepSeek API is not configured');
        $service = new DeepSeekAIService();
        $service->generateInsight('Summarize this.');
    }

    public function test_generate_summary_throws_when_not_configured(): void
    {
        $service = new DeepSeekAIService();
        $this->expectException(\RuntimeException::class);
        $service->generateSummary([]);
    }

    public function test_generate_insight_returns_success_when_configured_and_api_succeeds(): void
    {
        config(['services.deepseek.api_key' => 'sk-test']);
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Summary text here.']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        $service = new DeepSeekAIService();
        $result = $service->generateInsight('Summarize.', [], false);

        $this->assertTrue($result['success']);
        $this->assertSame('Summary text here.', $result['content']);
        $this->assertArrayHasKey('usage', $result);
    }

    public function test_generate_insight_returns_failure_on_api_error(): void
    {
        config(['services.deepseek.api_key' => 'sk-test']);
        Http::fake(['*' => Http::response([], 500)]);

        $service = new DeepSeekAIService();
        $result = $service->generateInsight('Summarize.', [], false);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertNull($result['content']);
    }

    public function test_generate_summary_returns_content_when_configured_and_api_succeeds(): void
    {
        config(['services.deepseek.api_key' => 'sk-test']);
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Business is doing well.']],
                ],
                'usage' => [],
            ], 200),
        ]);

        $service = new DeepSeekAIService();
        $summary = $service->generateSummary([], 'basic');

        $this->assertSame('Business is doing well.', $summary);
    }
}
