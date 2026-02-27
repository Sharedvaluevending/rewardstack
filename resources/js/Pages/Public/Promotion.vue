<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PunchCardProgress from '@/Components/PunchCardProgress.vue';
import ScrollDownIndicator from '@/Components/ScrollDownIndicator.vue';
import { collectAndSendScanGeo } from '@/utils/scanGeo';

const page = usePage();

const props = defineProps({
    qrCode: Object,
    promotion: Object,
    business: Object,
    canRedeem: Boolean,
    redeemMessage: String,
    staffAuth: Object, // null if customer, object if authorized staff
    punchCardProgress: Object, // punch card progress data
    userAvatar: Object, // logged in user's avatar info
    isSaved: Boolean, // whether user has saved this QR code
    userLevel: Number, // current user level (null if not logged in)
    isAvailable: Boolean, // whether the promotion is still active and not deleted
    isSubscribed: {
        type: Boolean,
        default: false,
    },
    showPlatformBranding: {
        type: Boolean,
        default: true,
    },
});

onMounted(() => {
    collectAndSendScanGeo(props.qrCode?.code);
});

// Staff redemption state
const showRedeemForm = ref(false);
const originalAmount = ref('');
const quantity = ref(1);
const customerCode = ref('');
const processing = ref(false);
const result = ref(null);
const error = ref(null);

// Calculator state
const calculatedDiscount = ref(0);
const calculatedFinal = ref(0);
const isFinalPunch = ref(false);

const buyPrices = ref([]);
const getPrices = ref([]);

onMounted(() => {
    // Check if this is the final punch for a punch card
    if (props.promotion?.discount_type === 'punch_card' && props.promotion?.punches_required) {
        const current = props.punchCardProgress?.current_punches || 0;
        isFinalPunch.value = (current >= props.promotion.punches_required);
    }

    // 1. Handle pre-fills from calculatorInfo (Percentage, Happy Hour, etc.)
    if (props.promotion?.calculator_prefills?.purchase_amount) {
        originalAmount.value = parseFloat(props.promotion.calculator_prefills.purchase_amount).toFixed(2);
    } else if (props.promotion?.original_price) {
        originalAmount.value = parseFloat(props.promotion.original_price).toFixed(2);
    }

    // Special pre-fill for final punch card reward
    if (isFinalPunch.value && props.promotion.reward_value) {
        originalAmount.value = parseFloat(props.promotion.reward_value).toFixed(2);
    }

    // 2. Handle Buy X Get Y and X for $Y item prices
    if (['buy_x_get_y', 'buy_x_for_y'].includes(props.promotion.discount_type)) {
        const rules = props.promotion.rules || {};
        const buyQty = props.promotion.buy_quantity || 1;
        const getQty = props.promotion.get_quantity || 1;
        
        // Ensure rules contain the arrays we expect
        const ruleBuyPrices = Array.isArray(rules.buy_item_prices) ? rules.buy_item_prices : [];
        const ruleGetPrices = Array.isArray(rules.get_item_prices) ? rules.get_item_prices : [];

        // Fill the individual boxes
        buyPrices.value = Array.from({ length: buyQty }, (_, i) => {
            const val = ruleBuyPrices[i];
            return (val !== null && val !== undefined && val !== '') ? parseFloat(val).toFixed(2) : '';
        });
        
        if (props.promotion.discount_type === 'buy_x_get_y') {
            getPrices.value = Array.from({ length: getQty }, (_, i) => {
                const val = ruleGetPrices[i];
                return (val !== null && val !== undefined && val !== '') ? parseFloat(val).toFixed(2) : '';
            });
        }

        // 3. Auto-calculate total original value if prices were pre-defined
        const hasSomePrices = buyPrices.value.some(p => p !== '');
        if (hasSomePrices) {
            const sum = buyPrices.value.reduce((sum, p) => sum + (parseFloat(p) || 0), 0);
            if (sum > 0) {
                originalAmount.value = sum.toFixed(2);
            }
        }
    }
    
    // Initial calculation
    calculateDiscount();

    // 4. Punch card specific
    if (props.promotion?.discount_type === 'punch_card' && props.promotion?.reward_value) {
        calculatedDiscount.value = parseFloat(props.promotion.reward_value) || 0;
    }
});

// Update originalAmount when individual buy prices change
watch(buyPrices, (newPrices) => {
    if (['buy_x_get_y', 'buy_x_for_y'].includes(props.promotion?.discount_type) && newPrices.some(p => p !== '')) {
        const sum = newPrices.reduce((sum, p) => sum + (parseFloat(p) || 0), 0);
        originalAmount.value = sum > 0 ? sum.toFixed(2) : '';
    }
}, { deep: true });

