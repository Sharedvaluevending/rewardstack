<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    employee: Object,
    business: Object,
    todayRedemptions: Array,
    todayStats: Object,
});

const scanMode = ref(false);
const manualCode = ref('');
const originalAmount = ref('');
const processing = ref(false);
const result = ref(null);
const error = ref(null);
const tokenInfo = ref(null);
const buyPrices = ref([]);
const getPrices = ref([]);
let _tokenInfoTimer = null;

const isBuyXGetY = computed(() =>
    tokenInfo.value?.promotion?.discount_type === 'buy_x_get_y' ||
    tokenInfo.value?.promotion?.discount_type === 'buy_x_for_y'
);
const buyQty = computed(() => tokenInfo.value?.promotion?.buy_quantity ?? 1);
const getQty = computed(() => tokenInfo.value?.promotion?.get_quantity ?? 1);

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

// Format time
const formatTime = (date) => {
    return new Date(date).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    });
};

// Fetch token/promotion info when staff types a customer code (for Buy X Get Y item prices, punch card state, etc.)
const fetchTokenInfo = async (code) => {
    if (!code?.trim()) return;
    try {
        const resp = await fetch(`/employee/token-info/${encodeURIComponent(code.trim())}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!resp.ok) return;
        const data = await resp.json();
        if (!data.success) return;
        tokenInfo.value = data;
        // Initialize item price arrays for Buy X Get Y
        if (['buy_x_get_y', 'buy_x_for_y'].includes(data.promotion?.discount_type)) {
            const rules = data.promotion?.rules ?? {};
            const ruleBuy = Array.isArray(rules.buy_item_prices) ? rules.buy_item_prices : [];
            const ruleGet = Array.isArray(rules.get_item_prices) ? rules.get_item_prices : [];
            const bq = data.promotion.buy_quantity ?? 1;
            const gq = data.promotion.get_quantity ?? 1;
            buyPrices.value = Array.from({ length: bq }, (_, i) =>
                (ruleBuy[i] != null && ruleBuy[i] !== '') ? parseFloat(ruleBuy[i]).toFixed(2) : ''
            );
            getPrices.value = Array.from({ length: gq }, (_, i) =>
                (ruleGet[i] != null && ruleGet[i] !== '') ? parseFloat(ruleGet[i]).toFixed(2) : ''
            );
            // Prefill original amount from sum of buy prices if available
            const sum = buyPrices.value.reduce((s, p) => s + (parseFloat(p) || 0), 0);
            if (sum > 0 && (!originalAmount.value || parseFloat(originalAmount.value) <= 0)) {
                originalAmount.value = sum.toFixed(2);
            }
        } else {
            buyPrices.value = [];
            getPrices.value = [];
        }
    } catch {
        // Ignore - don't block staff
    }
};

// Debounced watch on manualCode to fetch token info
watch(manualCode, (val) => {
    if (!val?.trim()) {
        tokenInfo.value = null;
        buyPrices.value = [];
        getPrices.value = [];
        return;
    }
    const code = val.trim();
    const normalized = code.replace(/-/g, '').toUpperCase();
    if (code.toUpperCase().startsWith('UP-') || (normalized.startsWith('UP') && normalized.length === 10)) {
        if (_tokenInfoTimer) clearTimeout(_tokenInfoTimer);
        _tokenInfoTimer = setTimeout(() => fetchTokenInfo(code), 350);
    }
});

// Handle code submission
const handleRedeem = async () => {
    if (!manualCode.value) {
        error.value = 'Please enter a Customer Promo Code';
        return;
    }

    processing.value = true;
    error.value = null;
    result.value = null;

    try {
        const csrf = getCsrfToken();
        if (!csrf) {
            error.value = 'Session error (missing CSRF token). Please refresh and try again.';
            return;
        }

        const code = String(manualCode.value || '').trim();
        const response = await fetch(`/employee/redeem/${encodeURIComponent(code)}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                original_amount: parseFloat(originalAmount.value) || 0,
                quantity: 1,
                item_prices: isBuyXGetY.value ? {
                    buy: buyPrices.value,
                    get: getPrices.value,
                } : null,
            }),
        });

        let data = null;
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            if (response.status === 401) {
                error.value = 'You are not logged in. Please log in and try again.';
                return;
            }
            if (response.status === 419) {
                error.value = 'Session expired. Please refresh the page and try again.';
                return;
            }
            error.value = `Redeem failed (HTTP ${response.status}). Please try again.`;
            if (text?.includes('Vite manifest not found')) {
                error.value = 'Server build missing (Vite manifest not found). Rebuild assets and refresh.';
            }
            return;
        }

        if (data.success) {
            result.value = data.redemption;
            // Reset form
            manualCode.value = '';
            originalAmount.value = '';
            tokenInfo.value = null;
            buyPrices.value = [];
            getPrices.value = [];
        } else {
            error.value = data.message;
        }
    } catch (err) {
        error.value = 'Something went wrong. Please try again.';
    } finally {
        processing.value = false;
    }
};

