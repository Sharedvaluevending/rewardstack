<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    promotion: Object,
    discountTypes: Object,
    punchIconOptions: Object,
    subscriptionTier: String,
});

const step = ref(1);
const totalSteps = 4;

const form = useForm({
    name: props.promotion?.name || '',
    description: props.promotion?.description || '',
    terms: props.promotion?.terms || '',
    discount_type: props.promotion?.discount_type || 'percentage',
    discount_value: props.promotion?.discount_value || null,
    buy_quantity: props.promotion?.buy_quantity || null,
    get_quantity: props.promotion?.get_quantity || null,
    for_price: props.promotion?.for_price || null,
    punches_required: props.promotion?.punches_required || 10,
    reward_value: props.promotion?.reward_value || null,
    punch_card_max_cards_per_user: props.promotion?.punch_card_max_cards_per_user || null,
    punch_card_total_cards_limit: props.promotion?.punch_card_total_cards_limit || null,
    punch_card_max_punches_per_day: props.promotion?.punch_card_max_punches_per_day || null,
    punch_icon: props.promotion?.punch_icon || '⭐',
    tiers: props.promotion?.tiers || [],
    original_price: props.promotion?.original_price || null,
    minimum_purchase: props.promotion?.minimum_purchase || null,
    maximum_discount: props.promotion?.maximum_discount || null,
    rules: {
        max_redemptions_total: props.promotion?.rules?.max_redemptions_total || null,
        max_redemptions_per_user: props.promotion?.rules?.max_redemptions_per_user || null,
        max_per_day: props.promotion?.rules?.max_per_day || null,
        max_per_week: props.promotion?.rules?.max_per_week || null,
        max_per_month: props.promotion?.rules?.max_per_month || null,
        // New: Toggle behavior for scans
        portal_multiple_scans: props.promotion?.rules?.portal_multiple_scans || false,
        respect_punch_limits: props.promotion?.rules?.respect_punch_limits || false,
        valid_days: props.promotion?.rules?.valid_days || [],
        valid_hours: props.promotion?.rules?.valid_hours || {
            start: null,
            end: null,
        },
        combinable: props.promotion?.rules?.combinable ?? true,
        buy_item_prices: props.promotion?.rules?.buy_item_prices || [],
        get_item_prices: props.promotion?.rules?.get_item_prices || [],
        first_time_kind: props.promotion?.rules?.first_time_kind || 'percentage',
    },
    starts_at: props.promotion?.starts_at ? props.promotion.starts_at.split('T')[0] : null,
    ends_at: props.promotion?.ends_at ? props.promotion.ends_at.split('T')[0] : null,
    is_active: props.promotion?.is_active ?? true,
    is_stackable: props.promotion?.is_stackable ?? false,
});

// Discount type cards with icons and descriptions
const discountTypeCards = [
    { type: 'percentage', name: 'Percentage Off', icon: '%', desc: '10%, 20%, 50% off', color: 'from-blue-500 to-cyan-500' },
    { type: 'fixed_amount', name: 'Fixed Amount', icon: '$', desc: '$5, $10, $20 off', color: 'from-green-500 to-emerald-500' },
    { type: 'bogo', name: 'BOGO', icon: '2=1', desc: 'Buy One Get One', color: 'from-purple-500 to-pink-500' },
    { type: 'buy_x_get_y', name: 'Buy X Get Y', icon: 'X+Y', desc: 'Buy 2 Get 1 Free', color: 'from-orange-500 to-red-500' },
    { type: 'buy_x_for_y', name: 'X for $Y', icon: '3/$', desc: '3 for $10', color: 'from-yellow-500 to-orange-500' },
    { type: 'punch_card', name: 'Punch Card', icon: '🎯', desc: 'Loyalty rewards', color: 'from-indigo-500 to-purple-500' },
    { type: 'tiered', name: 'Tiered Discount', icon: '📊', desc: 'Spend more save more', color: 'from-pink-500 to-rose-500' },
    { type: 'happy_hour', name: 'Happy Hour', icon: '⏰', desc: 'Time-based deals', color: 'from-cyan-500 to-blue-500' },
    { type: 'first_time', name: 'First Time Customer', icon: '👋', desc: 'New customer welcome offer', color: 'from-emerald-500 to-teal-500' },
];

const weekDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

// Computed preview of the discount
const discountPreview = computed(() => {
    switch (form.discount_type) {
        case 'percentage':
            return form.discount_value ? `${form.discount_value}% Off` : 'X% Off';
        case 'fixed_amount':
            return form.discount_value ? `$${form.discount_value} Off` : '$X Off';
        case 'bogo':
            return 'Buy One Get One Free';
        case 'buy_x_get_y':
            return `Buy ${form.buy_quantity || 'X'} Get ${form.get_quantity || 'Y'} Free`;
        case 'buy_x_for_y':
            return `${form.buy_quantity || 'X'} for $${form.for_price || 'Y'}`;
        case 'punch_card':
            return `Buy ${form.punches_required || 10}, Get 1 Free`;
        case 'tiered':
            return 'Spend More, Save More';
        case 'happy_hour':
            return form.discount_value ? `Happy Hour: ${form.discount_value}% Off` : 'Happy Hour Special';
        default:
            return 'Special Offer';
    }
});

// Computed final price for percentage and happy_hour discounts with original price
const finalPrice = computed(() => {
    if (['percentage', 'happy_hour'].includes(form.discount_type) && form.original_price && form.discount_value) {
        let discount = form.original_price * (form.discount_value / 100);
        
        // Apply maximum discount if set
        if (form.maximum_discount && discount > form.maximum_discount) {
            discount = form.maximum_discount;
        }
        
        return (form.original_price - discount).toFixed(2);
    }
    return null;
});

// Computed description placeholder based on promotion type
const descriptionPlaceholder = computed(() => {
    switch (form.discount_type) {
        case 'percentage':
            return 'Describe your percentage discount promotion. Example: "Get 20% off all menu items" or "Save 15% on your entire purchase"';
        case 'fixed_amount':
            return 'Describe your fixed amount discount. Example: "Save $5 on any order over $25" or "$10 off your next purchase"';
        case 'bogo':
            return 'Describe your Buy One Get One promotion. Example: "Buy one pizza, get one free" or "Purchase any entree and get a second one free"';
        case 'buy_x_get_y':
            return 'Describe your Buy X Get Y promotion. Specify what items to buy and what\'s free. Example: "Buy 2 pizzas, get 1 free" or "Buy 3 coffees, get 1 free"';
        case 'buy_x_for_y':
            return 'Describe your bundle deal. Example: "Buy 3 items for $20" or "Get 5 items for the price of 3"';
        case 'punch_card':
            return 'Describe your punch card loyalty program. Example: "Buy 10 coffees, get your 11th free" or "Earn a punch with each visit"';
        case 'tiered':
            return 'Describe your tiered discount. Example: "Spend $50 get 10% off, spend $100 get 15% off" or "The more you spend, the more you save"';
        case 'happy_hour':
            return 'Describe your happy hour promotion. Example: "20% off all drinks from 4pm-6pm" or "Happy hour specials every weekday evening"';
        case 'first_time':
            return 'Describe your first-time customer offer. Example: "Welcome! Get 15% off your first visit" or "New customers save 20% on their first order"';
        default:
            return 'Describe your promotion...';
    }
});

// Computed description hint based on promotion type
const descriptionHint = computed(() => {
    switch (form.discount_type) {
        case 'buy_x_get_y':
            return '💡 Example: "Buy 2 pizzas, get 1 free" or "Buy 3 coffees, get 1 free"';
        case 'bogo':
            return '💡 Example: "Buy one pizza, get one free" or "Purchase any entree and get a second one free"';
        case 'punch_card':
            return '💡 Example: "Buy 10 coffees, get your 11th free" or "Earn a punch with each visit"';
        default:
            return null;
    }
});

// Add tier for tiered discount
const addTier = () => {
    form.tiers.push({ min_spend: 1, discount: 1 });
};

const removeTier = (index) => {
    form.tiers.splice(index, 1);
};

// Toggle day selection
const toggleDay = (day) => {
    const index = form.rules.valid_days.indexOf(day);
    if (index > -1) {
        form.rules.valid_days.splice(index, 1);
    } else {
        form.rules.valid_days.push(day);
    }
};