// Calculate discount in real-time
const calculateDiscount = () => {
    if (!originalAmount.value || parseFloat(originalAmount.value) <= 0) {
        calculatedDiscount.value = 0;
        calculatedFinal.value = 0;
        return;
    }

    const amount = parseFloat(originalAmount.value);
    const qty = parseInt(quantity.value) || 1;
    const promo = props.promotion;

    let discount = 0;
    let totalValue = amount;

    switch (promo.discount_type) {
        case 'percentage':
        case 'happy_hour':
        case 'first_time':
        case 'loyalty_milestone':
            if (promo.discount_value) {
                discount = amount * (promo.discount_value / 100);
                // Apply maximum discount if set
                if (promo.maximum_discount && discount > promo.maximum_discount) {
                    discount = promo.maximum_discount;
                }
            }
            break;

        case 'fixed_amount':
            discount = Math.min(promo.discount_value || 0, amount);
            break;

        case 'bogo':
            // Simple 1:1 math: Savings = Purchase Amount
            discount = amount;
            totalValue = amount + discount;
            break;

        case 'buy_x_get_y':
            if (buyPrices.value.length > 0 || getPrices.value.length > 0) {
                const paidAmount = buyPrices.value.reduce((sum, p) => sum + (parseFloat(p) || 0), 0);
                const savedAmount = getPrices.value.reduce((sum, p) => sum + (parseFloat(p) || 0), 0);
                
                discount = savedAmount;
                totalValue = paidAmount + savedAmount;
            } else {
                const setSize = (promo.buy_quantity || 1) + (promo.get_quantity || 0);
                if (qty >= setSize) {
                    const sets = Math.floor(qty / setSize);
                    const freeQty = sets * (promo.get_quantity || 0);
                    const paidQty = qty - freeQty;
                    discount = freeQty * (amount / (paidQty || 1));
                    totalValue = amount + discount;
                }
            }
            break;

        case 'buy_x_for_y':
            if (buyPrices.value.length > 0) {
                const normalTotal = buyPrices.value.reduce((sum, p) => sum + (parseFloat(p) || 0), 0);
                const promoPrice = parseFloat(promo.for_price) || 0;
                
                discount = Math.max(0, normalTotal - promoPrice);
                totalValue = normalTotal;
            } else if (qty >= promo.buy_quantity) {
                discount = amount - (promo.for_price || 0);
            }
            break;

        case 'punch_card':
            if (isFinalPunch.value) {
                // For the free item, savings = what's in the box, customer pays $0
                discount = amount;
                totalValue = amount; // The total value is the value of the free item
            } else {
                // For a regular punch, savings = $0, customer pays the full amount
                discount = 0;
                totalValue = amount;
            }
            break;

        case 'tiered':
            if (promo.tiers && Array.isArray(promo.tiers)) {
                let applicableTier = null;
                promo.tiers.forEach(tier => {
                    if (tier.min_spend && amount >= tier.min_spend) {
                        if (!applicableTier || tier.min_spend > applicableTier.min_spend) {
                            applicableTier = tier;
                        }
                    }
                });
                if (applicableTier && applicableTier.discount) {
                    discount = amount * (applicableTier.discount / 100);
                }
            }
            break;
    }

    calculatedDiscount.value = Math.round(discount * 100) / 100;
    calculatedFinal.value = Math.round((totalValue - discount) * 100) / 100;
};

// Watch for changes
watch([originalAmount, quantity], () => {
    calculateDiscount();
    // Clear error when user starts typing
    if (error.value && originalAmount.value) {
        error.value = null;
    }
});

// Computed for original_amount field error
const originalAmountError = computed(() => {
    return error.value && (error.value.includes('Purchase amount') || error.value.includes('required')) ? error.value : null;
});

// Format currency
const formatCurrency = (amount) => {
    if (!amount) return null;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

const staffPortalHref = () => {
    if (!props.staffAuth) return null;
    return props.staffAuth.type === 'owner' ? '/business/dashboard' : '/employee/redeem';
};

const staffPortalLabel = () => {
    if (!props.staffAuth) return 'Portal';
    return props.staffAuth.type === 'owner' ? 'Business Portal' : 'Employee Portal';
};

const subscribeToBusiness = () => {
    if (!props.business?.id) return;
    router.post(`/portal/subscriptions/${props.business.id}/subscribe`, { source: 'promotion_page' }, { preserveScroll: true });
};

const unsubscribeFromBusiness = () => {
    if (!props.business?.id) return;
    router.post(`/portal/subscriptions/${props.business.id}/unsubscribe`, { source: 'promotion_page' }, { preserveScroll: true });
};

// Handle staff redemption
const handleRedeem = async () => {
    processing.value = true;
    error.value = null;

    router.post(`/employee/redeem/${props.qrCode.code}`, {
        original_amount: parseFloat(originalAmount.value) || 0,
        quantity: parseInt(quantity.value) || 1,
        customer_identifier: customerCode.value,
        item_prices: ['buy_x_get_y', 'buy_x_for_y'].includes(props.promotion.discount_type) ? {
            buy: buyPrices.value,
            get: getPrices.value
        } : null
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            // Redemption successful
            // The backend returns a redirect or JSON?
            // Since this is Inertia, success means the action completed.
            // We need to check the flash message or returned props.
            if (page.props.flash?.success || page.props.flash?.redemption || page.props.redemption) {
                result.value = page.props.flash?.redemption || page.props.redemption;
                showRedeemForm.value = false;
            }
        },
        onError: (errors) => {
            error.value = errors.original_amount || Object.values(errors)[0] || 'Something went wrong. Please try again.';
        },
        onFinish: () => {
            processing.value = false;
        }
    });
};

