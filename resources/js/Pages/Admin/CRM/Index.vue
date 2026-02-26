<script setup>
import { Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    stats: Object,
});

const fmt = (iso) => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
};
</script>

<template>
    <Head title="Admin CRM" />

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">CRM Oversight</h1>
            <p class="text-gray-400 text-sm mt-1">Deliverability signals and webhook health.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">SendGrid webhook last seen</div>
                <div class="text-white font-semibold mt-1">{{ fmt(stats?.last_webhook_at) }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">CRM event last seen</div>
                <div class="text-white font-semibold mt-1">{{ fmt(stats?.last_event_at) }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">Events (24h)</div>
                <div class="text-3xl font-bold text-white mt-1">{{ stats?.events_24h ?? 0 }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">Bounces (24h)</div>
                <div class="text-3xl font-bold text-white mt-1">{{ stats?.bounces_24h ?? 0 }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">Spam reports (24h)</div>
                <div class="text-3xl font-bold text-white mt-1">{{ stats?.spam_24h ?? 0 }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">Unsubscribes (24h)</div>
                <div class="text-3xl font-bold text-white mt-1">{{ stats?.unsubs_24h ?? 0 }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

