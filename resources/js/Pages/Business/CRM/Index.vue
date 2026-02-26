<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

const props = defineProps({
    stats: Object,
    recentCampaigns: Array,
});
</script>

<template>
    <Head title="CRM" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">CRM</h1>
                <p class="text-gray-400 text-sm mt-1">Email, segments, customers, and automations.</p>
            </div>
            <div class="flex gap-2">
                <Link href="/business/crm/campaigns/create" class="btn-primary text-sm">New Campaign</Link>
                <Link href="/business/crm/settings" class="px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 text-sm">
                    Settings
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">Subscribers</div>
                <div class="text-3xl font-bold text-white mt-1">{{ stats?.subscribers ?? 0 }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">Segments</div>
                <div class="text-3xl font-bold text-white mt-1">{{ stats?.segments ?? 0 }}</div>
            </div>
            <div class="glass-card p-5">
                <div class="text-xs text-gray-400">Campaigns</div>
                <div class="text-3xl font-bold text-white mt-1">{{ stats?.campaigns ?? 0 }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="glass-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-white font-semibold">Recent Campaigns</h2>
                    <Link href="/business/crm/campaigns" class="text-sm text-primary-300 hover:text-primary-200">View all →</Link>
                </div>
                <div v-if="(recentCampaigns || []).length" class="space-y-3">
                    <Link
                        v-for="c in recentCampaigns"
                        :key="c.id"
                        :href="`/business/crm/campaigns/${c.id}`"
                        class="block p-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-colors"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-white font-semibold truncate">{{ c.name }}</div>
                                <div class="text-xs text-gray-400 truncate">{{ c.subject }}</div>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full bg-white/10 border border-white/10 text-gray-200">
                                {{ c.status }}
                            </span>
                        </div>
                        <div class="mt-2 grid grid-cols-4 gap-2 text-xs text-gray-400">
                            <div><span class="text-gray-500">Sent</span> <span class="text-gray-200">{{ c.sent_total }}</span></div>
                            <div><span class="text-gray-500">Del</span> <span class="text-gray-200">{{ c.delivered_total }}</span></div>
                            <div><span class="text-gray-500">Open</span> <span class="text-gray-200">{{ c.open_total }}</span></div>
                            <div><span class="text-gray-500">Click</span> <span class="text-gray-200">{{ c.click_total }}</span></div>
                        </div>
                    </Link>
                </div>
                <div v-else class="text-gray-400 text-sm">
                    No campaigns yet.
                </div>
            </div>

            <div class="glass-card p-5">
                <h2 class="text-white font-semibold mb-3">Quick Links</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Link href="/business/crm/customers" class="p-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-colors">
                        <div class="text-white font-semibold">Customers</div>
                        <div class="text-xs text-gray-400 mt-1">Subscribed audience + engagement.</div>
                    </Link>
                    <Link href="/business/crm/segments" class="p-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-colors">
                        <div class="text-white font-semibold">Segments</div>
                        <div class="text-xs text-gray-400 mt-1">Target by scans, redemptions, level.</div>
                    </Link>
                    <Link href="/business/crm/automations" class="p-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-colors">
                        <div class="text-white font-semibold">Automations</div>
                        <div class="text-xs text-gray-400 mt-1">Winback, welcome, and nudges.</div>
                    </Link>
                    <Link href="/business/crm/settings" class="p-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-colors">
                        <div class="text-white font-semibold">Settings</div>
                        <div class="text-xs text-gray-400 mt-1">Sender + webhook health.</div>
                    </Link>
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