watch(() => form.buy_quantity, (newVal) => {
    if (['buy_x_get_y', 'buy_x_for_y'].includes(form.discount_type)) {
        const currentPrices = form.rules.buy_item_prices || [];
        form.rules.buy_item_prices = Array.from({ length: newVal || 0 }, (_, i) => currentPrices[i] || null);
    }
});

watch(() => form.get_quantity, (newVal) => {
    if (form.discount_type === 'buy_x_get_y') {
        const currentPrices = form.rules.get_item_prices || [];
        form.rules.get_item_prices = Array.from({ length: newVal || 0 }, (_, i) => currentPrices[i] || null);
    }
});

watch(() => form.discount_type, (newVal) => {
    if (newVal === 'buy_x_get_y') {
        // Set defaults if they are empty
        if (!form.buy_quantity) form.buy_quantity = 2;
        if (!form.get_quantity) form.get_quantity = 1;
        
        if (!form.rules.buy_item_prices || form.rules.buy_item_prices.length === 0) {
            form.rules.buy_item_prices = Array(form.buy_quantity).fill(null);
        }
        if (!form.rules.get_item_prices || form.rules.get_item_prices.length === 0) {
            form.rules.get_item_prices = Array(form.get_quantity).fill(null);
        }
    } else if (newVal === 'buy_x_for_y') {
        // Set defaults for X for $Y
        if (!form.buy_quantity) form.buy_quantity = 3;
        if (!form.for_price) form.for_price = 10.00;
        
        if (!form.rules.buy_item_prices || form.rules.buy_item_prices.length === 0) {
            form.rules.buy_item_prices = Array(form.buy_quantity).fill(null);
        }
    }
});

// Navigation
const nextStep = () => {
    if (step.value < totalSteps) step.value++;
};

const prevStep = () => {
    if (step.value > 1) step.value--;
};

// Check if business can use stackable deals (Growth+ required)
const canUseStackable = computed(() => {
    const tier = props.subscriptionTier || 'starter';
    return ['growth', 'pro', 'enterprise'].includes(tier.toLowerCase());
});

// Show popup if trying to use stackable without access
const showStackablePopup = () => {
    alert('Stackable Deals require a Growth or higher subscription. Please upgrade your plan to use this feature.');
};

// Submit
const submit = () => {
    form.put(route('business.promotions.update', props.promotion.id));
};
</script>

