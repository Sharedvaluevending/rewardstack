<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_scans: 0,
            promotion_scans: 0,
            punch_card_scans: 0,
            game_scans: 0,
            leaderboard_scans: 0,
            info_scans: 0,
            stackable_scans: 0,
            cross_promo_scans: 0,
            blocked_scans: 0,
            unique_scans: 0,
            scans_change: 0,
            total_redemptions: 0,
            redemptions_change: 0,
            total_savings: 0,
            savings_change: 0,
            prizes_issued: 0,
            prizes_issued_change: 0,
            prizes_redeemed: 0,
            prize_redemption_rate: 0,
            punch_stamps: 0,
            punch_completions: 0,
            punch_customers: 0,
            punch_completion_rate: 0,
            active_qr_codes: 0,
            active_promotions: 0,
        }),
    },
    scansOverTime: {
        type: Array,
        default: () => [],
    },
    redemptionsOverTime: {
        type: Array,
        default: () => [],
    },
    deviceBreakdown: {
        type: Array,
        default: () => [],
    },
    topQRCodes: {
        type: Array,
        default: () => [],
    },
    topPromotions: {
        type: Array,
        default: () => [],
    },
    hourlyDistribution: {
        type: Array,
        default: () => [],
    },
    locationBreakdown: {
        type: Array,
        default: () => [],
    },
    insights: {
        type: Array,
        default: () => [],
    },
    period: {
        type: String,
        default: '30',
    },
});

const selectedPeriod = ref(props.period || '30');
const showExportMenu = ref(false);

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount || 0);
};

// Format number with commas
const formatNumber = (num) => {
    return new Intl.NumberFormat('en-US').format(num || 0);
};

// Change period
const changePeriod = (period) => {
    selectedPeriod.value = period;
    router.get(route('business.analytics'), { period }, { preserveState: true });
};

// Chart helpers
const maxScanValue = computed(() => {
    if (!props.scansOverTime?.length) return 1;
    const peak = Math.max(...props.scansOverTime.map(d => d.value || 0));
    return Math.max(peak * 1.15, 1);
});

const maxHourlyValue = computed(() => {
    if (!props.hourlyDistribution?.length) return 1;
    const peak = Math.max(...props.hourlyDistribution.map(d => d.value || 0));
    return Math.max(peak * 1.15, 1);
});

// Grid labels for Y-axis
const scanYAxisLabels = computed(() => {
    const max = maxScanValue.value;
    return [
        { label: formatNumber(Math.round(max)), top: '0%' },
        { label: formatNumber(Math.round(max * 0.5)), top: '50%' },
        { label: '0', top: '100%' }
    ];
});

const hourlyYAxisLabels = computed(() => {
    const max = maxHourlyValue.value;
    return [
        { label: formatNumber(Math.round(max)), top: '0%' },
        { label: formatNumber(Math.round(max * 0.5)), top: '50%' },
        { label: '0', top: '100%' }
    ];
});

// Get bar height as percentage
const getBarHeight = (value, max) => {
    if (value === 0) return 0;
    return Math.max((value / max) * 100, 2);
};

// Get change indicator class
const getChangeClass = (change) => {
    if (change > 0) return 'text-green-400';
    if (change < 0) return 'text-red-400';
    return 'text-gray-400';
};

// Get change arrow
const getChangeArrow = (change) => {
    if (change > 0) return '↑';
    if (change < 0) return '↓';
    return '→';
};

// Device colors
const deviceColors = {
    'Mobile': '#0ea5e9',
    'Desktop': '#8b5cf6',
    'Tablet': '#f59e0b',
    'Unknown': '#6b7280',
};

// Total devices for percentage
const totalDevices = computed(() => {
    if (!props.deviceBreakdown?.length) return 0;
    return props.deviceBreakdown.reduce((sum, d) => sum + (d.value || 0), 0);
});
</script>

