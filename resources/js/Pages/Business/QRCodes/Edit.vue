<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    qrCode: Object,
    promotions: Array,
    placementOptions: Object,
    moduleShapes: Object,
    finderShapes: Object,
    fonts: Object,
    businessLogo: String, // Business logo URL for promotion QR codes
});

const activeTab = ref('basic');
const previewLoading = ref(false);
const previewImage = ref(null);
const logoPreview = ref(props.qrCode?.design?.logo || null);
const logoInput = ref(null);
const merchReward = props.qrCode?.merch_referral_reward || null;
const merchReferralPromotions = computed(() => {
    return (props.promotions || []).filter((promo) => promo.discount_type !== 'punch_card');
});

// Initialize form with existing QR code data
const form = useForm({
    name: props.qrCode?.name || '',
    destination_url: props.qrCode?.destination_url || '',
    promotion_id: props.qrCode?.promotion_id || null,
    reward_type: merchReward?.reward_type || null,
    reward_value: merchReward?.reward_value || null,
    reward_item_value: merchReward?.reward_item_value || null,
    reward_description: merchReward?.reward_description || '',
    redemptions_required: merchReward?.redemptions_required || null,
    intended_use: props.qrCode?.intended_use || 'public',
    required_level: props.qrCode?.required_level || null,
    placement_location: props.qrCode?.placement_location || '',
    placement_description: props.qrCode?.placement_description || '',
    is_active: props.qrCode?.is_active ?? true,
    confirm_internal: false,
    design: {
        size: props.qrCode?.design?.size || 300,
        margin: props.qrCode?.design?.margin || 2,
        error_correction: props.qrCode?.design?.error_correction || 'M',
        module_shape: props.qrCode?.design?.module_shape || 'square',
        finder_shape: props.qrCode?.design?.finder_shape || 'square',
        // Background
        background_color: props.qrCode?.design?.background_color || '#FFFFFF',
        background_gradient: props.qrCode?.design?.background_gradient || null,
        // Module (QR pattern)
        module_color: props.qrCode?.design?.module_color || '#000000',
        module_gradient: props.qrCode?.design?.module_gradient || null,
        // Finder (corners)
        finder_color: props.qrCode?.design?.finder_color || null,
        // Logo
        logo: props.qrCode?.design?.logo || null,
        logo_size: props.qrCode?.design?.logo_size || 0.25,
        logo_background: props.qrCode?.design?.logo_background || false,
        logo_border_radius: props.qrCode?.design?.logo_border_radius || 0,
        // Text Top
        text_top: props.qrCode?.design?.text_top || '',
        text_top_font: props.qrCode?.design?.text_top_font || 'Inter',
        text_top_size: props.qrCode?.design?.text_top_size || 16,
        text_top_color: props.qrCode?.design?.text_top_color || '#000000',
        text_top_outline: props.qrCode?.design?.text_top_outline || false,
        text_top_outline_color: props.qrCode?.design?.text_top_outline_color || '#FFFFFF',
        text_top_outline_width: props.qrCode?.design?.text_top_outline_width || 2,
        // Text Bottom
        text_bottom: props.qrCode?.design?.text_bottom || '',
        text_bottom_font: props.qrCode?.design?.text_bottom_font || 'Inter',
        text_bottom_size: props.qrCode?.design?.text_bottom_size || 16,
        text_bottom_color: props.qrCode?.design?.text_bottom_color || '#000000',
        text_bottom_outline: props.qrCode?.design?.text_bottom_outline || false,
        text_bottom_outline_color: props.qrCode?.design?.text_bottom_outline_color || '#FFFFFF',
        text_bottom_outline_width: props.qrCode?.design?.text_bottom_outline_width || 2,
        // Border
        border: props.qrCode?.design?.border || null,
        // Effects
        glow: props.qrCode?.design?.glow || null,
        shadow: props.qrCode?.design?.shadow || null,
    },
});

// Design presets
const presets = [
    { name: 'Classic', colors: { bg: '#FFFFFF', module: '#000000' } },
    { name: 'Ocean', colors: { bg: '#E0F7FA', module: '#006064' } },
    { name: 'Sunset', colors: { bg: '#FFF3E0', module: '#E65100' } },
    { name: 'Forest', colors: { bg: '#E8F5E9', module: '#1B5E20' } },
    { name: 'Royal', colors: { bg: '#EDE7F6', module: '#4A148C' } },
    { name: 'Midnight', colors: { bg: '#263238', module: '#ECEFF1' } },
    { name: 'Neon', colors: { bg: '#000000', module: '#00FF00' } },
    { name: 'Rose', colors: { bg: '#FCE4EC', module: '#880E4F' } },
];

const applyPreset = (preset) => {
    form.design.background_color = preset.colors.bg;
    form.design.module_color = preset.colors.module;
    form.design.background_gradient = null;
    form.design.module_gradient = null;
    generatePreview();
};

// Logo upload handler
const handleLogoUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        alert('Logo must be less than 2MB');
        return;
    }

    const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        alert('Please upload a PNG, JPG, SVG, or WebP image');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        logoPreview.value = e.target.result;
        form.design.logo = e.target.result;
        form.design.error_correction = 'H';
        generatePreview();
    };
    reader.readAsDataURL(file);
};

