<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    business: Object,
    plans: Array,
    subscriptionStatus: Object,
    currentPlan: Object,
    stripeConfigured: Boolean,
});

const billingPeriod = ref('monthly');
const processingPlanId = ref(null);

const sortedPlans = computed(() => {
    return [...(props.plans || [])].sort((a, b) => a.sort_order - b.sort_order);
});

const getPlanPrice = (plan) => {
    if (billingPeriod.value === 'yearly') {
        return plan.yearly_price || plan.monthly_price * 10;
    }
    return plan.monthly_price;
};

const formatCurrency = (amount) => {
    const n = Number(amount || 0);
    try {
        return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(n);
    } catch (e) {
        return `$${n.toFixed(2)} CAD`;
    }
};

// Get merch description based on categories, filtering by plan restrictions
const getMerchDescription = (categories, planSlug) => {
    if (!categories || categories.length === 0) return null;
    if (categories.includes('all')) return 'All Merch Products';
    
    // Defensive display filtering (real gating is enforced server-side too).
    // Starter: t-shirts only
    // Growth: t-shirts + hoodies
    let filteredCategories = [...categories];
    if (planSlug === 'starter') {
        filteredCategories = filteredCategories.filter(c => c === 't-shirt');
    } else if (planSlug === 'growth') {
        filteredCategories = filteredCategories.filter(c => c === 't-shirt' || c === 'hoodie');
    }
    
    if (filteredCategories.length === 0) return null;
    
    const names = {
        't-shirt': 'T-Shirts',
        'mug': 'Mugs',
        'sticker': 'Stickers',
        'hoodie': 'Hoodies',
        'poster': 'Posters',
        'bag': 'Bags',
    };
    
    return filteredCategories.map(c => names[c] || c).join(', ');
};

const getPlanFeatures = (plan) => {
    if (!plan.features) return [];
    
    const features = [];
    const f = plan.features;
    
    // Core limits
    if (f.qr_codes !== undefined) {
        features.push({ 
            text: f.qr_codes === -1 ? 'Unlimited QR Codes' : `${f.qr_codes} QR Codes`,
            included: true 
        });
    }
    if (f.promotions !== undefined) {
        features.push({ 
            text: f.promotions === -1 ? 'Unlimited Promotions' : `${f.promotions} Promotions`,
            included: true 
        });
    }
    if (f.scans_per_month !== undefined) {
        features.push({ 
            text: f.scans_per_month === -1 ? 'Unlimited Scans' : `${f.scans_per_month.toLocaleString()} Scans/month`,
            included: true 
        });
    }
    if (f.employees !== undefined) {
        features.push({ 
            text: f.employees === -1 ? 'Unlimited Employees' : `${f.employees} Employee${f.employees > 1 ? 's' : ''}`,
            included: true 
        });
    }
    
    // Analytics
    if (f.analytics_days) {
        features.push({ text: `${f.analytics_days} Days Analytics`, included: true });
    }
    if (f.advanced_analytics) {
        features.push({ text: 'Advanced Analytics', included: true });
    }
    if (f.ai_insights) {
        if (plan.slug === 'growth') {
            features.push({ text: 'Basic AI Insights', included: true });
        } else {
            features.push({ text: 'Advanced AI Insights', included: true });
        }
    }

    // CRM (included in all tiers)
    if (f.crm) {
        features.push({ text: 'Built-in CRM + Email Campaigns', included: true });
    }
    if (f.crm_automations) {
        features.push({ text: 'Automations: winback + reminders', included: true });
    }
    
    // Merch Store
    if (f.merch_store) {
        const merchDesc = getMerchDescription(f.merch_categories, plan.slug);
        if (merchDesc) {
            features.push({ text: `Merch: ${merchDesc}`, included: true });
        }
        if (f.merch_orders_per_month) {
            const orderLimit = f.merch_orders_per_month === -1 ? 'Unlimited' : f.merch_orders_per_month;
            features.push({ text: `${orderLimit} Merch Orders/mo`, included: true });
        }
    } else {
        features.push({ text: 'Merch Store', included: false });
    }
    if (f.merch_referral_qr) {
        features.push({ text: 'Merch Referral QR', included: true });
    } else {
        features.push({ text: 'Merch Referral QR', included: false });
    }
    
    // QRcade Games
    if (f.qrcade) {
        if (f.premium_games) {
            features.push({ text: 'All QRcade Games', included: true });
        } else if (f.pro_games) {
            features.push({ text: 'Basic + Pro Games', included: true });
        } else if (f.basic_games) {
            features.push({ text: 'Basic Games Only', included: true });
        }
    }
    if (f.leaderboards) {
        features.push({ text: 'Leaderboards', included: true });
    }
    if (f.tournaments) {
        features.push({ text: 'Tournaments', included: true });
    }
    
    // Additional Features
    if (f.print_studio) {
        features.push({ text: 'Print Studio', included: true });
    }
    // Only show Sticker Kits if the plan has access (Growth, Pro, Enterprise)
    // Don't show it at all for Starter
    if (f.print_kits) {
        features.push({ text: 'Sticker Kits', included: true });
    }
    if (f.cross_promotions) {
        features.push({ text: 'Partner Deal Chain', included: true });
    }
    if (f.stackable_pools) {
        features.push({ text: 'Stackable QR Codes', included: true });
    }
    if (f.featured_promo) {
        features.push({ text: 'Featured promo placement (customer home + business page)', included: true });
    }
    // NOTE: API Access is not yet exposed as a self-serve feature in-app; keep it out of the feature list until shipped.
    if (f.white_label) {
        features.push({ text: 'White Label Branding', included: true });
    }
    if (f.remove_branding) {
        features.push({ text: 'Remove Platform Branding (customer pages)', included: true });
    }
    if (f.sla_guarantee) {
        features.push({ text: 'SLA Guarantee', included: true });
    }
    if (f.priority_support) {
        features.push({ text: 'Priority Support', included: true });
    }
    
    return features;
};

