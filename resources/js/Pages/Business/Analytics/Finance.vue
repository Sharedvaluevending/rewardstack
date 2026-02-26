<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_revenue: 0,
            total_cost: 0,
            total_redemptions: 0,
            roi: 0,
            avg_discount: 0,
        }),
    },
    promotions: {
        type: Array,
        default: () => [],
    },
    overTime: {
        type: Array,
        default: () => [],
    },
    period: {
        type: String,
        default: '30',
    },
});

const selectedPeriod = ref(props.period || '30');

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
    router.get(route('business.analytics.finance'), { period }, { preserveState: true });
};

// Chart helpers
const maxRevenueValue = computed(() => {
    if (!props.overTime?.length) return 1;
    const peak = Math.max(...props.overTime.map(d => Math.max(d.revenue || 0, d.cost || 0)));
    // Add 15% padding so bars don't hit the very top
    return Math.max(peak * 1.15, 1);
});

// Grid labels for Y-axis
const yAxisLabels = computed(() => {
    const max = maxRevenueValue.value;
    return [
        { label: formatCurrency(max), top: '0%' },
        { label: formatCurrency(max * 0.75), top: '25%' },
        { label: formatCurrency(max * 0.5), top: '50%' },
        { label: formatCurrency(max * 0.25), top: '75%' },
        { label: '0', top: '100%' }
    ];
});

// Get bar height as percentage
const getBarHeight = (value, max) => {
    if (value === 0) return 0;
    return Math.max((value / max) * 100, 2);
};

// Sort promotions
const sortKey = ref('revenue');
const sortOrder = ref('desc');

const sortedPromotions = computed(() => {
    return [...props.promotions].sort((a, b) => {
        let valA = a[sortKey.value];
        let valB = b[sortKey.value];
        if (sortOrder.value === 'asc') {
            return valA > valB ? 1 : -1;
        } else {
            return valA < valB ? 1 : -1;
        }
    });
});

const toggleSort = (key) => {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortOrder.value = 'desc';
    }
};
</script>