const removeLogo = () => {
    logoPreview.value = null;
    form.design.logo = null;
    if (logoInput.value) {
        logoInput.value.value = '';
    }
    generatePreview();
};

// Gradient toggles
const toggleBackgroundGradient = () => {
    if (form.design.background_gradient) {
        form.design.background_gradient = null;
    } else {
        form.design.background_gradient = {
            type: 'linear',
            angle: 135,
            colors: [
                { color: '#FFFFFF', position: 0 },
                { color: '#F0F0F0', position: 100 }
            ]
        };
    }
};

const toggleModuleGradient = () => {
    if (form.design.module_gradient) {
        form.design.module_gradient = null;
    } else {
        form.design.module_gradient = {
            type: 'linear',
            angle: 45,
            colors: [
                { color: '#000000', position: 0 },
                { color: '#333333', position: 100 }
            ]
        };
    }
};

const addGradientColor = (gradient) => {
    if (gradient.colors.length < 5) {
        gradient.colors.push({ color: '#888888', position: 50 });
    }
};

const removeGradientColor = (gradient, index) => {
    if (gradient.colors.length > 2) {
        gradient.colors.splice(index, 1);
    }
};

// Effect toggles
const toggleGlow = () => {
    if (form.design.glow) {
        form.design.glow = null;
    } else {
        form.design.glow = { color: '#6366F1', intensity: 15, spread: 5 };
    }
};

const toggleShadow = () => {
    if (form.design.shadow) {
        form.design.shadow = null;
    } else {
        form.design.shadow = { color: '#000000', opacity: 0.3, blur: 10, offsetX: 5, offsetY: 5 };
    }
};

const previewBorderRadius = computed(() => {
    if (!form.design.border) return '0px';
    const radius = Number(form.design.border.radius || 0);
    return `${Math.max(0, radius)}px`;
});

const hasText = computed(() => {
    return !!(form.design.text_top || form.design.text_bottom);
});

// Generate preview with optimizations
let cancelSource = null;
let currentBlobUrl = null;
const generatePreview = async () => {
    // Cancel any in-flight request
    if (cancelSource) {
        cancelSource.cancel('New preview requested');
    }
    cancelSource = window.axios.CancelToken.source();
    
    previewLoading.value = true;
    
    try {
        const previewDesign = {
            ...form.design,
            preview_mode: true,
        };
        
        const response = await window.axios.post('/api/qr/preview', {
            data: form.destination_url || 'https://example.com',
            design: previewDesign,
        }, {
            responseType: 'blob',
            cancelToken: cancelSource.token,
        });

        const blob = response.data;
        // Revoke previous blob URL to free memory
        if (currentBlobUrl) {
            URL.revokeObjectURL(currentBlobUrl);
        }
        currentBlobUrl = URL.createObjectURL(blob);
        previewImage.value = currentBlobUrl;
    } catch (error) {
        if (window.axios.isCancel(error)) {
            return;
        }
        console.error('Preview generation failed:', error);
    } finally {
        previewLoading.value = false;
    }
};

// Watch for design changes
let debounceTimer = null;
watch(() => form.design, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(generatePreview, 300); // Fast preview updates
}, { deep: true });

// Submit form
const submit = () => {
    // Type is immutable (display-only) in the UI; don't submit it to avoid hidden validation failures.
    form.put(route('business.qr-codes.update', props.qrCode.id));
};

// Tabs
const tabs = [
    { id: 'basic', name: 'Basic Info', icon: '📝' },
    { id: 'colors', name: 'Colors', icon: '🎨' },
    { id: 'shape', name: 'Shape', icon: '⬡' },
    { id: 'logo', name: 'Logo', icon: '🖼️' },
    { id: 'text', name: 'Text', icon: '✏️' },
    { id: 'effects', name: 'Effects', icon: '✨' },
];

onMounted(() => {
    generatePreview();
});
</script>

