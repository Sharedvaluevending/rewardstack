<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    referralCode: String,
    referralLink: String,
    stats: Object,
    referrals: Object,
    recentCommissions: Object,
    minimumPayout: Number,
    stripeConnect: {
        type: Object,
        default: () => ({ enabled: false, connected: false }),
    },
});

const page = usePage();

const copied = ref(false);
const showPayoutModal = ref(false);
const showQRModal = ref(false);

const payoutForm = useForm({
    method: 'paypal',
    destination: '',
});

const resetStripeConnectForm = useForm({});

const platformUrl = computed(() => {
    try {
        return window?.location?.origin || 'https://revenueqr.com';
    } catch (e) {
        return 'https://revenueqr.com';
    }
});

// Generate QR code URL using Google Charts API (free, no dependencies)
const qrCodeSrc = computed(() => {
    const encodedUrl = encodeURIComponent(props.referralLink);
    return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodedUrl}&bgcolor=ffffff&color=000000&margin=10`;
});

// Print QR code
const printQRCode = () => {
    const printWindow = window.open('', '_blank');
    const html = [
        '<!DOCTYPE html>',
        '<html><head>',
        '<title>Revenue QR Referral Card</title>',
        '<style>',
        '* { margin: 0; padding: 0; box-sizing: border-box; }',
        '@page { size: letter; margin: 0.4in; }',
        'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #fff; color: #111; }',
        '.page { width: 100%; min-height: 10.2in; display: flex; flex-direction: column; align-items: center; }',
        '.content { width: 100%; max-width: 7.25in; display: flex; flex-direction: column; gap: 18px; text-align: center; }',
        '.header { text-align: center; }',
        '.logo { font-size: 34px; font-weight: 800; color: #7C3AED; letter-spacing: 0.2px; }',
        '.tagline { color: #555; font-size: 16px; margin-top: 6px; }',
        '.cta { text-align: center; }',
        '.cta-title { font-size: 24px; font-weight: 800; color: #10B981; }',
        '.cta-sub { color: #555; font-size: 14px; margin-top: 6px; }',
        '.qr { display: flex; justify-content: center; }',
        '.qr img { width: 260px; height: 260px; }',
        '.core { display: grid; gap: 8px; text-align: left; margin: 0 auto; max-width: 6.6in; }',
        '.core div { font-size: 15px; color: #222; font-weight: 600; }',
        '.advanced { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; text-align: left; }',
        '.group-title { font-weight: 700; color: #111; font-size: 13px; margin-bottom: 6px; }',
        '.group div { font-size: 12.5px; color: #333; margin-bottom: 6px; }',
        '.price { font-size: 13px; color: #333; font-weight: 600; text-align: center; margin-top: auto; }',
        '.trust { font-size: 11px; color: #555; text-align: center; }',
        '@media print { body { background: #fff; } }',
        '</style></head><body>',
        '<div class="page">',
        '<div class="content">',
        '<div class="header"><div class="logo">RevenueQR</div><div class="tagline">Bring customers back with QR codes</div></div>',
        '<div class="cta"><div class="cta-title">📱 Scan to Grow Your Business</div><div class="cta-sub">Set up deals, games & loyalty in minutes</div></div>',
        '<div class="qr"><img src="' + qrCodeSrc.value + '" alt="Referral QR Code" /></div>',
        '<div class="core">',
        '<div>✅ Instant deals customers actually use — no app needed</div>',
        '<div>✅ Games, punch cards & rewards that drive repeat visits</div>',
        '<div>✅ No POS • No screenshots • One per customer</div>',
        '</div>',
        '<div class="advanced">',
        '<div class="group"><div class="group-title">Grow & retain customers</div><div>🔁 Automated CRM nudges & win-backs</div><div>🤝 Cross-business (chain) promotions</div></div>',
        '<div class="group"><div class="group-title">Stand out & monetize</div><div>👕 Custom merch & ambassador rewards</div><div>🧠 AI insights & recommendations</div></div>',
        '</div>',
        '<div class="price">Free trial available • Plans start at ~66¢ per day</div>',
        '<div class="trust">No contracts • Cancel anytime</div>',
        '</div>',
        '</div>',
        '<scr' + 'ipt>window.onload = () => setTimeout(() => window.print(), 500);</scr' + 'ipt>',
        '</body></html>'
    ].join('');
    printWindow.document.write(html);
    printWindow.document.close();
};

// Download QR code
const downloadQRCode = async () => {
    try {
        const response = await fetch(qrCodeSrc.value);
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `qr-revenue-referral-${props.referralCode}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    } catch (err) {
        // Fallback - open in new tab
        window.open(qrCodeSrc.value, '_blank');
    }
};