<template>
    <Head title="Analytics" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Analytics</h1>
                <p class="text-gray-400 mt-1">Track performance and discover insights</p>
            </div>
            <div class="flex items-center space-x-2 mt-4 md:mt-0">
                <button
                    v-for="p in ['7', '30', '90']"
                    :key="p"
                    @click="changePeriod(p)"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                        selectedPeriod === p
                            ? 'bg-primary-500 text-white'
                            : 'bg-white/10 text-gray-400 hover:bg-white/20'
                    ]"
                >
                    {{ p === '7' ? '7 Days' : p === '30' ? '30 Days' : '90 Days' }}
                </button>
                <div class="relative">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg bg-white/10 text-gray-400 hover:bg-white/20 text-sm"
                        @click="showExportMenu = !showExportMenu"
                    >
                        Export
                    </button>

                    <div
                        v-if="showExportMenu"
                        class="absolute right-0 mt-2 w-56 rounded-xl border border-white/10 bg-[#0b0b0e]/95 backdrop-blur-xl shadow-2xl overflow-hidden z-50"
                    >
                        <a
                            :href="route('business.analytics.export', { period: selectedPeriod, type: 'scans' })"
                            class="block px-4 py-3 text-sm text-gray-200 hover:bg-white/10"
                            @click="showExportMenu = false"
                        >
                            Download scans (CSV)
                            <div class="text-xs text-gray-500 mt-0.5">Date, QR Code, Device, City, Browser</div>
                        </a>
                        <a
                            :href="route('business.analytics.export', { period: selectedPeriod, type: 'redemptions' })"
                            class="block px-4 py-3 text-sm text-gray-200 hover:bg-white/10 border-t border-white/10"
                            @click="showExportMenu = false"
                        >
                            Download redemptions (CSV)
                            <div class="text-xs text-gray-500 mt-0.5">Date, Promotion, Discount, Final Amount, Employee</div>
                        </a>
                        <a
                            :href="route('business.analytics.report', { period: selectedPeriod })"
                            target="_blank"
                            rel="noopener"
                            class="block px-4 py-3 text-sm text-white hover:bg-white/10 border-t border-white/10"
                            @click="showExportMenu = false"
                        >
                            Printable business report (PDF)
                            <div class="text-xs text-gray-500 mt-0.5">Professional layout • Print / Save as PDF</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm">Total Scans</span>
                    <span :class="getChangeClass(stats.scans_change)" class="text-xs font-medium">
                        {{ getChangeArrow(stats.scans_change) }} {{ Math.abs(stats.scans_change) }}%
                    </span>
                </div>
                <p class="text-3xl font-bold text-white">{{ formatNumber(stats.total_scans) }}</p>
                <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                    <span>{{ formatNumber(stats.promotion_scans || 0) }} promo</span>
                    <span v-if="(stats.punch_card_scans || 0) > 0" class="text-cyan-400">{{ formatNumber(stats.punch_card_scans) }} punch</span>
                    <span v-if="(stats.game_scans || 0) > 0" class="text-purple-400">{{ formatNumber(stats.game_scans) }} game</span>
                    <span v-if="(stats.leaderboard_scans || 0) > 0" class="text-amber-400">{{ formatNumber(stats.leaderboard_scans) }} lb</span>
                    <span v-if="(stats.blocked_scans || 0) > 0" class="text-red-400" :title="'Scans from customers who already hit their redemption limit'">{{ formatNumber(stats.blocked_scans) }} blocked</span>
                    <span>• {{ formatNumber(stats.unique_scans) }} unique</span>
                </div>
            </div>

            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm">Issued Prizes</span>
                    <span :class="getChangeClass(stats.prizes_issued_change)" class="text-xs font-medium">
                        {{ getChangeArrow(stats.prizes_issued_change) }} {{ Math.abs(stats.prizes_issued_change) }}%
                    </span>
                </div>
                <p class="text-3xl font-bold text-white">{{ formatNumber(stats.prizes_issued || 0) }}</p>
                <p class="text-gray-500 text-sm mt-1">
                    {{ formatNumber(stats.prizes_redeemed || 0) }} redeemed • {{ stats.prize_redemption_rate || 0 }}%
                </p>
            </div>

            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm">Revenue Generated</span>
                    <Link :href="route('business.analytics.finance')" class="text-primary-400 hover:text-primary-300 text-xs font-medium">Details →</Link>
                </div>
                <p class="text-3xl font-bold text-emerald-400">{{ formatCurrency(stats.total_revenue || 0) }}</p>
                <p class="text-gray-500 text-sm mt-1">Total collected</p>
            </div>

            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400 text-sm">Savings Generated</span>
                    <span :class="getChangeClass(stats.savings_change)" class="text-xs font-medium">
                        {{ getChangeArrow(stats.savings_change) }} {{ Math.abs(stats.savings_change) }}%
                    </span>
                </div>
                <p class="text-3xl font-bold text-rose-400">{{ formatCurrency(stats.total_savings) }}</p>
                <p class="text-gray-500 text-sm mt-1">Cost to business</p>
            </div>

            <div class="glass-card p-6">
                <span class="text-gray-400 text-sm">Punch Cards</span>
                <p class="text-3xl font-bold text-white mt-2">{{ stats.punch_completion_rate || 0 }}%</p>
                <p class="text-gray-500 text-sm mt-1">
                    {{ formatNumber(stats.punch_completions || 0) }} completions • {{ formatNumber(stats.punch_stamps || 0) }} stamps
                </p>
            </div>

            <div class="glass-card p-6">
                <span class="text-gray-400 text-sm">Active Assets</span>
                <div class="flex items-baseline space-x-4 mt-2">
                    <div>
                        <p class="text-2xl font-bold text-white">{{ stats.active_qr_codes }}</p>
                        <p class="text-gray-500 text-xs">QR Codes</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-white">{{ stats.active_promotions }}</p>
                        <p class="text-gray-500 text-xs">Promotions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Scans Over Time -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-6">Scans Over Time</h3>
                <div v-if="scansOverTime?.length" class="relative h-48 flex items-end">
                    <!-- Y-Axis Labels -->
                    <div class="absolute -left-2 top-0 bottom-0 w-full flex flex-col justify-between pointer-events-none pr-2">
                        <div v-for="label in scanYAxisLabels" :key="label.top" 
                            class="flex items-center w-full"
                            :style="{ position: 'absolute', top: label.top, width: '100%' }">
                            <span class="text-[9px] text-gray-500 font-mono pr-2 bg-[#121212] z-10">{{ label.label }}</span>
                            <div class="flex-1 h-[1px] bg-white/5 w-full"></div>
                        </div>
                    </div>

                    <div class="flex-1 h-full flex items-end space-x-1 ml-10">
                        <div
                            v-for="(point, index) in scansOverTime"
                            :key="index"
                            class="flex-1 h-full flex items-end group relative"
                        >
                            <div
                                class="w-full bg-gradient-to-t from-primary-500/30 to-primary-500/80 border-t border-primary-500/50 rounded-t transition-all group-hover:from-primary-500/50 group-hover:to-primary-500 shadow-[0_0_10px_rgba(59,130,246,0.1)]"
                                :style="{ height: `${getBarHeight(point.value, maxScanValue)}%` }"
                            ></div>
                            <!-- Tooltip -->
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 border border-white/10 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-20">
                                {{ point.date }}: {{ point.value }} scans
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="h-48 flex items-center justify-center text-gray-500">
                    No scan data yet
                </div>
                <div v-if="scansOverTime?.length" class="flex justify-between mt-2 text-xs text-gray-500 ml-10">
                    <span>{{ scansOverTime[0]?.date }}</span>
                    <span>{{ scansOverTime[scansOverTime.length - 1]?.date }}</span>
                </div>
            </div>

            <!-- Device Breakdown -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-6">Device Breakdown</h3>
                <div v-if="deviceBreakdown?.length && totalDevices > 0" class="flex items-center justify-center">
                    <!-- Donut Chart (simplified) -->
                    <div class="relative w-40 h-40">
                        <svg class="w-full h-full transform -rotate-90">
                            <circle
                                v-for="(device, index) in deviceBreakdown"
                                :key="device.name"
                                cx="80"
                                cy="80"
                                r="60"
                                fill="none"
                                :stroke="deviceColors[device.name] || '#6b7280'"
                                stroke-width="20"
                                :stroke-dasharray="`${(device.value / totalDevices) * 377} 377`"
                                :stroke-dashoffset="-deviceBreakdown.slice(0, index).reduce((sum, d) => sum + (d.value / totalDevices) * 377, 0)"
                            />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-white">{{ formatNumber(totalDevices) }}</p>
                                <p class="text-gray-500 text-xs">Total</p>
                            </div>
                        </div>
                    </div>
                    <!-- Legend -->
                    <div class="ml-8 space-y-3">
                        <div
                            v-for="device in deviceBreakdown"
                            :key="device.name"
                            class="flex items-center"
                        >
                            <div
                                class="w-3 h-3 rounded-full mr-3"
                                :style="{ backgroundColor: deviceColors[device.name] || '#6b7280' }"
                            ></div>
                            <span class="text-gray-300">{{ device.name }}</span>
                            <span class="text-gray-500 ml-2">{{ Math.round((device.value / totalDevices) * 100) }}%</span>
                        </div>
                    </div>
                </div>
                <div v-else class="flex items-center justify-center h-40 text-gray-500">
                    No device data yet
                </div>
            </div>
        </div>

        <!-- Hourly Distribution -->
        <div class="glass-card p-6 mb-8">
            <h3 class="text-lg font-semibold text-white mb-6">Hourly Activity Distribution</h3>
            <div v-if="hourlyDistribution?.length" class="relative h-32 flex items-end">
                <!-- Y-Axis Labels -->
                <div class="absolute -left-2 top-0 bottom-0 w-full flex flex-col justify-between pointer-events-none pr-2">
                    <div v-for="label in hourlyYAxisLabels" :key="label.top" 
                        class="flex items-center w-full"
                        :style="{ position: 'absolute', top: label.top, width: '100%' }">
                        <span class="text-[9px] text-gray-500 font-mono pr-2 bg-[#121212] z-10">{{ label.label }}</span>
                        <div class="flex-1 h-[1px] bg-white/5 w-full"></div>
                    </div>
                </div>

                <div class="flex-1 h-full flex items-end space-x-1 ml-10">
                    <div
                        v-for="(hour, index) in hourlyDistribution"
                        :key="index"
                        class="flex-1 h-full flex items-end group relative"
                    >
                        <div
                            class="w-full rounded-t transition-all border-t border-white/10 shadow-[0_0_10px_rgba(255,255,255,0.05)]"
                            :class="[
                                hour.value === maxHourlyValue
                                    ? 'bg-gradient-to-t from-accent-600/50 to-accent-400 border-accent-500/50 shadow-[0_0_10px_rgba(234,179,8,0.15)]'
                                    : 'bg-gradient-to-t from-gray-700/50 to-gray-500 group-hover:from-gray-600/70 group-hover:to-gray-400'
                            ]"
                            :style="{ height: `${getBarHeight(hour.value, maxHourlyValue)}%` }"
                        ></div>
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 border border-white/10 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-20">
                            {{ hour.label }}: {{ hour.value }} scans
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="h-32 flex items-center justify-center text-gray-500">
                No hourly data yet
            </div>
            <div v-if="hourlyDistribution?.length" class="flex justify-between mt-2 text-xs text-gray-500 ml-10">
                <span>12 AM</span>
                <span>6 AM</span>
                <span>12 PM</span>
                <span>6 PM</span>
                <span>11 PM</span>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Top QR Codes -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Top QR Codes</h3>
                <div class="space-y-3">
                    <div
                        v-for="(qr, index) in topQRCodes"
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
                    <p v-if="!topQRCodes.length" class="text-gray-500 text-center py-4">No data yet</p>
                </div>
            </div>

            <!-- Top Promotions -->
            <div class="glass-card p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Top Promotions</h3>
                <div class="space-y-3">
                    <div
                        v-for="(promo, index) in topPromotions"
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
                    <p v-if="!topPromotions.length" class="text-gray-500 text-center py-4">No data yet</p>
                </div>
            </div>

            <!-- AI Insights -->
            <div class="glass-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">AI Insights</h3>
                    <span class="px-2 py-1 rounded bg-accent-500/20 text-accent-400 text-xs">Beta</span>
                </div>
                <div class="space-y-3 mb-4">
                    <div
                        v-for="insight in insights"
                        :key="insight.id || insight.title"
                        class="p-3 rounded-lg"
                        :class="[
                            insight.type === 'alert' ? 'bg-yellow-500/10 border border-yellow-500/20' :
                            insight.type === 'trend' ? 'bg-blue-500/10 border border-blue-500/20' :
                            'bg-green-500/10 border border-green-500/20'
                        ]"
                    >
                        <p 
                            class="font-medium text-sm"
                            :class="[
                                insight.type === 'alert' ? 'text-yellow-400' :
                                insight.type === 'trend' ? 'text-blue-400' :
                                'text-green-400'
                            ]"
                        >
                            {{ insight.title }}
                        </p>
                        <p class="text-gray-400 text-xs mt-1">{{ insight.description || insight.content }}</p>
                        <p v-if="insight.potential_impact" class="text-gray-500 text-xs mt-2">
                            Potential impact: {{ formatCurrency(insight.potential_impact) }}
                        </p>
                    </div>
                    <p v-if="!insights.length" class="text-gray-500 text-center py-4">
                        Keep scanning to unlock insights!
                    </p>
                </div>
                <div class="flex items-center gap-2 pt-4 border-t border-white/10">
                    <Link
                        :href="route('business.ai-insights.basic', { period: selectedPeriod })"
                        class="flex-1 px-4 py-2 rounded-lg bg-primary-500 text-white text-sm font-medium hover:bg-primary-600 transition-colors text-center"
                    >
                        Basic
                    </Link>
                    <Link
                        :href="route('business.ai-insights.advanced', { period: selectedPeriod })"
                        class="flex-1 px-4 py-2 rounded-lg bg-purple-500 text-white text-sm font-medium hover:bg-purple-600 transition-colors text-center"
                    >
                        Advanced
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

