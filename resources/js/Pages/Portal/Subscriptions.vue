<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';

defineOptions({
    layout: PortalLayout,
});

const props = defineProps({
    subscriptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const active = computed(() => (props.subscriptions || []).filter(s => s.is_subscribed));
const inactive = computed(() => (props.subscriptions || []).filter(s => !s.is_subscribed));

const subscribe = (businessId) => {
    router.post(`/portal/subscriptions/${businessId}/subscribe`, { source: 'portal' }, { preserveScroll: true });
};

const unsubscribe = (businessId) => {
    router.post(`/portal/subscriptions/${businessId}/unsubscribe`, { source: 'portal' }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Email Subscriptions" />

    <div class="max-w-xl mx-auto px-4 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-white">Email Subscriptions</h1>
            <Link href="/portal/profile" class="text-sm text-gray-400 hover:text-white transition-colors">Profile</Link>
        </div>

        <div v-if="page.props?.flash?.success" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props?.flash?.error" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            {{ page.props.flash.error }}
        </div>

        <div class="glass-card p-4 mb-4">
            <div class="text-sm text-gray-300">
                Subscribing lets a business email you offers and updates. You can unsubscribe anytime.
            </div>
        </div>

        <div v-if="active.length" class="glass-card p-4 mb-4">
            <div class="text-white font-semibold mb-3">Subscribed</div>
            <div class="space-y-3">
                <div v-for="s in active" :key="s.id" class="p-3 rounded-xl bg-white/5 border border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-white border border-white/20 flex items-center justify-center p-1 shrink-0">
                            <img v-if="s.business?.logo_url" :src="s.business.logo_url" :alt="s.business?.name" class="w-full h-full object-contain" />
                            <span v-else class="text-gray-900 font-bold">{{ (s.business?.name || 'B').charAt(0) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-white font-semibold truncate">{{ s.business?.name }}</div>
                            <div class="text-xs text-gray-300 truncate">
                                {{ s.business?.type || 'Business' }}
                                <span v-if="s.business?.city && s.business?.state"> • {{ s.business.city }}, {{ s.business.state }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <Link
                                v-if="s.business?.public_href"
                                :href="s.business.public_href"
                                class="px-3 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 text-sm"
                            >
                                View
                            </Link>
                            <button
                                type="button"
                                class="px-3 py-2 rounded-lg bg-red-500/20 text-red-200 hover:bg-red-500/30 text-sm border border-red-500/20"
                                @click="s.business?.id && unsubscribe(s.business.id)"
                            >
                                Unsubscribe
                            </button>
                        </div>
                    </div>
                    <div v-if="s.subscribed_at" class="mt-2 text-xs text-gray-500">Subscribed {{ s.subscribed_at }}</div>
                </div>
            </div>
        </div>

        <div v-if="inactive.length" class="glass-card p-4">
            <div class="text-white font-semibold mb-3">Unsubscribed</div>
            <div class="space-y-3">
                <div v-for="s in inactive" :key="s.id" class="p-3 rounded-xl bg-white/5 border border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-white border border-white/20 flex items-center justify-center p-1 shrink-0">
                            <img v-if="s.business?.logo_url" :src="s.business.logo_url" :alt="s.business?.name" class="w-full h-full object-contain" />
                            <span v-else class="text-gray-900 font-bold">{{ (s.business?.name || 'B').charAt(0) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-white font-semibold truncate">{{ s.business?.name }}</div>
                            <div class="text-xs text-gray-300 truncate">
                                {{ s.business?.type || 'Business' }}
                                <span v-if="s.business?.city && s.business?.state"> • {{ s.business.city }}, {{ s.business.state }}</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="px-3 py-2 rounded-lg bg-emerald-500/20 text-emerald-200 hover:bg-emerald-500/30 text-sm border border-emerald-500/20"
                            @click="s.business?.id && subscribe(s.business.id)"
                        >
                            Resubscribe
                        </button>
                    </div>
                    <div v-if="s.unsubscribed_at" class="mt-2 text-xs text-gray-500">Unsubscribed {{ s.unsubscribed_at }}</div>
                </div>
            </div>
        </div>

        <div v-if="!active.length && !inactive.length" class="glass-card p-6 text-center">
            <div class="text-4xl mb-2">📬</div>
            <div class="text-white font-semibold">No subscriptions yet</div>
            <div class="text-sm text-gray-400 mt-1">
                Subscribe from a business page or promotion page to receive their offers by email.
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

