<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    qrCode: Object,
    promotion: Object,
    business: Object,
    employee: Object,
    canRedeem: Boolean,
    redeemMessage: String,
    prefillCustomerPromoCode: String,
    punchCardProgress: Object,
    calculatorInfo: Object, // needs_calculator, prefills
});

const originalAmount = ref('');
const quantity = ref(1);
const customerCode = ref(props.prefillCustomerPromoCode || '');
const processing = ref(false);
const result = ref(null);
const error = ref(null);
const showPinEntry = ref(false);
const employeePin = ref('');
const calculatedDiscount = ref(0);
const calculatedFinal = ref(0);
const isFinalPunch = ref(false);
const punchCardState = ref(props.punchCardProgress || null);
const lastTokenCode = ref(props.prefillCustomerPromoCode || null);
let _tokenInfoTimer = null;

const userRole = computed(() => page.props.auth?.user?.role);
const backLink = computed(() => {
    if (userRole.value === 'business' || userRole.value === 'admin') {
        return '/business/dashboard';
    }
    if (userRole.value === 'employee') {
        return '/employee/redeem';
    }
    return `/promo/${props.qrCode.code}`;
});
const backLabel = computed(() => {
    if (userRole.value === 'business' || userRole.value === 'admin') {
        return 'Back to dashboard';
    }
    if (userRole.value === 'employee') {
        return 'Back to redemption terminal';
    }
    return 'Back to customer view';
});

const buyPrices = ref([]);
const getPrices = ref([]);

