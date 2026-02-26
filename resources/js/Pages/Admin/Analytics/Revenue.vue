<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    planRevenue: Array,
    series: Object,
});

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
const mrrMax = computed(() => Math.max(...(props.series?.mrr || []).map((row) => row.value), 1));
</script>

<template>
    <MainLayout>
        <Head title="Admin Revenue" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white">Revenue</h1>
                    <p class="text-gray-400 mt-1">Subscriptions and merch revenue insights</p>
                </div>
                <div class="flex gap-3">
                    <Link href="/admin/analytics" class="btn-secondary">Overview</Link>
                    <Link href="/admin/analytics/usage" class="btn-secondary">Usage</Link>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-emerald-400">{{ formatCurrency(stats?.mrr) }}</div>
                    <div class="text-gray-400 text-sm">MRR (Active Subs)</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-white">{{ stats?.active_subscriptions || 0 }}</div>
                    <div class="text-gray-400 text-sm">Active Subscriptions</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-blue-400">{{ stats?.trialing || 0 }}</div>
                    <div class="text-gray-400 text-sm">Trialing</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-amber-400">{{ formatCurrency(stats?.merch_revenue_30) }}</div>
                    <div class="text-gray-400 text-sm">Merch Revenue (30d)</div>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div class="glass-card p-6">
                    <h2 class="text-white font-semibold mb-4">MRR by Plan</h2>
                    <div v-if="planRevenue?.length" class="space-y-3">
                        <div v-for="row in planRevenue" :key="row.tier" class="flex items-center justify-between">
                            <div class="text-gray-300 capitalize">{{ row.tier }}</div>
                            <div class="text-right">
                                <div class="text-white font-medium">{{ formatCurrency(row.mrr) }}</div>
                                <div class="text-xs text-gray-500">{{ row.count }} subs</div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-gray-500">No subscription revenue yet.</div>
                </div>

                <div class="glass-card p-6">
                    <h2 class="text-white font-semibold mb-4">New MRR (Last 30 Days)</h2>
                    <div class="space-y-2">
                        <div v-for="(row, idx) in series?.mrr || []" :key="idx" class="flex items-center gap-3">
                            <div class="text-xs text-gray-500 w-12">{{ row.date }}</div>
                            <div class="h-2 flex-1 bg-white/10 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500" :style="{ width: Math.round((row.value / mrrMax) * 100) + '%' }"></div>
                            </div>
                            <div class="text-xs text-gray-400 w-14 text-right">{{ formatCurrency(row.value) }}</div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-gray-500">Green = new MRR by signup date</div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg font-medium hover:bg-white/20 transition-colors;
}
</style>


