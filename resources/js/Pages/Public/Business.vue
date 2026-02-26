<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';

defineOptions({
    layout: PortalLayout,
});

const props = defineProps({
    business: Object,
    featuredPromo: Object,
    promotions: Array,
    stackables: Array,
    isSubscribed: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

const canSubscribe = computed(() => {
    const role = user.value?.role;
    return role === 'customer' || role === 'user';
});

const subscribe = () => {
    if (!props.business?.id) return;
    router.post(`/portal/subscriptions/${props.business.id}/subscribe`, { source: 'business_page' }, { preserveScroll: true });
};

const unsubscribe = () => {
    if (!props.business?.id) return;
    router.post(`/portal/subscriptions/${props.business.id}/unsubscribe`, { source: 'business_page' }, { preserveScroll: true });
};

const redirectTo = computed(() => {
    const slug = props.business?.slug;
    if (!slug) return '/';
    return `/b/${slug}`;
});

const fullAddress = () => {
    const parts = [
        props.business?.address_line1,
        props.business?.address_line2,
        props.business?.city,
        props.business?.state,
        props.business?.postal_code,
    ].filter(Boolean);
    return parts.join(', ');
};

const orderedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const dayLabel = (d) => ({
    monday: 'Mon',
    tuesday: 'Tue',
    wednesday: 'Wed',
    thursday: 'Thu',
    friday: 'Fri',
    saturday: 'Sat',
    sunday: 'Sun',
}[String(d || '').toLowerCase()] || d);

const sortedHours = computed(() => {
    const rows = Array.isArray(props.business?.business_hours) ? props.business.business_hours : [];
    const byDay = new Map(rows.map(r => [String(r?.day || '').toLowerCase(), r]));
    return orderedDays.map(d => {
        const row = byDay.get(d);
        if (!row) {
            return { day: d, closed: true, open: '', close: '' };
        }
        return {
            day: d,
            closed: !!row.closed,
            open: row.open || '',
            close: row.close || '',
        };
    });
});
</script>

<template>
    <Head :title="business?.name || 'Business'" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-gray-900 to-slate-900 p-4">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="glass-card overflow-hidden mb-4">
                <div
                    class="p-6"
                    :style="{ background: `linear-gradient(135deg, ${business?.primary_color || '#7C3AED'}40, ${business?.secondary_color || business?.primary_color || '#7C3AED'}10)` }"
                >
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-white border border-white/20 overflow-hidden flex items-center justify-center p-2">
                            <img v-if="business?.logo_url" :src="business.logo_url" :alt="business.name" class="w-full h-full object-contain" />
                            <span v-else class="text-gray-900 text-2xl font-bold">{{ business?.name?.charAt(0) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h1 class="text-2xl font-bold text-white truncate">{{ business?.name }}</h1>
                            <div class="text-gray-300 text-sm truncate">
                                {{ business?.type || 'Business' }}
                                <span v-if="business?.city && business?.state"> • {{ business.city }}, {{ business.state }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-5 flex flex-wrap gap-2">
                        <template v-if="canSubscribe">
                            <button
                                v-if="!isSubscribed"
                                type="button"
                                class="px-4 py-2 rounded-lg bg-emerald-500/20 text-emerald-100 border border-emerald-500/20 hover:bg-emerald-500/30 transition-colors"
                                @click="subscribe"
                            >
                                Subscribe for email deals
                            </button>
                            <button
                                v-else
                                type="button"
                                class="px-4 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors"
                                @click="unsubscribe"
                            >
                                Subscribed (manage)
                            </button>
                        </template>
                        <Link
                            v-else
                            :href="`/portal/join?redirect_to=${encodeURIComponent(redirectTo)}`"
                            class="px-4 py-2 rounded-lg bg-emerald-500/20 text-emerald-100 border border-emerald-500/20 hover:bg-emerald-500/30 transition-colors"
                        >
                            Create free account to subscribe
                        </Link>
                        <a
                            v-if="business?.directions_url"
                            :href="business.directions_url"
                            target="_blank"
                            rel="noopener"
                            class="px-4 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors"
                        >
                            Directions
                        </a>
                        <a
                            v-if="business?.website"
                            :href="business.website"
                            target="_blank"
                            rel="noopener"
                            class="px-4 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors"
                        >
                            Website
                        </a>
                        <a
                            v-if="business?.facebook_url"
                            :href="business.facebook_url"
                            target="_blank"
                            rel="noopener"
                            class="px-4 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors"
                        >
                            Facebook
                        </a>
                        <a
                            v-if="business?.instagram_url"
                            :href="business.instagram_url"
                            target="_blank"
                            rel="noopener"
                            class="px-4 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors"
                        >
                            Instagram
                        </a>
                    </div>
                </div>
            </div>

            <!-- About -->
            <div v-if="business?.description" class="glass-card p-5 mb-4">
                <h2 class="text-white font-semibold mb-2">About</h2>
                <p class="text-gray-300 text-sm whitespace-pre-line">{{ business.description }}</p>
            </div>

            <!-- Location & Info -->
            <div class="glass-card p-5 mb-4">
                <h2 class="text-white font-semibold mb-3">Location & Info</h2>

                <!-- Address -->
                <div v-if="fullAddress()" class="mb-4">
                    <div class="text-xs text-gray-400 mb-1">Address</div>
                    <div class="text-gray-200 text-sm">{{ fullAddress() }}</div>
                    <a
                        v-if="business?.directions_url"
                        :href="business.directions_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-block mt-2 text-sm text-primary-300 hover:text-primary-200"
                    >
                        Get Directions →
                    </a>
                </div>

                <!-- Contact -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div v-if="business?.phone">
                        <div class="text-xs text-gray-400 mb-1">Phone</div>
                        <a class="text-gray-200 text-sm hover:text-white" :href="`tel:${business.phone}`">
                            {{ business.phone }}
                        </a>
                    </div>

                    <div v-if="business?.email">
                        <div class="text-xs text-gray-400 mb-1">Email</div>
                        <a class="text-gray-200 text-sm hover:text-white" :href="`mailto:${business.email}`">
                            {{ business.email }}
                        </a>
                    </div>

                    <div v-if="business?.website">
                        <div class="text-xs text-gray-400 mb-1">Website</div>
                        <a class="text-gray-200 text-sm hover:text-white" :href="business.website" target="_blank" rel="noopener">
                            {{ business.website }}
                        </a>
                    </div>

                    <div v-if="business?.facebook_url || business?.instagram_url">
                        <div class="text-xs text-gray-400 mb-1">Social</div>
                        <div class="flex flex-wrap gap-3">
                            <a v-if="business?.facebook_url" class="text-gray-200 text-sm hover:text-white" :href="business.facebook_url" target="_blank" rel="noopener">
                                Facebook
                            </a>
                            <a v-if="business?.instagram_url" class="text-gray-200 text-sm hover:text-white" :href="business.instagram_url" target="_blank" rel="noopener">
                                Instagram
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Store Hours -->
                <div v-if="sortedHours.length">
                    <div class="text-xs text-gray-400 mb-2">Store Hours</div>
                    <div class="space-y-1">
                        <div
                            v-for="row in sortedHours"
                            :key="row.day"
                            class="flex items-center justify-between text-sm bg-white/5 border border-white/10 rounded-lg px-3 py-2"
                        >
                            <span class="text-gray-300">{{ dayLabel(row.day) }}</span>
                            <span v-if="row.closed" class="text-gray-500">Closed</span>
                            <span v-else class="text-gray-200">
                                {{ row.open || '—' }} - {{ row.close || '—' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Featured Promo -->
            <div v-if="featuredPromo" class="glass-card p-5 mb-4 border border-primary-500/20">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-white font-semibold">Featured Offer</h2>
                    <span class="text-xs px-2 py-1 rounded-full bg-primary-500/20 text-primary-200 border border-primary-500/20">
                        Growth+
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold text-white">{{ featuredPromo.display_value }}</div>
                    <div class="text-gray-200 font-medium mt-1">{{ featuredPromo.name }}</div>
                    <p v-if="featuredPromo.description" class="text-gray-400 text-sm mt-2">{{ featuredPromo.description }}</p>
                    <div v-if="featuredPromo.ends_at" class="text-xs text-gray-500 mt-2">Ends {{ featuredPromo.ends_at }}</div>
                    <div class="text-xs text-gray-500 mt-3">No code shown — scan in-store to redeem.</div>
                </div>
            </div>

            <!-- Stackable Deals -->
            <div v-if="(stackables || []).length" class="glass-card p-5 mb-4">
                <h2 class="text-white font-semibold mb-3">Stackable Deals</h2>
                <div class="space-y-3">
                    <div v-for="(s, idx) in stackables" :key="idx" class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-white font-medium">{{ s.pool?.name || 'Stackable Pool' }}</div>
                            <span v-if="s.is_featured" class="text-xs bg-amber-500/20 text-amber-400 px-2 py-0.5 rounded-full">Featured</span>
                        </div>
                        <div v-if="s.pool?.city" class="text-xs text-gray-500 mt-1">{{ s.pool.city }}{{ s.pool.region ? `, ${s.pool.region}` : '' }}</div>
                        <div class="mt-2 text-emerald-300 font-semibold">{{ s.promotion?.display_value }}</div>
                        <div class="text-xs text-gray-500 mt-2">Scan in-store to redeem.</div>
                    </div>
                </div>
            </div>

            <!-- All Promotions -->
            <div class="glass-card p-5 mb-6">
                <h2 class="text-white font-semibold mb-3">All Active Promotions</h2>
                <div v-if="(promotions || []).length" class="space-y-3">
                    <div v-for="p in promotions" :key="p.id" class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-2xl font-bold text-white">{{ p.display_value }}</div>
                        <div class="text-gray-200 font-medium mt-1">{{ p.name }}</div>
                        <p v-if="p.description" class="text-gray-400 text-sm mt-2">{{ p.description }}</p>
                        <div class="mt-3 text-xs text-gray-500">
                            <span v-if="p.starts_at">Starts {{ p.starts_at }}</span>
                            <span v-if="p.starts_at && p.ends_at"> • </span>
                            <span v-if="p.ends_at">Ends {{ p.ends_at }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">No code shown — scan in-store to redeem.</div>
                    </div>
                </div>
                <div v-else class="text-center py-10">
                    <div class="text-4xl mb-3">🏷️</div>
                    <p class="text-gray-400">No active promotions right now.</p>
                </div>
            </div>
        </div>
    </div>
</template>