<template>
    <Head :title="`Edit - ${promotion?.name || 'Promotion'}`" />

    <div class="max-w-4xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <Link href="/business/promotions" class="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center">
                ← Back to Promotions
            </Link>
            <h1 class="text-3xl font-bold text-white">Edit Promotion</h1>
            <p class="text-gray-400 mt-1">Update your discount or deal</p>
        </div>

        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex justify-between text-sm text-gray-400 mb-2">
                <span>Step {{ step }} of {{ totalSteps }}</span>
                <span>{{ ['Discount Type', 'Details', 'Rules', 'Review'][step - 1] }}</span>
            </div>
            <div class="h-2 bg-gray-700 rounded-full overflow-hidden">
                <div 
                    class="h-full bg-gradient-to-r from-primary-500 to-accent-500 transition-all duration-300"
                    :style="{ width: `${(step / totalSteps) * 100}%` }"
                ></div>
            </div>
        </div>

        <!-- Step Content -->
        <div class="glass-card p-8">
            <!-- Step 1: Discount Type -->
            <div v-show="step === 1" class="space-y-6">
                <h2 class="text-xl font-semibold text-white mb-4">Choose Discount Type</h2>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <button
                        v-for="type in discountTypeCards"
                        :key="type.type"
                        @click="form.discount_type = type.type"
                        :class="[
                            'p-4 rounded-xl border-2 text-center transition-all',
                            form.discount_type === type.type
                                ? 'border-primary-500 bg-primary-500/20'
                                : 'border-white/20 hover:border-white/40'
                        ]"
                    >
                        <div :class="`w-12 h-12 rounded-xl bg-gradient-to-br ${type.color} flex items-center justify-center mx-auto mb-3`">
                            <span class="text-white text-lg font-bold">{{ type.icon }}</span>
                        </div>
                        <p class="text-white font-medium text-sm">{{ type.name }}</p>
                        <p class="text-gray-500 text-xs mt-1">{{ type.desc }}</p>
                    </button>
                </div>

                <!-- Preview Card -->
                <div class="mt-8 p-6 rounded-xl bg-gradient-to-r from-primary-500/20 to-accent-500/20 border border-primary-500/30">
                    <p class="text-gray-400 text-sm mb-1">Preview</p>
                    <p class="text-3xl font-bold text-white">{{ discountPreview }}</p>
                </div>
            </div>

            <!-- Step 2: Details -->
            <div v-show="step === 2" class="space-y-6">
                <h2 class="text-xl font-semibold text-white mb-4">Promotion Details</h2>

                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Promotion Name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="input-glass"
                        placeholder="e.g., Summer Sale 20% Off"
                    />
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Description (shown to customers and staff when redeeming)</label>
                    <textarea
                        v-model="form.description"
                        class="input-glass"
                        rows="3"
                        :placeholder="descriptionPlaceholder"
                    ></textarea>
                    <p v-if="descriptionHint" class="text-gray-500 text-sm mt-2">
                        {{ descriptionHint }}
                    </p>
                </div>

                <!-- Type-specific fields -->
                <div v-if="['percentage', 'fixed_amount', 'happy_hour', 'first_time'].includes(form.discount_type)">
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <template v-if="form.discount_type === 'first_time' && form.rules.first_time_kind === 'fixed'">
                            Fixed $ Off (First Time)
                        </template>
                        <template v-else>
                            Discount Value {{ form.discount_type === 'fixed_amount' ? '($)' : '(%)' }}
                        </template>
                    </label>
                    <input
                        v-model="form.discount_value"
                        type="number"
                        min="0"
                        :max="(['percentage', 'happy_hour'].includes(form.discount_type) || (form.discount_type === 'first_time' && form.rules.first_time_kind === 'percentage')) ? 100 : null"
                        class="input-glass"
                        :placeholder="(form.discount_type === 'fixed_amount' || (form.discount_type === 'first_time' && form.rules.first_time_kind === 'fixed')) ? '5.00' : '10'"
                    />
                </div>

                <div v-if="form.discount_type === 'first_time'" class="grid grid-cols-2 gap-3">
                    <div class="flex items-center gap-2">
                        <input type="radio" id="ft-perc" value="percentage" v-model="form.rules.first_time_kind" />
                        <label for="ft-perc" class="text-sm text-gray-300">Percent Off</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="radio" id="ft-fixed" value="fixed" v-model="form.rules.first_time_kind" />
                        <label for="ft-fixed" class="text-sm text-gray-300">Fixed $ Off</label>
                    </div>
                </div>

                <div v-if="form.discount_type === 'buy_x_get_y'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Buy Quantity</label>
                            <input v-model="form.buy_quantity" type="number" min="1" class="input-glass" placeholder="2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Get Free Quantity</label>
                            <input v-model="form.get_quantity" type="number" min="1" class="input-glass" placeholder="1" />
                        </div>
                    </div>

                    <!-- Dynamic Price Fields for Buy Items -->
                    <div v-if="form.buy_quantity > 0" class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                        <p class="text-sm font-medium text-white">"Buy" Item Prices (Optional)</p>
                        <p class="text-xs text-gray-500">Enter prices if they are fixed. Leave empty to enter them at redemption.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div v-for="i in form.buy_quantity" :key="`buy-${i}`">
                                <label class="text-[10px] text-gray-500 uppercase">Item {{ i }} Price</label>
                                <div class="relative mt-1">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                    <input 
                                        v-model="form.rules.buy_item_prices[i-1]" 
                                        type="number" 
                                        step="0.01" 
                                        class="input-glass pl-7 text-sm py-2" 
                                        placeholder="0.00" 
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Price Fields for Get Items -->
                    <div v-if="form.get_quantity > 0" class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                        <p class="text-sm font-medium text-white">"Free" Item Prices (Optional)</p>
                        <p class="text-xs text-gray-500">Enter prices if they are fixed. Leave empty to enter them at redemption.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div v-for="i in form.get_quantity" :key="`get-${i}`">
                                <label class="text-[10px] text-gray-500 uppercase">Free Item {{ i }} Price</label>
                                <div class="relative mt-1">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                    <input 
                                        v-model="form.rules.get_item_prices[i-1]" 
                                        type="number" 
                                        step="0.01" 
                                        class="input-glass pl-7 text-sm py-2" 
                                        placeholder="0.00" 
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="form.discount_type === 'buy_x_for_y'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Quantity</label>
                            <input v-model="form.buy_quantity" type="number" min="1" class="input-glass" placeholder="3" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">For Price ($)</label>
                            <input v-model="form.for_price" type="number" min="0" step="0.01" class="input-glass" placeholder="10.00" />
                        </div>
                    </div>

                    <!-- Dynamic Price Fields for Items -->
                    <div v-if="form.buy_quantity > 0" class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                        <p class="text-sm font-medium text-white">Item Normal Prices (Optional)</p>
                        <p class="text-xs text-gray-500">Enter what these items usually cost to track customer savings. Leave empty to enter at redemption.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div v-for="i in form.buy_quantity" :key="`buy-x-y-${i}`">
                                <label class="text-[10px] text-gray-500 uppercase">Item {{ i }} Normal Price</label>
                                <div class="relative mt-1">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                    <input 
                                        v-model="form.rules.buy_item_prices[i-1]" 
                                        type="number" 
                                        step="0.01" 
                                        class="input-glass pl-7 text-sm py-2" 
                                        placeholder="0.00" 
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="form.discount_type === 'punch_card'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Punches Required</label>
                        <input v-model="form.punches_required" type="number" min="2" max="50" class="input-glass" placeholder="10" />
                        <p class="text-gray-500 text-sm mt-1">Customer gets a free item after {{ form.punches_required || 10 }} purchases</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Free Item Value <span class="text-gray-500 text-xs">(for tracking savings)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                            <input 
                                v-model="form.reward_value" 
                                type="number" 
                                step="0.01" 
                                min="0" 
                                class="input-glass pl-10" 
                                placeholder="0.00" 
                            />
                        </div>
                        <p class="text-gray-500 text-sm mt-1">Enter the dollar value of the free item customers receive (e.g., $5.00 for a free coffee)</p>
                    </div>

                    <!-- Punch Icon Picker -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Punch Card Icon</label>
                        <p class="text-gray-500 text-sm mb-3">Choose an icon to display in each punch box</p>
                        <div class="grid grid-cols-8 sm:grid-cols-10 gap-2">
                            <button
                                v-for="(name, icon) in punchIconOptions"
                                :key="icon"
                                type="button"
                                @click="form.punch_icon = icon"
                                :class="[
                                    'w-10 h-10 rounded-lg flex items-center justify-center text-xl transition-all',
                                    form.punch_icon === icon
                                        ? 'bg-primary-500 ring-2 ring-primary-400 ring-offset-2 ring-offset-gray-900'
                                        : 'bg-white/10 hover:bg-white/20'
                                ]"
                                :title="name"
                            >
                                {{ icon }}
                            </button>
                        </div>
                        <p class="text-gray-500 text-xs mt-2">Selected: {{ form.punch_icon }} {{ punchIconOptions[form.punch_icon] }}</p>
                    </div>

                    <!-- Punch Card Preview -->
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <p class="text-sm text-gray-400 mb-3">Preview:</p>
                        <div class="flex flex-wrap gap-2 justify-center">
                            <div 
                                v-for="i in Math.min(form.punches_required || 10, 10)" 
                                :key="i"
                                :class="[
                                    'w-10 h-10 rounded-lg flex items-center justify-center text-lg border-2',
                                    i <= 3 
                                        ? 'bg-emerald-500/20 border-emerald-500/50' 
                                        : 'bg-white/5 border-white/20 border-dashed'
                                ]"
                            >
                                <span :class="i <= 3 ? '' : 'opacity-30'">{{ form.punch_icon }}</span>
                            </div>
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-lg bg-gradient-to-br from-yellow-500/20 to-orange-500/20 border-2 border-yellow-500/30">
                                🎁
                            </div>
                        </div>
                        <p class="text-center text-xs text-gray-500 mt-2">
                            {{ form.punches_required > 10 ? `Showing first 10 of ${form.punches_required} punches` : '' }}
                        </p>
                    </div>
                </div>

                <div v-if="form.discount_type === 'tiered'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-300">Discount Tiers</label>
                        <button @click="addTier" class="text-primary-400 text-sm hover:underline">+ Add Tier</button>
                    </div>
                    <div v-for="(tier, index) in form.tiers" :key="index" class="flex items-center space-x-4">
                        <div class="flex-1">
                            <label class="text-xs text-gray-500">Min Spend ($)</label>
                            <input v-model="tier.min_spend" type="number" min="0" class="input-glass text-sm" />
                        </div>
                        <div class="flex-1">
                            <label class="text-xs text-gray-500">Discount (%)</label>
                            <input v-model="tier.discount" type="number" min="0" max="100" class="input-glass text-sm" />
                        </div>
                        <button @click="removeTier(index)" class="text-red-400 hover:text-red-300 mt-5">✕</button>
                    </div>
                    <button v-if="!form.tiers.length" @click="addTier" class="w-full py-3 rounded-lg border border-dashed border-white/20 text-gray-400 hover:border-white/40">
                        + Add Your First Tier
                    </button>
                </div>

                <!-- Original Price -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Original Price
                            <span class="text-gray-500 text-xs ml-1">(Optional: Leave empty for variable prices)</span>
                        </label>
                        <input 
                            v-model="form.original_price" 
                            type="number" 
                            min="0" 
                            step="0.01" 
                            class="input-glass" 
                            placeholder="e.g. 29.99"
                        />
                        <p class="text-gray-500 text-xs mt-1">If empty, you must enter the price during redemption.</p>
                        <!-- Final Price Display for Percentage and Happy Hour Discounts -->
                        <div v-if="finalPrice && ['percentage', 'happy_hour'].includes(form.discount_type)" class="mt-2 p-3 rounded-lg bg-emerald-500/20 border border-emerald-500/30">
                            <p class="text-xs text-gray-400 mb-1">Final Price (shown to staff when redeeming)</p>
                            <p class="text-2xl font-bold text-emerald-400">${{ finalPrice }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Minimum Purchase</label>
                        <input v-model="form.minimum_purchase" type="number" min="0" step="0.01" class="input-glass" placeholder="No minimum" />
                    </div>
                </div>
            </div>

            <!-- Step 3: Rules -->
            <div v-show="step === 3" class="space-y-6">
                <h2 class="text-xl font-semibold text-white mb-4">Redemption Rules</h2>

                <!-- Redemption Limits -->
                <div class="p-4 rounded-xl bg-white/5">
                    <h3 class="text-white font-medium mb-4">Redemption Limits</h3>

                    <!-- Non–punch card limits -->
                    <div v-if="form.discount_type !== 'punch_card'" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm text-gray-400 mb-1 block">Max Total Redemptions</label>
                            <input v-model="form.rules.max_redemptions_total" type="number" min="0" class="input-glass text-sm" placeholder="Unlimited" />
                            <p class="text-xs text-gray-500 mt-1">Maximum redemptions across all customers.</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400 mb-1 block">Max Per Customer</label>
                            <input v-model="form.rules.max_redemptions_per_user" type="number" min="0" class="input-glass text-sm" placeholder="Unlimited" />
                            <p class="text-xs text-gray-500 mt-1">Maximum redemptions per customer. Set to 1 for one-time use.</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400 mb-1 block">Max Per Day</label>
                            <input v-model="form.rules.max_per_day" type="number" min="0" class="input-glass text-sm" placeholder="Unlimited" />
                            <p class="text-xs text-gray-500 mt-1">Total redemptions allowed per day across all customers.</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400 mb-1 block">Max Per Week</label>
                            <input v-model="form.rules.max_per_week" type="number" min="0" class="input-glass text-sm" placeholder="Unlimited" />
                            <p class="text-xs text-gray-500 mt-1">Total redemptions allowed per week across all customers.</p>
                        </div>
                    </div>

                    <!-- Punch card–specific limits -->
                    <div v-else class="space-y-3">
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label class="text-sm text-gray-400 mb-1 block">Max Cards Per Customer</label>
                                <input v-model="form.punch_card_max_cards_per_user" type="number" min="1" class="input-glass text-sm" placeholder="Unlimited" />
                                <p class="text-xs text-gray-500 mt-1">Total cards a customer can use (completed + one active). Set to 1 for a single prize per person.</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-400 mb-1 block">Total Cards Available</label>
                                <input v-model="form.punch_card_total_cards_limit" type="number" min="1" class="input-glass text-sm" placeholder="Unlimited" />
                                <p class="text-xs text-gray-500 mt-1">Caps how many customers can get this punch card overall.</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-400 mb-1 block">Max Punches Per Day (Punch Cards)</label>
                                <input v-model="form.punch_card_max_punches_per_day" type="number" min="1" class="input-glass text-sm" placeholder="Unlimited" />
                                <p class="text-xs text-gray-500 mt-1">Per-customer daily stamp limit (prize redemptions don’t count).</p>
                            </div>
                        </div>
                        <p class="text-xs text-amber-300">Set cards per customer for how many prizes they can earn; use daily punches to throttle how fast they fill a card.</p>
                    </div>
                </div>

                <!-- Portal Behavior -->
                <div class="p-4 rounded-xl bg-white/5 space-y-4">
                    <h3 class="text-white font-medium mb-2">Portal & Scan Behavior</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 p-3 rounded-xl bg-white/5 border border-white/10 cursor-pointer hover:bg-white/10 transition-colors">
                            <input
                                type="checkbox"
                                class="mt-1 rounded border-white/20 bg-white/10 text-primary-500"
                                v-model="form.rules.portal_multiple_scans"
                            />
                            <div>
                                <div class="text-white text-sm font-medium">Allow multiple active scans</div>
                                <div class="text-gray-500 text-xs">If checked, customers can scan multiple times to save multiple "Ready to Redeem" codes (up to your per-user limit). Vouchers are one-time use; customers must scan again for a new one.</div>
                            </div>
                        </label>

                    </div>
                </div>

                <!-- Valid Days -->
                <div class="p-4 rounded-xl bg-white/5">
                    <h3 class="text-white font-medium mb-4">Valid Days</h3>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="day in weekDays"
                            :key="day"
                            @click="toggleDay(day)"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-medium capitalize transition-all',
                                form.rules.valid_days.includes(day)
                                    ? 'bg-primary-500 text-white'
                                    : 'bg-white/10 text-gray-400 hover:bg-white/20'
                            ]"
                        >
                            {{ day.slice(0, 3) }}
                        </button>
                    </div>
                    <p class="text-gray-500 text-sm mt-2">Leave empty for all days</p>
                </div>

                <!-- Valid Hours (for Happy Hour) -->
                <div v-if="form.discount_type === 'happy_hour'" class="p-4 rounded-xl bg-white/5">
                    <h3 class="text-white font-medium mb-4">Valid Hours</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm text-gray-400 mb-1 block">Start Time</label>
                            <input type="time" v-model="form.rules.valid_hours.start" class="input-glass text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-gray-400 mb-1 block">End Time</label>
                            <input type="time" v-model="form.rules.valid_hours.end" class="input-glass text-sm" />
                        </div>
                    </div>
                </div>

                <!-- Date Range -->
                <div class="p-4 rounded-xl bg-white/5">
                    <h3 class="text-white font-medium mb-4">Validity Period</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm text-gray-400 mb-1 block">Start Date</label>
                            <input type="date" v-model="form.starts_at" class="input-glass text-sm" />
                        </div>
                        <div>
                            <label class="text-sm text-gray-400 mb-1 block">End Date</label>
                            <input type="date" v-model="form.ends_at" class="input-glass text-sm" />
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm mt-2">Leave empty for no expiration</p>
                </div>

                <!-- Terms -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Terms & Conditions</label>
                    <textarea
                        v-model="form.terms"
                        class="input-glass"
                        rows="3"
                        placeholder="Any additional terms..."
                    ></textarea>
                </div>
            </div>

            <!-- Step 4: Review -->
            <div v-show="step === 4" class="space-y-6">
                <h2 class="text-xl font-semibold text-white mb-4">Review Your Changes</h2>

                <!-- Preview Card -->
                <div class="p-6 rounded-xl bg-gradient-to-r from-primary-500/20 to-accent-500/20 border border-primary-500/30">
                    <p class="text-4xl font-bold text-white mb-2">{{ discountPreview }}</p>
                    <p class="text-xl text-white">{{ form.name || 'Untitled Promotion' }}</p>
                    <p class="text-gray-400 mt-2">{{ form.description || 'No description' }}</p>
                </div>

                <!-- Summary -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl bg-white/5">
                        <p class="text-gray-400 text-sm">Type</p>
                        <p class="text-white font-medium">{{ discountTypes[form.discount_type] || form.discount_type }}</p>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5">
                        <p class="text-gray-400 text-sm">Status</p>
                        <p :class="form.is_active ? 'text-green-400' : 'text-yellow-400'" class="font-medium">
                            {{ form.is_active ? 'Active' : 'Draft' }}
                        </p>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5">
                        <p class="text-gray-400 text-sm">Valid Days</p>
                        <p class="text-white font-medium">
                            {{ form.rules.valid_days.length ? form.rules.valid_days.map(d => d.slice(0, 3)).join(', ') : 'All days' }}
                        </p>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5">
                        <p class="text-gray-400 text-sm">Duration</p>
                        <p class="text-white font-medium">
                            {{ form.starts_at && form.ends_at ? `${form.starts_at} to ${form.ends_at}` : 'No expiration' }}
                        </p>
                    </div>
                </div>

                <!-- Active Toggle -->
                <div class="flex items-center justify-between p-4 rounded-xl bg-white/5">
                    <div>
                        <p class="text-white font-medium">Active Status</p>
                        <p class="text-gray-400 text-sm">Toggle to activate or deactivate this promotion</p>
                    </div>
                    <button
                        @click="form.is_active = !form.is_active"
                        :class="[
                            'w-12 h-6 rounded-full transition-colors',
                            form.is_active ? 'bg-green-500' : 'bg-gray-600'
                        ]"
                    >
                        <div
                            :class="[
                                'w-5 h-5 rounded-full bg-white transform transition-transform',
                                form.is_active ? 'translate-x-6' : 'translate-x-0.5'
                            ]"
                        ></div>
                    </button>
                </div>

                <!-- Stackable Deals Toggle -->
                <div :class="[
                    'flex items-center justify-between p-4 rounded-xl border',
                    canUseStackable 
                        ? 'bg-gradient-to-r from-emerald-500/10 to-teal-500/10 border-emerald-500/30'
                        : 'bg-gray-500/10 border-gray-500/30 opacity-60'
                ]">
                    <div>
                        <p class="text-white font-medium flex items-center gap-2">
                            📍 Add to Stackable Deals
                            <span v-if="!canUseStackable" class="text-xs text-red-400">(Growth+ Required)</span>
                        </p>
                        <p class="text-gray-400 text-sm">Show this deal in the local deals pool - customers see closest deals first when they scan</p>
                        <p class="text-xs text-gray-400 mt-2">
                            You still need to create a QR code attached to this promotion.
                        </p>
                    </div>
                    <button
                        @click="canUseStackable ? form.is_stackable = !form.is_stackable : showStackablePopup()"
                        :disabled="!canUseStackable"
                        :class="[
                            'w-12 h-6 rounded-full transition-colors',
                            form.is_stackable ? 'bg-emerald-500' : 'bg-gray-600',
                            !canUseStackable ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    >
                        <div
                            :class="[
                                'w-5 h-5 rounded-full bg-white transform transition-transform',
                                form.is_stackable ? 'translate-x-6' : 'translate-x-0.5'
                            ]"
                        ></div>
                    </button>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="flex justify-between mt-8 pt-6 border-t border-white/10">
                <button
                    v-if="step > 1"
                    @click="prevStep"
                    class="px-6 py-3 rounded-xl text-gray-400 hover:text-white hover:bg-white/10 transition-all"
                >
                    ← Back
                </button>
                <div v-else></div>

                <button
                    v-if="step < totalSteps"
                    @click="nextStep"
                    class="btn-primary"
                >
                    Continue →
                </button>
                <button
                    v-else
                    @click="submit"
                    :disabled="form.processing"
                    class="btn-accent disabled:opacity-50"
                >
                    <span v-if="form.processing">Saving...</span>
                    <span v-else>Save Changes</span>
                </button>
            </div>
        </div>
    </div>
</template>