// Reset for another redemption
const handleAnother = () => {
    result.value = null;
    originalAmount.value = '';
    customerCode.value = '';
    showRedeemForm.value = true;
};

// Close success and go back to view
const handleDone = () => {
    result.value = null;
    showRedeemForm.value = false;
};

// Save/Unsave QR code
const saving = ref(false);
const isSavedLocal = ref(props.isSaved || false);

const toggleSave = async () => {
    if (!props.userAvatar) {
        // Redirect to login if not logged in
        window.location.href = '/login';
        return;
    }

    saving.value = true;
    const url = isSavedLocal.value 
        ? `/portal/qr-codes/${props.qrCode.id}/unsave`
        : `/portal/qr-codes/${props.qrCode.id}/save`;
    
    const method = isSavedLocal.value ? 'delete' : 'post';
    
    router[method](url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            isSavedLocal.value = !isSavedLocal.value;
            
            // Reload the scans page to sync the saved state
            const referrer = document.referrer;
            if (referrer && referrer.includes('/portal/scans')) {
                router.visit('/portal/scans', {
                    preserveState: false,
                    preserveScroll: false,
                    only: ['savedPromotions', 'scans'],
                });
            } else {
                router.reload({ only: ['isSaved'] });
            }
        },
        onError: (errors) => {
            console.error('Error saving/unsaving QR code:', errors);
        },
        onFinish: () => {
            saving.value = false;
        }
    });
};
</script>

