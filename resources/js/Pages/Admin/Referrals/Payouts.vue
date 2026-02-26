<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    payouts: Object,
    filters: Object,
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
        processing: 'bg-blue-500/20 text-blue-400',
        completed: 'bg-emerald-500/20 text-emerald-400',
        failed: 'bg-red-500/20 text-red-400',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400';
};

const setStatusFilter = (status) => {
    router.get('/admin/referrals/payouts', { status }, { replace: true, preserveState: true });
};
</script>

<template>
    <Head title="Referral Payouts" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/referrals" class="hover:text-white">Referrals</Link>
                    <span>/</span>
                    <span class="text-white">Payouts</span>
                </div>
                <h1 class="text-3xl font-bold text-white">Referral Payouts</h1>
                <p class="text-gray-400 mt-1">{{ payouts?.total || 0 }} total payouts</p>
            </div>
            <Link href="/admin/referrals" class="btn-secondary">← Back to Referrals</Link>
        </div>

        <div class="glass-card p-4 mb-6">
            <div class="flex flex-wrap gap-2">
                <button class="btn-secondary" @click="setStatusFilter(undefined)">All</button>
                <button class="btn-secondary" @click="setStatusFilter('pending')">Pending</button>
                <button class="btn-secondary" @click="setStatusFilter('processing')">Processing</button>
                <button class="btn-secondary" @click="setStatusFilter('completed')">Completed</button>
                <button class="btn-secondary" @click="setStatusFilter('failed')">Failed</button>
            </div>
        </div>

        <div class="glass-card overflow-hidden">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Referrer</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Method</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Amount</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Status</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Requested</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-300">Processed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    <tr v-for="payout in payouts.data" :key="payout.id" class="hover:bg-white/5">
                        <td class="px-6 py-4">
                            <div class="text-white font-medium">{{ payout.user?.name || 'Unknown' }}</div>
                            <Link v-if="payout.user?.id" :href="`/admin/referrals/referrer/${payout.user.id}`" class="text-xs text-primary-400 hover:underline">
                                View referrer →
                            </Link>
                        </td>
                        <td class="px-6 py-4 text-gray-300">
                            <div class="text-sm capitalize">{{ payout.method }}</div>
                            <div class="text-xs text-gray-500 truncate max-w-[200px]">{{ payout.destination }}</div>
                        </td>
                        <td class="px-6 py-4 text-emerald-400 font-medium">
                            {{ formatCurrency(payout.amount) }}
                        </td>
                        <td class="px-6 py-4">
                            <span :class="getStatusColor(payout.status)" class="px-2 py-1 rounded text-xs font-medium capitalize">
                                {{ payout.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-400 text-sm">
                            {{ formatDate(payout.requested_at) }}
                        </td>
                        <td class="px-6 py-4 text-gray-400 text-sm">
                            {{ formatDate(payout.processed_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!payouts.data?.length" class="p-12 text-center">
                <p class="text-gray-500">No payouts found</p>
            </div>
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
