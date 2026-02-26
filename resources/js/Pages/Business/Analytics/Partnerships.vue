<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    metrics: Object,
    partnerPerformance: Array,
    crossPromos: Array,
});

const fmt = (n) => new Intl.NumberFormat('en-US').format(n || 0);
</script>

<template>
    <Head title="Partnership Analytics" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Partnership Analytics</h1>
                <p class="text-gray-400 mt-1">Cross-promo performance across your partner network.</p>
            </div>
            <Link
                href="/business/partnerships"
                class="px-4 py-2 rounded-xl bg-white/10 text-gray-200 hover:bg-white/20 transition-colors text-sm"
            >
                Go to Partnerships →
            </Link>
        </div>

        <!-- Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Partners</div>
                <div class="text-3xl font-bold text-white">{{ fmt(metrics?.total_partners) }}</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Active Cross-Promos</div>
                <div class="text-3xl font-bold text-white">{{ fmt(metrics?.active_cross_promos) }}</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Cross-Promo Scans</div>
                <div class="text-3xl font-bold text-white">{{ fmt(metrics?.total_scans) }}</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Claims</div>
                <div class="text-3xl font-bold text-white">{{ fmt(metrics?.total_claims) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Partner performance -->
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Top Partners (by scans)</h2>
                <div v-if="(partnerPerformance?.length || 0) === 0" class="text-gray-400 text-sm">
                    No partner data yet.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="row in partnerPerformance"
                        :key="row.partner.id"
                        class="flex items-center justify-between p-3 rounded-xl bg-white/5 border border-white/10"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div v-if="row.partner.logo" class="w-10 h-10 rounded-lg bg-white p-1 shrink-0">
                                <img :src="row.partner.logo" :alt="row.partner.name" class="w-full h-full object-contain" />
                            </div>
                            <div v-else class="w-10 h-10 rounded-lg bg-white/10 border border-white/10 shrink-0 flex items-center justify-center">
                                <span class="text-white font-bold">{{ row.partner.name?.charAt(0) }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-white font-medium truncate">{{ row.partner.name }}</div>
                                <div class="text-xs text-gray-400">
                                    {{ fmt(row.cross_promos_count) }} cross-promos
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-white font-semibold">{{ fmt(row.total_scans) }}</div>
                            <div class="text-xs text-gray-400">scans</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cross promo list -->
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Cross-Promos</h2>
                <div v-if="(crossPromos?.length || 0) === 0" class="text-gray-400 text-sm">
                    No cross-promos yet.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="cp in crossPromos"
                        :key="cp.id"
                        class="p-3 rounded-xl bg-white/5 border border-white/10 flex items-center justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <div class="text-white font-medium truncate">{{ cp.name }}</div>
                            <div class="text-xs text-gray-400 truncate">Partner: {{ cp.partner }}</div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span
                                class="px-2 py-1 rounded-lg text-xs border"
                                :class="cp.is_active ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : 'bg-white/5 text-gray-300 border-white/10'"
                            >
                                {{ cp.is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <Link
                                :href="`/business/partnerships/cross-promo/${cp.id}/analytics`"
                                class="px-3 py-1.5 rounded-lg bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 text-xs"
                            >
                                Analytics
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

