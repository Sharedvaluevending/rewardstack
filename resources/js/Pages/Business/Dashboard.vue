<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import OnboardingChecklist from '@/Components/OnboardingChecklist.vue';
import { computed, ref } from 'vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    business: Object,
    stats: Object,
    partnershipStats: Object,
    chartData: Object,
    recentScans: Array,
    recentRedemptions: Array,
    topQRCodes: Array,
    topPromotions: Array,
    onboardingSteps: {
        type: Array,
        default: () => [],
    },
    onboardingUpsellSteps: {
        type: Array,
        default: () => [],
    },
    onboardingProgress: {
        type: Object,
        default: () => ({ completed: 0, total: 0, percentage: 0 }),
    },
    showOnboarding: {
        type: Boolean,
        default: false,
    },
});

const showRedeemByCode = ref(false);
const redeemCode = ref('');
const redeemError = ref('');

const openRedeemByCode = () => {
    redeemError.value = '';
    redeemCode.value = '';
    showRedeemByCode.value = true;
};

const closeRedeemByCode = () => {
    showRedeemByCode.value = false;
    redeemError.value = '';
};

const goRedeemByCode = () => {
    const code = (redeemCode.value || '').trim();
    if (!code) {
        redeemError.value = 'Enter the QR code (example: A1B2C3D4).';
        return;
    }
    // Route into the same redemption flow used after scanning.
    router.visit(`/redeem/${encodeURIComponent(code)}`);
};

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount || 0);
};

// Format number with commas
const formatNumber = (num) => {
    return new Intl.NumberFormat('en-US').format(num || 0);
};

// Get trial days remaining
const trialDaysRemaining = computed(() => {
    if (!props.business.trial_ends_at) return 0;
    const now = new Date();
    const trialEnd = new Date(props.business.trial_ends_at);
    const diffTime = trialEnd - now;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return Math.max(0, diffDays);
});

