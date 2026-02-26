<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const page = usePage();

const props = defineProps({
    stats: Object,
    featuredBadges: Array,
    businessDirectory: Array,
    stackableQrCode: Object,
    cityOptions: Array,
    directoryFilters: Object,
    userDefaultLocation: Object,
});

const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
};

const user = computed(() => page.props.auth?.user || null);

const userInitials = computed(() => {
    const name = user.value?.name || '';
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const initials = (parts[0]?.[0] || '') + (parts[1]?.[0] || '');
    return initials.toUpperCase() || 'U';
});

const directoryMode = computed(() => props.directoryFilters?.mode || 'all');
const selectedCityLabel = computed(() => {
    const c = props.directoryFilters?.city;
    const r = props.directoryFilters?.region;
    if (!c || !r) return null;
    return `${c}, ${r}`;
});

const stackableHref = computed(() => {
    if (!props.stackableQrCode?.code) return null;
    return `/s/${props.stackableQrCode.code}`;
});

const setMode = (mode) => {
    const params = {};
    params.mode = mode;
    if (mode === 'city') {
        params.city = props.directoryFilters?.city || props.userDefaultLocation?.city || null;
        params.region = props.directoryFilters?.region || props.userDefaultLocation?.region || null;
    }
    router.get('/portal', params, { preserveState: true, preserveScroll: true });
};