<template>
    <Head title="Finance Analytics" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Link :href="route('business.analytics')" class="text-primary-400 hover:text-primary-300 text-sm">Analytics</Link>
                    <span class="text-gray-600">/</span>
                    <span class="text-gray-400 text-sm">Finance</span>
                </div>
                <h1 class="text-3xl font-bold text-white">Finance Analytics 💰</h1>
                <p class="text-gray-400 mt-1">Track ROI and promotional costs</p>
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
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm mb-1">Total Revenue</p>
                <p class="text-3xl font-bold text-emerald-400">{{ formatCurrency(stats.total_revenue) }}</p>
                <p class="text-gray-500 text-xs mt-1">Money collected from redemptions</p>
            </div>

            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm mb-1">Promotional Cost</p>
                <p class="text-3xl font-bold text-rose-400">{{ formatCurrency(stats.total_cost) }}</p>
                <p class="text-gray-500 text-xs mt-1">Money saved by customers</p>
            </div>

            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm mb-1">Avg ROI Ratio</p>
                <p class="text-3xl font-bold text-white">{{ stats.roi }}x</p>
                <p class="text-gray-500 text-xs mt-1">$1 cost generated ${{ stats.roi }} revenue</p>
            </div>

            <div class="glass-card p-6">
                <p class="text-gray-400 text-sm mb-1">Avg. Savings / Use</p>
                <p class="text-3xl font-bold text-primary-400">{{ formatCurrency(stats.avg_discount) }}</p>
                <p class="text-gray-500 text-xs mt-1">Average cost per redemption</p>
            </div>
        </div>

        <!-- Profitability Chart -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-white">Revenue vs. Cost over Time</h3>
                <div class="flex items-center gap-4 text-xs">
                    <div class="flex items-center gap-1.5 text-emerald-400">
                        <div class="w-3 h-3 rounded-full bg-emerald-500/50 border border-emerald-500"></div>
                        <span>Revenue</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-rose-400">
                        <div class="w-3 h-3 rounded-full bg-rose-500/50 border border-rose-500"></div>
                        <span>Cost</span>
                    </div>
                </div>
            </div>
            
            <div v-if="overTime?.length" class="relative h-64 flex items-end">
                <!-- Y-Axis Labels -->
                <div class="absolute -left-2 top-0 bottom-0 w-full flex flex-col justify-between pointer-events-none pr-2">
                    <div v-for="label in yAxisLabels" :key="label.top" 
                        class="flex items-center w-full"
                        :style="{ position: 'absolute', top: label.top, width: '100%' }">
                        <span class="text-[9px] text-gray-500 font-mono pr-2 bg-[#121212] z-10">{{ label.label }}</span>
                        <div class="flex-1 h-[1px] bg-white/5 w-full"></div>
                    </div>
                </div>

                <div class="flex-1 h-full flex items-end space-x-2 px-2 ml-12">
                    <div
                        v-for="(point, index) in overTime"
                        :key="index"
                        class="flex-1 flex flex-row items-end justify-center gap-0.5 group relative h-full"
                    >
                        <!-- Revenue Bar -->
                        <div
                            class="w-full bg-gradient-to-t from-emerald-500/20 to-emerald-500/50 border-t border-emerald-500/50 rounded-t transition-all group-hover:from-emerald-500/40 group-hover:to-emerald-500/70 shadow-[0_0_10px_rgba(16,185,129,0.1)]"
                            :style="{ height: `${getBarHeight(point.revenue, maxRevenueValue)}%` }"
                        ></div>
                        <!-- Cost Bar -->
                        <div
                            class="w-full bg-gradient-to-t from-rose-500/20 to-rose-500/50 border-t border-rose-500/50 rounded-t transition-all group-hover:from-rose-500/40 group-hover:to-rose-500/70 shadow-[0_0_10px_rgba(244,63,94,0.1)]"
                            :style="{ height: `${getBarHeight(point.cost, maxRevenueValue)}%` }"
                        ></div>
                        
                        <!-- Tooltip -->
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 border border-white/10 text-white text-xs rounded-xl shadow-2xl opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-20">
                            <div class="font-bold mb-1">{{ point.date }}</div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-400">Revenue:</span>
                                <span class="text-emerald-400 font-mono">{{ formatCurrency(point.revenue) }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-400">Cost:</span>
                                <span class="text-rose-400 font-mono">{{ formatCurrency(point.cost) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="h-64 flex items-center justify-center text-gray-500 italic">
                No financial activity recorded for this period.
            </div>
            <div v-if="overTime?.length" class="flex justify-between mt-4 text-[10px] text-gray-500 font-mono uppercase tracking-wider ml-12">
                <span>{{ overTime[0]?.date }}</span>
                <span>{{ overTime[overTime.length - 1]?.date }}</span>
            </div>
        </div>

        <!-- ROI Table -->
        <div class="glass-card overflow-hidden">
            <div class="p-6 border-b border-white/5">
                <h3 class="text-lg font-semibold text-white">Promotion Performance ROI</h3>
                <p class="text-gray-500 text-xs mt-1">Detailed breakdown of revenue generated vs. cost per promotion.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/5 text-xs text-gray-400 uppercase tracking-wider">
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-white" @click="toggleSort('name')">Promotion Name</th>
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-white" @click="toggleSort('redemptions')">Redemptions</th>
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-white" @click="toggleSort('revenue')">Revenue</th>
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-white" @click="toggleSort('cost')">Cost</th>
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-white" @click="toggleSort('roi')">ROI Ratio</th>
                            <th class="px-6 py-4 font-medium cursor-pointer hover:text-white" @click="toggleSort('efficiency')">Cost %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-for="promo in sortedPromotions" :key="promo.id" class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-white group-hover:text-primary-400 transition-colors">{{ promo.name }}</div>
                                <div class="text-[10px] text-gray-500 uppercase mt-0.5">{{ promo.type }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-300 font-mono">{{ formatNumber(promo.redemptions) }}</td>
                            <td class="px-6 py-4 text-sm text-emerald-400 font-mono">{{ formatCurrency(promo.revenue) }}</td>
                            <td class="px-6 py-4 text-sm text-rose-400 font-mono">{{ formatCurrency(promo.cost) }}</td>
                            <td class="px-6 py-4">
                                <span :class="[
                                    'px-2 py-1 rounded text-xs font-bold font-mono',
                                    promo.roi >= 3 ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' :
                                    promo.roi >= 1.5 ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' :
                                    'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                ]">
                                    {{ promo.roi }}x
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 bg-white/10 rounded-full overflow-hidden w-16">
                                        <div 
                                            class="h-full bg-primary-500 transition-all duration-500"
                                            :style="{ width: `${promo.efficiency}%` }"
                                        ></div>
                                    </div>
                                    <span class="text-xs text-gray-400 font-mono">{{ promo.efficiency }}%</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!sortedPromotions.length">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 italic">
                                No promotion financial data available for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

