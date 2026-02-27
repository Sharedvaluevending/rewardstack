<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ScrollDownIndicator from '@/Components/ScrollDownIndicator.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

// Notifications
const notifications = computed(() => page.props.auth?.notifications || []);
const unreadCount = computed(() => page.props.auth?.unreadNotificationsCount || 0);
const showNotifications = ref(false);
const portalBadges = computed(() => page.props.auth?.portalBadges || { home: 0, scans: 0, games: 0, merch: 0, referrals: 0 });

const markAsRead = (id) => {
    router.post(`/notifications/${id}/read`, {}, { preserveScroll: true });
    showNotifications.value = false;
};

const markAllAsRead = () => {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
};

const navigation = [
    { name: 'Home', href: '/portal', icon: 'home', activePattern: /^\/portal$/, badgeKey: 'home' },
    { name: 'Scans', href: '/portal/scans', icon: 'scan', activePattern: /^\/portal\/scans/, badgeKey: 'scans' },
    { name: 'Games', href: '/portal/games', icon: 'games', activePattern: /^\/portal\/games/, badgeKey: 'games' },
    { name: 'Merch', href: '/portal/merch', icon: 'shirt', activePattern: /^\/portal\/merch/, badgeKey: 'merch' },
    { name: 'Referrals', href: '/portal/referrals', icon: 'cash', activePattern: /^\/portal\/referrals/, badgeKey: 'referrals' },
];

const currentPath = computed(() => page.url);

const isActive = (item) => {
    return item.activePattern.test(currentPath.value);
};

// --- PWA install banner (optional helper for users who skip login CTA) ---
const deferredPrompt = ref(null);
const showInstallHelp = ref(false);
const isStandalone = ref(false);
const dismissed = ref(false);

const isIOS = computed(() => {
    if (typeof window === 'undefined') return false;
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
});

const updateStandalone = () => {
    if (typeof window === 'undefined') return;
    isStandalone.value =
        window.matchMedia?.('(display-mode: standalone)')?.matches === true ||
        window.navigator.standalone === true;
};

let mql;
let onBeforeInstallPrompt;
let onAppInstalled;

onMounted(() => {
    dismissed.value = window.localStorage?.getItem('pwa_install_banner_dismissed') === '1';
    updateStandalone();
    checkOnboarding();

    mql = window.matchMedia?.('(display-mode: standalone)') || null;
    mql?.addEventListener?.('change', updateStandalone);

    onBeforeInstallPrompt = (e) => {
        // Store the event so we can trigger it from our button.
        // Note: we do NOT call preventDefault() to avoid Chrome's "Banner not shown" warning.
        deferredPrompt.value = e;
    };
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);

    onAppInstalled = () => {
        deferredPrompt.value = null;
        updateStandalone();
    };
    window.addEventListener('appinstalled', onAppInstalled);
});

onBeforeUnmount(() => {
    mql?.removeEventListener?.('change', updateStandalone);
    if (onBeforeInstallPrompt) window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    if (onAppInstalled) window.removeEventListener('appinstalled', onAppInstalled);
});

const showInstallBanner = computed(() => {
    if (dismissed.value) return false;
    return !isStandalone.value && (deferredPrompt.value !== null || isIOS.value);
});

const installLabel = computed(() => 'Add Revenue QR to Home Screen');

const dismissBanner = () => {
    dismissed.value = true;
    window.localStorage?.setItem('pwa_install_banner_dismissed', '1');
};

const handleInstall = async () => {
    if (deferredPrompt.value) {
        deferredPrompt.value.prompt();
        try {
            await deferredPrompt.value.userChoice;
        } finally {
            deferredPrompt.value = null;
        }
        return;
    }
    showInstallHelp.value = true;
};

// --- Portal onboarding popups (5 steps) ---
const ONBOARDING_KEY = 'portal_onboarding_v1_completed';
const onboardingStep = ref(0);
const onboardingMessages = [
    { text: 'Home screen shows your XP, businesses on the platform, and money you saved.', icon: 'home' },
    { text: 'Scan — where you find all your QR code promotions you scanned. Show them to the business to redeem the promotion.', icon: 'scan' },
    { text: 'Games — track games, scores, leaderboard, levels and badges.', icon: 'games' },
    { text: 'Merch and Referrals is where you earn business rewards and money from referrals.', icon: 'merch_referrals' },
    { text: 'Click the top right icon beside the bell to customize your profile and manage business subscriptions.', icon: 'user' },
];

const dismissOnboardingStep = () => {
    if (onboardingStep.value < 5) {
        onboardingStep.value++;
    } else {
        if (typeof window !== 'undefined') {
            window.localStorage?.setItem(ONBOARDING_KEY, '1');
        }
        onboardingStep.value = 0;
    }
};