<template>
    <Head :title="`Edit QR Code - ${qrCode?.name}`" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <Link href="/business/qr-codes" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                ← Back to QR Codes
            </Link>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white">Edit QR Code</h1>
                    <p class="text-gray-400 mt-1">Update your QR code settings and design</p>
                </div>
                <div class="flex items-center gap-3">
                    <span :class="[
                        'px-3 py-1 rounded-full text-sm font-medium',
                        form.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
                    ]">
                        {{ form.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Dynamic QR URL Change Notice -->
        <div v-if="qrCode?.type === 'dynamic'" class="mb-6 p-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
            <div class="flex items-start gap-3">
                <span class="text-2xl">🔗</span>
                <div>
                    <h4 class="text-blue-400 font-medium">Dynamic QR Code</h4>
                    <p class="text-gray-400 text-sm">You can change where this QR code points to at any time. The same printed QR code will redirect to your new URL!</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Design Panel -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Tabs -->
                <div class="glass-card p-2">
                    <div class="flex space-x-1 overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap flex items-center gap-2',
                                activeTab === tab.id
                                    ? 'bg-primary-500 text-white'
                                    : 'text-gray-400 hover:text-white hover:bg-white/10'
                            ]"
                        >
                            <span>{{ tab.icon }}</span>
                            {{ tab.name }}
                        </button>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="glass-card p-6">
                    <!-- Basic Info Tab -->
                    <div v-show="activeTab === 'basic'" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">QR Code Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="input-glass"
                                placeholder="e.g., Front Counter 10% Off"
                            />
                            <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">QR Code Type</label>
                            <div class="px-4 py-3 rounded-lg bg-white/5 border border-white/10">
                                <span class="text-white capitalize">{{ qrCode?.type }}</span>
                                <span class="text-gray-500 text-sm ml-2">(cannot be changed)</span>
                            </div>
                        </div>

                        <!-- Dynamic: URL (EDITABLE!) -->
                        <div v-if="['static', 'dynamic'].includes(qrCode?.type)" class="p-4 rounded-xl bg-primary-500/10 border border-primary-500/30">
                            <label class="block text-sm font-medium text-primary-400 mb-2">
                                🔗 Destination URL
                                <span v-if="qrCode?.type === 'dynamic'" class="text-gray-400 font-normal ml-2">(Change anytime!)</span>
                            </label>
                            <input
                                v-model="form.destination_url"
                                type="url"
                                class="input-glass"
                                placeholder="https://your-website.com"
                            />
                            <p class="text-gray-500 text-xs mt-2">
                                <span v-if="qrCode?.type === 'dynamic'">
                                    ✨ This is a dynamic QR code - you can change this URL and all existing printed QR codes will redirect to the new destination.
                                </span>
                                <span v-else>
                                    ⚠️ Static QR codes cannot be changed after creation. Consider using Dynamic QR codes for flexibility.
                                </span>
                            </p>
                        </div>

                        <!-- Promotion: Select -->
                        <div v-if="qrCode?.type === 'promotion'">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Linked Promotion</label>
                            <select v-model="form.promotion_id" class="input-glass">
                                <option value="" class="bg-gray-800 text-white">Select a promotion...</option>
                                <option 
                                    v-for="promo in promotions" 
                                    :key="promo.id" 
                                    :value="promo.id"
                                    class="bg-gray-800 text-white"
                                >
                                    {{ promo.name }} ({{ promo.discount_type }})
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 mt-2">
                                This QR code will open the promotion page and generate a customer code (UP-XXXX-XXXX) in the user portal.
                            </p>

                            <div class="mt-4 p-4 rounded-xl bg-white/5 border border-white/10">
                                <h4 class="text-white font-semibold mb-2">Purpose</h4>
                                <p class="text-xs text-gray-400 mb-3">
                                    Mark prize/config QR codes as internal so customers don’t accidentally scan and redeem them early.
                                </p>

                                <div class="space-y-2">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="radio" value="public" v-model="form.intended_use" class="mt-1" />
                                        <div>
                                            <div class="text-sm text-white font-medium">Customer-facing (print / display)</div>
                                            <div class="text-xs text-gray-500">Customers should scan this in the real world.</div>
                                        </div>
                                    </label>

                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="radio" value="leaderboard_prize" v-model="form.intended_use" class="mt-1" />
                                        <div>
                                            <div class="text-sm text-white font-medium">Leaderboard Prize (internal — do not print)</div>
                                            <div class="text-xs text-gray-500">Use this only in Leaderboard settings as the prize QR.</div>
                                        </div>
                                    </label>
                                </div>

                                <div v-if="form.intended_use === 'leaderboard_prize'" class="mt-3 p-3 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                    <p class="text-amber-300 text-xs font-medium">
                                        ⚠️ This QR is internal. Customers scanning it will be blocked with a clear message.
                                    </p>
                                    <label class="mt-3 flex items-center gap-2 text-xs text-gray-200">
                                        <input type="checkbox" v-model="form.confirm_internal" />
                                        I understand. This is an internal leaderboard prize QR and should not be printed.
                                    </label>
                                    <p v-if="form.errors.confirm_internal" class="mt-2 text-xs text-red-400">{{ form.errors.confirm_internal }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Merch Referral: Gateway promo + Ambassador reward -->
                        <div v-if="qrCode?.type === 'merch_referral'" class="space-y-4">
                            <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                                <h4 class="text-white font-semibold mb-2">👕 Merch Referral QR</h4>
                                <p class="text-xs text-gray-400">
                                    Gateway promo is one-time per customer and never expires in merch referrals.
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Gateway Promotion</label>
                                <select v-model="form.promotion_id" class="input-glass">
                                    <option value="" class="bg-gray-800 text-white">Select a promotion...</option>
                                    <option
                                        v-for="promo in merchReferralPromotions"
                                        :key="promo.id"
                                        :value="promo.id"
                                        class="bg-gray-800 text-white"
                                    >
                                        {{ promo.name }} ({{ promo.discount_type }})
                                    </option>
                                </select>
                                <p v-if="form.errors.promotion_id" class="mt-2 text-sm text-red-400">{{ form.errors.promotion_id }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Reward Type</label>
                                    <select v-model="form.reward_type" class="input-glass">
                                        <option value="" class="bg-gray-800 text-white">Select reward type...</option>
                                        <option value="percent" class="bg-gray-800 text-white">% Off</option>
                                        <option value="amount" class="bg-gray-800 text-white">$ Off</option>
                                        <option value="free_item" class="bg-gray-800 text-white">Free Item</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Reward Amount</label>
                                    <input
                                        v-model="form.reward_value"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="input-glass"
                                        :placeholder="form.reward_type === 'percent' ? 'e.g. 20' : (form.reward_type === 'amount' ? 'e.g. 10' : 'Optional')"
                                    />
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ form.reward_type === 'percent' ? 'Percent off (required).' : (form.reward_type === 'amount' ? 'Dollar amount off (required).' : 'Optional for free items.') }}
                                    </p>
                                    <p v-if="form.errors.reward_value" class="mt-2 text-sm text-red-400">{{ form.errors.reward_value }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Reward Description</label>
                                <input
                                    v-model="form.reward_description"
                                    type="text"
                                    class="input-glass"
                                    placeholder="Example: 20% off next visit"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Item Value (optional)</label>
                                <input
                                    v-model="form.reward_item_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="input-glass"
                                    placeholder="Example: 20.00"
                                />
                                <p class="text-xs text-gray-500 mt-1">
                                    Used to pre-fill the redemption calculator. Staff can change it at redemption time.
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Redemptions Required</label>
                                <input
                                    v-model="form.redemptions_required"
                                    type="number"
                                    min="1"
                                    step="1"
                                    class="input-glass"
                                    placeholder="e.g. 10"
                                />
                                <p v-if="form.errors.redemptions_required" class="mt-2 text-sm text-red-400">{{ form.errors.redemptions_required }}</p>
                                <p class="text-xs text-gray-500 mt-2">
                                    This reward repeats every N qualified redemptions. Don’t delete; it powers ambassador rewards.
                                </p>
                            </div>
                        </div>

                        <!-- Level Exclusive: Promotion + Level Requirement -->
                        <div v-if="qrCode?.type === 'level_exclusive'" class="space-y-4">
                            <div class="p-4 rounded-xl bg-purple-500/10 border border-purple-500/30">
                                <h4 class="text-purple-400 font-medium mb-2">⭐ Level Exclusive Promotion</h4>
                                <p class="text-gray-400 text-sm">
                                    Level exclusive promotions use the user's app-wide XP/level (from scanning, playing games, redeeming across all businesses), not XP specific to your store.
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Linked Promotion</label>
                                <select v-model="form.promotion_id" class="input-glass">
                                    <option value="" class="bg-gray-800 text-white">Select a promotion...</option>
                                    <option 
                                        v-for="promo in promotions" 
                                        :key="promo.id" 
                                        :value="promo.id"
                                        class="bg-gray-800 text-white"
                                    >
                                        {{ promo.name }} ({{ promo.discount_type }})
                                    </option>
                                </select>
                                <p v-if="form.errors.promotion_id" class="mt-2 text-sm text-red-400">{{ form.errors.promotion_id }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Required Level</label>
                                <select v-model="form.required_level" class="input-glass">
                                    <option :value="null" class="bg-gray-800 text-white">Select level...</option>
                                    <option
                                        v-for="level in Array.from({ length: 50 }, (_, i) => i + 1)"
                                        :key="level"
                                        :value="level"
                                        class="bg-gray-800 text-white"
                                    >
                                        Level {{ level }}
                                    </option>
                                </select>
                                <p v-if="form.errors.required_level" class="mt-2 text-sm text-red-400">{{ form.errors.required_level }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Users must reach this level to access this promotion
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Placement Location</label>
                                <select v-model="form.placement_location" class="input-glass">
                                    <option value="" class="bg-gray-800 text-white">Select location...</option>
                                    <option 
                                        v-for="(label, value) in placementOptions" 
                                        :key="value" 
                                        :value="value"
                                        class="bg-gray-800 text-white"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                                <input
                                    v-model="form.placement_description"
                                    type="text"
                                    class="input-glass"
                                    placeholder="e.g., Near register"
                                />
                            </div>
                        </div>

                        <!-- Active Toggle -->
                        <div class="flex items-center justify-between p-4 rounded-xl bg-white/5">
                            <div>
                                <span class="text-white font-medium">QR Code Active</span>
                                <p class="text-gray-400 text-sm">When disabled, scans will show an inactive message</p>
                            </div>
                            <button
                                @click="form.is_active = !form.is_active"
                                :class="[
                                    'w-12 h-6 rounded-full transition-colors relative',
                                    form.is_active ? 'bg-green-500' : 'bg-gray-600'
                                ]"
                            >
                                <div :class="['w-5 h-5 rounded-full bg-white absolute top-0.5 transition-transform', form.is_active ? 'translate-x-6' : 'translate-x-0.5']"></div>
                            </button>
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-4 p-4 rounded-xl bg-white/5">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-white">{{ qrCode?.total_scans || 0 }}</p>
                                <p class="text-gray-500 text-sm">Total Scans</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-white">{{ qrCode?.unique_scans || 0 }}</p>
                                <p class="text-gray-500 text-sm">Unique Scans</p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-white">{{ qrCode?.last_scanned_at ? new Date(qrCode.last_scanned_at).toLocaleDateString() : 'Never' }}</p>
                                <p class="text-gray-500 text-sm">Last Scanned</p>
                            </div>
                        </div>
                    </div>

                    <!-- Colors Tab -->
                    <div v-show="activeTab === 'colors'" class="space-y-6">
                        <!-- Presets -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-3">Quick Presets</label>
                            <div class="grid grid-cols-8 gap-2">
                                <button
                                    v-for="preset in presets"
                                    :key="preset.name"
                                    @click="applyPreset(preset)"
                                    class="group relative aspect-square rounded-xl border border-white/20 hover:border-primary-500 transition-all overflow-hidden"
                                    :style="{ background: `linear-gradient(135deg, ${preset.colors.bg}, ${preset.colors.module})` }"
                                    :title="preset.name"
                                >
                                </button>
                            </div>
                        </div>

                        <!-- Background Color/Gradient -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-300">Background</label>
                                <button
                                    @click="toggleBackgroundGradient"
                                    class="text-xs px-3 py-1 rounded-full transition-colors"
                                    :class="form.design.background_gradient ? 'bg-primary-500 text-white' : 'bg-white/10 text-gray-400 hover:bg-white/20'"
                                >
                                    {{ form.design.background_gradient ? '🌈 Gradient' : 'Solid' }}
                                </button>
                            </div>
                            
                            <div v-if="!form.design.background_gradient" class="flex items-center space-x-3">
                                <input type="color" v-model="form.design.background_color" class="w-12 h-12 rounded-lg border border-white/20 cursor-pointer" />
                                <input type="text" v-model="form.design.background_color" class="input-glass flex-1" placeholder="#FFFFFF" />
                            </div>
                            
                            <div v-else class="space-y-4">
                                <div class="flex items-center gap-4">
                                    <select v-model="form.design.background_gradient.type" class="input-glass text-sm flex-1">
                                        <option value="linear" class="bg-gray-800 text-white">Linear</option>
                                        <option value="radial" class="bg-gray-800 text-white">Radial</option>
                                    </select>
                                    <div v-if="form.design.background_gradient.type === 'linear'" class="flex items-center gap-2">
                                        <span class="text-gray-400 text-sm">Angle:</span>
                                        <input type="number" v-model="form.design.background_gradient.angle" min="0" max="360" class="input-glass w-20 text-sm" />
                                        <span class="text-gray-400 text-sm">°</span>
                                    </div>
                                </div>
                                <div v-for="(stop, index) in form.design.background_gradient.colors" :key="index" class="flex items-center gap-3">
                                    <input type="color" v-model="stop.color" class="w-10 h-10 rounded-lg border border-white/20 cursor-pointer" />
                                    <input type="range" v-model="stop.position" min="0" max="100" class="flex-1" />
                                    <span class="text-gray-400 text-sm w-12">{{ stop.position }}%</span>
                                    <button v-if="form.design.background_gradient.colors.length > 2" @click="removeGradientColor(form.design.background_gradient, index)" class="text-red-400 hover:text-red-300">✕</button>
                                </div>
                                <button @click="addGradientColor(form.design.background_gradient)" class="text-primary-400 text-sm hover:underline">+ Add Color Stop</button>
                            </div>
                        </div>

                        <!-- Module Color/Gradient -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-300">Module Color (QR Pattern)</label>
                                <button
                                    @click="toggleModuleGradient"
                                    class="text-xs px-3 py-1 rounded-full transition-colors"
                                    :class="form.design.module_gradient ? 'bg-primary-500 text-white' : 'bg-white/10 text-gray-400 hover:bg-white/20'"
                                >
                                    {{ form.design.module_gradient ? '🌈 Gradient' : 'Solid' }}
                                </button>
                            </div>
                            
                            <div v-if="!form.design.module_gradient" class="flex items-center space-x-3">
                                <input type="color" v-model="form.design.module_color" class="w-12 h-12 rounded-lg border border-white/20 cursor-pointer" />
                                <input type="text" v-model="form.design.module_color" class="input-glass flex-1" placeholder="#000000" />
                            </div>
                            
                            <div v-else class="space-y-4">
                                <div class="flex items-center gap-4">
                                    <select v-model="form.design.module_gradient.type" class="input-glass text-sm flex-1">
                                        <option value="linear" class="bg-gray-800 text-white">Linear</option>
                                        <option value="radial" class="bg-gray-800 text-white">Radial</option>
                                    </select>
                                    <div v-if="form.design.module_gradient.type === 'linear'" class="flex items-center gap-2">
                                        <span class="text-gray-400 text-sm">Angle:</span>
                                        <input type="number" v-model="form.design.module_gradient.angle" min="0" max="360" class="input-glass w-20 text-sm" />
                                        <span class="text-gray-400 text-sm">°</span>
                                    </div>
                                </div>
                                <div v-for="(stop, index) in form.design.module_gradient.colors" :key="index" class="flex items-center gap-3">
                                    <input type="color" v-model="stop.color" class="w-10 h-10 rounded-lg border border-white/20 cursor-pointer" />
                                    <input type="range" v-model="stop.position" min="0" max="100" class="flex-1" />
                                    <span class="text-gray-400 text-sm w-12">{{ stop.position }}%</span>
                                    <button v-if="form.design.module_gradient.colors.length > 2" @click="removeGradientColor(form.design.module_gradient, index)" class="text-red-400 hover:text-red-300">✕</button>
                                </div>
                                <button @click="addGradientColor(form.design.module_gradient)" class="text-primary-400 text-sm hover:underline">+ Add Color Stop</button>
                            </div>
                        </div>

                        <!-- Finder Color -->
                        <div class="p-4 rounded-xl bg-white/5">
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                Finder Color (Corners)
                                <span class="text-gray-500 text-xs ml-2">Leave empty to match module</span>
                            </label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    :value="form.design.finder_color || form.design.module_color"
                                    @input="form.design.finder_color = $event.target.value"
                                    class="w-12 h-12 rounded-lg border border-white/20 cursor-pointer"
                                />
                                <input type="text" v-model="form.design.finder_color" class="input-glass flex-1" placeholder="Same as module" />
                                <button v-if="form.design.finder_color" @click="form.design.finder_color = null" class="p-2 text-gray-400 hover:text-white">✕</button>
                            </div>
                        </div>
                    </div>

                    <!-- Shape Tab -->
                    <div v-show="activeTab === 'shape'" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-3">Module Shape</label>
                            <div class="grid grid-cols-3 gap-3">
                                <button
                                    v-for="(label, shape) in moduleShapes"
                                    :key="shape"
                                    @click="form.design.module_shape = shape"
                                    :class="[
                                        'p-4 rounded-xl border text-center transition-all',
                                        form.design.module_shape === shape
                                            ? 'border-primary-500 bg-primary-500/20 text-white'
                                            : 'border-white/20 text-gray-400 hover:border-white/40'
                                    ]"
                                >
                                    <div class="text-2xl mb-1">
                                        {{ shape === 'square' ? '■' : shape === 'rounded' ? '▢' : shape === 'dots' ? '●' : shape === 'diamond' ? '◆' : shape === 'star' ? '★' : '♥' }}
                                    </div>
                                    <div class="text-sm">{{ label }}</div>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-3">Finder Shape (Corners)</label>
                            <div class="grid grid-cols-4 gap-3">
                                <button
                                    v-for="(label, shape) in finderShapes"
                                    :key="shape"
                                    @click="form.design.finder_shape = shape"
                                    :class="[
                                        'p-4 rounded-xl border text-center transition-all',
                                        form.design.finder_shape === shape
                                            ? 'border-primary-500 bg-primary-500/20 text-white'
                                            : 'border-white/20 text-gray-400 hover:border-white/40'
                                    ]"
                                >
                                    <div class="text-sm">{{ label }}</div>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Size (px)</label>
                                <input type="range" v-model.number="form.design.size" min="200" max="1000" step="50" class="w-full" />
                                <div class="text-center text-gray-400 text-sm mt-1">{{ form.design.size }}px</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Margin</label>
                                <input type="range" v-model.number="form.design.margin" min="0" max="10" class="w-full" />
                                <div class="text-center text-gray-400 text-sm mt-1">{{ form.design.margin }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Error Correction</label>
                            <div class="grid grid-cols-4 gap-2">
                                <button
                                    v-for="level in ['L', 'M', 'Q', 'H']"
                                    :key="level"
                                    @click="form.design.error_correction = level"
                                    :class="[
                                        'py-2 rounded-lg border text-center transition-all',
                                        form.design.error_correction === level
                                            ? 'border-primary-500 bg-primary-500/20 text-white'
                                            : 'border-white/20 text-gray-400 hover:border-white/40'
                                    ]"
                                >
                                    {{ level }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Logo Tab -->
                    <div v-show="activeTab === 'logo'" class="space-y-6">
                        <div 
                            class="border-2 border-dashed rounded-xl p-8 text-center transition-colors"
                            :class="logoPreview ? 'border-primary-500/50 bg-primary-500/10' : 'border-white/20 hover:border-white/40'"
                        >
                            <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" class="hidden" id="logo-upload" @change="handleLogoUpload" />
                            
                            <div v-if="logoPreview" class="space-y-4">
                                <div class="w-24 h-24 mx-auto rounded-xl overflow-hidden bg-white">
                                    <img :src="logoPreview" class="w-full h-full object-contain" alt="Logo preview" />
                                </div>
                                <div class="flex justify-center gap-3">
                                    <label for="logo-upload" class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 cursor-pointer">Change</label>
                                    <button @click="removeLogo" class="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30">Remove</button>
                                </div>
                            </div>
                            
                            <label v-else for="logo-upload" class="cursor-pointer block">
                                <div class="w-16 h-16 rounded-full bg-white/10 mx-auto flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p class="text-white font-medium">Upload Logo</p>
                                <p class="text-gray-400 text-sm mt-1">PNG, JPG, SVG, WebP up to 2MB</p>
                            </label>
                        </div>

                        <div v-if="logoPreview" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Logo Size</label>
                                <input type="range" v-model="form.design.logo_size" min="0.1" max="0.4" step="0.05" class="w-full" />
                                <div class="text-center text-gray-400 text-sm mt-1">{{ Math.round(form.design.logo_size * 100) }}%</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Corner Radius</label>
                                <input type="range" v-model="form.design.logo_border_radius" min="0" max="50" step="5" class="w-full" />
                                <div class="text-center text-gray-400 text-sm mt-1">{{ form.design.logo_border_radius }}%</div>
                            </div>
                            <label class="flex items-center p-4 rounded-xl bg-white/5 cursor-pointer">
                                <input type="checkbox" v-model="form.design.logo_background" class="rounded border-white/20 bg-white/10 text-primary-500" />
                                <span class="ml-3 text-gray-300">Add white background</span>
                            </label>
                        </div>
                    </div>

                    <!-- Text Tab -->
                    <div v-show="activeTab === 'text'" class="space-y-6">
                        <!-- Top Text -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <h4 class="text-white font-medium">Text Above QR</h4>
                            <input type="text" v-model="form.design.text_top" class="input-glass" placeholder="e.g., SCAN ME" />
                            <div v-if="form.design.text_top" class="space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Font</label>
                                        <select v-model="form.design.text_top_font" class="input-glass text-sm">
                                            <option v-for="(label, font) in fonts" :key="font" :value="font" class="bg-gray-800 text-white">{{ label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Size</label>
                                        <input type="number" v-model="form.design.text_top_size" min="10" max="48" class="input-glass text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Color</label>
                                        <input type="color" v-model="form.design.text_top_color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-white/5">
                                    <span class="text-gray-300 text-sm">Text Outline</span>
                                    <button @click="form.design.text_top_outline = !form.design.text_top_outline" :class="['w-10 h-5 rounded-full transition-colors relative', form.design.text_top_outline ? 'bg-primary-500' : 'bg-gray-600']">
                                        <div :class="['w-4 h-4 rounded-full bg-white absolute top-0.5 transition-transform', form.design.text_top_outline ? 'translate-x-5' : 'translate-x-0.5']"></div>
                                    </button>
                                </div>
                                <div v-if="form.design.text_top_outline" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Outline Color</label>
                                        <input type="color" v-model="form.design.text_top_outline_color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Width</label>
                                        <input type="number" v-model="form.design.text_top_outline_width" min="1" max="10" class="input-glass text-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Text -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <h4 class="text-white font-medium">Text Below QR</h4>
                            <input type="text" v-model="form.design.text_bottom" class="input-glass" placeholder="e.g., Get 20% Off!" />
                            <div v-if="form.design.text_bottom" class="space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Font</label>
                                        <select v-model="form.design.text_bottom_font" class="input-glass text-sm">
                                            <option v-for="(label, font) in fonts" :key="font" :value="font" class="bg-gray-800 text-white">{{ label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Size</label>
                                        <input type="number" v-model="form.design.text_bottom_size" min="10" max="48" class="input-glass text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Color</label>
                                        <input type="color" v-model="form.design.text_bottom_color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-white/5">
                                    <span class="text-gray-300 text-sm">Text Outline</span>
                                    <button @click="form.design.text_bottom_outline = !form.design.text_bottom_outline" :class="['w-10 h-5 rounded-full transition-colors relative', form.design.text_bottom_outline ? 'bg-primary-500' : 'bg-gray-600']">
                                        <div :class="['w-4 h-4 rounded-full bg-white absolute top-0.5 transition-transform', form.design.text_bottom_outline ? 'translate-x-5' : 'translate-x-0.5']"></div>
                                    </button>
                                </div>
                                <div v-if="form.design.text_bottom_outline" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Outline Color</label>
                                        <input type="color" v-model="form.design.text_bottom_outline_color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Width</label>
                                        <input type="number" v-model="form.design.text_bottom_outline_width" min="1" max="10" class="input-glass text-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Effects Tab -->
                    <div v-show="activeTab === 'effects'" class="space-y-6">
                        <!-- Border -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-white font-medium">Border</span>
                                    <p class="text-gray-400 text-sm">Add a border around the QR</p>
                                </div>
                                <button @click="form.design.border = form.design.border ? null : { width: 2, color: '#000000', radius: 0 }" :class="['w-12 h-6 rounded-full transition-colors relative', form.design.border ? 'bg-primary-500' : 'bg-gray-600']">
                                    <div :class="['w-5 h-5 rounded-full bg-white absolute top-0.5 transition-transform', form.design.border ? 'translate-x-6' : 'translate-x-0.5']"></div>
                                </button>
                            </div>
                            <div v-if="form.design.border" class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Width</label>
                                    <input type="number" v-model="form.design.border.width" min="1" max="20" class="input-glass text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Color</label>
                                    <input type="color" v-model="form.design.border.color" class="w-full h-10 rounded-lg border border-white/20" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Radius</label>
                                    <input type="number" v-model="form.design.border.radius" min="0" max="50" class="input-glass text-sm" />
                                </div>
                            </div>
                        </div>

                        <!-- Glow -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-white font-medium">Glow Effect</span>
                                    <p class="text-gray-400 text-sm">Add colorful glow</p>
                                </div>
                                <button @click="toggleGlow" :class="['w-12 h-6 rounded-full transition-colors relative', form.design.glow ? 'bg-primary-500' : 'bg-gray-600']">
                                    <div :class="['w-5 h-5 rounded-full bg-white absolute top-0.5 transition-transform', form.design.glow ? 'translate-x-6' : 'translate-x-0.5']"></div>
                                </button>
                            </div>
                            <div v-if="form.design.glow" class="space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Color</label>
                                        <input type="color" v-model="form.design.glow.color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Intensity</label>
                                        <input type="range" v-model="form.design.glow.intensity" min="5" max="50" class="w-full mt-3" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Spread</label>
                                        <input type="range" v-model="form.design.glow.spread" min="0" max="20" class="w-full mt-3" />
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 rounded-lg bg-white/5">
                                    <div class="w-10 h-10 rounded-md flex-shrink-0 border border-white/10" :style="{ backgroundColor: form.design.glow.color, boxShadow: `0 0 ${form.design.glow.intensity}px ${form.design.glow.spread}px ${form.design.glow.color}` }"></div>
                                    <div class="text-xs text-gray-400">
                                        <span class="text-gray-300 font-medium">Intensity {{ form.design.glow.intensity }}</span> · Spread {{ form.design.glow.spread }}
                                        <br>See live preview for exact appearance →
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shadow -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-white font-medium">Drop Shadow</span>
                                    <p class="text-gray-400 text-sm">Add depth</p>
                                </div>
                                <button @click="toggleShadow" :class="['w-12 h-6 rounded-full transition-colors relative', form.design.shadow ? 'bg-primary-500' : 'bg-gray-600']">
                                    <div :class="['w-5 h-5 rounded-full bg-white absolute top-0.5 transition-transform', form.design.shadow ? 'translate-x-6' : 'translate-x-0.5']"></div>
                                </button>
                            </div>
                            <div v-if="form.design.shadow" class="space-y-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Color</label>
                                        <input type="color" v-model="form.design.shadow.color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Opacity</label>
                                        <input type="range" v-model="form.design.shadow.opacity" min="0.1" max="1" step="0.1" class="w-full mt-3" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Blur</label>
                                        <input type="number" v-model="form.design.shadow.blur" min="0" max="50" class="input-glass text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">X</label>
                                        <input type="number" v-model="form.design.shadow.offsetX" min="-30" max="30" class="input-glass text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Y</label>
                                        <input type="number" v-model="form.design.shadow.offsetY" min="-30" max="30" class="input-glass text-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Panel -->
            <div class="space-y-6">
                <div class="glass-card p-6 sticky top-24">
                    <h3 class="text-lg font-semibold text-white mb-4">Live Preview</h3>
                    
                    <div 
                        class="rounded-xl flex items-center justify-center relative overflow-hidden"
                        :class="hasText ? 'min-h-[200px]' : 'aspect-square'"
                        :style="{
                            backgroundColor: '#1a1a2e',
                            borderRadius: previewBorderRadius
                        }"
                    >
                        <img v-if="previewImage" :src="previewImage" class="max-w-full max-h-full qr-preview-img" :class="{ 'opacity-50': previewLoading }" alt="QR Preview" />
                        <div v-else-if="!previewLoading" class="text-gray-400 text-center p-8">
                            <p>Click "Refresh Preview" to generate</p>
                        </div>
                        <!-- Loading overlay on top of existing preview -->
                        <div v-if="previewLoading" class="absolute inset-0 flex items-center justify-center">
                            <div class="text-gray-400 bg-black/30 rounded-full p-3">
                                <svg class="animate-spin w-8 h-8" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <button @click="generatePreview" class="w-full py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                            🔄 Refresh Preview
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <div class="glass-card p-6">
                    <button
                        @click="submit"
                        :disabled="form.processing || !form.name"
                        class="w-full btn-accent disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>💾 Save Changes</span>
                    </button>
                    
                    <div class="flex gap-2 mt-3">
                        <a
                            :href="route('business.qr-codes.download', [qrCode.id, 'png'])"
                            class="flex-1 py-2 text-center rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 transition-colors text-sm"
                        >
                            📥 Download PNG
                        </a>
                        <a
                            :href="route('business.qr-codes.download', [qrCode.id, 'svg'])"
                            class="flex-1 py-2 text-center rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 transition-colors text-sm"
                        >
                            📥 Download SVG
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}

.input-glass {
    @apply w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors;
}

.btn-accent {
    @apply px-6 py-3 bg-gradient-to-r from-primary-500 to-accent-500 text-white font-semibold rounded-xl hover:opacity-90 transition-opacity;
}

/* High-quality QR preview rendering - the server renders at 2x resolution
   so the browser downscales for crisp, sharp text and clean module edges */
.qr-preview-img {
    image-rendering: -webkit-optimize-contrast;
    image-rendering: high-quality;
    -ms-interpolation-mode: bicubic;
}
</style>
