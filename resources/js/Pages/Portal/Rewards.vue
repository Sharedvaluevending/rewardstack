<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    redeemedRewards: Array,
    promoRedemptions: Array,
    stats: Object,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};
</script>

<template>
    <Head title="My Redemption History" />

    <PortalLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Redemption History 📜</h1>
            <p class="text-gray-400 text-sm mt-1">View your past rewards and used promotions</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="bg-white/5 backdrop-blur rounded-2xl p-5 border border-white/10">
                <div class="text-3xl font-bold text-emerald-400">{{ stats.total_redeemed }}</div>
                <div class="text-gray-400 text-sm mt-1">Total Redemptions</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-2xl p-5 border border-white/10">
                <div class="text-3xl font-bold text-yellow-400">{{ formatCurrency(stats.total_savings) }}</div>
                <div class="text-gray-400 text-sm mt-1">Total Savings</div>
            </div>
        </div>

        <!-- Redeemed Game Rewards -->
        <div v-if="redeemedRewards.length > 0" class="mb-8">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <span>🎁 Won Rewards</span>
                <span class="text-xs font-normal text-gray-500 bg-white/5 px-2 py-0.5 rounded-full">{{ redeemedRewards.length }}</span>
            </h2>
            <div class="space-y-3">
                <div v-for="reward in redeemedRewards" :key="reward.id" 
                    class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center text-2xl flex-shrink-0">
                            🎁
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white font-medium truncate">{{ reward.description }}</div>
                            <div class="text-gray-400 text-xs">{{ reward.business?.name }} • {{ formatDate(reward.redeemed_at) }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="px-2 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 text-xs font-bold">REDEEMED</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promotion Redemptions -->
        <div v-if="promoRedemptions.length > 0" class="mb-8">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <span>🏷️ Used Promotions</span>
                <span class="text-xs font-normal text-gray-500 bg-white/5 px-2 py-0.5 rounded-full">{{ promoRedemptions.length }}</span>
            </h2>
            <div class="space-y-3">
                <div v-for="redemption in promoRedemptions" :key="redemption.id" 
                    class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-2xl flex-shrink-0">
                            🏷️
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white font-medium truncate">{{ redemption.promotion?.name || 'Promotion' }}</div>
                            <div class="text-gray-400 text-xs">{{ redemption.business?.name }} • {{ formatDate(redemption.redeemed_at) }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-emerald-400 font-bold">-{{ formatCurrency(redemption.discount_amount) }}</div>
                            <div class="text-[10px] text-gray-500 uppercase tracking-wider mt-0.5">SAVED</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="redeemedRewards.length === 0 && promoRedemptions.length === 0" class="text-center py-12">
            <div class="text-6xl mb-4">📜</div>
            <h3 class="text-xl font-semibold text-white mb-2">No History Yet</h3>
            <p class="text-gray-400 mb-6">Start redeeming your rewards and promotions to see them here!</p>
            <Link href="/portal/scans" class="inline-block px-6 py-3 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors">
                View My Scans
            </Link>
        </div>

        <div class="mt-8 text-center">
            <Link href="/portal/scans" class="text-gray-400 hover:text-white transition-colors flex items-center justify-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to My Scans
            </Link>
        </div>
    </PortalLayout>
</template>