const checkOnboarding = () => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    const forceShow = url.searchParams.get('show_onboarding') === '1';
    if (forceShow) {
        window.localStorage?.removeItem(ONBOARDING_KEY);
    }
    const completed = window.localStorage?.getItem(ONBOARDING_KEY) === '1';
    if (!completed && user.value) {
        onboardingStep.value = 1;
    }
};

watch(() => page.url, () => {
    checkOnboarding();
});

// Reset scroll indicator on Inertia navigation
const scrollIndicatorKey = computed(() => page.url);
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 pb-20">
        <!-- Header -->
        <header class="sticky top-0 z-40 bg-black/30 backdrop-blur-xl border-b border-white/10">
            <div class="flex items-center justify-between px-4 h-14">
                <Link href="/portal" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-white/10 border border-white/10 flex items-center justify-center p-1">
                        <img src="/brand/logoRQ.png" alt="Revenue QR" class="w-full h-full object-contain" />
                    </div>
                    <span class="text-white font-semibold">Revenue QR</span>
                </Link>
                <div class="flex items-center gap-3">
                    <!-- Level Badge -->
                    <div class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gradient-to-r from-purple-500/20 to-pink-500/20 border border-purple-500/30">
                        <span class="text-xs font-bold text-purple-300">LV</span>
                        <span class="text-sm font-bold text-white">{{ user?.level || 1 }}</span>
                    </div>
                    <!-- Notifications -->
                    <div class="relative">
                        <button
                            @click="showNotifications = !showNotifications"
                            class="relative w-8 h-8 rounded-full bg-white/10 flex items-center justify-center"
                        >
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span v-if="unreadCount > 0" class="absolute top-0 right-0 block h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-gray-900"></span>
                        </button>

                        <!-- Dropdown -->
                        <div
                            v-if="showNotifications"
                            class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-xl bg-black/80 backdrop-blur-xl py-1 shadow-lg ring-1 ring-black ring-opacity-5 border border-white/10"
                        >
                            <div class="px-4 py-2 border-b border-white/10 flex justify-between items-center">
                                <h3 class="text-sm font-semibold text-white">Notifications</h3>
                                <button v-if="unreadCount > 0" @click="markAllAsRead" class="text-xs text-primary-400 hover:text-primary-300">Mark all read</button>
                            </div>
                            <div v-if="notifications.length === 0" class="px-4 py-6 text-center text-gray-400 text-sm">
                                No new notifications
                            </div>
                            <div v-else class="max-h-96 overflow-y-auto">
                                <Link
                                    v-for="notification in notifications"
                                    :key="notification.id"
                                    :href="notification.data.action_url || '#'"
                                    class="block px-4 py-3 hover:bg-white/5 transition-colors border-b border-white/5 last:border-0"
                                    @click="markAsRead(notification.id)"
                                >
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 pt-0.5 mr-3">
                                            <span class="text-lg">{{ notification.data.icon || '📢' }}</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-300 break-words">{{ notification.data.message }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ new Date(notification.created_at).toLocaleDateString() }}</p>
                                        </div>
                                        <div v-if="!notification.read_at" class="ml-2 flex-shrink-0">
                                            <span class="block h-2 w-2 rounded-full bg-primary-500"></span>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                    <!-- Profile -->
                    <Link href="/portal/profile" 
                        :class="['w-8 h-8 rounded-full flex items-center justify-center transition-colors',
                            /^\/portal\/(profile|badges|levels)/.test(currentPath) ? 'bg-purple-500/30 text-purple-400' : 'bg-white/10 text-white']">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="px-4 py-4">
            <!-- Install banner -->
            <div
                v-if="showInstallBanner"
                class="mb-4 p-3 rounded-2xl bg-white/5 backdrop-blur-xl border border-white/10 flex items-center justify-between gap-3"
            >
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-white">Install the portal</div>
                    <div class="text-xs text-gray-300/90 truncate">Faster launch, app-like experience.</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 transition-colors border border-white/10 text-white text-sm font-semibold"
                        @click="handleInstall"
                    >
                        {{ installLabel }}
                    </button>
                    <button
                        type="button"
                        class="w-9 h-9 rounded-xl bg-white/5 hover:bg-white/10 transition-colors border border-white/10 flex items-center justify-center"
                        @click="dismissBanner"
                        aria-label="Dismiss"
                    >
                        <svg class="w-5 h-5 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Flash Messages -->
            <div v-if="page.props?.flash?.success" class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props?.flash?.warning" class="mb-4 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm">
                {{ page.props.flash.warning }}
            </div>
            <div v-if="page.props?.flash?.error" class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
                {{ page.props.flash.error }}
            </div>

            <slot />
        </main>

        <!-- Scroll down indicator: fixed at bottom of viewport, above nav (hidden during onboarding) -->
        <div v-if="onboardingStep === 0" class="fixed bottom-20 left-0 right-0 z-30 pointer-events-none flex justify-center" :key="scrollIndicatorKey">
            <ScrollDownIndicator />
        </div>

        <!-- Bottom Navigation -->
        <nav class="fixed bottom-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-xl border-t border-white/10 safe-area-bottom">
            <div class="flex items-center justify-around h-16">
                <Link v-for="item in navigation" :key="item.name"
                    :href="item.href"
                    :class="['flex flex-col items-center gap-1 px-4 py-2 transition-colors',
                        isActive(item) ? 'text-purple-400' : 'text-gray-400']">
                    <!-- Icons with badge -->
                    <div class="relative inline-flex">
                        <svg v-if="item.icon === 'home'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <svg v-else-if="item.icon === 'games'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else-if="item.icon === 'trophy'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                    </svg>
                    <svg v-else-if="item.icon === 'shirt'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4l4 3 4-3 4 2-2 5-2-1v8H8v-8l-2 1-2-5 4-2z" />
                    </svg>
                    <svg v-else-if="item.icon === 'cash'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else-if="item.icon === 'scan'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                    <svg v-else-if="item.icon === 'user'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                        <span v-if="item.badgeKey !== 'home' && item.badgeKey !== 'games' && portalBadges[item.badgeKey] > 0"
                            class="nav-badge absolute -top-0.5 -right-1 block h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-gray-900"
                            :aria-label="`${portalBadges[item.badgeKey]} new`"
                        ></span>
                    </div>
                    <span class="text-xs font-medium">{{ item.name }}</span>
                </Link>
            </div>
        </nav>
    </div>

    <!-- Portal onboarding modal (5 steps) -->
    <div v-if="onboardingStep > 0" class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60 backdrop-blur-sm">
        <div class="relative w-full max-w-sm glass-card p-6 border border-white/10">
            <p class="text-xs text-gray-400 mb-2">{{ onboardingStep }} of 5</p>
            <!-- Icon for current step -->
            <div class="flex justify-center mb-4 text-purple-400">
                <template v-if="onboardingMessages[onboardingStep - 1].icon === 'merch_referrals'">
                    <div class="flex items-center gap-3">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4l4 3 4-3 4 2-2 5-2-1v8H8v-8l-2 1-2-5 4-2z" />
                        </svg>
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </template>
                <template v-else-if="onboardingMessages[onboardingStep - 1].icon === 'home'">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </template>
                <template v-else-if="onboardingMessages[onboardingStep - 1].icon === 'scan'">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </template>
                <template v-else-if="onboardingMessages[onboardingStep - 1].icon === 'games'">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
                <template v-else-if="onboardingMessages[onboardingStep - 1].icon === 'user'">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </template>
            </div>
            <p class="text-white text-sm leading-relaxed mb-6">{{ onboardingMessages[onboardingStep - 1].text }}</p>
            <button
                @click="dismissOnboardingStep"
                class="w-full py-3 rounded-xl bg-purple-500 text-white font-semibold hover:bg-purple-600 transition-colors"
            >
                Got it
            </button>
        </div>
    </div>

    <!-- iOS install instructions modal -->
    <div v-if="showInstallHelp" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <button
            type="button"
            class="absolute inset-0 bg-black/70"
            @click="showInstallHelp = false"
            aria-label="Close"
        ></button>
        <div class="relative w-full max-w-md glass-card p-6 border border-white/10">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-lg font-bold text-white">Add to Home Screen</h2>
                    <p class="text-sm text-gray-400 mt-1">Install the customer portal for a faster, app-like experience.</p>
                </div>
                <button
                    type="button"
                    class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 transition-colors flex items-center justify-center"
                    @click="showInstallHelp = false"
                    aria-label="Close"
                >
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <ol class="space-y-3 text-sm text-gray-200">
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-xs font-bold">1</span>
                    <span>Open Safari’s <span class="font-semibold">Share</span> menu.</span>
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-xs font-bold">2</span>
                    <span>Tap <span class="font-semibold">Add to Home Screen</span>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-xs font-bold">3</span>
                    <span>Tap <span class="font-semibold">Add</span>.</span>
                </li>
            </ol>

            <div class="mt-5 flex justify-end">
                <button
                    type="button"
                    class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors border border-white/10 text-white font-semibold"
                    @click="showInstallHelp = false"
                >
                    Got it
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}

.nav-badge {
    animation: badgeBoom 0.3s ease-out;
}

@keyframes badgeBoom {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    50% {
        transform: scale(1.2);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>

