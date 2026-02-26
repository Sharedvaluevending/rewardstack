<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    currentStackable: Object, // The promotion currently in stackable
    promotions: Array, // All active promotions for swapping
    totalBusinesses: Number, // Total businesses in stackable
    businessCity: String,
    subscriptionTier: String,
    stackableStats: Object,
});

const showSwapModal = ref(false);

const swapForm = useForm({
    promotion_id: props.currentStackable?.id || '',
});

const swapPromotion = () => {
    swapForm.post('/business/stackable/set', {
        onSuccess: () => {
            showSwapModal.value = false;
        },
    });
};

const removeFromStackable = () => {
    if (confirm('Remove your deal from the stackable pool?')) {
        router.post('/business/stackable/remove');
    }
};

const formatDiscount = (promo) => {
    if (!promo) return '';
    if (promo.discount_type === 'percentage') {
        return `${promo.discount_value}% off`;
    }
    if (promo.discount_type === 'fixed_amount') {
        return `$${promo.discount_value} off`;
    }
    return promo.discount_type;
};

// Check if stackable features are available
const canUseStackable = computed(() => {
    const tier = props.subscriptionTier || 'starter';
    return ['growth', 'pro', 'enterprise'].includes(tier.toLowerCase());
});

const showStackablePopup = () => {
    alert('Growth or higher subscription needed for Stackable Deals');
};
</script>

