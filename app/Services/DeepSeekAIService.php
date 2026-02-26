<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DeepSeekAIService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected int $timeout;
    protected int $advancedTimeout;

    public function __construct()
    {
        $this->apiKey = config('services.deepseek.api_key');
        $this->apiUrl = config('services.deepseek.api_url', 'https://api.deepseek.com/v1/chat/completions');
        $this->timeout = 30;
        $this->advancedTimeout = 60; // Longer timeout for advanced insights
    }

    /**
     * Check if DeepSeek is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate insight from prompt and context data
     */
    public function generateInsight(string $prompt, array $contextData = [], bool $isAdvanced = false): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('DeepSeek API is not configured.');
        }

        $systemPrompt = "You are a business analytics AI assistant specializing in QR code marketing, customer engagement, and promotional campaigns. Provide clear, actionable insights based on the data provided. Be concise but thorough.";

        // Limit context data size for large datasets
        $contextData = $this->limitContextData($contextData);

        $fullPrompt = $prompt;
        if (!empty($contextData)) {
            $fullPrompt .= "\n\nContext Data:\n" . json_encode($contextData, JSON_PRETTY_PRINT);
        }

        $timeout = $isAdvanced ? $this->advancedTimeout : $this->timeout;

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $fullPrompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                $usage = $data['usage'] ?? [];

                // Track cumulative token usage and estimated cost for the current month.
                // DeepSeek pricing is very low (~$0.14/1M input, $0.28/1M output tokens),
                // but monitoring prevents surprises.
                $this->trackUsage($usage);

                return [
                    'success' => true,
                    'content' => $content,
                    'usage' => $usage,
                ];
            }

            Log::error('DeepSeek API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'API request failed',
                'content' => null,
            ];
        } catch (\Exception $e) {
            Log::error('DeepSeek API exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'content' => null,
            ];
        }
    }

    /**
     * Generate summary of business performance
     */
    public function generateSummary(array $data, string $type = 'basic'): string
    {
        $prompt = match ($type) {
            'basic' => "Provide a 2-3 sentence summary of this business's performance. Highlight key metrics and overall health.",
            'advanced' => "Provide a comprehensive summary of this business's performance. Include trends, opportunities, and areas of concern.",
            default => "Summarize this business's performance.",
        };

        $isAdvanced = $type === 'advanced';
        $result = $this->generateInsight($prompt, $data, $isAdvanced);
        
        return $result['content'] ?? 'Unable to generate summary at this time.';
    }

    /**
     * Generate predictions based on historical data
     */
    public function generatePrediction(array $historicalData, string $metric): array
    {
        // Limit historical data to last 30 days for predictions (sample if needed)
        $limitedData = $this->limitTimeSeriesData($historicalData, 30);

        $prompt = "Based on the historical data provided, predict the {$metric} for the next 30 days. Include:
1. Predicted values
2. Confidence level
3. Key factors influencing the prediction
4. Potential risks or opportunities

Format the response as JSON with keys: predictions, confidence, factors, risks.";

        $result = $this->generateInsight($prompt, ['historical_data' => $limitedData, 'metric' => $metric], true);
        
        if ($result['success'] && $result['content']) {
            // Try to parse JSON from response
            $jsonMatch = [];
            if (preg_match('/\{.*\}/s', $result['content'], $jsonMatch)) {
                $parsed = json_decode($jsonMatch[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return array_merge(['success' => true], $parsed);
                }
            }
            
            return [
                'success' => true,
                'raw_content' => $result['content'],
                'predictions' => [],
                'confidence' => 'medium',
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to generate prediction',
        ];
    }

    /**
     * Generate actionable recommendations
     */
    public function generateRecommendations(array $businessData): array
    {
        $prompt = "Analyze this business's data and provide 5-7 actionable recommendations. For each recommendation, include:
1. Title
2. Description
3. Estimated impact/ROI
4. Priority (high/medium/low)
5. Implementation steps

Format as JSON array with keys: title, description, impact, priority, steps.";

        $result = $this->generateInsight($prompt, $businessData, true);
        
        if ($result['success'] && $result['content']) {
            $jsonMatch = [];
            if (preg_match('/\[.*\]/s', $result['content'], $jsonMatch)) {
                $parsed = json_decode($jsonMatch[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    return [
                        'success' => true,
                        'recommendations' => $parsed,
                    ];
                }
            }
            
            // Fallback: parse text format
            return [
                'success' => true,
                'raw_content' => $result['content'],
                'recommendations' => $this->parseRecommendationsFromText($result['content']),
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to generate recommendations',
            'recommendations' => [],
        ];
    }

    /**
     * Analyze customer segments
     */
    public function analyzeCustomerSegments(array $userData): array
    {
        // Limit customer data to top 100 customers for analysis
        $limitedData = array_slice($userData, 0, 100);

        $prompt = "Analyze customer behavior data and identify distinct segments. For each segment, provide:
1. Segment name
2. Characteristics
3. Size (percentage)
4. Engagement level
5. Recommendations for this segment

Format as JSON array with keys: name, characteristics, size_percent, engagement_level, recommendations.";

        $result = $this->generateInsight($prompt, ['customer_data' => $limitedData], true);
        
        if ($result['success'] && $result['content']) {
            $jsonMatch = [];
            if (preg_match('/\[.*\]/s', $result['content'], $jsonMatch)) {
                $parsed = json_decode($jsonMatch[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    return [
                        'success' => true,
                        'segments' => $parsed,
                    ];
                }
            }
            
            return [
                'success' => true,
                'raw_content' => $result['content'],
                'segments' => [],
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to analyze segments',
            'segments' => [],
        ];
    }

    /**
     * Optimize promotions based on performance data
     */
    public function optimizePromotions(array $promotionData): array
    {
        // Limit to top 20 promotions
        $limitedData = array_slice($promotionData, 0, 20);

        $prompt = "Analyze promotion performance data and provide optimization suggestions. For each suggestion, include:
1. Promotion name/type
2. Current performance
3. Optimization opportunity
4. Expected improvement
5. Implementation steps

Format as JSON array with keys: promotion, current_performance, opportunity, expected_improvement, steps.";

        $result = $this->generateInsight($prompt, ['promotion_data' => $limitedData], true);
        
        if ($result['success'] && $result['content']) {
            $jsonMatch = [];
            if (preg_match('/\[.*\]/s', $result['content'], $jsonMatch)) {
                $parsed = json_decode($jsonMatch[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    return [
                        'success' => true,
                        'optimizations' => $parsed,
                    ];
                }
            }
            
            return [
                'success' => true,
                'raw_content' => $result['content'],
                'optimizations' => [],
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to optimize promotions',
            'optimizations' => [],
        ];
    }

    /**
     * Limit context data size to prevent timeout
     */
    protected function limitContextData(array $data): array
    {
        // Limit scans_over_time to last 30 days
        if (isset($data['scans_over_time']) && is_array($data['scans_over_time'])) {
            $data['scans_over_time'] = $this->limitTimeSeriesData($data['scans_over_time'], 30);
        }

        // Limit redemptions_over_time to last 30 days
        if (isset($data['redemptions_over_time']) && is_array($data['redemptions_over_time'])) {
            $data['redemptions_over_time'] = $this->limitTimeSeriesData($data['redemptions_over_time'], 30);
        }

        // Limit customer_data to top 100
        if (isset($data['customer_data']) && is_array($data['customer_data'])) {
            $data['customer_data'] = array_slice($data['customer_data'], 0, 100);
        }

        // Limit promotions to top 20
        if (isset($data['promotions']) && is_array($data['promotions'])) {
            $data['promotions'] = array_slice($data['promotions'], 0, 20);
        }

        return $data;
    }

    /**
     * Limit time series data to specified number of days
     */
    protected function limitTimeSeriesData(array $data, int $maxDays): array
    {
        if (count($data) <= $maxDays) {
            return $data;
        }

        // Take the most recent N days
        return array_slice($data, -$maxDays);
    }

    /**
     * Track cumulative API token usage and estimated cost.
     * Stores monthly totals in cache and logs warnings when thresholds are crossed.
     */
    protected function trackUsage(array $usage): void
    {
        if (empty($usage)) {
            return;
        }

        $monthKey = 'deepseek_usage_' . date('Y_m');
        $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($usage['completion_tokens'] ?? 0);

        // Atomically increment monthly counters
        $current = Cache::get($monthKey, ['input_tokens' => 0, 'output_tokens' => 0, 'requests' => 0]);
        $current['input_tokens'] += $inputTokens;
        $current['output_tokens'] += $outputTokens;
        $current['requests'] += 1;

        // Estimate cost (DeepSeek: ~$0.14/1M input, $0.28/1M output)
        $estimatedCost = ($current['input_tokens'] * 0.14 / 1_000_000)
                       + ($current['output_tokens'] * 0.28 / 1_000_000);
        $current['estimated_cost_usd'] = round($estimatedCost, 4);

        // Store for 35 days (covers month + buffer)
        Cache::put($monthKey, $current, now()->addDays(35));

        // Log warnings at cost thresholds ($5, $10, $25, $50)
        $thresholds = [50, 25, 10, 5];
        foreach ($thresholds as $threshold) {
            $warningKey = "deepseek_cost_warning_{$threshold}_" . date('Y_m');
            if ($estimatedCost >= $threshold && !Cache::has($warningKey)) {
                Log::warning("DeepSeek AI monthly cost has exceeded \${$threshold}", [
                    'month' => date('Y-m'),
                    'estimated_cost' => $current['estimated_cost_usd'],
                    'total_requests' => $current['requests'],
                    'input_tokens' => $current['input_tokens'],
                    'output_tokens' => $current['output_tokens'],
                ]);
                Cache::put($warningKey, true, now()->addDays(35));
                break; // Only log the highest threshold not yet warned
            }
        }
    }

    /**
     * Get current month's usage statistics (for admin monitoring).
     */
    public function getMonthlyUsage(): array
    {
        $monthKey = 'deepseek_usage_' . date('Y_m');
        return Cache::get($monthKey, [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'requests' => 0,
            'estimated_cost_usd' => 0,
        ]);
    }

    /**
     * Parse recommendations from text format (fallback)
     */
    protected function parseRecommendationsFromText(string $text): array
    {
        $recommendations = [];
        $lines = explode("\n", $text);
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check if line starts a new recommendation (numbered or bulleted)
            if (preg_match('/^(\d+)[\.\)]\s*(.+)/', $line, $matches) || preg_match('/^[-*]\s*(.+)/', $line, $matches)) {
                if ($current) {
                    $recommendations[] = $current;
                }
                $current = [
                    'title' => $matches[1] ?? $matches[2] ?? $line,
                    'description' => '',
                    'impact' => 'medium',
                    'priority' => 'medium',
                    'steps' => [],
                ];
            } elseif ($current) {
                if (empty($current['description'])) {
                    $current['description'] = $line;
                } else {
                    $current['description'] .= ' ' . $line;
                }
            }
        }

        if ($current) {
            $recommendations[] = $current;
        }

        return $recommendations;
    }
}

