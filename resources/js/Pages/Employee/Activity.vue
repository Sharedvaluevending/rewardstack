<script setup>
import { Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    redemptions: Object, // paginator
    stats: Object,
});
</script>

<template>
    <Head title="Redemption Activity" />

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">🧾 Redemption Activity</h1>
            <p class="text-gray-400 text-sm mt-1">
                {{ stats?.total_redemptions || 0 }} total • {{ stats?.this_week || 0 }} this week • ${{ (stats?.total_savings || 0).toFixed ? (stats.total_savings).toFixed(2) : (stats?.total_savings || 0) }}
            </p>
        </div>

        <div v-if="redemptions?.data?.length" class="space-y-3">
            <div v-for="r in redemptions.data" :key="r.id" class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-white font-medium truncate">{{ r.promotion?.name || 'Redemption' }}</div>
                        <div class="text-gray-400 text-sm truncate">{{ r.qr_code?.name || r.qrCode?.name || '' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-emerald-400 font-bold">${{ r.discount_amount }}</div>
                        <div class="text-gray-500 text-xs">{{ r.redeemed_at }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-12">
            <div class="text-5xl mb-4">🧾</div>
            <h3 class="text-white font-semibold text-lg mb-2">No redemptions yet</h3>
            <p class="text-gray-400 text-sm">Your redemption activity will show up here.</p>
        </div>
    </div>
</template>