onMounted(() => {
    // Seed initial error from Inertia (e.g., bad code)
    if (page.props.errors?.message) {
        error.value = page.props.errors.message;
    }

    recomputePunchState();

    // 1. Handle pre-fills from calculatorInfo (Percentage, Happy Hour, etc.)
    if (props.calculatorInfo?.prefills?.purchase_amount) {
        originalAmount.value = parseFloat(props.calculatorInfo.prefills.purchase_amount).toFixed(2);
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
    
    // If a customer promo code was prefilled (redirect/token flow), fetch its token info
    if (props.prefillCustomerPromoCode) {
        // ensure customerCode model is set (it already is) and fetch server-side token info
        fetchTokenInfo(props.prefillCustomerPromoCode);
    }
});

// When staff types/pastes a customer promo code, fetch token info (punch-card progress / prefills)
const fetchTokenInfo = async (code) => {
    if (!code) return;
    try {
        const resp = await fetch(`/employee/token-info/${encodeURIComponent(code)}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        if (!resp.ok) {
            return;
        }
        const data = await resp.json();
        if (!data.success) return;

        // Set punch card progress if provided
        if (data.punch_card_progress) {
            punchCardState.value = data.punch_card_progress;
        }

        // If promotion provided, prefill amounts when appropriate
        if (data.promotion) {
            // If promotion has an original_price and no amount entered, prefill
            if (data.promotion.original_price && (!originalAmount.value || parseFloat(originalAmount.value) <= 0)) {
                originalAmount.value = parseFloat(data.promotion.original_price).toFixed(2);
            }

            // If this is a punch-card final prize, prefer reward_value for preview
            if (data.promotion.discount_type === 'punch_card') {
                const current = punchCardState.value?.current_punches ?? 0;
                const isFinal = (current >= (data.promotion.punches_required || 0));
                if (isFinal && data.promotion.reward_value) {
                    originalAmount.value = parseFloat(data.promotion.reward_value).toFixed(2);
                }
            }
        }

        recomputePunchState();
        calculateDiscount();
    } catch (e) {
        // ignore - don't block staff
        // console.warn('tokenInfo fetch failed', e);
    }
};

// Watch for new server-side errors (e.g., bad code) and surface inline
watch(() => page.props.errors, (errs) => {
    if (errs?.message) {
        error.value = errs.message;
    }
    if (errs?.original_amount) {
        error.value = errs.original_amount;
    }
});

// Computed for original_amount field error
const originalAmountError = computed(() => {
    return page.props.errors?.original_amount || null;
});

// Clear error when user starts typing
watch(originalAmount, () => {
    if (originalAmountError.value && originalAmount.value) {
        error.value = null;
    }
});

// Update originalAmount when individual buy prices change
watch(buyPrices, (newPrices) => {
    if (['buy_x_get_y', 'buy_x_for_y'].includes(props.promotion.discount_type) && newPrices.some(p => p !== '')) {
        const sum = newPrices.reduce((sum, p) => sum + (parseFloat(p) || 0), 0);
        originalAmount.value = sum > 0 ? sum.toFixed(2) : '';
    }
}, { deep: true });

const getCsrfToken = () => {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el?.content || null;
};

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount || 0);
};

// Recompute final-punch flag from latest punch card state
const recomputePunchState = () => {
    if (props.promotion.discount_type !== 'punch_card' || !props.promotion.punches_required) {
        isFinalPunch.value = false;
        return;
    }
    // punchCardState may come in different shapes depending on source:
    // - { punches, completed_cards } (PunchCard model)
    // - { current_punches, completed_cards } (server token API)
    const current = punchCardState.value?.punches
        ?? punchCardState.value?.current_punches
        ?? props.punchCardProgress?.current_punches
        ?? 0;
    isFinalPunch.value = (current >= props.promotion.punches_required);
};

    // Calculate discount in real-time
    const calculateDiscount = () => {
        const promo = props.promotion;

        const hasItemPrices =
            promo.discount_type === 'buy_x_get_y' &&
            (buyPrices.value.some(p => p !== '') || getPrices.value.some(p => p !== ''));

        const isPunchCardFinal = promo.discount_type === 'punch_card' && isFinalPunch.value;

        // Start with entered amount
        let amount = parseFloat(originalAmount.value) || 0;

        // For punch-card final prize, if no amount entered, use reward_value for preview
        if (isPunchCardFinal && (!originalAmount.value || parseFloat(originalAmount.value) <= 0) && promo.reward_value) {
            amount = parseFloat(promo.reward_value);
        }

        // If no amount and not a final punch with reward_value, bail (unless buy_x_get_y with item prices or punch card)
        // Punch cards should always calculate (will show $0.00 if no amount entered yet)
        if ((!amount || amount <= 0) && !hasItemPrices && promo.discount_type !== 'punch_card') {
            calculatedDiscount.value = 0;
            calculatedFinal.value = 0;
            return;
        }

        if (!amount || amount <= 0) {
            // allow zero here for buy_x_get_y when using item prices, or punch cards (will show $0.00)
            if (!(promo.discount_type === 'buy_x_get_y' && hasItemPrices) && promo.discount_type !== 'punch_card') {
                calculatedDiscount.value = 0;
                calculatedFinal.value = 0;
                return;
            }
        }
        const qty = parseInt(quantity.value) || 1;

        let discount = 0;
        let totalValue = amount;

        switch (promo.discount_type) {
            case 'percentage':
            case 'happy_hour':
            case 'loyalty_milestone':
                if (promo.discount_value) {
                    discount = amount * (promo.discount_value / 100);
                    if (promo.maximum_discount && discount > promo.maximum_discount) {
                        discount = promo.maximum_discount;
                    }
                }
                break;
            case 'first_time': {
                const mode = promo.rules?.first_time_kind || 'percentage';
                if (promo.discount_value) {
                    if (mode === 'fixed') {
                        discount = Math.min(promo.discount_value, amount);
                    } else {
                        discount = amount * (promo.discount_value / 100);
                        if (promo.maximum_discount && discount > promo.maximum_discount) {
                            discount = promo.maximum_discount;
                        }
                    }
                }
                break;
            }

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
                    // For the free item, savings = item's value, customer pays $0
                    discount = amount;
                    totalValue = amount;
                } else {
                    // Regular punch: customer pays the amount, no savings now
                    discount = 0;
                    totalValue = amount;
                }
                break;

            case 'tiered':
                if (promo.tiers && Array.isArray(promo.tiers)) {
                    let applicableTier = null;
                    for (const tier of promo.tiers) {
                        if (tier.min_spend && amount >= tier.min_spend) {
                            if (!applicableTier || tier.min_spend > applicableTier.min_spend) {
                                applicableTier = tier;
                            }
                        }
                    }
                    if (applicableTier && applicableTier.discount) {
                        discount = amount * (applicableTier.discount / 100);
                    }
                }
                break;
        }

        calculatedDiscount.value = Math.round(discount * 100) / 100;
        calculatedFinal.value = Math.round((totalValue - discount) * 100) / 100;
    };

// Watch for changes to recalculate
watch([originalAmount, quantity], () => {
    calculateDiscount();
});

// Recalculate when punch card state flips final/non-final
watch(isFinalPunch, (isFinal) => {
    if (props.promotion?.discount_type !== 'punch_card') return;
    if (isFinal && props.promotion?.reward_value) {
        const current = parseFloat(originalAmount.value);
        if (!current || current <= 0) {
            originalAmount.value = parseFloat(props.promotion.reward_value).toFixed(2);
        }
    }
    calculateDiscount();
});

// Watch customer promo code input and fetch token info (debounced)
watch(customerCode, (val) => {
    if (!val) return;
    const code = val.trim();
    // Simple validation: tokens start with UP- or normalized length 10
    const normalized = code.replace(/-/g, '').toUpperCase();
    if (code.toUpperCase().startsWith('UP-') || normalized.startsWith('UP') && normalized.length === 10) {
        if (_tokenInfoTimer) clearTimeout(_tokenInfoTimer);
        _tokenInfoTimer = setTimeout(() => fetchTokenInfo(code), 350);
    }
});

// Handle redemption
const handleRedeem = async () => {
    processing.value = true;
    error.value = null;

    // If we have a pre-filled customer promo code, use that in the URL instead of the QR code
    const redeemCode = props.prefillCustomerPromoCode || props.qrCode.code;

    router.post(`/employee/redeem/${redeemCode}`, {
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
            if (page.props.flash?.success || page.props.redemption) {
                const redemption = page.props.flash?.redemption || page.props.redemption;
                result.value = redemption;

                // Update punch card state if provided
                if (redemption?.punch_card_state) {
                    punchCardState.value = redemption.punch_card_state;
                }
                recomputePunchState();

                // remember token code for "redeem another"
                if (redemption?.token_code) {
                    lastTokenCode.value = redemption.token_code;
                }
            }
        },
        onError: (errors) => {
            error.value = errors.message || Object.values(errors)[0] || 'Something went wrong. Please try again.';
        },
        onFinish: () => {
            processing.value = false;
        }
    });
};

// Done - go back to appropriate terminal based on user role
const handleDone = () => {
    router.visit(backLink.value);
};

// Redeem another
const handleAnother = () => {
    result.value = null;
    // Recompute final punch state in case it changed after last redemption
    recomputePunchState();

    // Prefill amount if promo has a known price; otherwise leave blank
    if (props.promotion?.discount_type === 'punch_card' && props.promotion?.reward_value) {
        const nextIsPrize = isFinalPunch.value || punchCardState.value?.completed_and_reset;
        if (nextIsPrize) {
            originalAmount.value = parseFloat(props.promotion.reward_value).toFixed(2);
        } else if (props.promotion?.original_price) {
            originalAmount.value = parseFloat(props.promotion.original_price).toFixed(2);
        } else if (props.promotion?.final_price && props.promotion?.discount_type === 'percentage') {
            originalAmount.value = parseFloat(props.promotion.final_price).toFixed(2);
        } else if (props.calculatorInfo?.prefills?.purchase_amount) {
            originalAmount.value = parseFloat(props.calculatorInfo.prefills.purchase_amount).toFixed(2);
        } else {
            originalAmount.value = '';
        }
    } else if (props.promotion?.original_price) {
        originalAmount.value = parseFloat(props.promotion.original_price).toFixed(2);
    } else if (props.promotion?.final_price && props.promotion?.discount_type === 'percentage') {
        originalAmount.value = parseFloat(props.promotion.final_price).toFixed(2);
    } else if (props.calculatorInfo?.prefills?.purchase_amount) {
        originalAmount.value = parseFloat(props.calculatorInfo.prefills.purchase_amount).toFixed(2);
    } else {
        originalAmount.value = '';
    }
    customerCode.value = '';
    // Reuse last token code if we have one
    if (lastTokenCode.value) {
        customerCode.value = lastTokenCode.value;
    }
};
</script>

<template>
    <Head title="Staff Redemption" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-emerald-900/30 to-gray-900 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center gap-2 bg-emerald-500/20 text-emerald-400 px-4 py-2 rounded-full text-sm font-medium mb-4">
                    <span>👤</span>
                    <span>Staff Redemption Mode</span>
                </div>
                
                <!-- Business Logo -->
                <div v-if="business.logo_url" class="w-16 h-16 rounded-xl bg-white mx-auto mb-3 p-2">
                    <img :src="business.logo_url" :alt="business.name" class="w-full h-full object-contain" />
                </div>
                <h1 class="text-xl font-bold text-white">{{ business.name }}</h1>
                <p class="text-gray-400 text-sm">{{ employee.name }}</p>
            </div>

            <!-- Success State -->
            <div v-if="result" class="bg-white/10 backdrop-blur-lg rounded-2xl border-2 border-emerald-500/50 p-6">
                <div class="text-center">
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
                            Done - Show Customer
                        </button>
                        <button @click="handleAnother"
                            class="w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20 transition-all">
                            Redeem Another
                        </button>
                    </div>
                </div>
            </div>

            <!-- Redemption Form -->
            <div v-else class="bg-white/10 backdrop-blur-lg rounded-2xl border border-white/20 overflow-hidden">
                
                <!-- Error (If not redeemable) -->
                <div v-if="!canRedeem" class="bg-red-500/20 border-b border-red-500/50 p-6 text-center">
                    <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-2 border border-red-500/30">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <p class="text-red-400 font-bold text-lg mb-1">Cannot Redeem</p>
                    <p class="text-gray-300 text-sm font-medium">{{ redeemMessage || 'Promotion not eligible' }}</p>
                    <div v-if="redeemMessage && (redeemMessage.includes('scan again') || redeemMessage.includes('limit'))" class="mt-4 p-3 bg-white/10 rounded-xl">
                        <p class="text-white text-xs font-bold">INSTRUCTION FOR CUSTOMER:</p>
                        <p class="text-gray-300 text-xs">Please physically re-scan the original QR code at the business to get a fresh one-time use code.</p>
                    </div>
                </div>

                <!-- Promotion Preview -->
                <div class="p-4 bg-white/5 border-b border-white/10" :class="{ 'opacity-50 grayscale': !canRedeem }">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-2xl">
                            🎁
                        </div>
                        <div class="flex-1">
                            <p class="text-white font-semibold">{{ promotion.name }}</p>
                            <p class="text-2xl font-bold text-emerald-400">{{ promotion.display_value }}</p>
                            <p v-if="promotion.description" class="text-gray-400 text-sm mt-2">{{ promotion.description }}</p>
                            <!-- Final Price Display for Percentage Discounts -->
                            <div v-if="promotion.final_price && promotion.discount_type === 'percentage'" class="mt-3 p-3 rounded-lg bg-emerald-500/20 border border-emerald-500/30">
                                <p class="text-xs text-gray-400 mb-1">Charge Customer</p>
                                <p class="text-2xl font-bold text-emerald-400">${{ parseFloat(promotion.final_price).toFixed(2) }}</p>
                                <p v-if="promotion.original_price" class="text-xs text-gray-500 mt-1">
                                    Original: ${{ parseFloat(promotion.original_price).toFixed(2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Error Message -->
                    <div v-if="error" class="mb-4 p-4 rounded-xl bg-red-500/20 border border-red-500/30">
                        <p class="text-red-400 text-center text-sm">{{ error }}</p>
                    </div>

                    <!-- Cannot Redeem Warning -->
                    <div v-if="!canRedeem" class="mb-4 p-4 rounded-xl bg-amber-500/20 border border-amber-500/30">
                        <p class="text-amber-400 text-center text-sm">{{ redeemMessage }}</p>
                    </div>

                    <!-- Completed card notice -->
                    <div v-if="promotion.discount_type === 'punch_card' && punchCardState && punchCardState.punches === 0 && punchCardState.completed_cards > 0" class="mb-4 p-4 rounded-xl bg-amber-500/15 border border-amber-500/30">
                        <p class="text-amber-300 text-sm font-semibold">Card completed</p>
                        <p class="text-amber-100 text-xs mt-1">This punch card is completed. Please scan again to start a new card.</p>
                    </div>

                    <!-- Original Amount -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <template v-if="promotion.discount_type === 'punch_card'">
                                {{ isFinalPunch ? 'Free Item Value' : 'Money Collected (Sale Amount)' }}
                            </template>
                            <template v-else>
                                {{ promotion.discount_type === 'buy_x_get_y' ? 'Total Paid by Customer' : (promotion.discount_type === 'buy_x_for_y' ? 'Normal Total Value' : 'Purchase Amount') }}
                            </template>
                            <span v-if="calculatorInfo?.needs_calculator || promotion?.needs_calculator || (promotion.discount_type === 'punch_card' && isFinalPunch)" class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">$</span>
                            <input
                                v-model="originalAmount"
                                type="number"
                                step="0.01"
                                min="0"
                                :class="[
                                    'w-full bg-white/5 border rounded-xl px-4 py-3 pl-10 text-white text-xl focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed transition-colors',
                                    originalAmountError 
                                        ? 'border-red-500/50 focus:border-red-500/70 bg-red-500/5' 
                                        : 'border-white/10 focus:border-emerald-500/50'
                                ]"
                                :placeholder="isFinalPunch && promotion.reward_value ? parseFloat(promotion.reward_value).toFixed(2) : ((calculatorInfo?.needs_calculator || promotion?.needs_calculator) ? '0.00' : (promotion?.original_price ? parseFloat(promotion.original_price).toFixed(2) : '0.00'))"
                                :required="calculatorInfo?.needs_calculator || promotion?.needs_calculator || (promotion.discount_type === 'punch_card' && isFinalPunch)"
                                :disabled="processing || !canRedeem || (promotion?.original_price && !calculatorInfo?.needs_calculator && !promotion?.needs_calculator) || (['buy_x_get_y', 'buy_x_for_y'].includes(promotion.discount_type) && buyPrices.length > 0)"
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
                        <p v-else-if="calculatorInfo?.needs_calculator || promotion?.needs_calculator" class="text-xs text-gray-400 mt-1">
                            Enter the customer's purchase amount to calculate their savings.
                        </p>
                    </div>

                    <!-- Buy X Get Y or X for $Y Item Prices -->
                    <div v-if="['buy_x_get_y', 'buy_x_for_y'].includes(promotion.discount_type)" class="space-y-4 mb-4">
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
                    <div v-if="['buy_x_get_y', 'buy_x_for_y'].includes(promotion.discount_type) && false" class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Quantity
                        </label>
                        <input
                            v-model.number="quantity"
                            type="number"
                            min="1"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-xl focus:outline-none focus:border-emerald-500/50"
                            placeholder="1"
                            :disabled="processing || !canRedeem"
                        />
                    </div>

                    <!-- Calculated Discount Display -->
                    <!-- Always show for punch cards, or when amount is entered/pre-filled for other types -->
                    <div v-if="promotion.discount_type === 'punch_card' || (originalAmount && parseFloat(originalAmount) > 0) || (promotion.original_price && parseFloat(promotion.original_price) > 0)" class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-400 text-[10px] uppercase tracking-wider mb-1">Customer Saves</p>
                                <p class="text-emerald-400 text-2xl font-bold">{{ formatCurrency(calculatedDiscount) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400 text-[10px] uppercase tracking-wider mb-1">Cost to Customer</p>
                                <p class="text-white text-2xl font-bold">{{ formatCurrency(calculatedFinal) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Promo Code -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Customer Promo Code <span class="text-gray-500">(required)</span>
                        </label>
                        <input
                            v-model="customerCode"
                            type="text"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50"
                            placeholder="Customer shows this in their Portal promo"
                            :disabled="processing || !canRedeem"
                            :readonly="!!prefillCustomerPromoCode"
                        />
                    </div>

                    <!-- Redeem Button -->
                    <button
                        @click="handleRedeem"
                        :disabled="processing || !canRedeem"
                        class="w-full py-4 rounded-xl font-bold text-lg transition-all"
                        :class="[
                            processing || !canRedeem
                                ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                                : 'bg-gradient-to-r from-emerald-500 to-green-600 text-white hover:from-emerald-600 hover:to-green-700 shadow-lg shadow-emerald-500/25'
                        ]"
                    >
                        <span v-if="processing" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                        <span v-else-if="!canRedeem">🚫 LOCKED - {{ redeemMessage?.split(' ')[0] || 'Limit' }}</span>
                        <span v-else>✓ Confirm Redemption</span>
                    </button>
                </div>
            </div>

            <!-- Back Link -->
            <div class="mt-6 text-center">
                <Link :href="backLink" class="text-gray-400 text-sm hover:text-white transition-colors">
                    ← {{ backLabel }}
                </Link>
            </div>

            <!-- Security Notice -->
            <div class="mt-4 text-center">
                <p class="text-gray-600 text-xs">
                    🔒 This redemption is logged and attributed to {{ employee.name }}
                </p>
            </div>
        </div>
    </div>
</template>
