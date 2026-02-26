<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    crossPromo: Object,
    partner: Object,
    my_promotion: Object,
    partner_promotion: Object,
    analytics: Object,
    dailyMetrics: Array,
    revenueShare: Object,
});
</script>

<template>
    <Head :title="`Analytics: ${crossPromo.name}`" />

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <Link href="/business/partnerships" class="text-gray-400 hover:text-white mb-2 inline-block">
                    ← Back to Partnerships
                </Link>
                <h1 class="text-3xl font-bold text-white">{{ crossPromo.name }}</h1>
                <p class="text-gray-400 mt-1">Partner Deal Chain Analytics</p>
            </div>
        </div>

        <!-- Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Total Scans</div>
                <div class="text-3xl font-bold text-white">{{ analytics.overview.total_scans }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ analytics.overview.unique_scans }} unique</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Total Claims</div>
                <div class="text-3xl font-bold text-white">{{ analytics.overview.total_claims }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ analytics.overview.scan_to_claim_rate }}% conversion</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Total Redemptions</div>
                <div class="text-3xl font-bold text-white">{{ analytics.overview.total_redemptions }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ analytics.overview.scan_to_redemption_rate }}% conversion</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Claim → Redeem</div>
                <div class="text-3xl font-bold text-white">{{ analytics.overview.claim_to_redemption_rate }}%</div>
                <div class="text-xs text-gray-500 mt-1">Redemption rate</div>
            </div>
        </div>

        <!-- Chain Completion (if sequential) -->
        <div v-if="analytics.chain_metrics" class="glass-card p-6 mb-8">
            <h3 class="text-lg font-semibold text-white mb-4">Sequential Chain Performance</h3>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <div class="text-gray-400 text-sm mb-1">Chain Completions</div>
                    <div class="text-2xl font-bold text-white">{{ analytics.chain_metrics.chain_completions }}</div>
                    <div class="text-xs text-gray-500 mt-1">Users who redeemed both offers</div>
                </div>
                <div>
                    <div class="text-gray-400 text-sm mb-1">Completion Rate</div>
                    <div class="text-2xl font-bold text-white">{{ analytics.chain_metrics.chain_completion_rate }}%</div>
                    <div class="text-xs text-gray-500 mt-1">Of users who redeemed primary</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <div class="text-gray-400 text-sm mb-2">Primary Offer</div>
                    <div class="space-y-1 text-sm text-gray-300">
                        <div class="flex justify-between"><span>Claims</span><span class="text-white font-semibold">{{ analytics.chain_metrics.primary_claims }}</span></div>
                        <div class="flex justify-between"><span>Redemptions</span><span class="text-white font-semibold">{{ analytics.chain_metrics.primary_redemptions }}</span></div>
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <div class="text-gray-400 text-sm mb-2">Secondary Offer</div>
                    <div class="space-y-1 text-sm text-gray-300">
                        <div class="flex justify-between"><span>Claims</span><span class="text-white font-semibold">{{ analytics.chain_metrics.secondary_claims }}</span></div>
                        <div class="flex justify-between"><span>Redemptions</span><span class="text-white font-semibold">{{ analytics.chain_metrics.secondary_redemptions }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promotion Comparison -->
        <div class="glass-card p-6 mb-8">
            <h3 class="text-lg font-semibold text-white mb-4">Promotion Performance Comparison</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <!-- Your Promotion -->
                <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                    <div class="text-emerald-400 text-sm font-medium mb-2">Your Promotion</div>
                    <div class="text-white font-semibold mb-4">{{ my_promotion?.name || 'Not set' }}</div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Claims:</span>
                            <span class="text-white font-medium">{{ analytics.by_promotion.promotion_1.claims }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Redemptions:</span>
                            <span class="text-white font-medium">{{ analytics.by_promotion.promotion_1.redemptions }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Conversion:</span>
                            <span class="text-white font-medium">{{ analytics.by_promotion.promotion_1.conversion_rate }}%</span>
                        </div>
                    </div>
                </div>

                <!-- Partner Promotion -->
                <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
                    <div class="text-blue-400 text-sm font-medium mb-2">Partner: {{ partner.name }}</div>
                    <div class="text-white font-semibold mb-4">{{ partner_promotion?.name || 'Not set' }}</div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Claims:</span>
                            <span class="text-white font-medium">{{ analytics.by_promotion.promotion_2.claims }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Redemptions:</span>
                            <span class="text-white font-medium">{{ analytics.by_promotion.promotion_2.redemptions }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Conversion:</span>
                            <span class="text-white font-medium">{{ analytics.by_promotion.promotion_2.conversion_rate }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Business Breakdown -->
        <div class="glass-card p-6 mb-8">
            <h3 class="text-lg font-semibold text-white mb-4">Business Breakdown</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <div class="text-gray-400 text-sm mb-2">{{ analytics.business_breakdown.business_1.name || 'Business 1' }}</div>
                    <div class="space-y-2 text-sm text-gray-300">
                        <div class="flex justify-between"><span>Scans</span><span class="text-white font-semibold">{{ analytics.business_breakdown.business_1.scans }}</span></div>
                        <div class="flex justify-between"><span>Claims</span><span class="text-white font-semibold">{{ analytics.business_breakdown.business_1.claims }}</span></div>
                        <div class="flex justify-between"><span>Redemptions</span><span class="text-white font-semibold">{{ analytics.business_breakdown.business_1.redemptions }}</span></div>
                        <div class="flex justify-between"><span>Scan → Claim</span><span class="text-emerald-400 font-semibold">{{ analytics.business_breakdown.business_1.scan_to_claim_rate }}%</span></div>
                        <div class="flex justify-between"><span>Claim → Redeem</span><span class="text-emerald-400 font-semibold">{{ analytics.business_breakdown.business_1.claim_to_redemption_rate }}%</span></div>
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <div class="text-gray-400 text-sm mb-2">{{ analytics.business_breakdown.business_2.name || 'Business 2' }}</div>
                    <div class="space-y-2 text-sm text-gray-300">
                        <div class="flex justify-between"><span>Scans</span><span class="text-white font-semibold">{{ analytics.business_breakdown.business_2.scans }}</span></div>
                        <div class="flex justify-between"><span>Claims</span><span class="text-white font-semibold">{{ analytics.business_breakdown.business_2.claims }}</span></div>
                        <div class="flex justify-between"><span>Redemptions</span><span class="text-white font-semibold">{{ analytics.business_breakdown.business_2.redemptions }}</span></div>
                        <div class="flex justify-between"><span>Scan → Claim</span><span class="text-emerald-400 font-semibold">{{ analytics.business_breakdown.business_2.scan_to_claim_rate }}%</span></div>
                        <div class="flex justify-between"><span>Claim → Redeem</span><span class="text-emerald-400 font-semibold">{{ analytics.business_breakdown.business_2.claim_to_redemption_rate }}%</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="glass-card p-6 mb-8">
            <h3 class="text-lg font-semibold text-white mb-4">Recent Activity (Last 30 Days)</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <div class="text-gray-400 text-sm mb-1">Scans</div>
                    <div class="text-2xl font-bold text-white">{{ analytics.recent_activity.scans_last_30_days }}</div>
                </div>
                <div>
                    <div class="text-gray-400 text-sm mb-1">Claims</div>
                    <div class="text-2xl font-bold text-white">{{ analytics.recent_activity.claims_last_30_days }}</div>
                </div>
                <div>
                    <div class="text-gray-400 text-sm mb-1">Redemptions</div>
                    <div class="text-2xl font-bold text-white">{{ analytics.recent_activity.redemptions_last_30_days }}</div>
                </div>
            </div>
        </div>

        <!-- Revenue Share -->
        <div v-if="revenueShare" class="glass-card p-6">
            <h3 class="text-lg font-semibold text-white mb-4">💰 Revenue Share</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                    <div class="text-emerald-400 text-sm font-medium mb-2">Your Revenue</div>
                    <div class="text-2xl font-bold text-white">${{ revenueShare.my_revenue }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ revenueShare.my_redemptions_count }} redemptions</div>
                </div>
                <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
                    <div class="text-blue-400 text-sm font-medium mb-2">Partner Revenue</div>
                    <div class="text-2xl font-bold text-white">${{ revenueShare.partner_revenue }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ revenueShare.partner_redemptions_count }} redemptions</div>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-white/10">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Total Revenue:</span>
                    <span class="text-xl font-bold text-white">${{ revenueShare.total_revenue }}</span>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <span class="text-gray-400">Revenue Share Split:</span>
                    <span class="text-white">{{ revenueShare.revenue_share_percent }}% / {{ 100 - revenueShare.revenue_share_percent }}%</span>
                </div>
            </div>
        </div>
    </div>
</template>
