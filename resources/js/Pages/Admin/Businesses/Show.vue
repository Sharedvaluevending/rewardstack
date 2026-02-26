<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({ layout: MainLayout });

defineProps({
    business: Object,
});

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString();
};

const getSubscriptionStatusColor = (status) => {
    const colors = {
        active: 'bg-emerald-500/20 text-emerald-400',
        trialing: 'bg-blue-500/20 text-blue-400',
        past_due: 'bg-amber-500/20 text-amber-400',
        canceled: 'bg-red-500/20 text-red-400',
        incomplete: 'bg-gray-500/20 text-gray-400',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400';
};

const toggleStatus = (business) => {
    router.post(`/admin/businesses/${business.id}/toggle`);
};

const toggleTestingAccount = (business) => {
    router.post(`/admin/businesses/${business.id}/toggle-testing`);
};

const impersonate = (business) => {
    router.post(`/admin/businesses/${business.id}/impersonate`);
};
</script>

<template>
    <Head :title="`${business?.name || 'Business'} - Admin`" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <Link href="/admin/businesses" class="hover:text-white">Businesses</Link>
                    <span>/</span>
                    <span class="text-white">{{ business?.name }}</span>
                </div>
                <h1 class="text-3xl font-bold text-white">{{ business?.name }}</h1>
                <p class="text-gray-400 mt-1">{{ business?.slug }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/admin/businesses" class="btn-secondary">← Back to Businesses</Link>
                <Link :href="`/admin/businesses/${business?.id}/edit`" class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors text-sm">
                    Edit
                </Link>
                <button
                    v-if="business?.owner"
                    @click="impersonate(business)"
                    class="px-4 py-2 bg-primary-500/20 text-primary-400 rounded-lg hover:bg-primary-500/30 transition-colors text-sm"
                >
                    View as Owner
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-emerald-400">{{ business?.qr_codes_count ?? 0 }}</div>
                <div class="text-gray-400 text-sm">QR Codes</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-blue-400">{{ business?.promotions_count ?? 0 }}</div>
                <div class="text-gray-400 text-sm">Promotions</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-amber-400">{{ business?.scans_count ?? 0 }}</div>
                <div class="text-gray-400 text-sm">Total Scans</div>
            </div>
            <div class="glass-card p-4">
                <div class="text-2xl font-bold text-purple-400">{{ business?.redemptions_count ?? 0 }}</div>
                <div class="text-gray-400 text-sm">Redemptions</div>
            </div>
        </div>

        <!-- Business Info & Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Business Details</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Status</span>
                        <button
                            @click="toggleStatus(business)"
                            :class="business?.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'"
                            class="px-2 py-1 rounded text-xs font-medium"
                        >
                            {{ business?.is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Plan</span>
                        <span class="px-2 py-1 rounded text-xs font-medium bg-primary-500/20 text-primary-400">
                            {{ business?.subscription_plan || business?.subscription_tier || 'Free' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Subscription</span>
                        <span :class="['px-2 py-1 rounded text-xs font-medium', getSubscriptionStatusColor(business?.subscription_status)]">
                            {{ business?.subscription_status || 'none' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-400">Beta Tester</span>
                        <button
                            @click="toggleTestingAccount(business)"
                            :class="business?.is_testing_account ? 'bg-accent-500/20 text-accent-400' : 'bg-gray-500/20 text-gray-400'"
                            class="px-2 py-1 rounded text-xs font-medium"
                        >
                            {{ business?.is_testing_account ? 'Yes' : 'No' }}
                        </button>
                    </div>
                    <div v-if="business?.created_at" class="flex items-center justify-between">
                        <span class="text-gray-400">Created</span>
                        <span class="text-gray-300">{{ formatDate(business.created_at) }}</span>
                    </div>
                </div>
            </div>

            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Owner</h2>
                <div v-if="business?.owner" class="p-4 rounded-xl bg-white/5">
                    <p class="text-white font-medium">{{ business.owner.name }}</p>
                    <p class="text-gray-400 text-sm">{{ business.owner.email }}</p>
                </div>
                <div v-else class="text-gray-500">No owner assigned</div>
            </div>
        </div>

        <!-- QR Codes & Promotions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">QR Codes</h2>
                <div v-if="business?.qr_codes?.length" class="space-y-3">
                    <div v-for="qr in business.qr_codes.slice(0, 10)" :key="qr.id" class="p-3 rounded-xl bg-white/5">
                        <div class="text-white font-medium">{{ qr.name || qr.code }}</div>
                        <div class="text-gray-400 text-sm">{{ qr.code }}</div>
                    </div>
                    <p v-if="business.qr_codes.length > 10" class="text-gray-500 text-sm">
                        + {{ business.qr_codes.length - 10 }} more
                    </p>
                </div>
                <div v-else class="text-gray-500">No QR codes</div>
            </div>

            <div class="glass-card p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Promotions</h2>
                <div v-if="business?.promotions?.length" class="space-y-3">
                    <div v-for="promo in business.promotions.slice(0, 10)" :key="promo.id" class="p-3 rounded-xl bg-white/5">
                        <div class="text-white font-medium">{{ promo.name }}</div>
                        <div class="text-gray-400 text-sm">{{ promo.discount_type }} • {{ promo.is_active ? 'Active' : 'Inactive' }}</div>
                    </div>
                    <p v-if="business.promotions.length > 10" class="text-gray-500 text-sm">
                        + {{ business.promotions.length - 10 }} more
                    </p>
                </div>
                <div v-else class="text-gray-500">No promotions</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-lg rounded-xl border border-white/10;
}
.btn-secondary {
    @apply px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors;
}
</style>