// Dismiss result
const dismissResult = () => {
    result.value = null;
};
</script>

<template>
    <Head title="Redeem" />

    <div class="max-w-lg mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <div v-if="business.logo_path" class="w-16 h-16 rounded-xl bg-white mx-auto mb-4 p-2">
                <img :src="`/storage/${business.logo_path}`" :alt="business.name" class="w-full h-full object-contain" />
            </div>
            <h1 class="text-2xl font-bold text-white">{{ business.name }}</h1>
            <p class="text-gray-400">Redemption Terminal</p>
        </div>

        <!-- Today's Stats -->
        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="glass-card p-4 text-center">
                <p class="text-3xl font-bold text-white">{{ todayStats.total }}</p>
                <p class="text-gray-400 text-sm">Today's Redemptions</p>
            </div>
            <div class="glass-card p-4 text-center">
                <p class="text-3xl font-bold text-green-400">{{ formatCurrency(todayStats.savings) }}</p>
                <p class="text-gray-400 text-sm">Savings Generated</p>
            </div>
        </div>

        <!-- Success Result -->
        <div v-if="result" class="glass-card p-6 mb-6 border-2 border-green-500/50 bg-green-500/10">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full bg-green-500/20 mx-auto mb-4 flex items-center justify-center">
                    <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-green-400 mb-2">Redeemed Successfully!</h2>
                <p class="text-white text-lg mb-1">{{ result.promotion_name }}</p>
                
                <div class="grid grid-cols-2 gap-4 mt-4 text-left">
                    <div class="p-3 rounded-lg bg-white/5">
                        <p class="text-gray-400 text-xs">Discount</p>
                        <p class="text-green-400 text-xl font-bold">-{{ formatCurrency(result.discount_amount) }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-white/5">
                        <p class="text-gray-400 text-xs">Final Amount</p>
                        <p class="text-white text-xl font-bold">{{ formatCurrency(result.final_amount) }}</p>
                    </div>
                </div>

                <button @click="dismissResult" class="w-full mt-4 py-3 rounded-xl bg-green-500 text-white font-medium">
                    Done - Ready for Next
                </button>
            </div>
        </div>

        <!-- Redemption Form -->
        <div v-else class="glass-card p-6">
            <!-- Error Message -->
            <div v-if="error" class="mb-4 p-4 rounded-xl bg-red-500/20 border border-red-500/30">
                <p class="text-red-400 text-center">{{ error }}</p>
            </div>

            <!-- Code Input -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Customer Promo Code</label>
                <input
                    v-model="manualCode"
                    type="text"
                    class="input-glass text-center text-xl tracking-widest uppercase"
                    placeholder="UP-ABCD-2345"
                    :disabled="processing"
                />
                <p class="text-gray-500 text-xs mt-2">
                    Customer shows this in their portal under the promo (QR + code).
                </p>
            </div>

            <!-- Optional: Original Amount -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                    {{ isBuyXGetY ? (tokenInfo?.promotion?.discount_type === 'buy_x_for_y' ? 'Normal Total Value' : 'Total Paid by Customer') : 'Original Amount' }}
                    <span v-if="!isBuyXGetY" class="text-gray-500 text-xs ml-1">(Required if promotion has no fixed price)</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                    <input
                        v-model="originalAmount"
                        type="number"
                        step="0.01"
                        min="0"
                        class="input-glass pl-8"
                        placeholder="0.00"
                        :disabled="processing || (isBuyXGetY && buyPrices.some(p => p !== ''))"
                    />
                </div>
                <p v-if="isBuyXGetY && buyPrices.some(p => p !== '')" class="text-gray-500 text-xs mt-1">Sum of individual items below.</p>
                <p v-else-if="!isBuyXGetY" class="text-gray-500 text-xs mt-1">For variable-price promotions, you must enter the price here.</p>
            </div>

            <!-- Buy X Get Y / Buy X For Y: Individual Item Prices -->
            <div v-if="isBuyXGetY" class="space-y-4 mb-4">
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <p class="text-xs font-medium text-gray-400 mb-3 uppercase tracking-wider">
                        {{ tokenInfo?.promotion?.discount_type === 'buy_x_for_y' ? `Original prices of the ${buyQty} items` : `Prices of items BOUGHT (${buyQty})` }}
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="i in buyQty" :key="`buy-${i}`">
                            <label class="text-[10px] text-gray-500 uppercase">Item {{ i }}</label>
                            <div class="relative mt-1">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                <input
                                    v-model="buyPrices[i - 1]"
                                    type="number"
                                    step="0.01"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg pl-7 py-2 text-white text-sm focus:outline-none focus:border-emerald-500/50"
                                    placeholder="0.00"
                                    :disabled="processing"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="tokenInfo?.promotion?.discount_type === 'buy_x_get_y'" class="p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/20">
                    <p class="text-xs font-medium text-emerald-400/70 mb-3 uppercase tracking-wider">Prices of items FREE ({{ getQty }})</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="i in getQty" :key="`get-${i}`">
                            <label class="text-[10px] text-emerald-500/50 uppercase">Free Item {{ i }}</label>
                            <div class="relative mt-1">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-400/40 text-xs">$</span>
                                <input
                                    v-model="getPrices[i - 1]"
                                    type="number"
                                    step="0.01"
                                    class="w-full bg-emerald-500/5 border border-emerald-500/20 rounded-lg pl-7 py-2 text-emerald-400 text-sm focus:outline-none focus:border-emerald-500/50"
                                    placeholder="0.00"
                                    :disabled="processing"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button
                @click="handleRedeem"
                :disabled="processing || !manualCode"
                class="w-full py-4 rounded-xl font-semibold text-lg transition-all"
                :class="[
                    processing || !manualCode
                        ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                        : 'bg-gradient-to-r from-green-500 to-emerald-600 text-white hover:from-green-600 hover:to-emerald-700'
                ]"
            >
                <span v-if="processing">Processing...</span>
                <span v-else>Redeem Promotion</span>
            </button>
        </div>

        <!-- Recent Redemptions -->
        <div v-if="todayRedemptions.length" class="mt-8">
            <h3 class="text-lg font-semibold text-white mb-4">Today's Activity</h3>
            <div class="space-y-2">
                <div
                    v-for="redemption in todayRedemptions.slice(0, 5)"
                    :key="redemption.id"
                    class="flex items-center justify-between p-3 rounded-xl bg-white/5"
                >
                    <div>
                        <p class="text-white font-medium">{{ redemption.promotion?.name }}</p>
                        <p class="text-gray-500 text-sm">{{ formatTime(redemption.redeemed_at) }}</p>
                    </div>
                    <p class="text-green-400 font-medium">-{{ formatCurrency(redemption.discount_amount) }}</p>
                </div>
            </div>
            <Link href="/employee/activity" class="block text-center text-primary-400 text-sm mt-4 hover:underline">
                View All Activity →
            </Link>
        </div>
    </div>
</template>