<template>
    <Head :title="promotion?.name || 'Promotion'" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 flex items-center justify-center p-4">
        <!-- Fixed scroll indicator at bottom of viewport (when promotion is available) -->
        <div v-if="isAvailable" class="fixed bottom-8 left-0 right-0 z-30 pointer-events-none flex justify-center">
            <ScrollDownIndicator />
        </div>
        <div class="w-full max-w-md">
            
            <!-- Offer No Longer Available -->
            <div v-if="!isAvailable" class="glass-card overflow-hidden">
                <div class="p-8 text-center">
                    <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-white/20">
                        <span class="text-4xl">🚫</span>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-3">Offer No Longer Available</h2>
                    <p class="text-gray-400 mb-8 leading-relaxed">
                        This promotion has been ended or modified by the business. 
                        <span class="block mt-2 font-medium text-emerald-400 italic">This will now be removed from your portal.</span>
                    </p>
                    <Link 
                        href="/portal/scans" 
                        class="w-full py-4 rounded-xl font-bold text-lg transition-all bg-gradient-to-r from-primary-500 to-accent-500 text-white hover:from-primary-600 hover:to-accent-600 shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to My Portal
                    </Link>
                </div>
            </div>

            <!-- Staff Mode Indicator (only shows for authorized staff) -->
            <div v-else-if="staffAuth" class="mb-4 text-center">
                <div class="inline-flex items-center gap-2 bg-emerald-500/20 text-emerald-400 px-4 py-2 rounded-full text-sm font-medium">
                    <span>{{ staffAuth.type === 'owner' ? '👔' : '👤' }}</span>
                    <span>{{ staffAuth.type === 'owner' ? 'Owner' : 'Staff' }} Mode: {{ staffAuth.name }}</span>
                </div>
                <div class="mt-3 flex items-center justify-center">
                    <Link
                        :href="staffPortalHref()"
                        class="px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 transition-colors text-sm font-semibold"
                    >
                        {{ staffPortalLabel() }}
                    </Link>
                </div>
            </div>

            <!-- Success Result (Staff Only) -->
            <div v-if="result" class="glass-card overflow-hidden border-2 border-emerald-500/50">
                <div class="p-6 text-center">
                    <div class="w-20 h-20 rounded-full bg-emerald-500/20 mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-emerald-400 mb-2">Redeemed! ✓</h2>
                    <p class="text-white text-lg mb-4">{{ result.promotion_name }}</p>
                    
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="p-4 rounded-xl bg-white/5">
                            <p class="text-gray-400 text-xs mb-1">
                                {{ promotion.discount_type === 'punch_card' ? 'Total Value' : 'Original Cost' }}
                            </p>
                            <p class="text-gray-300 text-xl font-bold">{{ formatCurrency(result.original_amount || 0) }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white/5">
                            <p class="text-gray-400 text-xs mb-1">Customer Saved</p>
                            <p class="text-emerald-400 text-2xl font-bold">-{{ formatCurrency(result.discount_amount || 0) }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white/5">
                            <p class="text-gray-400 text-xs mb-1">
                                {{ promotion.discount_type === 'punch_card' ? 'Sale Amount' : 'Cost to Customer' }}
                            </p>
                            <p class="text-white text-2xl font-bold">{{ formatCurrency(result.final_amount || 0) }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <button @click="handleDone"
                            class="w-full py-3 bg-emerald-500 text-white font-semibold rounded-xl hover:bg-emerald-600 transition-all">
                            Done
                        </button>
                        <button @click="handleAnother"
                            class="w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all">
                            Redeem Another
                        </button>
                    </div>
                </div>
            </div>

            <!-- Redemption Form (Staff Only) -->
            <div v-else-if="showRedeemForm && staffAuth" class="glass-card overflow-hidden">
                <div class="p-4 bg-emerald-500/10 border-b border-white/10">
                    <h3 class="text-emerald-400 font-semibold text-center">Staff Redemption</h3>
                </div>
                
                <div class="p-6">
                    <!-- Error -->
                    <div v-if="error" class="mb-4 p-4 rounded-xl bg-red-500/20 border border-red-500/30">
                        <p class="text-red-400 text-center text-sm">{{ error }}</p>
                    </div>

                    <!-- Promotion Summary -->
                    <div class="mb-6 p-4 rounded-xl bg-white/5 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-2xl">
                            🎁
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-white font-semibold">{{ promotion.name }}</p>
                                <!-- Level Requirement Badge -->
                                <span v-if="qrCode?.required_level" 
                                    :class="[
                                        'px-2 py-1 rounded-full text-xs font-medium flex items-center gap-1',
                                        userLevel !== null && userLevel >= qrCode.required_level
                                            ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30'
                                            : 'bg-gray-500/20 text-gray-400 border border-gray-500/30'
                                    ]">
                                    <span v-if="userLevel !== null && userLevel < qrCode.required_level">🔒</span>
                                    <span>Level {{ qrCode.required_level }}+</span>
                                </span>
                            </div>
                            <p class="text-xl font-bold text-emerald-400">{{ promotion.display_value }}</p>
                            <p v-if="promotion.description" class="text-gray-400 text-sm mt-2">{{ promotion.description }}</p>
                            <!-- Final Price Display for Percentage and Happy Hour Discounts -->
                            <div v-if="promotion.final_price && ['percentage', 'happy_hour'].includes(promotion.discount_type)" class="mt-3 p-3 rounded-lg bg-emerald-500/20 border border-emerald-500/30">
                                <p class="text-xs text-gray-400 mb-1">Charge Customer</p>
                                <p class="text-2xl font-bold text-emerald-400">${{ parseFloat(promotion.final_price).toFixed(2) }}</p>
                                <p v-if="promotion.original_price" class="text-xs text-gray-500 mt-1">
                                    Original: ${{ parseFloat(promotion.original_price).toFixed(2) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Calculator (if needed) -->
                    <!-- Always show calculator for percentage discounts (even if original_price is set, allows override) -->
                    <!-- Also show for punch cards and other types that need calculator -->
                    <div v-if="promotion.needs_calculator || promotion.discount_type === 'punch_card' || promotion.discount_type === 'percentage'" class="mb-4 space-y-4">
                        <!-- Purchase Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                <template v-if="promotion.discount_type === 'punch_card'">
                                    {{ isFinalPunch ? 'Free Item Value' : 'Money Collected (Sale Amount)' }}
                                </template>
                                <template v-else>
                                    {{ promotion.discount_type === 'buy_x_get_y' ? 'Total Paid by Customer' : (promotion.discount_type === 'buy_x_for_y' ? 'Normal Total Value' : 'Purchase Amount') }}
                                </template>
                                <span v-if="promotion.needs_calculator || (promotion.discount_type === 'punch_card' && isFinalPunch)" class="text-red-400">*</span>
                                <span v-if="promotion.minimum_purchase" class="text-xs text-gray-500">
                                    (Min: ${{ parseFloat(promotion.minimum_purchase).toFixed(2) }})
                                </span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">$</span>
                                <input
                                    v-model="originalAmount"
                                    @input="calculateDiscount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :class="[
                                        'w-full bg-white/5 border rounded-xl px-4 py-3 pl-10 text-white text-xl focus:outline-none transition-colors',
                                        originalAmountError 
                                            ? 'border-red-500/50 focus:border-red-500/70 bg-red-500/5' 
                                            : 'border-white/10 focus:border-emerald-500/50'
                                    ]"
                                    :placeholder="isFinalPunch && promotion.reward_value ? parseFloat(promotion.reward_value).toFixed(2) : (promotion.original_price ? parseFloat(promotion.original_price).toFixed(2) : (promotion.calculator_prefills?.purchase_amount ? parseFloat(promotion.calculator_prefills.purchase_amount).toFixed(2) : '0.00'))"
                                    :disabled="processing || (['buy_x_get_y', 'buy_x_for_y'].includes(promotion.discount_type) && buyPrices.length > 0)"
                                    :required="promotion.needs_calculator || (promotion.discount_type === 'punch_card' && isFinalPunch)"
                                />
                            </div>
                            <p v-if="originalAmountError" class="text-xs text-red-400 mt-1 font-medium">
                                ⚠️ {{ originalAmountError }}
                            </p>
                            <p v-else-if="promotion.discount_type === 'punch_card' && isFinalPunch" class="text-xs text-emerald-400 mt-1">
                                Final punch! Item is free for customer.
                            </p>
                            <p v-else-if="['buy_x_get_y', 'buy_x_for_y'].includes(promotion.discount_type) && buyPrices.length > 0" class="text-[10px] text-gray-500 mt-1">
                                Sum of individual items below.
                            </p>
                            <p v-else-if="promotion.needs_calculator" class="text-xs text-gray-400 mt-1">
                                Enter the customer's purchase amount to calculate their savings.
                            </p>
                        </div>

                        <!-- Buy X Get Y or X for $Y Item Prices -->
                        <div v-if="['buy_x_get_y', 'buy_x_for_y'].includes(promotion.discount_type)" class="space-y-4">
                            <!-- Buy Items -->
                            <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                                <p class="text-xs font-medium text-gray-400 mb-3 uppercase tracking-wider">
                                    {{ promotion.discount_type === 'buy_x_for_y' ? `Original prices of the ${promotion.buy_quantity} items` : `Prices of items BOUGHT (${promotion.buy_quantity})` }}
                                </p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div v-for="i in promotion.buy_quantity" :key="`buy-${i}`">
                                        <label class="text-[10px] text-gray-500 uppercase">Item {{ i }}</label>
                                        <div class="relative mt-1">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                            <input 
                                                v-model="buyPrices[i-1]" 
                                                @input="calculateDiscount"
                                                type="number" 
                                                step="0.01" 
                                                class="w-full bg-white/5 border border-white/10 rounded-lg pl-7 py-2 text-white text-sm focus:outline-none focus:border-emerald-500/50" 
                                                placeholder="0.00" 
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Items (Buy X Get Y only) -->
                            <div v-if="promotion.discount_type === 'buy_x_get_y'" class="p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/20">
                                <p class="text-xs font-medium text-emerald-400/70 mb-3 uppercase tracking-wider">Prices of items FREE ({{ promotion.get_quantity }})</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div v-for="i in promotion.get_quantity" :key="`get-${i}`">
                                        <label class="text-[10px] text-emerald-500/50 uppercase">Free Item {{ i }}</label>
                                        <div class="relative mt-1">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-400/40 text-xs">$</span>
                                            <input 
                                                v-model="getPrices[i-1]" 
                                                @input="calculateDiscount"
                                                type="number" 
                                                step="0.01" 
                                                class="w-full bg-emerald-500/5 border border-emerald-500/20 rounded-lg pl-7 py-2 text-emerald-400 text-sm focus:outline-none focus:border-emerald-500/50" 
                                                placeholder="0.00" 
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quantity (for Buy X Get Y only, hide for BOGO) -->
                        <div v-if="['buy_x_for_y'].includes(promotion.discount_type) && false">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Quantity</label>
                            <input
                                v-model="quantity"
                                @input="calculateDiscount"
                                type="number"
                                min="1"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-xl focus:outline-none focus:border-emerald-500/50"
                                placeholder="1"
                                :disabled="processing"
                            />
                        </div>

                        <!-- Calculated Discount Display -->
                        <div v-if="originalAmount && parseFloat(originalAmount) > 0" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Customer Saves</p>
                                    <p class="text-2xl font-bold text-emerald-400">-${{ calculatedDiscount.toFixed(2) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-1">Cost to Customer</p>
                                    <p class="text-2xl font-bold text-white">${{ calculatedFinal.toFixed(2) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fixed Amount Display (for promotions that don't need calculator) -->
                    <div v-else-if="promotion.discount_type === 'fixed_amount'" class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                        <p class="text-xs text-gray-400 mb-1">Fixed Discount</p>
                        <p class="text-2xl font-bold text-emerald-400">-${{ parseFloat(promotion.discount_value || 0).toFixed(2) }}</p>
                    </div>

                    <!-- Punch Card Reward Value Display -->
                    <div v-else-if="promotion.discount_type === 'punch_card' && promotion.reward_value" class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                        <p class="text-xs text-gray-400 mb-1">Free Item Value</p>
                        <p class="text-2xl font-bold text-emerald-400">${{ parseFloat(promotion.reward_value).toFixed(2) }}</p>
                    </div>

                    <!-- Customer Code -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Customer Code <span class="text-gray-500">(required for punch cards)</span>
                        </label>
                        <input
                            v-model="customerCode"
                            type="text"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50"
                            placeholder="Customer shows this in Portal Profile"
                            :disabled="processing"
                        />
                    </div>

                    <!-- Buttons -->
                    <div class="space-y-3">
                        <button
                            @click="handleRedeem"
                            :disabled="processing"
                            class="w-full py-4 rounded-xl font-bold text-lg transition-all bg-gradient-to-r from-emerald-500 to-green-600 text-white hover:from-emerald-600 hover:to-green-700 shadow-lg shadow-emerald-500/25 disabled:opacity-50"
                        >
                            <span v-if="processing" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                            <span v-else>✓ Confirm Redemption</span>
                        </button>
                        <button @click="showRedeemForm = false"
                            class="w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Promotion Card -->
            <div v-else class="glass-card overflow-hidden">
                <!-- Header with Business Branding -->
                <div 
                    class="p-6 text-center"
                    :style="{ background: `linear-gradient(135deg, ${business.primary_color || '#7C3AED'}40, ${business.secondary_color || business.primary_color || '#7C3AED'}20)` }"
                >
                    <!-- Business Logo -->
                    <div v-if="business.logo_url" class="w-20 h-20 rounded-2xl bg-white mx-auto mb-4 p-2 shadow-lg">
                        <img :src="business.logo_url" :alt="business.name" class="w-full h-full object-contain" />
                    </div>
                    <div v-else class="w-20 h-20 rounded-2xl mx-auto mb-4 flex items-center justify-center"
                        :style="{ background: `linear-gradient(to bottom right, ${business.primary_color || '#7C3AED'}, ${business.secondary_color || '#EC4899'})` }">
                        <span class="text-3xl font-bold text-white">{{ business.name.charAt(0) }}</span>
                    </div>
                    
                    <h2 class="text-xl font-semibold text-white">{{ business.name }}</h2>
                    
                    <!-- User Avatar Badge (shows if logged in with avatar) -->
                    <div v-if="userAvatar" class="mt-4 flex items-center justify-center gap-2">
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/20 backdrop-blur-sm">
                            <div v-if="userAvatar.avatar_url" class="w-6 h-6 rounded-full overflow-hidden bg-white">
                                <img :src="userAvatar.avatar_url" :alt="userAvatar.name" class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="w-6 h-6 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white text-xs font-bold">
                                {{ userAvatar.name?.charAt(0).toUpperCase() }}
                            </div>
                            <span class="text-white text-sm font-medium">{{ userAvatar.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Promotion Details -->
                <div class="p-6">
                    <!-- Main Offer -->
                    <div class="text-center mb-6">
                        <p class="text-5xl font-bold text-white mb-2">{{ promotion.display_value }}</p>
                        <h1 class="text-xl text-gray-300">{{ promotion.name }}</h1>
                    </div>

                    <!-- QR Code Image and Code Display -->
                    <div v-if="canRedeem || staffAuth" class="mb-6 p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="flex flex-col items-center gap-4">
                            <!-- QR Code Image -->
                            <div
                                v-if="qrCode.image_url"
                                class="w-full max-w-[340px] bg-white p-4 rounded-lg shadow-lg"
                                style="aspect-ratio: 1 / 1;"
                            >
                                <img 
                                    :src="qrCode.image_url" 
                                    :alt="qrCode.name" 
                                    class="w-full h-full object-contain"
                                />
                            </div>
                            <div v-else class="w-full max-w-[340px] bg-white/10 rounded-lg flex items-center justify-center" style="aspect-ratio: 1 / 1;">
                                <svg class="w-32 h-32 text-gray-600" viewBox="0 0 100 100">
                                    <rect x="10" y="10" width="25" height="25" rx="3" fill="currentColor"/>
                                    <rect x="65" y="10" width="25" height="25" rx="3" fill="currentColor"/>
                                    <rect x="10" y="65" width="25" height="25" rx="3" fill="currentColor"/>
                                    <rect x="40" y="40" width="20" height="20" rx="2" fill="currentColor"/>
                                </svg>
                            </div>
                            
                            <!-- QR Code Code Display -->
                            <div class="text-center w-full">
                                <div class="text-xs text-gray-400 mb-2">
                                    {{ qrCode.is_customer_promo ? 'Your Customer Promo Code' : 'Redemption Code' }}
                                </div>
                                <div class="text-3xl font-bold text-white font-mono tracking-wider px-6 py-3 rounded-lg border-2 mb-2"
                                    :style="{ backgroundColor: `${business.primary_color || '#0ea5e9'}20`, borderColor: `${business.primary_color || '#0ea5e9'}4D` }">
                                    {{ qrCode.code }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ qrCode.is_customer_promo 
                                        ? 'Show staff this QR code or code to redeem' 
                                        : 'Tell staff this code over the phone to redeem' }}
                                </div>
                            </div>

                            <!-- Save Button (for logged-in users) -->
                            <button
                                v-if="userAvatar"
                                @click="toggleSave"
                                :disabled="saving"
                                class="px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2"
                                :class="isSavedLocal 
                                    ? 'bg-yellow-500/20 text-yellow-400 hover:bg-yellow-500/30 border border-yellow-500/30' 
                                    : 'bg-white/10 text-gray-300 hover:bg-white/20 border border-white/10'"
                            >
                                <svg v-if="isSavedLocal" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                                <span>{{ isSavedLocal ? 'Saved to My Promotions' : 'Save to My Promotions' }}</span>
                            </button>
                            <div v-if="userAvatar" class="text-xs mt-2" :class="isSavedLocal ? 'text-emerald-300' : 'text-gray-400'">
                                <span v-if="isSavedLocal">Saved — shows in My Promotions.</span>
                                <span v-else>Not saved yet.</span>
                                <Link v-if="isSavedLocal" href="/portal/scans" class="ml-1 text-primary-300 hover:text-primary-200">View</Link>
                            </div>

                            <!-- Subscribe Button (explicit opt-in) -->
                            <div v-if="!staffAuth && promotion?.discount_type !== 'punch_card'" class="w-full mt-3">
                                <div v-if="userAvatar" class="flex items-center justify-center gap-2 flex-wrap">
                                    <button
                                        v-if="!isSubscribed"
                                        type="button"
                                        class="px-4 py-2 rounded-lg bg-emerald-500/20 text-emerald-100 border border-emerald-500/20 hover:bg-emerald-500/30 transition-colors"
                                        @click="subscribeToBusiness"
                                    >
                                        Subscribe for email deals
                                    </button>
                                    <Link
                                        v-else
                                        href="/portal/subscriptions"
                                        class="px-4 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors"
                                    >
                                        Subscribed (manage)
                                    </Link>
                                    <button
                                        v-if="isSubscribed"
                                        type="button"
                                        class="px-4 py-2 rounded-lg bg-red-500/15 text-red-100 border border-red-500/20 hover:bg-red-500/25 transition-colors"
                                        @click="unsubscribeFromBusiness"
                                    >
                                        Unsubscribe
                                    </button>
                                </div>
                                <div v-else class="p-4 rounded-xl bg-white/5 border border-white/10">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-white font-semibold text-sm">Subscribe for email deals</div>
                                            <div class="text-gray-400 text-xs mt-1">Join free to opt in and get offers from this business.</div>
                                        </div>
                                        <Link
                                            :href="`/portal/join?redirect_to=/promo/${qrCode.code}&from=subscribe`"
                                            class="px-4 py-2 bg-emerald-500 text-white text-sm font-semibold rounded-lg hover:bg-emerald-600 transition-colors whitespace-nowrap"
                                        >
                                            Join Free
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Guest: push them into wallet/app instead of download/email -->
                            <div v-if="!staffAuth && !userAvatar" class="w-full mt-2">
                                <div class="p-4 rounded-xl bg-gradient-to-r from-emerald-500/10 to-green-500/10 border border-emerald-500/20">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex-1">
                                            <p class="text-emerald-400 font-medium text-sm">💰 Save this discount to your wallet</p>
                                            <p class="text-gray-400 text-xs">Join free so it stays in your portal (and the app soon)</p>
                                        </div>
                                        <Link
                                            :href="`/portal/join?redirect_to=/promo/${qrCode.code}&from=promo_scan`"
                                            class="px-4 py-2 bg-emerald-500 text-white text-sm font-semibold rounded-lg hover:bg-emerald-600 transition-colors whitespace-nowrap"
                                        >
                                            Join Free
                                        </Link>
                                    </div>
                                    <div class="mt-2 text-center">
                                        <Link
                                            :href="`/login?redirect_to=/promo/${qrCode.code}`"
                                            class="text-gray-500 text-xs hover:text-gray-300 transition-colors"
                                        >
                                            Already have an account? Sign in
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Punch Card Progress (for punch card promotions) -->
                    <div v-if="promotion.discount_type === 'punch_card' && punchCardProgress" class="mb-6">
                        <PunchCardProgress
                            :total-required="punchCardProgress.total_required"
                            :current-punches="punchCardProgress.current_punches"
                            :completed-cards="punchCardProgress.completed_cards"
                            :icon="punchCardProgress.icon"
                            :primary-color="business.primary_color"
                            size="md"
                        />
                        <!-- Punch card subscribe/unsubscribe/manage -->
                        <div v-if="!staffAuth" class="mt-4 flex items-center justify-center gap-2 flex-wrap">
                            <button
                                v-if="userAvatar && !isSubscribed"
                                type="button"
                                class="px-4 py-2 rounded-lg bg-emerald-500/20 text-emerald-100 border border-emerald-500/20 hover:bg-emerald-500/30 transition-colors"
                                @click="subscribeToBusiness"
                            >
                                Subscribe for email deals
                            </button>
                            <Link
                                v-else-if="userAvatar && isSubscribed"
                                href="/portal/subscriptions"
                                class="px-4 py-2 rounded-lg bg-white/10 text-gray-100 border border-white/10 hover:bg-white/20 transition-colors"
                            >
                                Subscribed (manage)
                            </Link>
                            <button
                                v-if="userAvatar && isSubscribed"
                                type="button"
                                class="px-4 py-2 rounded-lg bg-red-500/15 text-red-100 border border-red-500/20 hover:bg-red-500/25 transition-colors"
                                @click="unsubscribeFromBusiness"
                            >
                                Unsubscribe
                            </button>
                            <div v-if="!userAvatar" class="w-full text-center text-xs text-gray-400 mt-2">
                                <Link :href="`/portal/join?redirect_to=/promo/${qrCode.code}&from=subscribe`" class="text-primary-300 hover:text-primary-200 font-semibold">
                                    Join free
                                </Link>
                                to get emails when this punch card updates.
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="promotion.description" class="text-gray-400 text-center mb-6">
                        {{ promotion.description }}
                    </p>

                    <!-- Original Price -->
                    <div v-if="promotion.original_price" class="text-center mb-4">
                        <span class="text-gray-500 line-through text-lg">{{ formatCurrency(promotion.original_price) }}</span>
                    </div>

                    <!-- Minimum Purchase -->
                    <div v-if="promotion.minimum_purchase" class="text-center mb-4 p-3 rounded-lg bg-white/5">
                        <p class="text-gray-400 text-sm">Minimum purchase: {{ formatCurrency(promotion.minimum_purchase) }}</p>
                    </div>

                    <!-- Validity -->
                    <div v-if="promotion.starts_at || promotion.ends_at" class="text-center mb-6 p-3 rounded-lg bg-white/5">
                        <p class="text-gray-400 text-sm">
                            <span v-if="promotion.starts_at && promotion.ends_at">
                                Valid {{ promotion.starts_at }} - {{ promotion.ends_at }}
                            </span>
                            <span v-else-if="promotion.ends_at">
                                Expires {{ promotion.ends_at }}
                            </span>
                        </p>
                    </div>

                    <!-- Customer View: Redemption Status -->
                    <div v-if="!staffAuth" class="mb-6">
                        <div v-if="canRedeem" class="bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-4 text-center">
                            <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-2">
                                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-emerald-400 font-bold text-lg">Ready to Redeem!</p>
                            <p class="text-gray-400 text-sm mt-1">Show this screen to a staff member to save</p>
                        </div>
                        <div v-else class="bg-red-500/20 border border-red-500/50 rounded-xl p-6 text-center shadow-lg shadow-red-500/10">
                            <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/30">
                                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <p class="text-red-400 font-bold text-xl mb-2">{{ redeemMessage || 'Cannot redeem at this time' }}</p>
                            <p class="text-gray-400 text-sm">
                                This offer is currently unavailable for your account or at this time.
                                <template
                                    v-if="
                                        redeemMessage &&
                                        (redeemMessage.includes('scan again') || redeemMessage.includes('limit')) &&
                                        promotion?.discount_type !== 'punch_card'
                                    "
                                >
                                    <br><span class="text-white font-bold block mt-2 underline">Please physically re-scan the QR code at the business location.</span>
                                </template>
                            </p>
                        </div>
                    </div>

                    <!-- Staff View: Redeem Button -->
                    <div v-if="staffAuth && staffAuth.canRedeem">
                        <button
                            @click="showRedeemForm = true"
                            class="w-full py-4 rounded-xl font-bold text-lg transition-all bg-gradient-to-r from-emerald-500 to-green-600 text-white hover:from-emerald-600 hover:to-green-700 shadow-lg shadow-emerald-500/25"
                        >
                            💰 Redeem This Promotion
                        </button>
                        <p class="text-gray-500 text-xs text-center mt-2">
                            🔒 Logged as {{ staffAuth.name }}
                        </p>
                    </div>

                    <!-- Terms -->
                    <div v-if="promotion.terms" class="mt-6 pt-6 border-t border-white/10">
                        <p class="text-gray-500 text-xs">{{ promotion.terms }}</p>
                    </div>
                </div>

                    <!-- Footer -->
                <div class="p-4 bg-white/5 text-center space-y-3">
                    <!-- Back to Portal Button (if logged in) -->
                    <div v-if="$page.props.auth?.user" class="mb-3">
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
                    <p v-if="showPlatformBranding" class="text-gray-500 text-xs">
                        Powered by <span class="text-primary-400">Revenue QR</span>
                    </p>
                </div>
            </div>

            <!-- Punch cards require an account -->
            <div
                v-if="promotion.discount_type === 'punch_card' && !staffAuth && !userAvatar"
                class="mt-4 p-4 rounded-xl bg-white/5 border border-white/10"
            >
                <p class="text-sm text-white font-semibold text-center mb-1">
                    Punch cards need an account
                </p>
                <p class="text-xs text-gray-500 text-center">
                    Create a free account (or sign in) so your punches save to your portal and you never lose your progress.
                </p>
            </div>

            <!-- Customer Sign Up Prompt (only for non-logged-in, non-staff) -->
            <div v-if="!staffAuth && !userAvatar" class="mt-4 p-4 rounded-xl bg-gradient-to-r from-emerald-500/10 to-green-500/10 border border-emerald-500/20">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-emerald-400 font-medium text-sm">💰 Save your won discounts & promotions!</p>
                        <p class="text-gray-400 text-xs">Create a free account to keep your rewards & redeem them anytime</p>
                    </div>
                    <Link :href="`/portal/join?redirect_to=/promo/${qrCode.code}&from=promo_scan`" class="px-4 py-2 bg-emerald-500 text-white text-sm font-semibold rounded-lg hover:bg-emerald-600 transition-colors whitespace-nowrap">
                        Join Free
                    </Link>
                </div>
            </div>

            <!-- Already have account link -->
            <div v-if="!staffAuth && !userAvatar" class="mt-2 text-center">
                <Link :href="`/login?redirect_to=/promo/${qrCode.code}`" class="text-gray-500 text-xs hover:text-gray-300 transition-colors">
                    Already have an account? Sign in
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 shadow-2xl;
}
</style>
