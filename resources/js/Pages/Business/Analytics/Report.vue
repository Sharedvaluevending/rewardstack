<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    businessName: { type: String, default: 'Business' },
    businessType: { type: String, default: 'Other' },
    period: { type: String, default: '30' },
    range: {
        type: Object,
        default: () => ({ start: null, end: null }),
    },
    generatedAt: { type: String, default: '' },
    stats: { type: Object, default: () => ({}) },
    scansOverTime: { type: Array, default: () => [] },
    redemptionsOverTime: { type: Array, default: () => [] },
    deviceBreakdown: { type: Array, default: () => [] },
    topQRCodes: { type: Array, default: () => [] },
    topPromotions: { type: Array, default: () => [] },
    hourlyDistribution: { type: Array, default: () => [] },
    locationBreakdown: { type: Array, default: () => [] },
    insights: { type: Array, default: () => [] },
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount || 0);
};

const formatNumber = (num) => new Intl.NumberFormat('en-US').format(num || 0);

const normalizedBusinessType = computed(() => (props.businessType || 'Other').trim().toLowerCase());

const reportTuning = computed(() => {
    const t = normalizedBusinessType.value;

    // Groupings (keep aligned with registration list)
    const isFood = ['restaurant', 'coffee shop', 'bakery', 'food truck', 'bar/nightclub'].includes(t);
    const isRetail = ['retail store'].includes(t);
    const isService = ['salon/spa', 'auto service'].includes(t);
    const isFitness = ['gym/fitness'].includes(t);

    if (isFood) {
        return {
            categoryLabel: 'Food & Beverage',
            redemptionsLabel: 'Offer redemptions',
            redemptionsHelp: 'How many guests redeemed an offer at checkout.',
            revenueLabel: 'Revenue captured',
            revenueHelp: 'Total final amount from redeemed offers (what you collected).',
            costLabel: 'Discount given (cost)',
            costHelp: 'Total discount amount across redemptions.',
            promotionsLabel: 'Top offers',
            promotionsSavingsLabel: 'Discount given',
            intro:
                'Use this report to spot which offers drive traffic, when guests scan most, and how much discount you’re giving to generate revenue.',
        };
    }

    if (isRetail) {
        return {
            categoryLabel: 'Retail',
            redemptionsLabel: 'Offer redemptions',
            redemptionsHelp: 'How many customers redeemed an offer at purchase.',
            revenueLabel: 'Revenue captured',
            revenueHelp: 'Total final amount from redeemed offers (what you collected).',
            costLabel: 'Discount given (cost)',
            costHelp: 'Total discount amount across redemptions.',
            promotionsLabel: 'Top offers',
            promotionsSavingsLabel: 'Discount given',
            intro:
                'Use this report to measure in-store engagement (scans), conversion (redemptions), and which offers move customers to purchase.',
        };
    }

    if (isService) {
        return {
            categoryLabel: 'Services',
            redemptionsLabel: 'Offer redemptions',
            redemptionsHelp: 'How many clients redeemed an offer at checkout.',
            revenueLabel: 'Revenue captured',
            revenueHelp: 'Total final amount from redeemed offers (what you collected).',
            costLabel: 'Discount given (cost)',
            costHelp: 'Total discount amount across redemptions.',
            promotionsLabel: 'Top offers',
            promotionsSavingsLabel: 'Discount given',
            intro:
                'Use this report to track client engagement, which offers convert, and how promotions impact revenue and discount cost.',
        };
    }

    if (isFitness) {
        return {
            categoryLabel: 'Fitness',
            redemptionsLabel: 'Reward/offer redemptions',
            redemptionsHelp: 'How many members redeemed an offer or reward.',
            revenueLabel: 'Revenue captured',
            revenueHelp: 'Total final amount from redeemed offers (what you collected).',
            costLabel: 'Discount given (cost)',
            costHelp: 'Total discount amount across redemptions.',
            promotionsLabel: 'Top offers/rewards',
            promotionsSavingsLabel: 'Discount given',
            intro:
                'Use this report to track member engagement (scans), rewards claimed (redemptions), and which offers drive the most action.',
        };
    }

    return {
        categoryLabel: 'General',
        redemptionsLabel: 'Redemptions',
        redemptionsHelp: 'How many times an offer was redeemed.',
        revenueLabel: 'Revenue generated',
        revenueHelp: 'Total final amount from redeemed offers (what you collected).',
        costLabel: 'Savings (cost to business)',
        costHelp: 'Total discount amount across redemptions.',
        promotionsLabel: 'Top promotions',
        promotionsSavingsLabel: 'Savings',
        intro:
            'Use this report to measure engagement (scans), conversion (redemptions), and the revenue/cost impact of your promotions.',
    };
});