const setCity = (label) => {
    const opt = (props.cityOptions || []).find(o => o.label === label);
    if (!opt) return;
    router.get('/portal', { mode: 'city', city: opt.city, region: opt.region }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <PortalLayout>
        <!-- Welcome Header -->
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Welcome back!</h1>
                <p class="text-gray-400 text-sm mt-1">Keep playing to earn more rewards</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full overflow-hidden bg-white/10 border border-white/10 flex items-center justify-center text-white font-bold">
                    <img
                        v-if="user?.avatar_url"
                        :src="user.avatar_url"
                        alt="Profile photo"
                        class="w-full h-full object-cover"
                    />
                    <span v-else class="text-sm">{{ userInitials }}</span>
                </div>
            </div>
        </div>

        <!-- Level & XP Card -->
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl p-5 mb-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="text-white/70 text-sm">Current Level</div>
                        <div class="text-4xl font-bold text-white">{{ stats.level }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-white/70 text-sm">Total XP</div>
                        <div class="text-2xl font-bold text-white">{{ formatNumber(stats.xp) }}</div>
                    </div>
                </div>
                <div class="h-2 bg-white/20 rounded-full overflow-hidden">
                    <div class="h-full bg-white transition-all duration-500"
                        :style="{ width: stats.level_progress + '%' }"></div>
                </div>
                <div class="text-white/70 text-xs mt-2">{{ stats.level_progress }}% to Level {{ stats.level + 1 }}</div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-emerald-400">{{ formatNumber(stats.total_games) }}</div>
                <div class="text-gray-400 text-xs mt-1">Games Played</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-amber-400">{{ stats.current_streak }}🔥</div>
                <div class="text-gray-400 text-xs mt-1">Day Streak</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-purple-400">{{ stats.total_badges }}</div>
                <div class="text-gray-400 text-xs mt-1">Badges</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-yellow-400">${{ Number(stats.total_savings || 0).toFixed(2) }}</div>
                <div class="text-gray-400 text-xs mt-1">Saved</div>
            </div>
        </div>

        <!-- Business Directory Filters -->
        <div class="glass-card p-4 mb-6">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="text-lg font-semibold text-white">🏪 Businesses</h2>
                <div class="text-xs text-gray-400" v-if="directoryMode === 'near' && userDefaultLocation?.city && userDefaultLocation?.region">
                    Near: {{ userDefaultLocation.city }}, {{ userDefaultLocation.region }}
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="stackableHref && userDefaultLocation?.city && userDefaultLocation?.region"
                    :href="stackableHref"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-colors bg-primary-500 text-white hover:bg-primary-600"
                >
                    Deals Near Me
                </Link>
                <button
                    v-else
                    type="button"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-colors bg-white/10 text-gray-400 cursor-not-allowed"
                    :title="stackableHref ? 'Set your default city/state in Profile first' : 'Deals Near Me is not available yet'"
                    disabled
                >
                    Deals Near Me
                </button>
                <button
                    type="button"
                    @click="setMode('all')"
                    :class="[
                        'px-4 py-2 rounded-full text-sm font-medium transition-colors',
                        directoryMode === 'all' ? 'bg-primary-500 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20'
                    ]"
                >
                    All Businesses
                </button>
                <button
                    type="button"
                    @click="setMode('city')"
                    :class="[
                        'px-4 py-2 rounded-full text-sm font-medium transition-colors',
                        directoryMode === 'city' ? 'bg-primary-500 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20'
                    ]"
                >
                    By City
                </button>
            </div>

            <div v-if="directoryMode === 'city'" class="mt-3">
                <label class="block text-xs text-gray-400 mb-2">City</label>
                <select
                    class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-primary-500/50"
                    :value="selectedCityLabel || ''"
                    @change="setCity($event.target.value)"
                >
                    <option value="" class="bg-gray-800 text-white" disabled>Select a city</option>
                    <option v-for="o in (cityOptions || [])" :key="o.label" :value="o.label" class="bg-gray-800 text-white">
                        {{ o.label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Businesses List (Vertical Scroll) -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-white">Explore</h2>
                <Link href="/portal/profile" class="text-purple-400 text-sm">Set location →</Link>
            </div>
            <div v-if="(businessDirectory || []).length > 0" class="glass-card p-3 max-h-[520px] overflow-y-auto">
                <Link
                    v-for="b in (businessDirectory || [])"
                    :key="b.id"
                    :href="b.public_href"
                    class="block rounded-2xl p-4 mb-3 last:mb-0 bg-white/10 border border-white/15 hover:bg-white/15 hover:border-white/25 transition-colors"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-white border border-white/20 flex items-center justify-center p-1">
                            <img v-if="b.logo_url" :src="b.logo_url" :alt="b.name" class="w-full h-full object-contain" />
                            <span v-else class="text-gray-900 font-bold">{{ (b.name || 'B').charAt(0) }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-white font-semibold truncate">{{ b.name }}</div>
                            <div class="text-xs text-gray-200 truncate">
                                {{ b.type || 'Business' }} • {{ b.city && b.region ? `${b.city}, ${b.region}` : 'Location not set' }}
                            </div>
                        </div>
                    </div>

                    <div v-if="b.featured_promo" class="p-3 rounded-xl bg-black/20 border border-white/10">
                        <div class="text-xs text-gray-400 mb-1">Featured Offer</div>
                        <div class="text-white font-medium truncate">{{ b.featured_promo.name }}</div>
                        <div class="text-purple-300 font-semibold">{{ b.featured_promo.display_value }}</div>
                        <div v-if="b.featured_promo.ends_at" class="text-[11px] text-gray-500 mt-1">Ends {{ b.featured_promo.ends_at }}</div>
                        <div class="text-[11px] text-gray-300 mt-2">Scan in-store to redeem</div>
                    </div>
                    <div v-else class="p-3 rounded-xl bg-black/20 border border-white/10">
                        <div class="text-xs text-gray-400 mb-1">Offers</div>
                        <div class="text-gray-200 text-sm">
                            Visit store to scan for promotions
                        </div>
                        <div class="text-[11px] text-gray-500 mt-2">Tap to view business page</div>
                    </div>
                </Link>
            </div>
            <div v-if="(businessDirectory || []).length === 0" class="text-center py-10 glass-card">
                <div class="text-4xl mb-3">🏪</div>
                <p class="text-gray-400">No businesses found for this filter.</p>
            </div>
        </div>

        <!-- Featured Badges -->
        <div v-if="featuredBadges.length" class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">🏆 Featured Badges</h2>
            <div class="flex gap-3 overflow-x-auto pb-2 -mx-4 px-4">
                <div v-for="ub in featuredBadges" :key="ub.id"
                    class="flex-shrink-0 w-24 bg-white/5 backdrop-blur rounded-xl p-3 text-center border border-white/10">
                    <div class="text-3xl mb-2">{{ ub.badge?.icon || '🏅' }}</div>
                    <div class="text-white text-xs font-medium truncate">{{ ub.badge?.name }}</div>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

