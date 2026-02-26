<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    plans: {
        type: Array,
        default: () => [],
    },
});

const billingPeriod = ref('monthly');

const formatNumber = (value) => {
    if (value === -1) return 'Unlimited';
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat().format(value);
};

const planFeatureList = (plan) => {
    const f = plan?.features || {};

    const list = [];
    if (f.qr_codes !== undefined) list.push(`${formatNumber(f.qr_codes)} QR Codes`);
    if (f.promotions !== undefined) list.push(`${formatNumber(f.promotions)} Promotions`);
    if (f.scans_per_month !== undefined) list.push(`${formatNumber(f.scans_per_month)} Scans/month`);
    if (f.employees !== undefined) list.push(`${formatNumber(f.employees)} Employees`);
    if (f.analytics_days !== undefined) list.push(`${formatNumber(f.analytics_days)}-Day Analytics`);

    // QRcade (10-game ladder is driven by plan features)
    if (f.qrcade) {
        const gamesCount = f.games ?? null;
        if (gamesCount === -1) {
            list.push('QRcade Games (Unlimited)');
        } else if (gamesCount !== null) {
            list.push(`QRcade Games (${gamesCount})`);
        } else {
            list.push('QRcade Games');
        }
    }

    if (f.print_studio) list.push('Print Studio');
    if (f.merch_store) list.push('Merch Store');
    if (f.merch_referral_qr) list.push('Merch Referral QR');
    if (f.advanced_analytics) list.push('Advanced Analytics');
    if (f.ai_insights) {
        if (plan.slug === 'growth') {
            list.push('Basic AI Insights');
        } else {
            list.push('Advanced AI Insights');
        }
    }

    // CRM (included in all tiers)
    if (f.crm) list.push('Built-in CRM + Email Campaigns');
    if (f.crm_automations) list.push('Automations: winback + reminders');

    if (f.cross_promotions) list.push('Partner Deal Chain');
    if (f.stackable_pools) list.push('Stackable Deals');
    if (f.leaderboards) list.push('Leaderboards');
    if (f.tournaments) list.push('Tournaments');
    if (f.api_access) list.push('API Access');
    if (f.priority_support) list.push('Priority Support');
    if (f.remove_branding) list.push('Remove Branding');
    if (f.white_label) list.push('White Label');

    return list;
};

const yn = (v) => (v ? '✓' : '—');

const planNotIncludedList = (plan) => {
    const f = plan?.features || {};

    const notIncluded = [];
    const maybeAdd = (enabled, label) => {
        if (!enabled) notIncluded.push(label);
    };

    // Only show “not included” for non-enterprise tiers (keeps Enterprise clean)
    if ((plan?.slug || '').toLowerCase() === 'enterprise') {
        return [];
    }

    maybeAdd(f.cross_promotions, 'Partner Deal Chain');
    maybeAdd(f.stackable_pools, 'Stackable Deals');
    maybeAdd(f.leaderboards, 'Leaderboards');
    maybeAdd(f.tournaments, 'Tournaments');
    maybeAdd(f.merch_referral_qr, 'Merch Referral QR');
    maybeAdd(f.api_access, 'API Access');
    maybeAdd(f.white_label, 'White Label');
    // CRM should be on all tiers; if it ever gets disabled, show it here.
    maybeAdd(f.crm, 'CRM + Email Campaigns');
    maybeAdd(f.crm_automations, 'Automations');

    return notIncluded;
};

const uiPlans = computed(() => {
    return (props.plans || []).map((p) => {
        const slug = (p.slug || '').toLowerCase();
        return {
            ...p,
            monthlyPrice: Number(p.monthly_price ?? p.monthlyPrice ?? 0),
            yearlyPrice: Number(p.yearly_price ?? p.yearlyPrice ?? 0),
            popular: !!p.is_featured,
            cta: slug === 'enterprise' ? 'Contact Sales' : 'Start Free Trial',
            // Keep original plan.features for the comparison table.
            featureList: planFeatureList(p),
            notIncluded: planNotIncludedList(p),
        };
    });
});

const getPrice = (plan) => {
    return billingPeriod.value === 'yearly' ? plan.yearlyPrice : plan.monthlyPrice;
};

const planBySlug = computed(() => {
    const map = {};
    for (const p of uiPlans.value) {
        if (p.slug) map[p.slug] = p;
    }
    return map;
});

