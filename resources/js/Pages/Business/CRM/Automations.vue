<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    automations: {
        type: Array,
        default: () => [],
    },
    sendGridConfigured: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const runNow = () => {
    router.post('/business/crm/automations/run', {}, { preserveScroll: true });
};

const toggle = (id) => {
    router.post(`/business/crm/automations/${id}/toggle`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="CRM Automations" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Automations</h1>
                <p class="text-gray-400 text-sm mt-1">Winback, punch card nudges, and expiring-offer reminders.</p>
            </div>
            <button type="button" class="btn-primary text-sm" :disabled="!sendGridConfigured" @click="runNow">
                Run now
            </button>
        </div>

        <div v-if="page.props?.flash?.success" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ page.props.flash.success }}
        </div>
        <div v-if="!sendGridConfigured" class="mb-4 p-3 rounded-xl bg-yellow-500/10 border border-yellow-500/20 text-yellow-200 text-sm">
            SendGrid API is not configured. Set <span class="font-mono">SENDGRID_API_KEY</span> to send automation emails.
        </div>

        <div class="glass-card overflow-hidden">
            <div class="p-5 border-b border-white/10">
                <div class="text-white font-semibold">Active automations</div>
                <div class="text-xs text-gray-400 mt-1">All automations only email customers who explicitly opted in.</div>
            </div>
            <div class="p-5 space-y-3">
                <div v-for="a in (automations || [])" :key="a.id" class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-white font-semibold">{{ a.name }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                Trigger: <span class="text-gray-200">{{ a.trigger }}</span>
                                <span v-if="a.trigger === 'winback' && a.config?.days"> • after {{ a.config.days }} days away</span>
                                <span v-if="a.trigger === 'punch_card_nudge' && a.config?.inactive_days"> • inactive {{ a.config.inactive_days }} days</span>
                                <span v-if="a.trigger === 'promo_expiring' && a.config?.days"> • expiring in {{ a.config.days }} days</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="px-3 py-2 rounded-lg text-sm border transition-colors"
                            :class="a.is_active ? 'bg-emerald-500/15 text-emerald-200 border-emerald-500/20 hover:bg-emerald-500/25' : 'bg-white/10 text-gray-200 border-white/10 hover:bg-white/20'"
                            @click="toggle(a.id)"
                        >
                            {{ a.is_active ? 'Enabled' : 'Disabled' }}
                        </button>
                    </div>
                </div>

                <div v-if="!(automations || []).length" class="text-gray-400 text-sm">
                    No automations yet.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