// Copy referral link to clipboard
const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.referralLink);
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    } catch (err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = props.referralLink;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    }
};

// Share via native share API if available
const shareLink = async () => {
    if (navigator.share) {
        try {
            await navigator.share({
                title: 'Join Revenue QR',
                text: 'Check out Revenue QR - the easiest way to bring customers back to your business!',
                url: props.referralLink,
            });
        } catch (err) {
            copyLink();
        }
    } else {
        copyLink();
    }
};

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

// Submit payout request
const submitPayout = () => {
    payoutForm.post('/portal/referrals/payout', {
        onSuccess: () => {
            showPayoutModal.value = false;
            payoutForm.reset();
        },
    });
};

// Can request payout?
const canRequestPayout = computed(() => {
    return props.stats.available_payout >= props.minimumPayout;
});

// Status badge color
const getStatusColor = (status) => {
    const colors = {
        'active': 'bg-emerald-500/20 text-emerald-400',
        'paused': 'bg-yellow-500/20 text-yellow-400',
        'cancelled': 'bg-red-500/20 text-red-400',
        'pending': 'bg-blue-500/20 text-blue-400',
        'approved': 'bg-emerald-500/20 text-emerald-400',
        'paid': 'bg-purple-500/20 text-purple-400',
        'processing': 'bg-yellow-500/20 text-yellow-400',
        'on_hold': 'bg-orange-500/20 text-orange-400',
    };
    return colors[status] || 'bg-gray-500/20 text-gray-400';
};
</script>