// Option A: Starter should feel positive (show what's included),
// and include a small "Unlock with Growth+" upsell instead of lots of ❌ rows.
const getDisplayedFeatures = (plan) => {
    const all = getPlanFeatures(plan);
    const slug = (plan?.slug || '').toLowerCase();
    // Keep the comparison view positive for all tiers: show what's included, then a small upsell block.
    if (['starter', 'growth', 'pro'].includes(slug)) {
        return all.filter(f => f.included);
    }
    return all;
};

const getUnlockBlock = (plan) => {
    const slug = (plan?.slug || '').toLowerCase();
    const f = plan?.features || {};

    const unlock = [];

    if (slug === 'starter') {
        // Unlock with Growth+
        if (!f.featured_promo) unlock.push({ text: 'Featured promo placement (customer home + business page)' });
        if (!f.ai_insights) unlock.push({ text: 'AI Insights (Growth+)' });
        // Merch upsell requested: hoodies appear starting in Growth
        unlock.push({ text: 'Merch: Hoodies (Growth+)' });
        if (!f.leaderboards) unlock.push({ text: 'Leaderboards' });
        if (!f.stackable_pools) unlock.push({ text: 'Stackable QR Codes' });
        if (!f.cross_promotions) unlock.push({ text: 'Partner Deal Chain' });
        if (!f.advanced_analytics) unlock.push({ text: 'Advanced Analytics' });

        return {
            label: 'Unlock with Growth+',
            items: unlock,
        };
    }

    if (slug === 'growth') {
        // Unlock with Pro
        if (!f.priority_support) unlock.push({ text: 'Priority Support' });
        if (!f.remove_branding) unlock.push({ text: 'Remove Branding' });
        if (!f.tournaments) unlock.push({ text: 'Tournaments' });
        if (!f.premium_games) unlock.push({ text: 'Premium QRcade Games' });
        unlock.push({ text: 'Merch: Full catalog (Pro+)' });

        return {
            label: 'Unlock with Pro',
            items: unlock,
        };
    }

    if (slug === 'pro') {
        // Unlock with Enterprise
        if (!f.white_label) unlock.push({ text: 'White Label Branding' });
        if (!f.sla_guarantee) unlock.push({ text: 'SLA Guarantee' });

        return {
            label: 'Unlock with Enterprise',
            items: unlock,
        };
    }

    return { label: null, items: [] };
};

const isCurrentPlan = (plan) => {
    return props.currentPlan?.id === plan.id;
};

const isTrial = computed(() => {
    return !!props.subscriptionStatus?.is_trial;
});

const canSubscribeToPlan = (plan) => {
    if (!props.stripeConfigured) return false;
    // While on trial, allow starting the CURRENT plan immediately (exits trial by creating a subscription).
    if (isTrial.value) return true;
    // Once subscribed, prevent re-subscribing to the same plan from this UI.
    if (isCurrentPlan(plan)) return false;
    return true;
};

const subscribeToPlan = async (plan) => {
    if (processingPlanId.value) return;
    if (!canSubscribeToPlan(plan)) return;
    
    processingPlanId.value = plan.id;
    
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('/business/billing/subscribe', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify({
                plan_id: plan.id,
                billing_period: billingPeriod.value,
            }),
        });
        
        // If the CSRF token/session expired, Laravel will respond 419 (often with an HTML page).
        // Reload to get a fresh token instead of showing a generic error.
        if (response.status === 419) {
            window.location.reload();
            return;
        }

        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            // Non-JSON response (e.g. HTML error page). Fall back to a generic message.
            console.error('Subscription error (non-JSON response):', e);
            alert('Failed to process subscription. Please refresh and try again.');
            return;
        }
        
        if (data.checkout_url) {
            window.location.href = data.checkout_url;
        } else if (data.error) {
            alert(data.error);
        } else if (!response.ok) {
            alert('Failed to process subscription. Please try again.');
        }
    } catch (error) {
        console.error('Subscription error:', error);
        alert('Failed to process subscription. Please try again.');
    } finally {
        processingPlanId.value = null;
    }
};

