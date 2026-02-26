<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    campaign: Object,
    recentMessages: Array,
});

const page = usePage();

const queueSend = () => {
    router.post(`/business/crm/campaigns/${props.campaign.id}/queue`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Campaign: ${campaign?.name || ''}`" />

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ campaign?.name }}</h1>
                <p class="text-gray-400 text-sm mt-1">{{ campaign?.subject }}</p>
            </div>
            <div class="flex gap-2">
                <Link href="/business/crm/campaigns" class="px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 text-sm">
                    Back
                </Link>
                <Link
                    v-if="campaign?.status === 'draft'"
                    :href="`/business/crm/campaigns/${campaign.id}/edit`"
                    class="px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 text-sm"
                >
                    Edit
                </Link>
                <button
                    type="button"
                    class="btn-primary text-sm"
                    @click="queueSend"
                    :disabled="campaign?.status === 'sending' || campaign?.status === 'sent'"
                >
                    {{ campaign?.status === 'sending' ? 'Sending…' : 'Send now' }}
                </button>
            </div>
        </div>

        <div v-if="page.props?.flash?.success" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props?.errors?.status" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            {{ page.props.errors.status }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="glass-card p-5 lg:col-span-1">
                <div class="text-white font-semibold mb-3">Details</div>
                <div class="text-sm text-gray-300 space-y-2">
                    <div><span class="text-gray-500">Status:</span> {{ campaign?.status }}</div>
                    <div><span class="text-gray-500">Segment:</span> {{ campaign?.segment?.name || 'All subscribers' }}</div>
                    <div><span class="text-gray-500">Promotion:</span> {{ campaign?.promotion?.name || 'None attached' }}</div>
                    <div><span class="text-gray-500">Created:</span> {{ campaign?.created_at }}</div>
                </div>
            </div>

            <div class="glass-card p-5 lg:col-span-2">
                <div class="text-white font-semibold mb-3">Metrics</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-xs text-gray-400">Recipients</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ campaign?.recipients_total ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-xs text-gray-400">Sent</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ campaign?.sent_total ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-xs text-gray-400">Delivered</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ campaign?.delivered_total ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-xs text-gray-400">Opens</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ campaign?.open_total ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-xs text-gray-400">Clicks</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ campaign?.click_total ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-xs text-gray-400">Bounces</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ campaign?.bounce_total ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-xs text-gray-400">Unsubs</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ campaign?.unsubscribe_total ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card p-5 mt-6">
            <div class="text-white font-semibold mb-3">Recent messages</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5">
                        <tr class="text-left text-gray-300">
                            <th class="px-4 py-2 font-semibold">Email</th>
                            <th class="px-4 py-2 font-semibold">Status</th>
                            <th class="px-4 py-2 font-semibold">Sent</th>
                            <th class="px-4 py-2 font-semibold">Last event</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in (recentMessages || [])" :key="m.id" class="border-t border-white/5">
                            <td class="px-4 py-2 text-gray-200">{{ m.email }}</td>
                            <td class="px-4 py-2">
                                <span class="text-xs px-2 py-1 rounded-full bg-white/10 border border-white/10 text-gray-200">
                                    {{ m.status }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-300">{{ m.sent_at || '—' }}</td>
                            <td class="px-4 py-2 text-gray-300">{{ m.last_event_at || '—' }}</td>
                        </tr>
                        <tr v-if="!(recentMessages || []).length">
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">No messages yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

