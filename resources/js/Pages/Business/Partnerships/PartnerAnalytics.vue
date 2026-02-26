<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    partnership: Object,
    partner: Object,
    crossPromos: Array,
    metrics: Object,
});
</script>

<template>
    <Head :title="`Partner Analytics: ${partner.name}`" />

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <Link href="/business/partnerships" class="text-gray-400 hover:text-white mb-2 inline-block">
                ← Back to Partnerships
            </Link>
            <div class="flex items-center gap-4">
                <div v-if="partner.logo" class="w-16 h-16 rounded-xl bg-white p-2">
                    <img :src="partner.logo" :alt="partner.name" class="w-full h-full object-contain" />
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white">{{ partner.name }}</h1>
                    <p class="text-gray-400 mt-1">Partnership Analytics</p>
                </div>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Cross-Promos</div>
                <div class="text-3xl font-bold text-white">{{ metrics.total_cross_promos }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ metrics.active_cross_promos }} active</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Total Scans</div>
                <div class="text-3xl font-bold text-white">{{ metrics.total_scans }}</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Total Claims</div>
                <div class="text-3xl font-bold text-white">{{ metrics.total_claims }}</div>
            </div>
            <div class="glass-card p-6">
                <div class="text-gray-400 text-sm mb-1">Partnership Since</div>
                <div class="text-lg font-bold text-white">{{ new Date(partnership.created_at).toLocaleDateString() }}</div>
            </div>
        </div>

        <!-- Cross-Promos List -->
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Cross-Promotions</h3>
            <div v-if="crossPromos.length === 0" class="text-center py-8 text-gray-400">
                No cross-promotions yet
            </div>
            <div v-else class="space-y-3">
                <div v-for="cp in crossPromos" :key="cp.id" class="p-4 rounded-xl bg-white/5 border border-white/10 flex items-center justify-between">
                    <div>
                        <h4 class="text-white font-semibold">{{ cp.name }}</h4>
                        <p class="text-gray-400 text-sm">
                            Status: <span :class="cp.is_active ? 'text-emerald-400' : 'text-gray-500'">
                                {{ cp.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </p>
                    </div>
                    <Link :href="`/business/partnerships/cross-promo/${cp.id}/analytics`" 
                        class="px-4 py-2 bg-primary-500/20 text-primary-400 rounded-lg hover:bg-primary-500/30 text-sm">
                        View Analytics
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
