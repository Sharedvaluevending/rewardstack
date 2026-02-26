<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import { collectAndSendScanGeo } from '@/utils/scanGeo';

const props = defineProps({
    qrCode: Object,
    crossPromo: Object,
    business1: Object,
    promotion1: Object,
    business2: Object,
    promotion2: Object,
    chainProgress: Object,
    token1: Object,
    token2: Object,
    subscription1: Object,
    subscription2: Object,
    staffAuth: Boolean,
    availability1: Object,
    availability2: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const userAvatar = computed(() => page.props.auth.user?.avatar_url);
const userName = computed(() => page.props.auth.user?.name || '');

const isSubscribed1 = computed(() => !!props.subscription1?.is_subscribed);
const isSubscribed2 = computed(() => !!props.subscription2?.is_subscribed);

const showDetails1 = ref(false);
const showDetails2 = ref(false);
const claiming = ref(null); // Track which promo is being claimed

onMounted(() => {
    collectAndSendScanGeo(props.qrCode?.code);
});

const claimOffer = (promotionId) => {
    if (!user.value) {
        // Redirect to login with return url
        window.location.href = `/login?return=${window.location.pathname}`;
        return;
    }

    if (claiming.value) return;
    claiming.value = promotionId;
    
    router.post(`/cross-promo/${props.crossPromo.id}/claim/${promotionId}`, {}, {
        onFinish: () => claiming.value = null,
    });
};

const subscribeToBusiness = (id) => {
    if (!id) return;
    router.post(`/portal/subscriptions/${id}/subscribe`, { source: 'cross_promo_page' }, { preserveScroll: true });
};

const unsubscribeFromBusiness = (id) => {
    if (!id) return;
    router.post(`/portal/subscriptions/${id}/unsubscribe`, { source: 'cross_promo_page' }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="crossPromo?.name || 'Partner Deal Chain'" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 p-4">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ crossPromo?.name || 'Partner Deal Chain' }}</h1>
                <p class="text-gray-400 mt-1">
                    Pick an offer below. You can keep it open, or chain it so one deal unlocks the other after redemption.
                </p>

                <!-- User & subscription controls -->
                <div class="mt-4 flex flex-col items-center gap-3">
                    <div v-if="userAvatar || userName" class="flex items-center gap-3 text-white">
                        <img v-if="userAvatar" :src="userAvatar" alt="" class="w-10 h-10 rounded-full object-cover border border-white/20" />
                        <div v-else class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-semibold">
                            {{ userName ? userName.charAt(0) : '🙂' }}
                        </div>
                        <span class="text-sm font-medium">{{ userName || 'Guest' }}</span>
                    </div>
                    <div v-if="user && !staffAuth" class="flex items-center gap-3 flex-wrap justify-center text-xs text-gray-300">
                        <Link href="/portal/subscriptions" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 border border-white/10">
                            Manage subscriptions
                        </Link>
                    </div>
                    <div v-else-if="!user && !staffAuth" class="flex items-center gap-3 flex-wrap justify-center text-sm">
                        <Link :href="`/portal/join?redirect_to=${encodeURIComponent(location.pathname)}`" class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600">
                            Join free to save offers
                        </Link>
                        <Link :href="`/login?redirect_to=${encodeURIComponent(location.pathname)}`" class="text-gray-300 hover:text-white">
                            Sign in
                        </Link>
                    </div>
                </div>
                
                <!-- Chain Progress Indicator (for sequential chains) -->
                <div v-if="crossPromo?.chain_mode === 'sequential'" class="mt-4 max-w-md mx-auto">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <div class="flex-1 h-1 rounded-full" 
                            :class="chainProgress?.step >= 1 ? 'bg-emerald-500' : 'bg-gray-700'">
                        </div>
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                            :class="chainProgress?.step >= 1 ? 'bg-emerald-500 text-white' : 'bg-gray-700 text-gray-400'">
                            1
                        </div>
                        <div class="flex-1 h-1 rounded-full"
                            :class="chainProgress?.step >= 2 ? 'bg-emerald-500' : 'bg-gray-700'">
                        </div>
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                            :class="chainProgress?.step >= 2 ? 'bg-emerald-500 text-white' : 'bg-gray-700 text-gray-400'">
                            2
                        </div>
                        <div class="flex-1 h-1 rounded-full" 
                            :class="chainProgress?.step >= 2 ? 'bg-emerald-500' : 'bg-gray-700'">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">
                        <span v-if="chainProgress?.step === 0">Step 1 of 2: Redeem first offer</span>
                        <span v-else-if="chainProgress?.step === 1">Step 2 of 2: Redeem second offer</span>
                        <span v-else-if="chainProgress?.step === 2">Complete! Both offers redeemed</span>
                        <span v-else>Chain progress</span>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Business 1 -->
                <div class="glass-card overflow-hidden relative">
                    <!-- Lock Overlay -->
                    <div v-if="promotion1?.is_locked" class="absolute inset-0 z-10 bg-black/80 backdrop-blur-sm flex flex-col items-center justify-center p-6 text-center">
                        <div class="w-16 h-16 rounded-full bg-white/10 flex items-center justify-center mb-4 border border-white/20">
                            <span class="text-3xl">🔒</span>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">Offer Locked</h3>
                        <p class="text-gray-300 text-sm">
                            Redeem the offer from <strong>{{ business2?.name }}</strong> first to unlock this deal!
                        </p>
                    </div>

                    <div
                        class="p-5"
                        :style="{ background: `linear-gradient(135deg, ${business1?.primary_color || '#7C3AED'}40, ${business1?.primary_color || '#7C3AED'}20)` }"
                    >
                        <div class="flex items-center gap-3 justify-center">
                            <div v-if="business1?.logo_url" class="w-12 h-12 rounded-xl bg-white p-2 shadow-lg">
                                <img :src="business1.logo_url" :alt="business1.name" class="w-full h-full object-contain" />
                            </div>
                            <div v-else class="w-12 h-12 rounded-xl bg-white/15 flex items-center justify-center">
                                <span class="text-white font-bold text-lg">{{ business1?.name?.charAt(0) }}</span>
                            </div>
                            <div class="text-left">
                                <div class="text-white font-semibold leading-tight">{{ business1?.name }}</div>
                                <div class="text-gray-300 text-sm">
                                    Offer
                                    <span v-if="availability1?.status" class="ml-2 text-xs px-2 py-0.5 rounded-full border"
                                        :class="availability1.status === 'active' ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-200' : 'bg-amber-500/15 border-amber-400/30 text-amber-200'">
                                        {{ availability1.message }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="text-center mb-4">
                            <div class="text-4xl font-bold text-white">{{ promotion1?.display_value }}</div>
                            <div class="text-gray-300 mt-1 font-medium">{{ promotion1?.name }}</div>
                        </div>

                        <p v-if="promotion1?.description" class="text-gray-400 text-sm mb-4">
                            {{ promotion1.description }}
                        </p>

                        <div class="flex items-center justify-between gap-3">
                            <button
                                type="button"
                                class="flex-1 py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all"
                                @click="showDetails1 = !showDetails1"
                            >
                                {{ showDetails1 ? 'Hide Details' : 'View Details' }}
                            </button>
                            <button
                                v-if="!promotion1?.is_locked"
                                type="button"
                                :disabled="claiming === promotion1?.id"
                                class="flex-1 py-3 bg-emerald-500 text-white font-medium rounded-xl hover:bg-emerald-600 transition-all disabled:opacity-50"
                                @click="claimOffer(promotion1?.id)"
                            >
                                {{ claiming === promotion1?.id ? 'Loading...' : 'Get Deal' }}
                            </button>
                        </div>
                        <div v-if="!staffAuth" class="mt-3 flex items-center gap-2 flex-wrap">
                            <button
                                v-if="user && !isSubscribed1"
                                type="button"
                                class="px-3 py-2 rounded-lg bg-emerald-500/20 text-emerald-100 border border-emerald-500/20 hover:bg-emerald-500/30 transition-colors text-sm"
                                @click="subscribeToBusiness(business1?.id)"
                            >
                                Subscribe to {{ business1?.name }}
                            </button>
                            <Link
                                v-else-if="user && isSubscribed1"
                                href="/portal/subscriptions"
                                class="px-3 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors text-sm"
                            >
                                Subscribed (manage)
                            </Link>
                            <button
                                v-if="user && isSubscribed1"
                                type="button"
                                class="px-3 py-2 rounded-lg bg-red-500/15 text-red-100 border border-red-500/20 hover:bg-red-500/25 transition-colors text-sm"
                                @click="unsubscribeFromBusiness(business1?.id)"
                            >
                                Unsubscribe
                            </button>
                        </div>

                        <div v-if="showDetails1" class="mt-4 p-4 rounded-xl bg-white/5 border border-white/10">
                            <div v-if="promotion1?.terms" class="text-gray-300 text-sm whitespace-pre-line">
                                {{ promotion1.terms }}
                            </div>
                            <div v-else class="text-gray-400 text-sm">
                                No additional terms.
                            </div>
                            <div class="mt-3 text-xs text-gray-500">
                                <span v-if="promotion1?.starts_at">Starts: {{ promotion1.starts_at }}</span>
                                <span v-if="promotion1?.starts_at && promotion1?.ends_at"> • </span>
                                <span v-if="promotion1?.ends_at">Ends: {{ promotion1.ends_at }}</span>
                            </div>
                            <div v-if="token1?.code" class="mt-4 p-4 rounded-lg bg-white/5 border border-white/10 text-center">
                                <div class="text-xs text-gray-400 mb-1">Your code</div>
                                <div class="text-2xl font-bold text-white font-mono tracking-wide">{{ token1.code }}</div>
                                <div v-if="token1.qr_image_url" class="mt-3 flex justify-center">
                                    <img :src="token1.qr_image_url" alt="QR" class="w-40 h-40 rounded-lg bg-white p-2" />
                                </div>
                                <div class="text-xs text-gray-500 mt-2">Show staff this QR or code to redeem.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Business 2 -->
                <div class="glass-card overflow-hidden relative">
                    <!-- Lock Overlay -->
                    <div v-if="promotion2?.is_locked" class="absolute inset-0 z-10 bg-black/80 backdrop-blur-sm flex flex-col items-center justify-center p-6 text-center">
                        <div class="w-16 h-16 rounded-full bg-white/10 flex items-center justify-center mb-4 border border-white/20">
                            <span class="text-3xl">🔒</span>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">Offer Locked</h3>
                        <p class="text-gray-300 text-sm">
                            Redeem the offer from <strong>{{ business1?.name }}</strong> first to unlock this deal!
                        </p>
                    </div>

                    <div
                        class="p-5"
                        :style="{ background: `linear-gradient(135deg, ${business2?.primary_color || '#0EA5E9'}40, ${business2?.primary_color || '#0EA5E9'}20)` }"
                    >
                        <div class="flex items-center gap-3 justify-center">
                            <div v-if="business2?.logo_url" class="w-12 h-12 rounded-xl bg-white p-2 shadow-lg">
                                <img :src="business2.logo_url" :alt="business2.name" class="w-full h-full object-contain" />
                            </div>
                            <div v-else class="w-12 h-12 rounded-xl bg-white/15 flex items-center justify-center">
                                <span class="text-white font-bold text-lg">{{ business2?.name?.charAt(0) }}</span>
                            </div>
                            <div class="text-left">
                                <div class="text-white font-semibold leading-tight">{{ business2?.name }}</div>
                                <div class="text-gray-300 text-sm">
                                    Offer
                                    <span v-if="availability2?.status" class="ml-2 text-xs px-2 py-0.5 rounded-full border"
                                        :class="availability2.status === 'active' ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-200' : 'bg-amber-500/15 border-amber-400/30 text-amber-200'">
                                        {{ availability2.message }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="text-center mb-4">
                            <div class="text-4xl font-bold text-white">{{ promotion2?.display_value }}</div>
                            <div class="text-gray-300 mt-1 font-medium">{{ promotion2?.name }}</div>
                        </div>

                        <p v-if="promotion2?.description" class="text-gray-400 text-sm mb-4">
                            {{ promotion2.description }}
                        </p>

                        <div class="flex items-center justify-between gap-3">
                            <button
                                type="button"
                                class="flex-1 py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all"
                                @click="showDetails2 = !showDetails2"
                            >
                                {{ showDetails2 ? 'Hide Details' : 'View Details' }}
                            </button>
                            <button
                                v-if="!promotion2?.is_locked"
                                type="button"
                                :disabled="claiming === promotion2?.id"
                                class="flex-1 py-3 bg-emerald-500 text-white font-medium rounded-xl hover:bg-emerald-600 transition-all disabled:opacity-50"
                                @click="claimOffer(promotion2?.id)"
                            >
                                {{ claiming === promotion2?.id ? 'Loading...' : 'Get Deal' }}
                            </button>
                        </div>
                        <div v-if="!staffAuth" class="mt-3 flex items-center gap-2 flex-wrap">
                            <button
                                v-if="user && !isSubscribed2"
                                type="button"
                                class="px-3 py-2 rounded-lg bg-emerald-500/20 text-emerald-100 border border-emerald-500/20 hover:bg-emerald-500/30 transition-colors text-sm"
                                @click="subscribeToBusiness(business2?.id)"
                            >
                                Subscribe to {{ business2?.name }}
                            </button>
                            <Link
                                v-else-if="user && isSubscribed2"
                                href="/portal/subscriptions"
                                class="px-3 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors text-sm"
                            >
                                Subscribed (manage)
                            </Link>
                            <button
                                v-if="user && isSubscribed2"
                                type="button"
                                class="px-3 py-2 rounded-lg bg-red-500/15 text-red-100 border border-red-500/20 hover:bg-red-500/25 transition-colors text-sm"
                                @click="unsubscribeFromBusiness(business2?.id)"
                            >
                                Unsubscribe
                            </button>
                        </div>

                        <div v-if="showDetails2" class="mt-4 p-4 rounded-xl bg-white/5 border border-white/10">
                            <div v-if="promotion2?.terms" class="text-gray-300 text-sm whitespace-pre-line">
                                {{ promotion2.terms }}
                            </div>
                            <div v-else class="text-gray-400 text-sm">
                                No additional terms.
                            </div>
                            <div class="mt-3 text-xs text-gray-500">
                                <span v-if="promotion2?.starts_at">Starts: {{ promotion2.starts_at }}</span>
                                <span v-if="promotion2?.starts_at && promotion2?.ends_at"> • </span>
                                <span v-if="promotion2?.ends_at">Ends: {{ promotion2.ends_at }}</span>
                            </div>
                            <div v-if="token2?.code" class="mt-4 p-4 rounded-lg bg-white/5 border border-white/10 text-center">
                                <div class="text-xs text-gray-400 mb-1">Your code</div>
                                <div class="text-2xl font-bold text-white font-mono tracking-wide">{{ token2.code }}</div>
                                <div v-if="token2.qr_image_url" class="mt-3 flex justify-center">
                                    <img :src="token2.qr_image_url" alt="QR" class="w-40 h-40 rounded-lg bg-white p-2" />
                                </div>
                                <div class="text-xs text-gray-500 mt-2">Show staff this QR or code to redeem.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back to Portal -->
            <div v-if="user" class="mt-8 flex justify-center">
                <Link 
                    href="/portal/scans" 
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to My Portal
                </Link>
            </div>

            <div class="mt-6 text-center text-xs text-gray-500">QR: {{ qrCode?.code }}</div>
        </div>
    </div>
</template>

