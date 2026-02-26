<script setup>
import { computed } from 'vue';
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
    quick_insights: {
        type: Array,
        default: () => [],
    },
    action_items: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    previous_stats: {
        type: Object,
        default: () => ({}),
    },
    top_qr_codes: {
        type: Array,
        default: () => [],
    },
    top_promotions: {
        type: Array,
        default: () => [],
    },
    device_breakdown: {
        type: Array,
        default: () => [],
    },
    hourly_distribution: {
        type: Array,
        default: () => [],
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
    generated_at: {
        type: String,
        default: null,
    },
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount || 0);
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-US').format(num || 0);
};

const getInsightTypeClass = (type) => {
    return {
        success: 'bg-green-500/10 border-green-500/20 text-green-400',
        warning: 'bg-yellow-500/10 border-yellow-500/20 text-yellow-400',
        opportunity: 'bg-blue-500/10 border-blue-500/20 text-blue-400',
        alert: 'bg-red-500/10 border-red-500/20 text-red-400',
    }[type] || 'bg-gray-500/10 border-gray-500/20 text-gray-400';
};

const getPriorityClass = (priority) => {
    return {
        high: 'bg-red-500/20 text-red-400 border-red-500/30',
        medium: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
        low: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
    }[priority] || 'bg-gray-500/20 text-gray-400 border-gray-500/30';
};

const changePeriod = (period) => {
    router.get(route('business.ai-insights.basic'), { period }, { preserveState: true });
};
</script>

<template>
    <Head title="AI Insights - Basic" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">AI Insights - Basic</h1>
                <p class="text-gray-400 mt-1">Quick AI-powered insights and recommendations</p>
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
                    {{ p === '7' ? '7 Days' : p === '30' ? '30 Days' : '90 Days' }}
                </button>
                <Link
                    :href="route('business.ai-insights.advanced', { period })"
                    class="px-4 py-2 rounded-lg bg-purple-500 text-white text-sm font-medium hover:bg-purple-600 transition-colors"
                >
                    Advanced →
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
                    <p class="text-blue-400 font-medium mb-1">Insights Not Ready Yet</p>
                    <p class="text-gray-400 text-sm">
                        Insights are generated automatically every Monday at 8 AM. They'll appear here once ready.
                    </p>
                </div>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-white">Performance Summary</h2>
            </div>
            <p class="text-gray-300 leading-relaxed">{{ summary || 'Generating summary...' }}</p>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-6">
                <span class="text-gray-400 text-sm">Total Scans</span>
                <p class="text-3xl font-bold text-white mt-2">{{ formatNumber(stats.total_scans || 0) }}</p>
                <p class="text-gray-500 text-xs mt-1">{{ formatNumber(stats.unique_scans || 0) }} unique</p>
            </div>
            <div class="glass-card p-6">
                <span class="text-gray-400 text-sm">Prize Redemption</span>
                <p class="text-3xl font-bold text-white mt-2">{{ stats.prize_redemption_rate || 0 }}%</p>
                <p class="text-gray-500 text-xs mt-1">
                    {{ formatNumber(stats.prizes_redeemed || 0) }} redeemed / {{ formatNumber(stats.prizes_issued || 0) }} issued
                </p>
            </div>
            <div class="glass-card p-6">
                <span class="text-gray-400 text-sm">Savings Generated</span>
                <p class="text-3xl font-bold text-green-400 mt-2">{{ formatCurrency(stats.total_savings || 0) }}</p>
                <p class="text-gray-500 text-xs mt-1">Value to customers</p>
            </div>
            <div class="glass-card p-6">
                <span class="text-gray-400 text-sm">Active Assets</span>
                <div class="flex items-baseline space-x-4 mt-2">
                    <div>
                        <p class="text-2xl font-bold text-white">{{ stats.active_qr_codes || 0 }}</p>
                        <p class="text-gray-500 text-xs">QR Codes</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-white">{{ stats.active_promotions || 0 }}</p>
                        <p class="text-gray-500 text-xs">Promotions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Insights -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Key Insights</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="(insight, index) in quick_insights"
                    :key="index"
                    class="glass-card p-5 border rounded-xl"
                    :class="getInsightTypeClass(insight.type || 'opportunity')"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-1">
                            <span v-if="insight.type === 'success'">✅</span>
                            <span v-else-if="insight.type === 'warning'">⚠️</span>
                            <span v-else-if="insight.type === 'alert'">🚨</span>
                            <span v-else>💡</span>
                        </div>
                        <div>
                            <h3 class="font-semibold mb-2">{{ insight.title }}</h3>
                            <p class="text-sm opacity-90">{{ insight.description }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="quick_insights.length === 0" class="col-span-full glass-card p-8 text-center">
                    <p class="text-gray-500">No insights available yet. Keep scanning to unlock insights!</p>
                </div>
            </div>
        </div>

        <!-- Action Items -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Action Items</h2>
            <div class="space-y-3">
                <div
                    v-for="(action, index) in action_items"
                    :key="index"
                    class="glass-card p-5 border rounded-xl"
                    :class="getPriorityClass(action.priority || 'medium')"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <span class="px-2 py-1 rounded text-xs font-bold border" :class="getPriorityClass(action.priority || 'medium')">
                                {{ (action.priority || 'medium').toUpperCase() }}
                            </span>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold mb-1">{{ action.title }}</h3>
                            <p class="text-sm opacity-90">{{ action.description }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="action_items.length === 0" class="glass-card p-8 text-center">
                    <p class="text-gray-500">No action items at this time.</p>
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Top QR Codes -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Top QR Codes</h3>
                <div class="space-y-3">
                    <div
                        v-for="(qr, index) in top_qr_codes"
                        :key="qr.id"
                        class="flex items-center justify-between p-3 rounded-lg bg-white/5"
                    >
                        <div class="flex items-center space-x-3">
                            <span class="text-gray-500 text-sm w-4">{{ index + 1 }}</span>
                            <div>
                                <p class="text-white font-medium text-sm">{{ qr.name }}</p>
                                <p class="text-gray-500 text-xs">{{ qr.placement || 'No placement' }}</p>
                            </div>
                        </div>
                        <span class="text-primary-400 font-medium">{{ formatNumber(qr.scans) }}</span>
                    </div>
                    <p v-if="!top_qr_codes.length" class="text-gray-500 text-center py-4">No data yet</p>
                </div>
            </div>

            <!-- Top Promotions -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Top Promotions</h3>
                <div class="space-y-3">
                    <div
                        v-for="(promo, index) in top_promotions"
                        :key="promo.id"
                        class="flex items-center justify-between p-3 rounded-lg bg-white/5"
                    >
                        <div class="flex items-center space-x-3">
                            <span class="text-gray-500 text-sm w-4">{{ index + 1 }}</span>
                            <div>
                                <p class="text-white font-medium text-sm">{{ promo.name }}</p>
                                <p class="text-gray-500 text-xs">{{ promo.redemptions }} redemptions</p>
                            </div>
                        </div>
                        <span class="text-green-400 font-medium">{{ formatCurrency(promo.savings) }}</span>
                    </div>
                    <p v-if="!top_promotions.length" class="text-gray-500 text-center py-4">No data yet</p>
                </div>
            </div>
        </div>
    </div>
</template>