<template>
    <Head title="Stackable Deals" />

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">📍 Stackable Deals</h1>
            <p class="text-gray-400">Your deal appears to customers based on their location - closest deals show first!</p>
        </div>

        <!-- How It Works -->
        <div class="glass-card p-6 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">How Stackable Deals Work</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="text-center p-4">
                    <div class="text-3xl mb-2">📱</div>
                    <div class="text-white font-medium">Customer Scans</div>
                    <div class="text-gray-400 text-sm">One QR code for all deals</div>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-2">📍</div>
                    <div class="text-white font-medium">Geo-Sorted</div>
                    <div class="text-gray-400 text-sm">Closest businesses show first</div>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-2">🔄</div>
                    <div class="text-white font-medium">Dynamic</div>
                    <div class="text-gray-400 text-sm">Swap your deal anytime</div>
                </div>
            </div>
        </div>

        <!-- Current Status -->
        <div class="glass-card p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white">Your Stackable Deal</h2>
                <div class="text-gray-400 text-sm">{{ totalBusinesses }} businesses in pool</div>
            </div>

            <!-- Has Stackable Deal -->
            <div v-if="currentStackable" class="p-6 rounded-xl bg-gradient-to-r from-emerald-500/20 to-teal-500/20 border border-emerald-500/30">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-1 bg-emerald-500/30 text-emerald-400 text-xs rounded-full">LIVE</span>
                            <span class="text-gray-400 text-sm">Showing to nearby customers</span>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-1">{{ currentStackable.name }}</h3>
                        <p class="text-emerald-400 text-lg font-semibold">{{ formatDiscount(currentStackable) }}</p>
                        <p v-if="currentStackable.description" class="text-gray-400 text-sm mt-2">
                            {{ currentStackable.description }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <button 
                            @click="canUseStackable ? showSwapModal = true : showStackablePopup()"
                            :disabled="!canUseStackable"
                            :class="[
                                'px-4 py-2 rounded-lg transition-colors',
                                canUseStackable 
                                    ? 'bg-blue-500/20 text-blue-400 hover:bg-blue-500/30'
                                    : 'bg-gray-500/20 text-gray-400 opacity-50 cursor-not-allowed'
                            ]"
                        >
                            🔄 Swap Deal
                        </button>
                        <button 
                            @click="removeFromStackable"
                            class="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition-colors"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- No Stackable Deal -->
            <div v-else class="text-center py-12">
                <div class="w-20 h-20 rounded-full bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <span class="text-4xl">📍</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">No Deal in Pool Yet</h3>
                <p class="text-gray-400 mb-6 max-w-md mx-auto">
                    <span v-if="canUseStackable">Add one of your promotions to the stackable pool. Customers will see your deal when they're near your location!</span>
                    <span v-else class="text-red-400">Growth or higher subscription needed for Stackable Deals</span>
                </p>
                <button 
                    @click="canUseStackable ? showSwapModal = true : showStackablePopup()"
                    :disabled="!canUseStackable"
                    :class="[
                        'px-6 py-3 font-semibold rounded-xl transition-all',
                        canUseStackable
                            ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white hover:opacity-90'
                            : 'bg-gray-500/20 text-gray-400 opacity-50 cursor-not-allowed'
                    ]"
                >
                    ✨ Add Your Deal
                </button>
            </div>
        </div>

        <!-- Stackable Performance -->
        <div class="glass-card p-6 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Performance (all-time)</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <div class="text-gray-400 text-sm mb-1">Scans (stackable QR)</div>
                    <div class="text-2xl font-bold text-white">{{ stackableStats?.scans ?? 0 }}</div>
                </div>
                <div>
                    <div class="text-gray-400 text-sm mb-1">Claims (tokens issued)</div>
                    <div class="text-2xl font-bold text-white">{{ stackableStats?.claims ?? 0 }}</div>
                </div>
                <div>
                    <div class="text-gray-400 text-sm mb-1">Redemptions</div>
                    <div class="text-2xl font-bold text-white">{{ stackableStats?.redemptions ?? 0 }}</div>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                Redemptions only count when a stackable promo is actually redeemed (claims may be higher).
            </p>
        </div>

        <!-- Quick Tips -->
        <div class="glass-card p-6">
            <h2 class="text-lg font-semibold text-white mb-4">💡 Tips for Success</h2>
            <ul class="space-y-3 text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="text-emerald-400">✓</span>
                    <span>Use your <strong class="text-white">best offer</strong> - it's competing with nearby businesses!</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-emerald-400">✓</span>
                    <span><strong class="text-white">Swap seasonally</strong> - update your deal for holidays or slow periods</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-emerald-400">✓</span>
                    <span>Make sure your <strong class="text-white">business address</strong> is accurate in settings</span>
                </li>
            </ul>
            <Link href="/business/settings" class="inline-block mt-4 text-primary-400 hover:underline text-sm">
                Update business address →
            </Link>
        </div>

        <!-- Swap Modal -->
        <div v-if="showSwapModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
            <div class="bg-gray-900 rounded-2xl p-6 w-full max-w-md border border-white/10">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white">
                        {{ currentStackable ? 'Swap Your Deal' : 'Add Deal to Pool' }}
                    </h2>
                    <button @click="showSwapModal = false" class="text-gray-400 hover:text-white">✕</button>
                </div>

                <form @submit.prevent="swapPromotion" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Select Promotion</label>
                        <div v-if="promotions.length === 0" class="text-center py-6 bg-white/5 rounded-xl">
                            <p class="text-gray-400 mb-3">No active promotions</p>
                            <Link href="/business/promotions/create" class="text-primary-400 hover:underline">
                                Create a promotion first →
                            </Link>
                        </div>
                        <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                            <label 
                                v-for="promo in promotions" 
                                :key="promo.id"
                                :class="[
                                    'flex items-center p-4 rounded-xl cursor-pointer transition-all',
                                    swapForm.promotion_id == promo.id 
                                        ? 'bg-emerald-500/20 border border-emerald-500/50' 
                                        : 'bg-white/5 border border-transparent hover:bg-white/10'
                                ]"
                            >
                                <input 
                                    type="radio" 
                                    v-model="swapForm.promotion_id" 
                                    :value="promo.id"
                                    class="sr-only"
                                />
                                <div class="flex-1">
                                    <div class="text-white font-medium">{{ promo.name }}</div>
                                    <div class="text-emerald-400 text-sm">{{ formatDiscount(promo) }}</div>
                                </div>
                                <div v-if="swapForm.promotion_id == promo.id" class="text-emerald-400">
                                    ✓
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button 
                            type="button" 
                            @click="showSwapModal = false" 
                            class="flex-1 py-3 bg-white/10 text-white rounded-xl hover:bg-white/20"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="swapForm.processing || !swapForm.promotion_id || !canUseStackable"
                            class="flex-1 py-3 bg-emerald-500 text-white rounded-xl hover:bg-emerald-600 disabled:opacity-50"
                        >
                            {{ currentStackable ? 'Swap Deal' : 'Add to Pool' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
