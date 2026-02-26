<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    summary: {
        type: String,
        default: '',
    },
    predictions: {
        type: Object,
        default: () => ({}),
    },
    recommendations: {
        type: Array,
        default: () => [],
    },
    customer_segments: {
        type: Array,
        default: () => [],
    },
    promotion_optimizations: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    scans_over_time: {
        type: Array,
        default: () => [],
    },
    redemptions_over_time: {
        type: Array,
        default: () => [],
    },
    promotions: {
        type: Array,
        default: () => [],
    },
    customer_data: {
        type: Array,
        default: () => [],
    },
    game_data: {
        type: Object,
        default: () => ({}),
    },
    period: {
        type: String,
        default: '7',
    },
    ai_enabled: {
        type: Boolean,
        default: false,
    },
    needs_generation: {
        type: Boolean,
        default: false,
    },
    from_cache: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
    generated_at: {
        type: String,
        default: null,
    },
});

const activeTab = ref('predictions');

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount || 0);
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-US').format(num || 0);
};

const getPriorityClass = (priority) => {
    return {
        high: 'bg-red-500/20 text-red-400 border-red-500/30',
        medium: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
        low: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
    }[priority] || 'bg-gray-500/20 text-gray-400 border-gray-500/30';
};

const changePeriod = (period) => {
    router.get(route('business.ai-insights.advanced'), { period }, { preserveState: true });
};
</script>

