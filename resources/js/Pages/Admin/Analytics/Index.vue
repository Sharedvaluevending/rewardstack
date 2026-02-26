<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    planMix: Array,
    series: Object,
});

const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value || 0);
const scanMax = computed(() => Math.max(...(props.series?.scans || []).map((row) => row.value), 1));
</script>

<template>
    <MainLayout>
        <Head title="Admin Analytics" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white">Admin Analytics</h1>
                    <p class="text-gray-400 mt-1">Platform-wide overview</p>
                </div>
                <div class="flex gap-3">
                    <Link href="/admin/analytics/revenue" class="btn-secondary">Revenue</Link>
                    <Link href="/admin/analytics/usage" class="btn-secondary">Usage</Link>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-white">{{ formatNumber(stats?.total_businesses) }}</div>
                    <div class="text-gray-400 text-sm">Total Businesses</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-emerald-400">{{ formatNumber(stats?.active_businesses) }}</div>
                    <div class="text-gray-400 text-sm">Active Businesses</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-white">{{ formatNumber(stats?.total_users) }}</div>
                    <div class="text-gray-400 text-sm">Total Users</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-purple-400">{{ formatNumber(stats?.new_users_30) }}</div>
                    <div class="text-gray-400 text-sm">New Users (30d)</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-amber-400">{{ formatNumber(stats?.total_scans) }}</div>
                    <div class="text-gray-400 text-sm">Total Scans</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-blue-400">{{ formatNumber(stats?.scans_30) }}</div>
                    <div class="text-gray-400 text-sm">Scans (30d)</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-emerald-400">{{ formatNumber(stats?.redemptions_30) }}</div>
                    <div class="text-gray-400 text-sm">Redemptions (30d)</div>
                </div>
                <div class="glass-card p-4">
                    <div class="text-2xl font-bold text-gray-200">{{ formatNumber(planMix?.length) }}</div>
                    <div class="text-gray-400 text-sm">Plan Tiers</div>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div class="glass-card p-6">
                    <h2 class="text-white font-semibold mb-4">Plan Mix</h2>
                    <div v-if="planMix?.length" class="space-y-3">
                        <div v-for="row in planMix" :key="row.tier" class="flex items-center justify-between">
                            <div class="text-gray-300 capitalize">{{ row.tier }}</div>
                            <div class="text-white font-medium">{{ formatNumber(row.count) }}</div>
                        </div>
                    </div>
                    <div v-else class="text-gray-500">No plan data yet.</div>
                </div>

                <div class="glass-card p-6">
                    <h2 class="text-white font-semibold mb-4">Activity (Last 14 Days)</h2>
                    <div class="space-y-2">
                        <div v-for="(row, idx) in series?.scans || []" :key="idx" class="flex items-center gap-3">
                            <div class="text-xs text-gray-500 w-12">{{ row.date }}</div>
                            <div class="h-2 flex-1 bg-white/10 rounded-full overflow-hidden">
                                <div class="h-full bg-purple-500" :style="{ width: Math.round((row.value / scanMax) * 100) + '%' }"></div>
                            </div>
                            <div class="text-xs text-gray-400 w-10 text-right">{{ row.value }}</div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-gray-500">Purple = scans</div>
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


