<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    referrer: Object,
    referrals: Array,
    commissions: Object,
    payouts: Array,
    stats: Object,
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount || 0);
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString();
};

const getStatusColor = (status) => {
    const colors = {
        pending: 'bg-yellow-500/20 text-yellow-400',
        approved: 'bg-emerald-500/20 text-emerald-400',
        processing: 'bg-blue-500/20 text-blue-400',
        paid: 'bg-purple-500/20 text-purple-400',
        cancelled: 'bg-gray-500/20 text-gray-400',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400';
};
</script>

<template>
    <Head title="Referrer Details" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/referrals" class="hover:text-white">Referrals</Link>
                    <span>/</span>
                    <span class="text-white">{{ referrer?.name }}</span>
                </div>
                <h1 class="text-3xl font-bold text-white">{{ referrer?.name }}</h1>
                <p class="text-gray-400 mt-1">{{ referrer?.email }}</p>
            </div>
            <Link href="/admin/referrals" class="btn-secondary">← Back to Referrals</Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-emerald-400">{{ stats?.total_referrals || 0 }}</div>
                <div class="text-gray-400 text-sm">Total Referrals</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-blue-400">{{ stats?.active_referrals || 0 }}</div>
                <div class="text-gray-400 text-sm">Active Referrals</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-amber-400">{{ formatCurrency(stats?.pending_earnings) }}</div>
                <div class="text-gray-400 text-sm">Pending</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-purple-400">{{ formatCurrency(stats?.total_paid_out) }}</div>
                <div class="text-gray-400 text-sm">Paid Out</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Referrals</h2>
                <div v-if="referrals?.length" class="space-y-3">
                    <div v-for="ref in referrals" :key="ref.id" class="p-3 rounded-xl bg-white/5">
                        <div class="text-white font-medium">{{ ref.business?.name }}</div>
                        <div class="text-gray-400 text-sm">Status: {{ ref.status }}</div>
                    </div>
                </div>
                <div v-else class="text-gray-500">No referrals found.</div>
            </div>

            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Recent Payouts</h2>
                <div v-if="payouts?.length" class="space-y-3">
                    <div v-for="payout in payouts" :key="payout.id" class="p-3 rounded-xl bg-white/5">
                        <div class="flex items-center justify-between">
                            <div class="text-white font-medium">{{ formatCurrency(payout.amount) }}</div>
                            <span class="text-xs px-2 py-0.5 rounded-full" :class="getStatusColor(payout.status)">
                                {{ payout.status }}
                            </span>
                        </div>
                        <div class="text-gray-400 text-xs mt-1">{{ payout.method }} • {{ formatDate(payout.requested_at) }}</div>
                    </div>
                </div>
                <div v-else class="text-gray-500">No payouts found.</div>
            </div>
        </div>

        <div class="glass-card p-6 mt-6">
            <h2 class="text-lg font-semibold text-white mb-4">Commission History</h2>
            <div v-if="commissions?.data?.length" class="space-y-3">
                <div v-for="commission in commissions.data" :key="commission.id" class="p-3 rounded-xl bg-white/5">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-white text-sm">{{ commission.business?.name }}</div>
                            <div class="text-gray-500 text-xs">{{ commission.subscription_period }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-emerald-400 text-sm">{{ formatCurrency(commission.commission_amount) }}</div>
                            <span class="text-xs px-2 py-0.5 rounded-full" :class="getStatusColor(commission.status)">
                                {{ commission.status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-gray-500">No commissions found.</div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-lg rounded-xl border border-white/10;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors;
}
</style>