const openBillingPortal = () => {
    // Use a full page navigation so Stripe's external redirect works reliably
    window.location.href = '/business/billing/portal';
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};
</script>

<template>
    <Head title="Subscription & Billing" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Subscription & Billing</h1>
            <p class="text-gray-400 mt-1">Manage your subscription plan and billing details</p>
        </div>

        <!-- Stripe Not Configured Warning -->
        <div v-if="!stripeConfigured" class="glass-card p-4 mb-6 border border-yellow-500/30 bg-yellow-500/10">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="text-yellow-200 text-sm">Payment system is being configured. Subscription features will be available soon.</p>
            </div>
        </div>

        <!-- Current Plan Status -->
        <div class="glass-card p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white mb-2">Current Plan</h2>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="px-4 py-2 bg-primary-500/20 text-primary-400 rounded-lg font-medium text-lg capitalize">
                            {{ currentPlan?.name || business.subscription_tier || 'Starter' }}
                        </span>
                        <span v-if="subscriptionStatus?.is_trial" class="px-3 py-1 bg-yellow-500/20 text-yellow-400 rounded-full text-sm">
                            Trial ends {{ formatDate(business.trial_ends_at) }}
                        </span>
                        <span v-else-if="subscriptionStatus?.status === 'active'" class="px-3 py-1 bg-green-500/20 text-green-400 rounded-full text-sm">
                            Active
                        </span>
                        <span v-else-if="subscriptionStatus?.status === 'canceled'" class="px-3 py-1 bg-red-500/20 text-red-400 rounded-full text-sm">
                            Canceled
                        </span>
                        <span v-else class="px-3 py-1 bg-gray-500/20 text-gray-400 rounded-full text-sm">
                            No Active Subscription
                        </span>
                    </div>
                    <p v-if="subscriptionStatus?.next_billing_date" class="text-gray-400 text-sm mt-2">
                        Next billing date: {{ formatDate(subscriptionStatus.next_billing_date) }}
                    </p>
                </div>
                
                <div class="flex gap-3">
                    <button 
                        v-if="stripeConfigured"
                        @click="openBillingPortal"
                        class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition-colors flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        {{ isTrial ? 'Payment Method & Invoices' : 'Manage Billing' }}
                    </button>
                </div>
            </div>
            <p v-if="isTrial" class="mt-3 text-gray-400 text-sm">
                You’re currently on a free trial. Adding a card in Stripe saves your payment method, but it does <span class="text-white font-medium">not</span> start billing.
                To exit the trial and start your plan now, choose a plan below.
            </p>
        </div>

        <!-- Billing Period Toggle -->
        <div class="flex justify-center mb-8">
            <div class="glass-card inline-flex p-1 gap-1">
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

        <!-- Plans Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div 
                v-for="plan in sortedPlans" 
                :key="plan.id"
                :class="[
                    'glass-card p-6 relative flex flex-col',
                    plan.is_featured ? 'ring-2 ring-primary-500' : '',
                    isCurrentPlan(plan) ? 'ring-2 ring-green-500' : ''
                ]"
            >
                <!-- Featured Badge -->
                <div v-if="plan.is_featured" class="absolute -top-3 left-1/2 -translate-x-1/2">
                    <span class="px-3 py-1 bg-primary-500 text-white text-sm font-semibold rounded-full">
                        Most Popular
                    </span>
                </div>

                <!-- Current Plan Badge -->
                <div v-if="isCurrentPlan(plan)" class="absolute -top-3 right-4">
                    <span class="px-3 py-1 bg-green-500 text-white text-sm font-semibold rounded-full">
                        Current
                    </span>
                </div>

                <h3 class="text-xl font-bold text-white mb-2">{{ plan.name }}</h3>
                <p class="text-gray-400 text-sm mb-4 min-h-[40px]">{{ plan.description }}</p>
                
                <div class="mb-6">
                    <span v-if="getPlanPrice(plan) > 0" class="text-4xl font-bold text-white">
                        {{ formatCurrency(getPlanPrice(plan)) }}
                    </span>
                    <span v-else class="text-4xl font-bold text-white">Free</span>
                    <span v-if="getPlanPrice(plan) > 0" class="text-gray-400">
                        /{{ billingPeriod === 'yearly' ? 'year' : 'month' }}
                    </span>
                </div>

                <button 
                    @click="subscribeToPlan(plan)"
                    :disabled="!canSubscribeToPlan(plan) || processingPlanId === plan.id"
                    :class="[
                        'w-full py-3 rounded-xl font-semibold text-center transition-all mb-6',
                        (isCurrentPlan(plan) && !isTrial)
                            ? 'bg-green-500/20 text-green-400 cursor-default'
                            : plan.is_featured 
                                ? 'bg-primary-500 text-white hover:bg-primary-600' 
                                : 'bg-white/10 text-white hover:bg-white/20',
                        processingPlanId === plan.id ? 'opacity-50 cursor-wait' : ''
                    ]"
                >
                    <span v-if="processingPlanId === plan.id">Processing...</span>
                    <span v-else-if="isCurrentPlan(plan) && isTrial">Start {{ plan.name }} Now</span>
                    <span v-else-if="isCurrentPlan(plan)">Current Plan</span>
                    <span v-else-if="getPlanPrice(plan) === 0">Get Started Free</span>
                    <span v-else>{{ isTrial ? `Switch to ${plan.name} Now` : (currentPlan ? 'Switch Plan' : 'Start Free Trial') }}</span>
                </button>

                <p v-if="isTrial && getPlanPrice(plan) > 0" class="text-gray-500 text-xs -mt-4 mb-6">
                    You will be charged <span class="text-gray-300 font-medium">{{ formatCurrency(getPlanPrice(plan)) }}/{{ billingPeriod === 'yearly' ? 'year' : 'month' }}</span> once the trial ends — or immediately if you click “Start Now”.
                </p>

                <div class="flex-1 space-y-3">
                    <div 
                        v-for="feature in getDisplayedFeatures(plan)" 
                        :key="feature.text" 
                        class="flex items-start"
                    >
                        <svg 
                            v-if="feature.included"
                            class="w-5 h-5 text-green-400 mr-2 flex-shrink-0" 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg 
                            v-else
                            class="w-5 h-5 text-gray-500 mr-2 flex-shrink-0" 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span :class="feature.included ? 'text-gray-300' : 'text-gray-500'">
                            {{ feature.text }}
                        </span>
                    </div>

                    <!-- Starter: small upsell block -->
                    <div
                        v-if="getUnlockBlock(plan).label && getUnlockBlock(plan).items.length"
                        class="mt-4 pt-4 border-t border-white/10"
                    >
                        <div class="text-xs uppercase tracking-wide text-gray-400 mb-3">{{ getUnlockBlock(plan).label }}</div>
                        <div class="space-y-3">
                            <div
                                v-for="item in getUnlockBlock(plan).items"
                                :key="item.text"
                                class="flex items-start"
                            >
                                <svg class="w-5 h-5 text-amber-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11V7a4 4 0 10-8 0v4m0 0h8m-8 0v10a2 2 0 002 2h4a2 2 0 002-2V11m-8 0h8" />
                                </svg>
                                <span class="text-gray-400">{{ item.text }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enterprise Contact -->
        <div class="mt-12 text-center">
            <div class="glass-card p-8 max-w-2xl mx-auto">
                <h3 class="text-2xl font-bold text-white mb-4">Need a Custom Solution?</h3>
                <p class="text-gray-400 mb-6">
                    For enterprise-level features, white-label solutions, or high-volume needs, 
                    our team is ready to create a tailored plan for your business.
                </p>
                <a 
                    href="mailto:support@revenueqr.com" 
                    class="inline-flex items-center px-6 py-3 bg-accent-500 text-white rounded-xl font-semibold hover:bg-accent-600 transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Contact Sales
                </a>
            </div>
        </div>

        <!-- FAQs -->
        <div class="mt-12">
            <h2 class="text-2xl font-bold text-white text-center mb-8">Frequently Asked Questions</h2>
            <div class="max-w-3xl mx-auto space-y-4">
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-2">Can I switch plans later?</h3>
                    <p class="text-gray-400">Yes! You can upgrade or downgrade your plan at any time. Changes take effect on your next billing cycle, and we'll prorate any differences.</p>
                </div>
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-2">Is there a free trial?</h3>
                    <p class="text-gray-400">Yes, all paid plans include a 14-day free trial. No credit card required to start your trial.</p>
                </div>
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-2">What happens if I exceed my limits?</h3>
                    <p class="text-gray-400">We'll notify you when you're approaching your limits. You can upgrade your plan anytime, or we can discuss overage options for your needs.</p>
                </div>
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-white mb-2">Can I cancel anytime?</h3>
                    <p class="text-gray-400">Absolutely. Cancel anytime with no questions asked. You'll retain access until the end of your current billing period.</p>
                </div>
            </div>
        </div>
    </div>
</template>
