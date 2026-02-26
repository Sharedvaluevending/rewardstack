<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

defineOptions({
    layout: PortalLayout,
});

const props = defineProps({
    crossPromos: Array,
});
</script>

<template>
    <Head title="Partner Deals" />

    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold text-white mb-6">🤝 Partner Deal Chains</h1>

        <div v-if="crossPromos.length === 0" class="glass-card p-12 text-center">
            <div class="w-20 h-20 rounded-full bg-white/5 flex items-center justify-center mx-auto mb-4">
                <span class="text-4xl">🤝</span>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">No Partner Deals Yet</h3>
            <p class="text-gray-400">Scan QR codes from partner businesses to see their deals here!</p>
        </div>

        <div v-else class="space-y-4">
            <div 
                v-for="cp in crossPromos" 
                :key="cp.id"
                class="glass-card p-6"
            >
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-white">{{ cp.name }}</h3>
                        <p class="text-gray-400 text-sm">Code: {{ cp.code }}</p>
                    </div>
                    <Link 
                        v-if="cp.qr_code"
                        :href="`/s/${cp.qr_code.code}`"
                        class="px-4 py-2 bg-primary-500/20 text-primary-400 rounded-lg hover:bg-primary-500/30 text-sm"
                    >
                        View Deal
                    </Link>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <!-- Business 1 -->
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                        <div class="flex items-center gap-3 mb-2">
                            <div v-if="cp.business1.logo" class="w-10 h-10 rounded-lg bg-white p-1">
                                <img :src="cp.business1.logo" :alt="cp.business1.name" class="w-full h-full object-contain" />
                            </div>
                            <div class="text-emerald-400 text-sm font-medium">{{ cp.business1.name }}</div>
                        </div>
                        <div class="text-white font-semibold">{{ cp.promotion1.name }}</div>
                        <div class="text-gray-300 text-sm">{{ cp.promotion1.display_value }}</div>
                    </div>

                    <!-- Business 2 -->
                    <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
                        <div class="flex items-center gap-3 mb-2">
                            <div v-if="cp.business2.logo" class="w-10 h-10 rounded-lg bg-white p-1">
                                <img :src="cp.business2.logo" :alt="cp.business2.name" class="w-full h-full object-contain" />
                            </div>
                            <div class="text-blue-400 text-sm font-medium">{{ cp.business2.name }}</div>
                        </div>
                        <div class="text-white font-semibold">{{ cp.promotion2.name }}</div>
                        <div class="text-gray-300 text-sm">{{ cp.promotion2.display_value }}</div>
                    </div>
                </div>

                <div v-if="cp.chain_mode === 'sequential'" class="mt-4 p-3 rounded-lg bg-purple-500/10 border border-purple-500/30">
                    <p class="text-purple-300 text-sm">
                        🔗 Sequential Chain: Redeem the first offer to unlock the second!
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