<template>
    <Head title="AI Insights - Advanced" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">AI Insights - Advanced</h1>
                <p class="text-gray-400 mt-1">Deep analysis, predictions, and optimization recommendations</p>
            </div>
            <div class="flex items-center space-x-2 mt-4 md:mt-0">
                <button
                    v-for="p in ['7', '30', '90']"
                    :key="p"
                    @click="changePeriod(p)"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                        period === p
                            ? 'bg-primary-500 text-white'
                            : 'bg-white/10 text-gray-400 hover:bg-white/20'
                    ]"
                >
                    {{ p }} Days
                </button>
                <Link
                    :href="route('business.ai-insights.basic', { period })"
                    class="px-4 py-2 rounded-lg bg-white/10 text-gray-400 hover:bg-white/20 text-sm"
                >
                    ← Basic
                </Link>
            </div>
        </div>

        <!-- Generated timestamp -->
        <div v-if="generated_at && !needs_generation" class="mb-4 text-right">
            <span class="text-xs text-gray-500">Generated: {{ generated_at }} &bull; Updates every Monday at 8 AM</span>
        </div>

        <!-- AI Status Badge -->
        <div v-if="!ai_enabled" class="mb-6 p-4 rounded-xl bg-yellow-500/10 border border-yellow-500/30">
            <p class="text-yellow-400 text-sm">
                ⚠️ AI features are currently disabled. Showing rule-based insights.
            </p>
        </div>

        <!-- Needs Generation Message -->
        <div v-if="needs_generation" class="mb-6 p-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
            <div class="flex items-center gap-3">
                <div class="text-2xl">⏳</div>
                <div>
                    <p class="text-blue-400 font-medium mb-1">Advanced Insights Not Ready Yet</p>
                    <p class="text-gray-400 text-sm">
                        Advanced insights are generated automatically every Monday at 8 AM. They'll appear here once ready.
                    </p>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30">
            <p class="text-red-400 text-sm">
                ⚠️ {{ error }}
            </p>
        </div>

        <!-- Summary Section -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-white">Executive Summary</h2>
            </div>
            <p class="text-gray-300 leading-relaxed">{{ summary || 'Generating comprehensive summary...' }}</p>
        </div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-white/10">
            <nav class="flex space-x-8">
                <button
                    @click="activeTab = 'predictions'"
                    :class="[
                        'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                        activeTab === 'predictions'
                            ? 'border-primary-500 text-primary-400'
                            : 'border-transparent text-gray-400 hover:text-gray-300'
                    ]"
                >
                    Predictions
                </button>
                <button
                    @click="activeTab = 'recommendations'"
                    :class="[
                        'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                        activeTab === 'recommendations'
                            ? 'border-primary-500 text-primary-400'
                            : 'border-transparent text-gray-400 hover:text-gray-300'
                    ]"
                >
                    Recommendations
                </button>
                <button
                    @click="activeTab = 'segments'"
                    :class="[
                        'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                        activeTab === 'segments'
                            ? 'border-primary-500 text-primary-400'
                            : 'border-transparent text-gray-400 hover:text-gray-300'
                    ]"
                >
                    Customer Segments
                </button>
                <button
                    @click="activeTab = 'optimization'"
                    :class="[
                        'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                        activeTab === 'optimization'
                            ? 'border-primary-500 text-primary-400'
                            : 'border-transparent text-gray-400 hover:text-gray-300'
                    ]"
                >
                    Optimization
                </button>
            </nav>
        </div>

        <!-- Predictions Tab -->
        <div v-show="activeTab === 'predictions'" class="space-y-6">
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">📈 Forecasts & Predictions</h3>
                <div v-if="predictions.success && predictions.predictions">
                    <div class="space-y-4">
                        <div v-if="predictions.predictions.length" class="space-y-3">
                            <div
                                v-for="(pred, index) in predictions.predictions"
                                :key="index"
                                class="p-4 rounded-lg bg-white/5 border border-white/10"
                            >
                                <p class="text-white font-medium">{{ pred.date || `Day ${index + 1}` }}</p>
                                <p class="text-gray-400 text-sm">Predicted: {{ pred.value || pred }}</p>
                            </div>
                        </div>
                        <div v-else-if="predictions.raw_content" class="p-4 rounded-lg bg-white/5 border border-white/10">
                            <p class="text-gray-300 whitespace-pre-wrap">{{ predictions.raw_content }}</p>
                        </div>
                        <div v-if="predictions.confidence" class="mt-4">
                            <p class="text-sm text-gray-400">Confidence Level: <span class="text-white font-medium">{{ predictions.confidence }}</span></p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-gray-500">No predictions available yet. More historical data needed for accurate forecasts.</p>
                </div>
            </div>

            <!-- Historical Trends -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Historical Trends</h3>
                <div class="text-sm text-gray-400 space-y-2">
                    <p>Total Scans: {{ formatNumber(stats.total_scans || 0) }}</p>
                    <p>Total Redemptions: {{ formatNumber(stats.total_redemptions || 0) }}</p>
                    <p>Prize Redemption Rate: {{ stats.prize_redemption_rate || 0 }}% ({{ formatNumber(stats.prizes_redeemed || 0) }}/{{ formatNumber(stats.prizes_issued || 0) }})</p>
                    <p>Total Savings: {{ formatCurrency(stats.total_savings || 0) }}</p>
                </div>
            </div>
        </div>

        <!-- Recommendations Tab -->
        <div v-show="activeTab === 'recommendations'" class="space-y-6">
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">💡 Actionable Recommendations</h3>
                <div v-if="recommendations.length > 0" class="space-y-4">
                    <div
                        v-for="(rec, index) in recommendations"
                        :key="index"
                        class="p-5 rounded-xl border"
                        :class="getPriorityClass(rec.priority || 'medium')"
                    >
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <span class="px-2 py-1 rounded text-xs font-bold border" :class="getPriorityClass(rec.priority || 'medium')">
                                    {{ (rec.priority || 'medium').toUpperCase() }}
                                </span>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-lg mb-2">{{ rec.title }}</h4>
                                <p class="text-sm opacity-90 mb-3">{{ rec.description }}</p>
                                <div v-if="rec.impact" class="mb-3">
                                    <p class="text-sm font-medium">Estimated Impact: <span class="text-green-400">{{ rec.impact }}</span></p>
                                </div>
                                <div v-if="rec.steps && rec.steps.length" class="mt-3">
                                    <p class="text-sm font-medium mb-2">Implementation Steps:</p>
                                    <ol class="list-decimal list-inside space-y-1 text-sm opacity-90">
                                        <li v-for="(step, stepIndex) in rec.steps" :key="stepIndex">{{ step }}</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-gray-500">No recommendations available at this time.</p>
                </div>
            </div>
        </div>

        <!-- Customer Segments Tab -->
        <div v-show="activeTab === 'segments'" class="space-y-6">
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">👥 Customer Segmentation</h3>
                <div v-if="customer_segments.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="(segment, index) in customer_segments"
                        :key="index"
                        class="p-5 rounded-xl bg-white/5 border border-white/10"
                    >
                        <h4 class="font-semibold text-lg mb-2">{{ segment.name }}</h4>
                        <p class="text-sm text-gray-400 mb-3">{{ segment.characteristics }}</p>
                        <div class="space-y-2 text-sm">
                            <p><span class="text-gray-400">Size:</span> <span class="text-white font-medium">{{ segment.size_percent || 'N/A' }}%</span></p>
                            <p><span class="text-gray-400">Engagement:</span> <span class="text-white font-medium">{{ segment.engagement_level || 'N/A' }}</span></p>
                        </div>
                        <div v-if="segment.recommendations" class="mt-4">
                            <p class="text-sm font-medium mb-2">Recommendations:</p>
                            <p class="text-sm text-gray-400">{{ segment.recommendations }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-gray-500">No customer segments identified yet. More customer data needed for segmentation.</p>
                </div>
            </div>

            <!-- Customer Data Summary -->
            <div v-if="customer_data.length > 0" class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Customer Data Overview</h3>
                <div class="text-sm text-gray-400 space-y-2">
                    <p>Total Customers: {{ formatNumber(customer_data.length) }}</p>
                    <p>Average Scans per Customer: {{ formatNumber(customer_data.reduce((sum, c) => sum + (c.scans_count || 0), 0) / customer_data.length || 0) }}</p>
                    <p>Average Redemptions per Customer: {{ formatNumber(customer_data.reduce((sum, c) => sum + (c.redemptions_count || 0), 0) / customer_data.length || 0) }}</p>
                </div>
            </div>
        </div>

        <!-- Optimization Tab -->
        <div v-show="activeTab === 'optimization'" class="space-y-6">
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">⚙️ Promotion Optimization</h3>
                <div v-if="promotion_optimizations.length > 0" class="space-y-4">
                    <div
                        v-for="(opt, index) in promotion_optimizations"
                        :key="index"
                        class="p-5 rounded-xl bg-white/5 border border-white/10"
                    >
                        <h4 class="font-semibold text-lg mb-2">{{ opt.promotion || `Promotion ${index + 1}` }}</h4>
                        <div v-if="opt.current_performance" class="mb-3">
                            <p class="text-sm text-gray-400">Current Performance: <span class="text-white">{{ opt.current_performance }}</span></p>
                        </div>
                        <div v-if="opt.opportunity" class="mb-3">
                            <p class="text-sm text-gray-400">Opportunity: <span class="text-green-400">{{ opt.opportunity }}</span></p>
                        </div>
                        <div v-if="opt.expected_improvement" class="mb-3">
                            <p class="text-sm text-gray-400">Expected Improvement: <span class="text-blue-400">{{ opt.expected_improvement }}</span></p>
                        </div>
                        <div v-if="opt.steps && opt.steps.length" class="mt-3">
                            <p class="text-sm font-medium mb-2">Implementation Steps:</p>
                            <ol class="list-decimal list-inside space-y-1 text-sm text-gray-400">
                                <li v-for="(step, stepIndex) in opt.steps" :key="stepIndex">{{ step }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8">
                    <p class="text-gray-500">No optimization suggestions available at this time.</p>
                </div>
            </div>

            <!-- All Promotions Performance -->
            <div v-if="promotions.length > 0" class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">All Promotions Performance</h3>
                <div class="space-y-3">
                    <div
                        v-for="promo in promotions"
                        :key="promo.id"
                        class="flex items-center justify-between p-3 rounded-lg bg-white/5"
                    >
                        <div>
                            <p class="text-white font-medium text-sm">{{ promo.name }}</p>
                            <p class="text-gray-500 text-xs">{{ promo.type }} • {{ promo.redemptions }} redemptions</p>
                        </div>
                        <div class="text-right">
                            <p class="text-green-400 font-medium">{{ formatCurrency(promo.savings) }}</p>
                            <p class="text-gray-500 text-xs" :class="promo.is_active ? 'text-green-400' : 'text-red-400'">
                                {{ promo.is_active ? 'Active' : 'Inactive' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

