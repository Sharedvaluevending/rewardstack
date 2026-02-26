<script setup>
import { Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    sendGridConfigured: Boolean,
    sender: Object,
    webhook: Object,
});
</script>

<template>
    <Head title="CRM Settings" />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">CRM Settings</h1>
            <p class="text-gray-400 text-sm mt-1">Email sender and webhook configuration.</p>
        </div>

        <div class="glass-card p-5 mb-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-white font-semibold">SendGrid API</div>
                    <div class="text-xs text-gray-400 mt-1">Used for CRM campaigns (not your transactional SMTP).</div>
                </div>
                <span
                    class="text-xs px-2 py-1 rounded-full border"
                    :class="sendGridConfigured ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' : 'bg-red-500/10 border-red-500/20 text-red-300'"
                >
                    {{ sendGridConfigured ? 'Configured' : 'Missing SENDGRID_API_KEY' }}
                </span>
            </div>
        </div>

        <div class="glass-card p-5 mb-4">
            <div class="text-white font-semibold mb-2">Sender</div>
            <div class="text-sm text-gray-300 space-y-1">
                <div><span class="text-gray-500">From name:</span> {{ sender?.from_name }}</div>
                <div><span class="text-gray-500">From email:</span> {{ sender?.from_email }}</div>
                <div><span class="text-gray-500">Reply-to:</span> {{ sender?.reply_to || '—' }}</div>
            </div>
            <div class="text-xs text-gray-500 mt-3">
                We send from the platform identity for deliverability; reply-to can be your business email.
            </div>
        </div>

        <div class="glass-card p-5">
            <div class="text-white font-semibold mb-2">Signed Event Webhook</div>
            <div class="text-sm text-gray-300">
                Endpoint: <span class="font-mono text-gray-200">{{ webhook?.endpoint }}</span>
            </div>
            <div class="text-xs text-gray-500 mt-2">
                Enable “Signed Event Webhook” in SendGrid and set <span class="font-mono">SENDGRID_EVENT_WEBHOOK_PUBLIC_KEY</span>.
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