// Stat cards data
const statCards = computed(() => [
    {
        title: 'Total QR Codes',
        value: formatNumber(props.stats.total_qr_codes),
        icon: 'qrcode',
        color: 'from-blue-500 to-cyan-500',
        link: '/business/qr-codes',
    },
    {
        title: 'Active Promotions',
        value: formatNumber(props.stats.active_promotions),
        icon: 'gift',
        color: 'from-purple-500 to-pink-500',
        link: '/business/promotions',
    },
    {
        title: 'Scans (30 days)',
        value: formatNumber(props.stats.scans_period),
        icon: 'eye',
        color: 'from-orange-500 to-red-500',
        link: '/business/analytics',
    },
    {
        title: 'Game Plays (30 days)',
        value: formatNumber(props.stats.game_plays_period || 0),
        icon: 'play',
        color: 'from-purple-500 to-indigo-500',
        link: '/business/qrcade/analytics',
    },
    {
        title: 'Redemptions (30 days)',
        value: formatNumber(props.stats.redemptions_period),
        icon: 'check-circle',
        color: 'from-green-500 to-emerald-500',
        link: '/business/analytics',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Dashboard</h1>
                <p class="text-gray-400 mt-1">Welcome back, {{ business.name }}</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-4">
                <!-- Trial Badge -->
                <div v-if="trialDaysRemaining > 0" class="px-4 py-2 rounded-lg bg-accent-500/20 border border-accent-500/30">
                    <span class="text-accent-400 text-sm font-medium">
                        {{ trialDaysRemaining }} days left in trial
                    </span>
                </div>
                <button
                    type="button"
                    @click="openRedeemByCode"
                    class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                >
                    Redeem by Code
                </button>
                <Link href="/business/qr-codes/create" class="btn-primary">
                    + New QR Code
                </Link>
            </div>
        </div>

        <!-- Redeem by Code Modal -->
        <div
            v-if="showRedeemByCode"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown.esc="closeRedeemByCode"
        >
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closeRedeemByCode"></div>

            <div class="relative w-full max-w-md glass-card p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Redeem by Code</h2>
                        <p class="text-gray-400 text-sm mt-1">Type the QR code to open the redemption screen.</p>
                    </div>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-white transition-colors"
                        @click="closeRedeemByCode"
                        aria-label="Close"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">QR Code</label>
                        <input
                            v-model="redeemCode"
                            type="text"
                            class="input-glass"
                            placeholder="A1B2C3D4"
                            autocomplete="off"
                            @keyup.enter="goRedeemByCode"
                        />
                        <p v-if="redeemError" class="mt-2 text-sm text-red-400">{{ redeemError }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                            @click="closeRedeemByCode"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="btn-primary"
                            @click="goRedeemByCode"
                        >
                            Redeem
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <Link
                v-for="stat in statCards"
                :key="stat.title"
                :href="stat.link"
                class="glass-card p-6 card-hover"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">{{ stat.title }}</p>
                        <p class="text-3xl font-bold text-white mt-1">{{ stat.value }}</p>
                    </div>
                    <div :class="`w-12 h-12 rounded-xl bg-gradient-to-br ${stat.color} flex items-center justify-center`">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path v-if="stat.icon === 'qrcode'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            <path v-else-if="stat.icon === 'gift'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                            <path v-else-if="stat.icon === 'eye'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            <path v-else-if="stat.icon === 'play'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-6.518-3.76A1 1 0 007 8.273v7.454a1 1 0 001.234.97l6.518-1.88a1 1 0 000-1.94l-6.518-1.88v-.001l6.518-1.88a1 1 0 000-1.94z" />
                            <path v-else-if="stat.icon === 'check-circle'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </Link>
        </div>

        <!-- Partnerships & Deal Pools Section -->
        <div v-if="partnershipStats" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Partnerships Card -->
            <Link href="/business/partnerships" class="glass-card p-6 card-hover border-l-4 border-purple-500">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        🤝 Partnerships
                    </h3>
                    <span v-if="partnershipStats.pending_incoming > 0" class="px-2 py-1 bg-amber-500/20 text-amber-400 text-xs rounded-full animate-pulse">
                        {{ partnershipStats.pending_incoming }} pending
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-white">{{ partnershipStats.active_partners }}</div>
                        <div class="text-gray-400 text-xs">Active Partners</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-amber-400">{{ partnershipStats.pending_incoming }}</div>
                        <div class="text-gray-400 text-xs">Incoming</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-400">{{ partnershipStats.pending_outgoing }}</div>
                        <div class="text-gray-400 text-xs">Outgoing</div>
                    </div>
                </div>
                <p v-if="partnershipStats.active_partners === 0" class="text-gray-500 text-sm mt-4 text-center">
                    Partner with other businesses for Partner Deal Chains →
                </p>
            </Link>

            <!-- Deal Pools Card -->
            <Link href="/business/stackable-pools" class="glass-card p-6 card-hover border-l-4 border-emerald-500">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        📍 Deal Pools
                    </h3>
                    <span v-if="partnershipStats.pending_pool_approvals > 0" class="px-2 py-1 bg-amber-500/20 text-amber-400 text-xs rounded-full">
                        {{ partnershipStats.pending_pool_approvals }} pending
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-white">{{ partnershipStats.active_pools }}</div>
                        <div class="text-gray-400 text-xs">Pools Joined</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-emerald-400">{{ partnershipStats.pool_reach }}</div>
                        <div class="text-gray-400 text-xs">Partner Businesses</div>
                    </div>
                </div>
                <p v-if="partnershipStats.active_pools === 0" class="text-gray-500 text-sm mt-4 text-center">
                    Join deal pools to reach more customers →
                </p>
                <p v-else class="text-gray-500 text-sm mt-4 text-center">
                    Your deals appear alongside {{ partnershipStats.pool_reach }} other businesses
                </p>
            </Link>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Recent Activity (2 columns) -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Redemption Rate Card -->
                <div class="glass-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Performance</h2>
                        <Link href="/business/analytics" class="text-primary-400 text-sm hover:underline">
                            View Details →
                        </Link>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Overall Prize Redemption</p>
                            <div class="flex items-end space-x-2">
                                <span class="text-4xl font-bold text-white">{{ stats.prize_redemption_rate || 0 }}%</span>
                            </div>
                            <p class="text-gray-500 text-xs mt-1">
                                {{ stats.prizes_redeemed_period || 0 }} redeemed / {{ stats.prizes_issued_period || 0 }} issued
                            </p>
                            <div class="mt-2 h-2 bg-gray-700 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-gradient-to-r from-primary-500 to-accent-500 rounded-full transition-all duration-500"
                                    :style="{ width: `${Math.min(stats.prize_redemption_rate || 0, 100)}%` }"
                                ></div>
                            </div>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Total Savings Generated</p>
                            <span class="text-4xl font-bold text-green-400">{{ formatCurrency(stats.total_savings) }}</span>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm mb-1">Punch Cards (Completion)</p>
                            <div class="flex items-end space-x-2">
                                <span class="text-4xl font-bold text-white">{{ stats.punch_completion_rate || 0 }}%</span>
                            </div>
                            <p class="text-gray-500 text-xs mt-1">
                                {{ stats.punch_completions_period || 0 }} completions • {{ stats.punch_stamps_period || 0 }} stamps • {{ stats.punch_customers_period || 0 }} customers
                            </p>
                            <div class="mt-2 h-2 bg-gray-700 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-gradient-to-r from-cyan-500 to-sky-500 rounded-full transition-all duration-500"
                                    :style="{ width: `${Math.min(stats.punch_completion_rate || 0, 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Scans -->
                <div class="glass-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Recent Scans</h2>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="scan in recentScans"
                            :key="scan.id"
                            class="flex items-center justify-between p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-primary-500/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-white font-medium">{{ scan.qr_code }}</p>
                                    <p class="text-gray-400 text-sm">{{ scan.device }} • {{ scan.location || 'Location not provided' }}</p>
                                </div>
                            </div>
                            <span class="text-gray-500 text-sm">{{ scan.time }}</span>
                        </div>
                        <p v-if="!recentScans.length" class="text-center text-gray-500 py-4">
                            No scans yet. Share your QR codes to start tracking!
                        </p>
                    </div>
                </div>

                <!-- Recent Redemptions -->
                <div class="glass-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Recent Redemptions</h2>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="redemption in recentRedemptions"
                            :key="redemption.id"
                            class="flex items-center justify-between p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-white font-medium">{{ redemption.promotion }}</p>
                                    <p class="text-gray-400 text-sm">
                                        Customer: {{ redemption.customer }}
                                        <span v-if="redemption.staff"> • Processed by {{ redemption.staff }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-green-400 font-medium">-{{ formatCurrency(redemption.discount) }}</p>
                                <p class="text-gray-500 text-sm">{{ redemption.time }}</p>
                            </div>
                        </div>
                        <p v-if="!recentRedemptions.length" class="text-center text-gray-500 py-4">
                            No redemptions yet. Create promotions and attach them to QR codes!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Sidebar (1 column) -->
            <div class="space-y-8">
                <!-- Onboarding Checklist -->
                <OnboardingChecklist
                    v-if="showOnboarding"
                    :steps="onboardingSteps"
                    :upsell-steps="onboardingUpsellSteps"
                    :progress="onboardingProgress"
                    :show-onboarding="showOnboarding"
                />

                <!-- Quick Actions -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <Link href="/business/qr-codes/create" class="flex items-center p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <span class="text-white">Create QR Code</span>
                        </Link>
                        <Link href="/business/promotions/create" class="flex items-center p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                </svg>
                            </div>
                            <span class="text-white">New Promotion</span>
                        </Link>
                        <Link href="/business/partnerships" class="flex items-center p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-pink-500/20 flex items-center justify-center mr-3">
                                <span class="text-lg">🤝</span>
                            </div>
                            <div class="flex-1 flex items-center justify-between">
                                <span class="text-white">Find Partners</span>
                                <span v-if="partnershipStats?.pending_incoming > 0" class="px-2 py-0.5 bg-amber-500/20 text-amber-400 text-xs rounded-full">
                                    {{ partnershipStats.pending_incoming }}
                                </span>
                            </div>
                        </Link>
                        <Link href="/business/stackable-pools" class="flex items-center p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center mr-3">
                                <span class="text-lg">📍</span>
                            </div>
                            <span class="text-white">Join Deal Pools</span>
                        </Link>
                        <Link href="/business/print-studio" class="flex items-center p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                            </div>
                            <span class="text-white">Print Studio</span>
                        </Link>
                        <Link href="/business/employees" class="flex items-center p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
                            <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <span class="text-white">Manage Employees</span>
                        </Link>
                    </div>
                </div>

                <!-- Top QR Codes -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Top QR Codes</h2>
                    <div class="space-y-3">
                        <div
                            v-for="(qr, index) in topQRCodes"
                            :key="qr.id"
                            class="flex items-center justify-between"
                        >
                            <div class="flex items-center space-x-3">
                                <span class="text-gray-500 text-sm w-4">{{ index + 1 }}</span>
                                <div>
                                    <p class="text-white font-medium text-sm">{{ qr.name }}</p>
                                    <p class="text-gray-500 text-xs">{{ qr.placement_location || 'No location' }}</p>
                                </div>
                            </div>
                            <span class="text-primary-400 font-medium">{{ formatNumber(qr.total_scans) }}</span>
                        </div>
                        <p v-if="!topQRCodes.length" class="text-center text-gray-500 py-2 text-sm">
                            No QR codes yet
                        </p>
                    </div>
                </div>

                <!-- Top Promotions -->
                <div class="glass-card p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Top Promotions</h2>
                    <div class="space-y-3">
                        <div
                            v-for="(promo, index) in topPromotions"
                            :key="promo.id"
                            class="flex items-center justify-between"
                        >
                            <div class="flex items-center space-x-3">
                                <span class="text-gray-500 text-sm w-4">{{ index + 1 }}</span>
                                <div>
                                    <p class="text-white font-medium text-sm">{{ promo.name }}</p>
                                    <p class="text-gray-500 text-xs">{{ promo.discount_type }}</p>
                                </div>
                            </div>
                            <span class="text-green-400 font-medium">{{ promo.total_redemptions }}</span>
                        </div>
                        <p v-if="!topPromotions.length" class="text-center text-gray-500 py-2 text-sm">
                            No promotions yet
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

