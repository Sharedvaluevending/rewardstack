<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    campaigns: Object, // paginator
});

const sendingId = ref(null);
const deletingId = ref(null);

const queueSend = (campaignId) => {
    if (!campaignId) return;
    sendingId.value = campaignId;
    router.post(`/business/crm/campaigns/${campaignId}/queue`, {}, { preserveScroll: true, onFinish: () => (sendingId.value = null) });
};

const deleteCampaign = (campaignId) => {
    if (!campaignId) return;
    if (!confirm('Delete this campaign? This removes queued messages too.')) return;
    deletingId.value = campaignId;
    router.delete(`/business/crm/campaigns/${campaignId}`, { preserveScroll: true, onFinish: () => (deletingId.value = null) });
};
</script>

<template>
    <Head title="CRM Campaigns" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Campaigns</h1>
                <p class="text-gray-400 text-sm mt-1">Create and send email campaigns to subscribed customers.</p>
            </div>
            <Link href="/business/crm/campaigns/create" class="btn-primary text-sm">New Campaign</Link>
        </div>

        <div class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5">
                        <tr class="text-left text-gray-300">
                            <th class="px-5 py-3 font-semibold">Campaign</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Recipients</th>
                            <th class="px-5 py-3 font-semibold">Sent</th>
                            <th class="px-5 py-3 font-semibold">Delivered</th>
                            <th class="px-5 py-3 font-semibold">Open</th>
                            <th class="px-5 py-3 font-semibold">Click</th>
                    <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in (campaigns?.data || [])" :key="c.id" class="border-t border-white/5">
                            <td class="px-5 py-3">
                                <Link :href="`/business/crm/campaigns/${c.id}`" class="text-white font-semibold hover:text-primary-200">
                                    {{ c.name }}
                                </Link>
                                <div class="text-xs text-gray-400">{{ c.subject }}</div>
                                <div class="text-[11px] text-emerald-300" v-if="c.promotion">
                                    Promotion: {{ c.promotion.name }}
                                </div>
                            </td>
                            <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-1 rounded-full bg-white/10 border border-white/10 text-gray-200">{{ c.status }}</span>
                            <span
                                v-if="c.is_automation"
                                class="text-[11px] px-2 py-1 rounded-full bg-amber-500/15 border border-amber-400/30 text-amber-200"
                            >
                                Automated
                            </span>
                        </div>
                            </td>
                            <td class="px-5 py-3 text-gray-300">{{ c.recipients_total }}</td>
                            <td class="px-5 py-3 text-gray-300">{{ c.sent_total }}</td>
                            <td class="px-5 py-3 text-gray-300">{{ c.delivered_total }}</td>
                            <td class="px-5 py-3 text-gray-300">{{ c.open_total }}</td>
                            <td class="px-5 py-3 text-gray-300">{{ c.click_total }}</td>
                    <td class="px-5 py-3 text-gray-300">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="!c.is_automation && c.status === 'draft'"
                                type="button"
                                class="px-3 py-1 rounded-lg bg-primary-500 text-white text-xs font-semibold hover:bg-primary-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="sendingId === c.id"
                                @click="queueSend(c.id)"
                            >
                                {{ sendingId === c.id ? 'Sending…' : 'Send now' }}
                            </button>
                            <button
                                v-if="!c.is_automation"
                                type="button"
                                class="px-3 py-1 rounded-lg bg-white/10 text-gray-100 text-xs font-semibold border border-white/20 hover:bg-white/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="deletingId === c.id"
                                @click="deleteCampaign(c.id)"
                            >
                                {{ deletingId === c.id ? 'Deleting…' : 'Delete' }}
                            </button>
                        </div>
                    </td>
                        </tr>
                        <tr v-if="!(campaigns?.data || []).length">
                    <td colspan="8" class="px-5 py-10 text-center text-gray-400">No campaigns yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="campaigns?.last_page > 1" class="p-5 border-t border-white/10 flex justify-center">
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="campaigns.current_page > 1"
                        :href="campaigns.prev_page_url"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors text-sm"
                        preserve-scroll
                    >
                        Previous
                    </Link>
                    <span class="px-4 py-2 text-gray-400 text-sm">
                        Page {{ campaigns.current_page }} of {{ campaigns.last_page }}
                    </span>
                    <Link
                        v-if="campaigns.current_page < campaigns.last_page"
                        :href="campaigns.next_page_url"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors text-sm"
                        preserve-scroll
                    >
                        Next
                    </Link>
                </nav>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