<template>
    <PortalLayout>
        <Head title="Referral Program" />

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                💰 Referral Army
            </h1>
            <p class="text-gray-400 text-sm mt-1">Refer businesses, earn recurring income</p>
        </div>

        <!-- Flash / Errors -->
        <div v-if="page.props?.flash?.success" class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props?.flash?.warning" class="mb-4 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm">
            {{ page.props.flash.warning }}
        </div>
        <div v-if="page.props?.flash?.error" class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            {{ page.props.flash.error }}
        </div>
        <div v-if="page.props?.errors?.stripe" class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            {{ page.props.errors.stripe }}
        </div>
        <div v-if="page.props?.errors?.payout" class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            {{ page.props.errors.payout }}
        </div>

        <!-- Referral QR Code Card -->
        <div class="bg-gradient-to-br from-purple-600 via-purple-700 to-pink-600 rounded-2xl p-5 mb-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-40 h-40 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>
            
            <div class="relative">
                <div class="text-center mb-4">
                    <div class="text-white/80 text-sm mb-1">Your Referral Code</div>
                    <div class="text-2xl font-bold text-white tracking-wider">{{ referralCode }}</div>
                </div>

                <!-- QR Code -->
                <div class="flex justify-center mb-4">
                    <div class="bg-white rounded-2xl p-3 shadow-xl cursor-pointer transform hover:scale-105 transition-transform"
                        @click="showQRModal = true">
                        <img :src="qrCodeSrc" alt="Referral QR Code" class="w-40 h-40" />
                    </div>
                </div>

                <div class="text-center mb-4">
                    <div class="text-white font-semibold text-lg">📱 Scan to Grow Your Business</div>
                    <div class="text-white/70 text-sm">Have businesses scan this to sign up!</div>
                </div>
                
                <div class="grid grid-cols-3 gap-2">
                    <button @click="copyLink"
                        class="py-2 px-3 bg-white/20 hover:bg-white/30 text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1">
                        <span v-if="copied">✓</span>
                        <span v-else>📋</span>
                        <span class="hidden sm:inline">{{ copied ? 'Copied' : 'Copy' }}</span>
                    </button>
                    <button @click="printQRCode"
                        class="py-2 px-3 bg-white/20 hover:bg-white/30 text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1">
                        🖨️ <span class="hidden sm:inline">Print</span>
                    </button>
                    <button @click="shareLink"
                        class="py-2 px-3 bg-white/20 hover:bg-white/30 text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1">
                        📤 <span class="hidden sm:inline">Share</span>
                    </button>
                </div>

                <div class="mt-3 text-white/50 text-xs text-center break-all">
                    {{ referralLink }}
                </div>
            </div>
        </div>

        <!-- Leave at Counter Tip -->
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                <span class="text-2xl">💡</span>
                <div>
                    <div class="text-amber-400 font-semibold">Pro Tip: Print & Leave at Counters!</div>
                    <p class="text-gray-400 text-sm mt-1">
                        Print your QR card and leave it at business counters, break rooms, or bulletin boards. 
                        Every business that scans and signs up = <span class="text-emerald-400 font-medium">recurring income for you!</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-emerald-400">{{ stats.total_referrals }}</div>
                <div class="text-gray-400 text-xs mt-1">Businesses Referred</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-purple-400">{{ formatCurrency(stats.monthly_recurring) }}</div>
                <div class="text-gray-400 text-xs mt-1">This Month</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-amber-400">{{ formatCurrency(stats.total_earned) }}</div>
                <div class="text-gray-400 text-xs mt-1">Total Earned</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-blue-400">{{ formatCurrency(stats.available_payout) }}</div>
                <div class="text-gray-400 text-xs mt-1">Available Payout</div>
            </div>
        </div>

        <!-- Payout Section -->
        <div class="mb-6">
            <!-- Stripe Connected Badge -->
            <div v-if="stripeConnect.connected" class="mb-3 p-3 rounded-xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-blue-400">🏦</span>
                    <span class="text-blue-400 text-sm font-medium">Bank Account Connected</span>
                    <span class="text-gray-500 text-xs">• Auto-payouts enabled</span>
                </div>
                <a href="/portal/stripe/dashboard" class="text-blue-400 text-xs hover:underline">Manage</a>
            </div>

            <!-- Connect Bank (if Stripe enabled but not connected) -->
            <a v-else-if="stripeConnect.enabled" 
                href="/portal/stripe/connect"
                class="block mb-3 p-3 rounded-xl bg-gradient-to-r from-blue-500/10 to-indigo-500/10 border border-blue-500/30 hover:border-blue-500/50 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-white font-medium text-sm flex items-center gap-2">
                            <span>🏦</span> Connect Bank Account
                        </div>
                        <p class="text-gray-400 text-xs mt-0.5">Get automatic payouts directly to your bank</p>
                    </div>
                    <span class="text-blue-400 text-xl">→</span>
                </div>
            </a>

            <!-- Stripe onboarding helper -->
            <div v-if="stripeConnect.enabled && !stripeConnect.connected" class="mb-3 p-4 rounded-xl bg-white/5 border border-white/10">
                <div class="text-white text-sm font-semibold mb-1">What to expect (Stripe verification)</div>
                <div class="text-gray-400 text-xs space-y-2">
                    <p>
                        Stripe requires identity verification (KYC) to pay you to a bank account. The steps may be labeled “Business details” even for individuals/sole proprietors.
                    </p>
                    <ul class="list-disc list-inside space-y-1">
                        <li><span class="text-gray-300 font-medium">Bank Details</span>: Have your transit/routing & account numbers ready (find on a check, online banking, or ask your bank)</li>
                        <li><span class="text-gray-300 font-medium">Website</span>: use <span class="text-gray-200 font-mono">{{ platformUrl }}</span></li>
                        <li><span class="text-gray-300 font-medium">Business name</span>: use your legal name (sole proprietor/individual is OK)</li>
                        <li><span class="text-gray-300 font-medium">Job title</span>: “Referral Partner” / “Affiliate”</li>
                    </ul>
                    <p class="text-gray-500">
                        If you previously picked the wrong option and Stripe won’t show the choices again, click “Start over” below and re-run Connect.
                    </p>
                </div>
            </div>

            <!-- If user got stuck in onboarding, allow reset -->
            <button
                v-if="stripeConnect.enabled && !stripeConnect.connected"
                type="button"
                class="w-full mb-3 p-3 rounded-xl bg-white/5 border border-white/10 text-gray-200 hover:bg-white/10 transition-colors text-sm"
                @click="resetStripeConnectForm.post('/portal/stripe/reset')"
            >
                Start over (reset bank connect)
            </button>

            <!-- Payout Button -->
            <button 
                @click="showPayoutModal = true"
                :disabled="!canRequestPayout || stripeConnect.connected"
                class="w-full py-3 rounded-xl font-semibold transition-all"
                :class="[
                    stripeConnect.connected 
                        ? 'bg-white/5 text-gray-500 cursor-not-allowed'
                        : canRequestPayout 
                            ? 'bg-gradient-to-r from-emerald-500 to-green-600 text-white hover:from-emerald-600 hover:to-green-700' 
                            : 'bg-white/5 text-gray-500 cursor-not-allowed'
                ]"
            >
                <span v-if="stripeConnect.connected">✓ Auto-payouts enabled</span>
                <span v-else-if="canRequestPayout">💵 Request Payout ({{ formatCurrency(stats.available_payout) }})</span>
                <span v-else>Minimum {{ formatCurrency(minimumPayout) }} to withdraw</span>
            </button>
            <p v-if="stripeConnect.connected" class="text-gray-500 text-xs text-center mt-2">
                Payouts are sent automatically when you have ${{ minimumPayout }}+
            </p>
        </div>

        <!-- How It Works -->
        <div class="bg-white/5 backdrop-blur rounded-xl p-4 mb-6 border border-white/10">
            <h3 class="text-white font-semibold mb-3">🎯 How It Works</h3>
            <div class="space-y-3 text-sm">
                <div class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>
                    <p class="text-gray-400">Share your referral link with business owners you know</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>
                    <p class="text-gray-400">When they sign up and subscribe, you earn <span class="text-emerald-400 font-medium">10% commission</span></p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>
                    <p class="text-gray-400">You keep earning <span class="text-emerald-400 font-medium">every month</span> they stay subscribed!</p>
                </div>
            </div>

            <div class="mt-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                <p class="text-emerald-400 text-sm text-center">
                    <strong>Example:</strong> Refer 5 businesses at $79/mo = <span class="text-xl font-bold">$39.50/mo</span> passive income!
                </p>
            </div>
        </div>

        <!-- Referred Businesses -->
        <div v-if="referrals.data?.length" class="mb-6">
            <h3 class="text-lg font-semibold text-white mb-3">🏪 Your Referrals <span class="text-gray-500 text-sm font-normal">({{ referrals.total }})</span></h3>
            <div class="space-y-3">
                <div v-for="referral in referrals.data" :key="referral.id"
                    :class="['backdrop-blur rounded-xl p-4 border', referral.status === 'cancelled' ? 'bg-red-500/5 border-red-500/20' : 'bg-white/5 border-white/10']">
                    <div class="flex items-center gap-3">
                        <div v-if="referral.business_logo" class="w-12 h-12 rounded-xl bg-white p-1">
                            <img :src="referral.business_logo" :alt="referral.business_name" class="w-full h-full object-contain" />
                        </div>
                        <div v-else class="w-12 h-12 rounded-xl flex items-center justify-center"
                            :class="referral.status === 'cancelled' ? 'bg-gradient-to-br from-gray-500 to-gray-600' : 'bg-gradient-to-br from-purple-500 to-pink-500'">
                            <span class="text-xl font-bold text-white">{{ referral.business_name.charAt(0) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-white font-medium truncate">{{ referral.business_name }}</div>
                            <div class="text-gray-400 text-sm">{{ referral.subscription_tier }} plan · {{ referral.commission_rate }}% commission</div>
                            <div v-if="referral.status === 'cancelled'" class="text-red-400 text-xs mt-0.5">
                                Subscription cancelled — no future commissions
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-emerald-400 font-bold">{{ formatCurrency(referral.total_earned) }}</div>
                            <span :class="['text-xs px-2 py-0.5 rounded-full', getStatusColor(referral.status)]">
                                {{ referral.status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Referrals Pagination -->
            <div v-if="referrals.links && referrals.links.length > 3" class="mt-4 flex justify-center gap-2">
                <Link
                    v-for="link in referrals.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    :class="[
                        'px-3 py-1 rounded-lg text-sm transition-colors',
                        link.active 
                            ? 'bg-primary-500 text-white' 
                            : 'bg-white/5 text-gray-400 hover:bg-white/10',
                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                    v-html="link.label"
                ></Link>
            </div>
        </div>

        <!-- Recent Commissions -->
        <div v-if="recentCommissions.data?.length" class="mb-6">
            <h3 class="text-lg font-semibold text-white mb-3">📊 Recent Earnings <span class="text-gray-500 text-sm font-normal">({{ recentCommissions.total }})</span></h3>
            <div class="space-y-2">
                <div v-for="commission in recentCommissions.data" :key="commission.id"
                    class="flex items-center justify-between p-3 rounded-xl bg-white/5 border border-white/10">
                    <div>
                        <div class="text-white text-sm">{{ commission.business_name }}</div>
                        <div class="text-gray-500 text-xs">{{ commission.period }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-emerald-400 font-medium">+{{ formatCurrency(commission.amount) }}</div>
                        <span :class="['text-xs px-2 py-0.5 rounded-full', getStatusColor(commission.status)]">
                            {{ commission.status }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Commissions Pagination -->
            <div v-if="recentCommissions.links && recentCommissions.links.length > 3" class="mt-4 flex justify-center gap-2">
                <Link
                    v-for="link in recentCommissions.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    :class="[
                        'px-3 py-1 rounded-lg text-sm transition-colors',
                        link.active 
                            ? 'bg-primary-500 text-white' 
                            : 'bg-white/5 text-gray-400 hover:bg-white/10',
                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                    v-html="link.label"
                ></Link>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!referrals.data?.length" class="text-center py-8">
            <div class="text-6xl mb-4">🚀</div>
            <h3 class="text-white font-semibold mb-2">Start Earning Today!</h3>
            <p class="text-gray-400 text-sm mb-4">Share your referral link with business owners you know.</p>
            <button @click="copyLink"
                class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-semibold rounded-xl">
                Copy Your Link
            </button>
        </div>

        <!-- Payout Modal -->
        <div v-if="showPayoutModal" class="fixed inset-0 bg-black/80 flex items-center justify-center p-4 z-50">
            <div class="bg-gray-900 rounded-2xl p-6 w-full max-w-md border border-white/10">
                <h3 class="text-xl font-bold text-white mb-4">💵 Request Payout</h3>
                
                <div class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                    <div class="text-gray-400 text-sm">Amount to withdraw</div>
                    <div class="text-3xl font-bold text-emerald-400">{{ formatCurrency(stats.available_payout) }}</div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Payout Method</label>
                    <select v-model="payoutForm.method"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white">
                        <option value="paypal" class="bg-gray-800 text-white">PayPal</option>
                        <option value="venmo" class="bg-gray-800 text-white">Venmo</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        {{ payoutForm.method === 'paypal' ? 'PayPal Email' : 'Venmo Username' }}
                    </label>
                    <input v-model="payoutForm.destination"
                        type="text"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white"
                        :placeholder="payoutForm.method === 'paypal' ? 'your@email.com' : '@username'"
                    />
                </div>

                <div class="flex gap-3">
                    <button @click="showPayoutModal = false"
                        class="flex-1 py-3 bg-white/10 text-white rounded-xl hover:bg-white/20">
                        Cancel
                    </button>
                    <button @click="submitPayout"
                        :disabled="payoutForm.processing || !payoutForm.destination"
                        class="flex-1 py-3 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-semibold rounded-xl disabled:opacity-50">
                        {{ payoutForm.processing ? 'Processing...' : 'Request Payout' }}
                    </button>
                </div>

                <p class="text-gray-500 text-xs text-center mt-4">
                    Payouts are processed within 3-5 business days
                </p>
            </div>
        </div>

        <!-- QR Code Full Modal -->
        <div v-if="showQRModal" class="fixed inset-0 bg-black/90 flex items-center justify-center p-4 z-50" @click="showQRModal = false">
            <div class="bg-white rounded-3xl p-8 max-w-sm w-full text-center" @click.stop>
                <div class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent mb-2">
                    Revenue QR
                </div>
                <div class="text-gray-500 text-sm mb-6">Referral Code: {{ referralCode }}</div>
                
                <div class="bg-gray-50 rounded-2xl p-4 mb-6">
                    <img :src="qrCodeSrc" alt="Referral QR Code" class="w-64 h-64 mx-auto" />
                </div>

                <div class="text-xl font-bold text-emerald-600 mb-2">📱 Scan to Grow Your Business</div>
                <p class="text-gray-500 text-sm mb-6">Get started with powerful QR marketing today!</p>

                <div class="grid grid-cols-2 gap-3">
                    <button @click="printQRCode"
                        class="py-3 bg-purple-600 text-white font-semibold rounded-xl hover:bg-purple-700 transition-colors">
                        🖨️ Print Card
                    </button>
                    <button @click="downloadQRCode"
                        class="py-3 bg-emerald-600 text-white font-semibold rounded-xl hover:bg-emerald-700 transition-colors">
                        💾 Download
                    </button>
                </div>

                <button @click="showQRModal = false" class="mt-4 text-gray-400 text-sm hover:text-gray-600">
                    Close
                </button>
            </div>
        </div>
    </PortalLayout>
</template>