const totalDevices = computed(() => {
    return (props.deviceBreakdown || []).reduce((sum, d) => sum + (d.value || 0), 0);
});

const topScanDay = computed(() => {
    if (!props.scansOverTime?.length) return null;
    return props.scansOverTime.reduce((best, cur) => ((cur.value || 0) > (best?.value || 0) ? cur : best), null);
});

const topRedemptionDay = computed(() => {
    if (!props.redemptionsOverTime?.length) return null;
    return props.redemptionsOverTime.reduce((best, cur) => ((cur.count || 0) > (best?.count || 0) ? cur : best), null);
});

const printReport = () => {
    window.print();
};
</script>

<template>
    <Head title="Analytics Report" />

    <div class="max-w-5xl mx-auto px-4 py-8 sm:px-6 lg:px-8 report-root">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white report-title">
                    Analytics Report
                </h1>
                <p class="text-gray-400 mt-1 report-subtitle">
                    {{ businessName }} • {{ reportTuning.categoryLabel }} • {{ period }} days • {{ range?.start }} → {{ range?.end }}
                </p>
                <p class="text-xs text-gray-500 mt-1 report-meta">
                    Generated {{ generatedAt }}
                </p>
            </div>

            <div class="flex items-center gap-2 no-print">
                <Link
                    :href="route('business.analytics', { period })"
                    class="px-3 py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 text-sm"
                >
                    Back
                </Link>
                <a
                    :href="route('business.analytics.export', { period, type: 'scans' })"
                    class="px-3 py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 text-sm"
                >
                    Scans CSV
                </a>
                <a
                    :href="route('business.analytics.export', { period, type: 'redemptions' })"
                    class="px-3 py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 text-sm"
                >
                    Redemptions CSV
                </a>
                <button
                    type="button"
                    class="px-3 py-2 rounded-lg bg-primary-500 text-white hover:bg-primary-600 text-sm font-medium"
                    @click="printReport"
                >
                    Print / Save PDF
                </button>
            </div>
        </div>

        <!-- Executive summary -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white mb-2">Executive Summary</h2>
                    <p class="text-sm text-gray-400 mb-3">
                        {{ reportTuning.intro }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-gray-300">
                        <div>
                            <span class="text-gray-500">Total scans:</span>
                            <span class="text-white font-semibold ml-1">{{ formatNumber(stats.total_scans) }}</span>
                            <span class="text-gray-500 ml-2">({{ formatNumber(stats.unique_scans) }} unique)</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ reportTuning.redemptionsLabel }}:</span>
                            <span class="text-white font-semibold ml-1">{{ formatNumber(stats.total_redemptions) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ reportTuning.revenueLabel }}:</span>
                            <span class="text-emerald-300 font-semibold ml-1">{{ formatCurrency(stats.total_revenue || 0) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ reportTuning.costLabel }}:</span>
                            <span class="text-rose-300 font-semibold ml-1">{{ formatCurrency(stats.total_savings || 0) }}</span>
                        </div>
                        <div v-if="topScanDay">
                            <span class="text-gray-500">Busiest scan day:</span>
                            <span class="text-white font-semibold ml-1">{{ topScanDay.date }}</span>
                            <span class="text-gray-500 ml-2">({{ formatNumber(topScanDay.value) }} scans)</span>
                        </div>
                        <div v-if="topRedemptionDay">
                            <span class="text-gray-500">Busiest redemption day:</span>
                            <span class="text-white font-semibold ml-1">{{ topRedemptionDay.date }}</span>
                            <span class="text-gray-500 ml-2">({{ formatNumber(topRedemptionDay.count) }} redemptions)</span>
                        </div>
                    </div>
                </div>

                <div class="hidden sm:block text-right text-xs text-gray-500">
                    <div>Active QR codes: <span class="text-gray-300">{{ formatNumber(stats.active_qr_codes || 0) }}</span></div>
                    <div>Active promotions: <span class="text-gray-300">{{ formatNumber(stats.active_promotions || 0) }}</span></div>
                    <div>Punch completion rate: <span class="text-gray-300">{{ stats.punch_completion_rate || 0 }}%</span></div>
                </div>
            </div>
        </div>

        <!-- KPI grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">Total Scans</div>
                <div class="text-2xl font-bold text-white mt-1">{{ formatNumber(stats.total_scans) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ formatNumber(stats.unique_scans) }} unique</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">{{ reportTuning.redemptionsLabel }}</div>
                <div class="text-2xl font-bold text-white mt-1">{{ formatNumber(stats.total_redemptions) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ reportTuning.redemptionsHelp }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">{{ reportTuning.revenueLabel }}</div>
                <div class="text-2xl font-bold text-emerald-300 mt-1">{{ formatCurrency(stats.total_revenue || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ reportTuning.revenueHelp }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">{{ reportTuning.costLabel }}</div>
                <div class="text-2xl font-bold text-rose-300 mt-1">{{ formatCurrency(stats.total_savings || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ reportTuning.costHelp }}</div>
            </div>
        </div>

        <!-- Performance tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="glass-card p-6">
                <h3 class="text-base font-semibold text-white mb-4">Top QR Codes</h3>
                <div class="overflow-hidden rounded-lg border border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5">
                            <tr class="text-left text-gray-400">
                                <th class="px-3 py-2">QR Code</th>
                                <th class="px-3 py-2">Placement</th>
                                <th class="px-3 py-2 text-right">Scans</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="qr in topQRCodes" :key="qr.id" class="border-t border-white/10">
                                <td class="px-3 py-2 text-white font-medium">{{ qr.name }}</td>
                                <td class="px-3 py-2 text-gray-400">{{ qr.placement || '—' }}</td>
                                <td class="px-3 py-2 text-right text-primary-300 font-semibold">{{ formatNumber(qr.scans) }}</td>
                            </tr>
                            <tr v-if="!topQRCodes?.length" class="border-t border-white/10">
                                <td colspan="3" class="px-3 py-6 text-center text-gray-500">No data yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass-card p-6">
                <h3 class="text-base font-semibold text-white mb-4">{{ reportTuning.promotionsLabel }}</h3>
                <div class="overflow-hidden rounded-lg border border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5">
                            <tr class="text-left text-gray-400">
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2 text-right">Redemptions</th>
                                <th class="px-3 py-2 text-right">{{ reportTuning.promotionsSavingsLabel }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in topPromotions" :key="p.id" class="border-t border-white/10">
                                <td class="px-3 py-2 text-white font-medium">{{ p.name }}</td>
                                <td class="px-3 py-2 text-right text-gray-200 font-semibold">{{ formatNumber(p.redemptions) }}</td>
                                <td class="px-3 py-2 text-right text-rose-300 font-semibold">{{ formatCurrency(p.savings || 0) }}</td>
                            </tr>
                            <tr v-if="!topPromotions?.length" class="border-t border-white/10">
                                <td colspan="3" class="px-3 py-6 text-center text-gray-500">No data yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="glass-card p-6">
                <h3 class="text-base font-semibold text-white mb-4">Device Breakdown</h3>
                <div class="overflow-hidden rounded-lg border border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5">
                            <tr class="text-left text-gray-400">
                                <th class="px-3 py-2">Device</th>
                                <th class="px-3 py-2 text-right">Scans</th>
                                <th class="px-3 py-2 text-right">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in deviceBreakdown" :key="d.name" class="border-t border-white/10">
                                <td class="px-3 py-2 text-white font-medium">{{ d.name }}</td>
                                <td class="px-3 py-2 text-right text-gray-200 font-semibold">{{ formatNumber(d.value) }}</td>
                                <td class="px-3 py-2 text-right text-gray-400">
                                    {{ totalDevices ? Math.round((d.value / totalDevices) * 100) : 0 }}%
                                </td>
                            </tr>
                            <tr v-if="!deviceBreakdown?.length" class="border-t border-white/10">
                                <td colspan="3" class="px-3 py-6 text-center text-gray-500">No data yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass-card p-6">
                <h3 class="text-base font-semibold text-white mb-4">Top Cities</h3>
                <div class="overflow-hidden rounded-lg border border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5">
                            <tr class="text-left text-gray-400">
                                <th class="px-3 py-2">City</th>
                                <th class="px-3 py-2 text-right">Scans</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in locationBreakdown" :key="c.city" class="border-t border-white/10">
                                <td class="px-3 py-2 text-white font-medium">{{ c.city }}</td>
                                <td class="px-3 py-2 text-right text-gray-200 font-semibold">{{ formatNumber(c.count) }}</td>
                            </tr>
                            <tr v-if="!locationBreakdown?.length" class="border-t border-white/10">
                                <td colspan="2" class="px-3 py-6 text-center text-gray-500">No location data yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AI Insights -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-white">Insights</h3>
                <span class="text-xs text-gray-500">Optional</span>
            </div>
            <div v-if="insights?.length" class="space-y-3">
                <div
                    v-for="insight in insights"
                    :key="insight.id || insight.title"
                    class="p-3 rounded-lg border border-white/10 bg-white/5"
                >
                    <div class="text-sm font-semibold text-white">{{ insight.title }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ insight.description }}</div>
                </div>
            </div>
            <div v-else class="text-sm text-gray-500">No insights available yet.</div>
        </div>

        <!-- Appendix -->
        <div class="glass-card p-6">
            <h3 class="text-base font-semibold text-white mb-2">Appendix (Daily Totals)</h3>
            <p class="text-xs text-gray-500 mb-4">
                These are raw daily totals used to build the charts (not screenshots).
            </p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="overflow-hidden rounded-lg border border-white/10">
                    <div class="px-3 py-2 bg-white/5 text-gray-400 text-sm font-medium">Scans by day</div>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="row in scansOverTime" :key="row.date" class="border-t border-white/10">
                                <td class="px-3 py-2 text-gray-300">{{ row.date }}</td>
                                <td class="px-3 py-2 text-right text-white font-semibold">{{ formatNumber(row.value) }}</td>
                            </tr>
                            <tr v-if="!scansOverTime?.length" class="border-t border-white/10">
                                <td colspan="2" class="px-3 py-6 text-center text-gray-500">No data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-hidden rounded-lg border border-white/10">
                    <div class="px-3 py-2 bg-white/5 text-gray-400 text-sm font-medium">Redemptions by day</div>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="row in redemptionsOverTime" :key="row.date" class="border-t border-white/10">
                                <td class="px-3 py-2 text-gray-300">{{ row.date }}</td>
                                <td class="px-3 py-2 text-right text-white font-semibold">{{ formatNumber(row.count) }}</td>
                            </tr>
                            <tr v-if="!redemptionsOverTime?.length" class="border-t border-white/10">
                                <td colspan="2" class="px-3 py-6 text-center text-gray-500">No data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    .no-print {
        display: none !important;
    }

    /* Make the printed version look like a real report */
    .report-root {
        max-width: none !important;
        padding: 0 !important;
        color: #111827 !important;
    }

    .report-title,
    .report-subtitle,
    .report-meta {
        color: #111827 !important;
    }

    /* Force white backgrounds & high contrast on print */
    .glass-card {
        background: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
    }

    table,
    th,
    td {
        border-color: #e5e7eb !important;
    }

    thead {
        background: #f9fafb !important;
    }

    th {
        color: #374151 !important;
    }

    td {
        color: #111827 !important;
    }
}
</style>