const faqs = [
    {
        question: 'Can I switch plans later?',
        answer: 'Yes! You can upgrade or downgrade your plan at any time. Changes take effect on your next billing cycle, and we\'ll prorate any differences.',
    },
    {
        question: 'Is there a free trial?',
        answer: 'Yes, all paid plans include a 14-day free trial. No credit card required to start your trial.',
    },
    {
        question: 'What happens if I exceed my limits?',
        answer: 'We\'ll notify you when you\'re approaching your limits. You can upgrade your plan anytime, or we can discuss overage options for your needs.',
    },
    {
        question: 'Can I cancel anytime?',
        answer: 'Absolutely. Cancel anytime with no questions asked. You\'ll retain access until the end of your billing period.',
    },
    {
        question: 'What are QRcade games?',
        answer: 'QRcade is our gamification feature. When customers scan your QR code, they can play mini-games like Memory Match, Snake, Word Search, and more. Winners can receive your promotions as prizes!',
    },
    {
        question: 'What is a Partner Deal Chain?',
        answer: 'Partner Deal Chain lets you pair your offer with a partner business offer on a single scan. You can leave it open (any order) or chain it so one deal unlocks the other after redemption.',
    },
];
</script>

<template>
    <Head title="Pricing" />

    <!-- Hero -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-5xl font-bold mb-6">
                <span class="gradient-text">Simple, Transparent Pricing</span>
            </h1>
            <p class="text-xl text-gray-400 max-w-3xl mx-auto mb-8">
                Choose the plan that fits your business. All plans include a 14-day free trial.
            </p>

            <!-- Billing Toggle -->
            <div class="inline-flex items-center p-1 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20">
                <button
                    @click="billingPeriod = 'monthly'"
                    :class="[
                        'px-6 py-2 rounded-lg font-medium transition-all',
                        billingPeriod === 'monthly'
                            ? 'bg-primary-500 text-white'
                            : 'text-gray-400 hover:text-white'
                    ]"
                >
                    Monthly
                </button>
                <button
                    @click="billingPeriod = 'yearly'"
                    :class="[
                        'px-6 py-2 rounded-lg font-medium transition-all',
                        billingPeriod === 'yearly'
                            ? 'bg-primary-500 text-white'
                            : 'text-gray-400 hover:text-white'
                    ]"
                >
                    Yearly
                    <span class="ml-2 px-2 py-0.5 bg-green-500/20 text-green-400 rounded text-xs">
                        Save 17%
                    </span>
                </button>
            </div>
        </div>
    </section>

    <!-- Pricing Grid -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div 
                    v-for="plan in uiPlans" 
                    :key="plan.name"
                    :class="[
                        'glass-card p-6 relative flex flex-col',
                        plan.popular ? 'ring-2 ring-primary-500' : ''
                    ]"
                >
                    <!-- Popular Badge -->
                    <div v-if="plan.popular" class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="px-3 py-1 bg-primary-500 text-white text-sm font-semibold rounded-full">
                            Most Popular
                        </span>
                    </div>

                    <h3 class="text-xl font-bold text-white mb-2">{{ plan.name }}</h3>
                    <p class="text-gray-400 text-sm mb-4 min-h-[48px]">{{ plan.description }}</p>
                    
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-white">${{ getPrice(plan) }}</span>
                        <span class="text-gray-400">/{{ billingPeriod === 'yearly' ? 'year' : 'month' }}</span>
                    </div>

                    <Link 
                        :href="plan.name === 'Enterprise' ? 'mailto:support@revenueqr.com' : '/register'"
                        :class="[
                            'block w-full py-3 rounded-xl font-semibold text-center transition-all mb-6',
                            plan.popular 
                                ? 'bg-primary-500 text-white hover:bg-primary-600' 
                                : 'bg-white/10 text-white hover:bg-white/20'
                        ]"
                    >
                        {{ plan.cta }}
                    </Link>

                    <div class="flex-1 space-y-3">
                        <div v-for="feature in plan.featureList" :key="feature" class="flex items-start">
                            <svg class="w-5 h-5 text-green-400 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-300">{{ feature }}</span>
                        </div>
                        <div v-for="feature in plan.notIncluded" :key="feature" class="flex items-start opacity-50">
                            <svg class="w-5 h-5 text-gray-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span class="text-sm text-gray-500">{{ feature }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature Comparison -->
    <section class="py-20 bg-white/5">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white text-center mb-12">Compare All Features</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-white/10">
                            <th class="text-left py-4 text-gray-400 font-medium">Feature</th>
                            <th class="text-center py-4 text-white font-semibold">Starter</th>
                            <th class="text-center py-4 text-primary-400 font-semibold">Growth</th>
                            <th class="text-center py-4 text-white font-semibold">Pro</th>
                            <th class="text-center py-4 text-white font-semibold">Enterprise</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <tr>
                            <td class="py-4 text-gray-300">QR Codes</td>
                            <td class="py-4 text-center text-gray-400">{{ planBySlug.starter?.features?.qr_codes ?? '—' }}</td>
                            <td class="py-4 text-center text-gray-400">{{ planBySlug.growth?.features?.qr_codes ?? '—' }}</td>
                            <td class="py-4 text-center text-gray-400">{{ planBySlug.pro?.features?.qr_codes ?? '—' }}</td>
                            <td class="py-4 text-center text-gray-400">{{ planBySlug.enterprise?.features?.qr_codes ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Promotions</td>
                            <td class="py-4 text-center text-gray-400">{{ planBySlug.starter?.features?.promotions ?? '—' }}</td>
                            <td class="py-4 text-center text-gray-400">{{ planBySlug.growth?.features?.promotions ?? '—' }}</td>
                            <td class="py-4 text-center text-gray-400">{{ planBySlug.pro?.features?.promotions ?? '—' }}</td>
                            <td class="py-4 text-center text-gray-400">{{ planBySlug.enterprise?.features?.promotions ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Scans/Month</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.starter?.features?.scans_per_month ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.growth?.features?.scans_per_month ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.pro?.features?.scans_per_month ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.enterprise?.features?.scans_per_month ?? null) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Employees</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.starter?.features?.employees ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.growth?.features?.employees ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.pro?.features?.employees ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.enterprise?.features?.employees ?? null) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Analytics (days)</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.starter?.features?.analytics_days ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.growth?.features?.analytics_days ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.pro?.features?.analytics_days ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.enterprise?.features?.analytics_days ?? null) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">QRcade Games</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.starter?.features?.games ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.growth?.features?.games ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.pro?.features?.games ?? null) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ formatNumber(planBySlug.enterprise?.features?.games ?? null) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Game Analytics</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.starter?.features?.game_analytics) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.growth?.features?.game_analytics) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.pro?.features?.game_analytics) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.enterprise?.features?.game_analytics) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Leaderboards</td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.starter?.features?.leaderboards" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.growth?.features?.leaderboards" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.pro?.features?.leaderboards" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.enterprise?.features?.leaderboards" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Tournaments</td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.starter?.features?.tournaments" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.growth?.features?.tournaments" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.pro?.features?.tournaments" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.enterprise?.features?.tournaments" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Partner Deal Chain</td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.starter?.features?.cross_promotions" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.growth?.features?.cross_promotions" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.pro?.features?.cross_promotions" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.enterprise?.features?.cross_promotions" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">AI Insights</td>
                            <td class="py-4 text-center text-gray-500">—</td>
                            <td class="py-4 text-center text-gray-400">Basic</td>
                            <td class="py-4 text-center text-gray-400">Advanced</td>
                            <td class="py-4 text-center text-gray-400">Advanced</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Advanced Analytics</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.starter?.features?.advanced_analytics) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.growth?.features?.advanced_analytics) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.pro?.features?.advanced_analytics) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.enterprise?.features?.advanced_analytics) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">CRM + Email Campaigns</td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.starter?.features?.crm" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.growth?.features?.crm" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.pro?.features?.crm" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.enterprise?.features?.crm" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">CRM Automations</td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.starter?.features?.crm_automations" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.growth?.features?.crm_automations" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.pro?.features?.crm_automations" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.enterprise?.features?.crm_automations" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Print Studio</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.starter?.features?.print_studio) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.growth?.features?.print_studio) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.pro?.features?.print_studio) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.enterprise?.features?.print_studio) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Sticker Kits</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.starter?.features?.print_kits) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.growth?.features?.print_kits) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.pro?.features?.print_kits) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.enterprise?.features?.print_kits) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">Merch Store</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.starter?.features?.merch_store) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.growth?.features?.merch_store) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.pro?.features?.merch_store) }}</td>
                            <td class="py-4 text-center text-gray-400">{{ yn(planBySlug.enterprise?.features?.merch_store) }}</td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">API Access</td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.starter?.features?.api_access" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.growth?.features?.api_access" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.pro?.features?.api_access" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.enterprise?.features?.api_access" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-4 text-gray-300">White Label</td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.starter?.features?.white_label" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.growth?.features?.white_label" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.pro?.features?.white_label" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                            <td class="py-4 text-center">
                                <span v-if="planBySlug.enterprise?.features?.white_label" class="text-green-400">✓</span>
                                <span v-else class="text-gray-500">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- FAQs -->
    <section class="py-20">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white text-center mb-12">Frequently Asked Questions</h2>
            <div class="space-y-6">
                <div v-for="faq in faqs" :key="faq.question" class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-2">{{ faq.question }}</h3>
                    <p class="text-gray-400">{{ faq.answer }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass-card p-12 text-center relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-primary-500/20 to-accent-500/20"></div>
                <div class="relative">
                    <h2 class="text-3xl font-bold text-white mb-4">Ready to Get Started?</h2>
                    <p class="text-xl text-gray-400 mb-8">
                        Start your 14-day free trial today. No credit card required.
                    </p>
                    <Link href="/register" class="btn-accent text-lg px-10 py-4 inline-block">
                        Start Free Trial
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
