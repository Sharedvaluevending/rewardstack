<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    promotions: Array,
    availableGames: Array,
    leaderboards: Array,
    partners: Array, // Accepted partners for cross-promotion
    acceptedCrossPromos: Array, // Accepted cross-promos for selection
    placementOptions: Object,
    moduleShapes: Object,
    finderShapes: Object,
    fonts: Object,
    businessLogo: String, // Business logo URL for promotion QR codes
    subscriptionTier: String,
});

const page = usePage();
const activeTab = ref('basic');
const previewLoading = ref(false);
const previewImage = ref(null);
const logoPreview = ref(null);
const logoInput = ref(null);
const showSuccessModal = ref(false);

// Track thumbnails that failed to load so we can show a fallback
const erroredThumbs = reactive({});
const onThumbError = (gameId, e) => {
    if (e && e.target) e.target.style.display = 'none';
    erroredThumbs[gameId] = true;
};

// Check if stackable features are available
const canUseStackable = computed(() => {
    const tier = props.subscriptionTier || 'starter';
    return ['growth', 'pro', 'enterprise'].includes(tier.toLowerCase());
});

const canUseMerchReferral = computed(() => {
    const tier = props.subscriptionTier || 'starter';
    return ['growth', 'pro', 'enterprise'].includes(tier.toLowerCase());
});

const showStackablePopup = () => {
    alert('Growth or higher subscription needed to unlock Stackable QR Codes');
};

const showMerchReferralPopup = () => {
    alert('Growth or higher subscription needed for Merch Referral QR Codes');
};

const merchReferralPromotions = computed(() => {
    return (props.promotions || []).filter((promo) => promo.discount_type !== 'punch_card');
});


const form = useForm({
    name: '',
    type: 'dynamic',
    destination_url: '',
    intended_use: 'public',
    promotion_id: null,
    reward_type: null,
    reward_value: null,
    reward_item_value: null,
    reward_description: '',
    redemptions_required: null,
    add_tag: false,
    add_tag_quantity: 1,
    promotion_ids: [], // For stackable
    cross_promotion_id: null, // For cross-promotion (accepted CrossPromotion)
    required_level: null, // For level-exclusive QR codes
    placement_location: '',
    placement_description: '',
    confirm_internal: false,
    game_ids: [],
    leaderboard_id: null,
    design: {
        size: 300,
        margin: 2,
        error_correction: 'M',
        module_shape: 'square',
        finder_shape: 'square',
        // Background
        background_color: '#FFFFFF',
        background_gradient: null,
        // Module (QR pattern)
        module_color: '#000000',
        module_gradient: null,
        // Finder (corners) - separate from module
        finder_color: null,
        // Logo
        logo: null,
        logo_size: 0.25,
        logo_background: false,
        logo_border_radius: 0,
        // Text Top
        text_top: '',
        text_top_font: 'Inter',
        text_top_size: 16,
        text_top_color: '#000000',
        text_top_outline: false,
        text_top_outline_color: '#FFFFFF',
        text_top_outline_width: 2,
        // Text Bottom
        text_bottom: '',
        text_bottom_font: 'Inter',
        text_bottom_size: 16,
        text_bottom_color: '#000000',
        text_bottom_outline: false,
        text_bottom_outline_color: '#FFFFFF',
        text_bottom_outline_width: 2,
        // Border
        border: null,
        // Effects
        glow: null,
        shadow: null,
    },
});

// Watch for type changes to switch tabs appropriately
watch(() => form.type, (newType) => {
    // Keep QRcade types behaving like other types: stay on Basic where their tips/fields live.
    // (Previously, switching to a non-existent "qrcade" tab caused a blank screen.)
    if (newType === 'qrcade' || newType === 'qrcade_leaderboard') {
        activeTab.value = 'basic';
    }

    // Clear type-specific fields when switching
    if (newType !== 'promotion' && newType !== 'level_exclusive' && newType !== 'merch_referral' && newType !== 'stackable') {
        form.promotion_id = null;
    }
    if (newType !== 'stackable') {
        form.promotion_ids = [];
    }
    if (newType !== 'cross_promo') {
        form.cross_promotion_id = null;
    }
    if (newType !== 'level_exclusive') {
        form.required_level = null;
    }

    if (newType !== 'merch_referral') {
        form.reward_type = null;
        form.reward_value = null;
        form.reward_item_value = null;
        form.reward_description = '';
        form.redemptions_required = null;
        form.add_tag = false;
        form.add_tag_quantity = 1;
    }

    // Reset internal/prize intent when switching away from promotion QR codes
    if (newType !== 'promotion') {
        form.intended_use = 'public';
        form.confirm_internal = false;
    }
});

// When a leaderboard is selected for qrcade_leaderboard, auto-select the game from it
const selectedLeaderboard = computed(() => {
    if (!form.leaderboard_id || !props.leaderboards) return null;
    return props.leaderboards.find(lb => lb.id === form.leaderboard_id) || null;
});

watch(() => form.leaderboard_id, (newId) => {
    if (!newId || form.type !== 'qrcade_leaderboard') return;
    const lb = (props.leaderboards || []).find(l => l.id === newId);
    if (lb && lb.game_id) {
        // Auto-select the game from the leaderboard
        form.game_ids = [lb.game_id];
    }
});

// Clear leaderboard_id when switching away from qrcade_leaderboard type
watch(() => form.type, (newType) => {
    if (newType !== 'qrcade_leaderboard') {
        form.leaderboard_id = null;
    }
});

// Cross-promo request builder (creates a pending CrossPromotion request)
const showCrossPromoBuilder = ref(false);
const crossPromoBuilder = reactive({
    partner_business_id: null,
    name: '',
    my_promotion_id: null,
});
const crossPromoBuilderSending = ref(false);
const crossPromoBuilderResult = ref(null);
const crossPromoBuilderError = ref(null);

const sendCrossPromoRequest = async () => {
    crossPromoBuilderError.value = null;
    crossPromoBuilderResult.value = null;

    if (!crossPromoBuilder.partner_business_id || !crossPromoBuilder.my_promotion_id) {
        crossPromoBuilderError.value = 'Select a partner and your promotion first.';
        return;
    }

    crossPromoBuilderSending.value = true;
    try {
        const res = await window.axios.post('/business/partnerships/cross-promo', {
            partner_business_id: crossPromoBuilder.partner_business_id,
            name: crossPromoBuilder.name || 'Partner Chain Deal',
            my_promotion_id: crossPromoBuilder.my_promotion_id,
            display_mode: 'split',
            revenue_share_percent: 50,
        });

        const data = res.data || {};
        if (!data?.success) {
            crossPromoBuilderError.value = data?.message || 'Failed to send cross-promo request.';
            return;
        }

        crossPromoBuilderResult.value = 'Request sent! Your partner must accept and choose their offer (in Partnerships).';
        showCrossPromoBuilder.value = false;
        crossPromoBuilder.partner_business_id = null;
        crossPromoBuilder.name = '';
        crossPromoBuilder.my_promotion_id = null;
    } catch (e) {
        crossPromoBuilderError.value = 'Failed to send cross-promo request.';
    } finally {
        crossPromoBuilderSending.value = false;
    }
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const crossPromoId = urlParams.get('cross_promo');
    if (crossPromoId) {
        form.type = 'cross_promo';
        form.cross_promotion_id = parseInt(crossPromoId, 10) || null;
    }
});

// QR Code Types with descriptions
const qrTypes = [
    { id: 'static', name: 'Static', desc: 'Fixed URL', icon: '🔗' },
    { id: 'dynamic', name: 'Dynamic', desc: 'Trackable', icon: '📊' },
    { id: 'promotion', name: 'Promotion', desc: 'Single Deal', icon: '🎫' },
    { id: 'merch_referral', name: 'Merch Referral', desc: 'Ambassador rewards', icon: '👕' },
    { id: 'level_exclusive', name: 'Level Exclusive', desc: 'Requires User Level', icon: '⭐' },
    { id: 'stackable', name: 'Stackable', desc: 'Multiple Deals', icon: '📚' },
    { id: 'cross_promo', name: 'Partner Deal Chain', desc: 'Partner deal (open or chained unlock)', icon: '🤝' },
    { id: 'qrcade', name: 'QRcade Gaming', desc: 'Play-to-win rewards', icon: '🎮' },
    { id: 'qrcade_leaderboard', name: 'QRcade Leaderboard', desc: 'Leaderboard challenge', icon: '🏆' },
];

// Toggle game selection
const toggleGame = (gameId) => {
    const isLeaderboardQr = form.type === 'qrcade_leaderboard';
    if (isLeaderboardQr) {
        // Leaderboard QR codes can only have one game
        form.game_ids = [gameId];
        return;
    }

    const index = form.game_ids.indexOf(gameId);
    if (index === -1) {
        form.game_ids.push(gameId);
    } else {
        form.game_ids.splice(index, 1);
    }
};

// Toggle stackable promotion selection
const togglePromotion = (promoId) => {
    const index = form.promotion_ids.indexOf(promoId);
    if (index === -1) {
        form.promotion_ids.push(promoId);
    } else {
        form.promotion_ids.splice(index, 1);
    }
};

// Get selected cross-promo for rules preview
const selectedCrossPromo = computed(() => {
    if (!form.cross_promotion_id || !props.acceptedCrossPromos) return null;
    return props.acceptedCrossPromos.find(cp => cp.id === form.cross_promotion_id);
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

    // Validate file size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        alert('Logo must be less than 2MB');
        return;
    }

    // Validate file type
    const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        alert('Please upload a PNG, JPG, SVG, or WebP image');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        logoPreview.value = e.target.result;
        form.design.logo = e.target.result;
        // Auto-set error correction to H when logo is added
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

// Background gradient toggle - only enables, use Solid button to disable
const toggleBackgroundGradient = () => {
    if (!form.design.background_gradient) {
        form.design.background_gradient = {
            type: 'linear',
            angle: 135,
            colors: [
                { color: '#667eea', position: 0 },
                { color: '#764ba2', position: 100 }
            ]
        };
    }
};

// Module gradient toggle - only enables, use Solid button to disable
const toggleModuleGradient = () => {
    if (!form.design.module_gradient) {
        form.design.module_gradient = {
            type: 'linear',
            angle: 45,
            colors: [
                { color: '#f97316', position: 0 },
                { color: '#dc2626', position: 100 }
            ]
        };
    }
};

// Add gradient color stop
const addGradientColor = (gradient) => {
    if (gradient.colors.length < 5) {
        gradient.colors.push({
            color: '#888888',
            position: 50
        });
    }
};

// Remove gradient color stop
const removeGradientColor = (gradient, index) => {
    if (gradient.colors.length > 2) {
        gradient.colors.splice(index, 1);
    }
};

// Glow effect toggle
const toggleGlow = () => {
    if (form.design.glow) {
        form.design.glow = null;
    } else {
        form.design.glow = {
            color: '#6366F1',
            intensity: 15,
            spread: 5
        };
    }
};

// Shadow effect toggle  
const toggleShadow = () => {
    if (form.design.shadow) {
        form.design.shadow = null;
    } else {
        form.design.shadow = {
            color: '#000000',
            opacity: 0.3,
            blur: 10,
            offsetX: 5,
            offsetY: 5
        };
    }
};

const hexToRgba = (hex, alpha = 1) => {
    if (!hex) return `rgba(0,0,0,${alpha})`;
    let clean = hex.replace('#', '').trim();
    if (clean.length === 3) {
        clean = clean.split('').map((c) => c + c).join('');
    }
    if (clean.length !== 6) return `rgba(0,0,0,${alpha})`;
    const r = parseInt(clean.slice(0, 2), 16);
    const g = parseInt(clean.slice(2, 4), 16);
    const b = parseInt(clean.slice(4, 6), 16);
    if (Number.isNaN(r) || Number.isNaN(g) || Number.isNaN(b)) {
        return `rgba(0,0,0,${alpha})`;
    }
    return `rgba(${r},${g},${b},${alpha})`;
};

const shadowRgba = computed(() => {
    const color = form.design.shadow?.color || '#000000';
    const opacity = form.design.shadow?.opacity ?? 0.3;
    return hexToRgba(color, opacity);
});

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
        // Create preview-optimized design (scaled for faster rendering)
        const previewDesign = {
            ...form.design,
            preview_mode: true, // Tell server to scale effects for preview
        };
        
        const response = await window.axios.post('/api/qr/preview', {
            data: form.destination_url || 'https://example.com',
            design: previewDesign,
        }, {
            responseType: 'blob',
            cancelToken: cancelSource.token,
        });

        // Receive binary PNG and create a blob URL (faster than base64 data URI)
        const blob = response.data;
        // Revoke previous blob URL to free memory
        if (currentBlobUrl) {
            URL.revokeObjectURL(currentBlobUrl);
        }
        currentBlobUrl = URL.createObjectURL(blob);
        previewImage.value = currentBlobUrl;
    } catch (error) {
        // Ignore cancel errors (user made another change)
        if (window.axios.isCancel(error)) {
            return;
        }
        console.error('Preview generation failed:', error);
    } finally {
        previewLoading.value = false;
    }
};

// Watch for design changes and regenerate preview (debounced)
let debounceTimer = null;
watch(() => form.design, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(generatePreview, 300); // Fast preview updates
}, { deep: true });

// Submit form
const submit = () => {
    form.post(route('business.qr-codes.store'), {
        onSuccess: () => {
            showSuccessModal.value = true;
            // Reset form after successful creation
            form.reset();
            previewImage.value = null;
            logoPreview.value = null;
        },
        onError: (errors) => {
            // Handle general errors (limit, subscription, etc.)
            if (errors.limit) {
                alert(errors.limit);
            }
            if (errors.subscription) {
                alert(errors.subscription);
            }
            if (errors.error) {
                alert(errors.error);
            }
        }
    });
};

// Close success modal and redirect
const closeSuccessModal = () => {
    showSuccessModal.value = false;
    router.visit(route('business.qr-codes.index'));
};

// Watch for flash success message (fallback)
watch(() => page.props.flash?.success, (success) => {
    if (success) {
        showSuccessModal.value = true;
    }
});

// Check for success on mount (in case page reloads with success message)
onMounted(() => {
    if (page.props.flash?.success) {
        showSuccessModal.value = true;
    }
    generatePreview();
});

// Tabs
const tabs = [
    { id: 'basic', name: 'Basic Info', icon: '📝' },
    { id: 'colors', name: 'Colors', icon: '🎨' },
    { id: 'shape', name: 'Shape', icon: '⬡' },
    { id: 'logo', name: 'Logo', icon: '🖼️' },
    { id: 'text', name: 'Text', icon: '✏️' },
    { id: 'effects', name: 'Effects', icon: '✨' },
];

</script>

<template>
    <Head title="Create QR Code" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <Link href="/business/qr-codes" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                ← Back to QR Codes
            </Link>
            <h1 class="text-3xl font-bold text-white">Create QR Code</h1>
            <p class="text-gray-400 mt-1">Design your custom QR code with advanced styling options</p>
        </div>

        <!-- Error Messages -->
        <div v-if="form.errors.limit || form.errors.subscription || form.errors.error || page.props.flash?.error" class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p v-if="form.errors.limit" class="text-red-400 font-medium">{{ form.errors.limit }}</p>
                    <p v-if="form.errors.subscription" class="text-red-400 font-medium">{{ form.errors.subscription }}</p>
                    <p v-if="form.errors.error" class="text-red-400 font-medium">{{ form.errors.error }}</p>
                    <p v-if="page.props.flash?.error" class="text-red-400 font-medium">{{ page.props.flash.error }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Design Panel (2 columns) -->
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
                            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                                <button
                                    v-for="type in qrTypes"
                                    :key="type.id"
                                    @click="type.id === 'stackable' && !canUseStackable
                                        ? showStackablePopup()
                                        : (type.id === 'merch_referral' && !canUseMerchReferral ? showMerchReferralPopup() : (form.type = type.id))"
                                    :disabled="(type.id === 'stackable' && !canUseStackable) || (type.id === 'merch_referral' && !canUseMerchReferral)"
                                    :class="[
                                        'p-3 rounded-xl border text-center transition-all',
                                        form.type === type.id
                                            ? 'border-primary-500 bg-primary-500/20 text-white'
                                            : 'border-white/20 text-gray-400 hover:border-white/40',
                                        (type.id === 'stackable' && !canUseStackable) || (type.id === 'merch_referral' && !canUseMerchReferral)
                                            ? 'opacity-50 cursor-not-allowed'
                                            : ''
                                    ]"
                                >
                                    <div class="text-2xl mb-1">{{ type.icon }}</div>
                                    <div class="text-sm font-medium">{{ type.name }}</div>
                                    <div class="text-xs opacity-75">{{ type.desc }}</div>
                                    <div v-if="type.id === 'stackable' && !canUseStackable" class="text-xs text-red-400 mt-1">🔒 Growth+</div>
                                    <div v-if="type.id === 'merch_referral' && !canUseMerchReferral" class="text-xs text-red-400 mt-1">🔒 Growth+</div>
                                </button>
                            </div>
                        </div>

                        <!-- Static/Dynamic: URL -->
                        <div v-if="['static', 'dynamic'].includes(form.type)">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Destination URL</label>
                            <input
                                v-model="form.destination_url"
                                type="url"
                                class="input-glass"
                                placeholder="https://your-website.com"
                            />
                        </div>

                        <!-- Promotion: Single select -->
                        <div v-if="form.type === 'promotion'">
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

                            <!-- Intended Use / Purpose (to prevent accidental scans of internal prize QR codes) -->
                            <div class="mt-4 p-4 rounded-xl bg-white/5 border border-white/10">
                                <h4 class="text-white font-semibold mb-2">Purpose (required)</h4>
                                <p class="text-xs text-gray-400 mb-3">
                                    This helps prevent accidental scans when a QR is meant for internal configuration (like leaderboard prizes).
                                </p>

                                <div class="space-y-2">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input
                                            type="radio"
                                            value="public"
                                            v-model="form.intended_use"
                                            class="mt-1"
                                        />
                                        <div>
                                            <div class="text-sm text-white font-medium">Customer-facing (print / display)</div>
                                            <div class="text-xs text-gray-500">Customers should scan this in the real world.</div>
                                        </div>
                                    </label>

                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input
                                            type="radio"
                                            value="leaderboard_prize"
                                            v-model="form.intended_use"
                                            class="mt-1"
                                        />
                                        <div>
                                            <div class="text-sm text-white font-medium">Leaderboard Prize (internal — do not print)</div>
                                            <div class="text-xs text-gray-500">Use this only in Leaderboard settings as the prize QR. If someone scans it, they may redeem the prize early.</div>
                                        </div>
                                    </label>
                                </div>

                                <div v-if="form.intended_use === 'leaderboard_prize'" class="mt-3 p-3 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                    <p class="text-amber-300 text-xs font-medium">
                                        ⚠️ This QR is not meant for customers to scan. It will be treated as an internal prize QR.
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
                        <div v-if="form.type === 'merch_referral'" class="space-y-4">
                            <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                                <h4 class="text-white font-semibold mb-2">👕 Merch Referral QR</h4>
                                <p class="text-xs text-gray-400">
                                    This QR is printed on merch. Customers see the gateway promo once, and ambassadors earn
                                    repeat rewards every N unique redemptions.
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
                                <p class="text-xs text-gray-500 mt-2">
                                    This promo is one-time per customer and never expires in merch referrals.
                                </p>
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
                                <p class="text-xs text-gray-500 mt-2">
                                    This reward repeats every N qualified merch redemptions. Don’t delete; it powers ambassador rewards.
                                </p>
                            </div>

                            <div class="flex items-start gap-3 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                <input
                                    id="add-tag"
                                    v-model="form.add_tag"
                                    type="checkbox"
                                    class="mt-1 rounded border-white/20 bg-white/10 text-amber-500 focus:ring-amber-500"
                                />
                                <div>
                                    <label for="add-tag" class="text-sm font-medium text-amber-400">Add tag (testing)</label>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Create a merch tag linked to this QR so you can test the claim flow without ordering merch. Remove before go-live.
                                    </p>
                                    <div v-if="form.add_tag" class="mt-3">
                                        <label class="block text-xs text-gray-300 mb-1">Test tag quantity</label>
                                        <input
                                            v-model="form.add_tag_quantity"
                                            type="number"
                                            min="1"
                                            max="50"
                                            step="1"
                                            class="input-glass"
                                            placeholder="e.g. 4"
                                        />
                                        <p class="text-xs text-gray-500 mt-1">
                                            Creates this many unique merch tag URLs for testing (max 50).
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Level Exclusive: Promotion + Level Requirement -->
                        <div v-if="form.type === 'level_exclusive'" class="space-y-4">
                            <div class="p-4 rounded-xl bg-purple-500/10 border border-purple-500/30">
                                <h4 class="text-purple-400 font-medium mb-2">⭐ Level Exclusive Promotion</h4>
                                <p class="text-gray-400 text-sm mb-2">
                                    Level exclusive promotions use the user's app-wide XP/level (from scanning, playing games, redeeming across all businesses), not XP specific to your store.
                                </p>
                                <p class="text-gray-500 text-xs">
                                    Ask your local business or look for QR codes marked with level requirements.
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

                        <!-- QRcade: Game selection (and optional instant reward) -->
                        <div v-if="form.type === 'qrcade' || form.type === 'qrcade_leaderboard'" class="space-y-4">
                            <!-- Leaderboard Picker (only for qrcade_leaderboard) -->
                            <div v-if="form.type === 'qrcade_leaderboard'">
                                <label class="block text-sm font-medium text-gray-300 mb-2">Link to Leaderboard</label>
                                <p class="text-xs text-gray-500 mb-2">
                                    Select which leaderboard this QR code feeds into. The game will be set automatically for game-specific leaderboards.
                                </p>
                                <div v-if="!props.leaderboards || props.leaderboards.length === 0" class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p class="text-amber-400 text-sm font-medium">No leaderboards created yet</p>
                                    <p class="text-gray-400 text-xs mt-1">Create a leaderboard first in <strong>QRcade → Leaderboards</strong>, then come back here to link a QR code.</p>
                                </div>
                                <select v-else v-model="form.leaderboard_id" class="input-glass">
                                    <option :value="null" class="bg-gray-800 text-white">Select a leaderboard...</option>
                                    <option
                                        v-for="lb in props.leaderboards"
                                        :key="lb.id"
                                        :value="lb.id"
                                        class="bg-gray-800 text-white"
                                    >
                                        {{ lb.name }} ({{ lb.reset_frequency }}) {{ lb.game ? '- ' + lb.game.name : '- All Games' }}
                                    </option>
                                </select>
                                <div v-if="selectedLeaderboard" class="mt-3 p-3 bg-primary-500/10 border border-primary-500/30 rounded-lg text-sm">
                                    <div class="text-primary-300 font-medium mb-1">{{ selectedLeaderboard.name }}</div>
                                    <div class="text-gray-400 text-xs space-y-1">
                                        <div>Resets: <span class="text-white">{{ selectedLeaderboard.reset_frequency }}</span></div>
                                        <div>Type: <span class="text-white">{{ selectedLeaderboard.type === 'game_specific' ? 'Per Game' : 'This Location' }}</span></div>
                                        <div v-if="selectedLeaderboard.game">Game: <span class="text-white">{{ selectedLeaderboard.game.name }}</span> (auto-selected below)</div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    {{ form.type === 'qrcade_leaderboard' ? 'Game (plays this when scanned)' : 'Select Games' }}
                                </label>
                                <p class="text-xs text-gray-500 mb-2">
                                    <span v-if="form.type === 'qrcade_leaderboard'">
                                        This is the game players play when they scan this QR code.
                                        <span v-if="selectedLeaderboard && selectedLeaderboard.game"> Auto-selected from the leaderboard.</span>
                                    </span>
                                    <span v-else>
                                        The games players can play when scanning this QR code.
                                    </span>
                                </p>
                                <div v-if="!props.availableGames || props.availableGames.length === 0" class="text-gray-400 text-sm p-4 bg-gray-800/50 rounded-lg">
                                    No games available. Enable games in your QRcade settings first.
                                </div>
                                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div
                                        v-for="game in props.availableGames"
                                        :key="game.id"
                                        @click="toggleGame(game.id)"
                                        :class="[
                                            'p-3 rounded-lg border cursor-pointer transition-all',
                                            form.game_ids.includes(game.id)
                                                ? 'border-primary-500 bg-primary-500/20'
                                                : 'border-gray-600 hover:border-gray-500'
                                        ]"
                                    >
                                        <div class="flex items-center gap-3">
                                            <img
                                                v-if="game.thumbnail && !erroredThumbs[game.id]"
                                                :src="game.thumbnail"
                                                :alt="game.name"
                                                class="w-10 h-10 rounded object-cover"
                                                @error="(e) => onThumbError(game.id, e)"
                                            />
                                            <div v-else class="w-10 h-10 bg-gray-600 rounded flex items-center justify-center">
                                                🎮
                                            </div>
                                            <div>
                                                <div class="font-medium text-white">{{ game.name }}</div>
                                                <div class="text-xs text-gray-400">{{ game.category }}</div>
                                            </div>
                                            <div v-if="form.game_ids.includes(game.id)" class="ml-auto text-primary-400">
                                                ✓
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    {{ form.type === 'qrcade_leaderboard' ? 'Instant Win Reward (Disabled)' : 'Win Reward (Optional)' }}
                                </label>
                                <select
                                    v-model="form.promotion_id"
                                    class="input-glass"
                                    :disabled="form.type === 'qrcade_leaderboard'"
                                >
                                    <option value="" class="bg-gray-800 text-white">
                                        {{ form.type === 'qrcade_leaderboard' ? 'Prizes are awarded via the leaderboard' : 'No reward - just for fun' }}
                                    </option>
                                    <option
                                        v-for="promo in promotions"
                                        :key="promo.id"
                                        :value="promo.id"
                                        class="bg-gray-800 text-white"
                                    >
                                        {{ promo.name }} ({{ promo.discount_type }})
                                    </option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span v-if="form.type === 'qrcade_leaderboard'">
                                        This QR code is a leaderboard challenge. Prizes are awarded when the leaderboard period ends.
                                    </span>
                                    <span v-else>
                                        Players who complete games can win this reward immediately
                                    </span>
                                </p>
                                <p v-if="form.type === 'qrcade_leaderboard'" class="text-xs text-purple-400 mt-2 flex items-start gap-1">
                                    <span>💡</span>
                                    <span>
                                        <strong>How it works:</strong> Players scan this QR, play the game, and their score goes to the linked leaderboard.
                                        Prizes are set on the leaderboard itself (QRcade → Leaderboards).
                                    </span>
                                </p>
                                <p v-else-if="!form.promotion_id" class="text-xs text-purple-400 mt-2 flex items-start gap-1">
                                    <span>💡</span>
                                    <span>
                                        <strong>Tip:</strong> Add a promotion here so customers can scan, play, and win a reward instantly.
                                        If you leave this empty, it becomes play-only (no instant prize).
                                        To run a leaderboard challenge with prizes, use <strong>QRcade Leaderboard</strong>.
                                    </span>
                                </p>
                                <p v-else class="text-xs text-emerald-400 mt-2 flex items-start gap-1">
                                    <span>✅</span>
                                    <span>
                                        <strong>Instant Win:</strong> Customers can scan, play, and win this reward immediately (not a leaderboard prize).
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Stackable: Revenue QR pool -->
                        <div v-if="form.type === 'stackable'" class="space-y-4">
                            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                                <h4 class="text-emerald-400 font-medium mb-2">📍 Revenue QR (Stackable)</h4>
                                <p class="text-gray-400 text-sm">
                                    One shared QR code shows all participating businesses' deals.
                                    Customers see deals sorted by distance, with the closest first.
                                </p>
                            </div>

                            <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="text-white font-semibold">Linked Promotion (required)</h4>
                                        <p class="text-gray-400 text-sm">
                                            This promotion will be used for your Revenue QR deal pool.
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Your Promotion QR design is what customers will see when they save the deal.
                                        </p>
                                    </div>
                                    <Link href="/business/stackable-pools" class="text-emerald-400 text-sm hover:underline">
                                        Manage Pool →
                                    </Link>
                                </div>
                                <div>
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
                                    <p class="text-xs text-gray-400 mt-2">
                                        This creates a Stackable QR that you can print. Add it to the pool in
                                        <strong>Stackable Deals</strong> when you're ready to go live.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div class="p-3 rounded-lg bg-white/5">
                                    <div class="text-2xl mb-1">🎯</div>
                                    <div class="text-gray-400 text-xs">One deal per business</div>
                                </div>
                                <div class="p-3 rounded-lg bg-white/5">
                                    <div class="text-2xl mb-1">🔄</div>
                                    <div class="text-gray-400 text-xs">Swap anytime</div>
                                </div>
                                <div class="p-3 rounded-lg bg-white/5">
                                    <div class="text-2xl mb-1">📱</div>
                                    <div class="text-gray-400 text-xs">Same QR for all</div>
                                </div>
                            </div>
                        </div>

                        <!-- Partner Deal Chain -->
                        <div v-if="form.type === 'cross_promo'" class="space-y-4">
                            <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                <h4 class="text-amber-400 font-medium mb-2">🤝 Partner Deal Chain</h4>
                                <p class="text-gray-400 text-sm">
                                    Show two businesses’ offers on one scan. Optionally chain them so one offer unlocks the other after redemption.
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Select an Accepted Partner Deal Chain</label>
                                <div v-if="acceptedCrossPromos && acceptedCrossPromos.length > 0" class="space-y-2">
                                    <select v-model="form.cross_promotion_id" class="input-glass">
                                        <option value="" class="bg-gray-800 text-white">Select a partner deal...</option>
                                        <option
                                            v-for="cp in acceptedCrossPromos"
                                            :key="cp.id"
                                            :value="cp.id"
                                            class="bg-gray-800 text-white"
                                        >
                                            {{ cp.name }} — Partner: {{ cp.partner?.name }}
                                        </option>
                                    </select>
                                    
                                    <!-- Rules Preview -->
                                    <div v-if="form.cross_promotion_id" class="mt-3 p-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
                                        <h4 class="text-blue-400 font-medium mb-2 text-sm">📋 Cross-Promo Rules</h4>
                                        <div v-if="selectedCrossPromo?.cross_promo_rules" class="space-y-1 text-xs text-gray-300">
                                            <div v-if="selectedCrossPromo.cross_promo_rules.valid_days?.length">
                                                <strong>Valid Days:</strong> {{ selectedCrossPromo.cross_promo_rules.valid_days.map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(', ') }}
                                            </div>
                                            <div v-if="selectedCrossPromo.cross_promo_rules.valid_hours?.start">
                                                <strong>Valid Hours:</strong> {{ selectedCrossPromo.cross_promo_rules.valid_hours.start }} - {{ selectedCrossPromo.cross_promo_rules.valid_hours.end }}
                                            </div>
                                            <div v-if="selectedCrossPromo.cross_promo_rules.max_redemptions_per_user">
                                                <strong>Max Per User:</strong> {{ selectedCrossPromo.cross_promo_rules.max_redemptions_per_user }}
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-gray-400">
                                            Using each promotion's own rules
                                        </div>
                                        <div v-if="selectedCrossPromo?.expires_at" class="mt-2 text-xs text-amber-400">
                                            ⏰ Expires: {{ new Date(selectedCrossPromo.expires_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                    <p class="text-gray-500 text-xs">
                                        Manage partner deals in
                                        <Link href="/business/partnerships" class="text-primary-400 hover:underline">Partnerships</Link>.
                                    </p>
                                </div>
                                <div v-else class="p-4 rounded-xl bg-white/5 border border-white/10 text-center">
                                    <p class="text-gray-400 mb-3">No accepted partner deals yet.</p>
                                    <Link href="/business/partnerships" class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                                        🤝 Go to Partnerships
                                    </Link>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1">Need a new partner deal?</label>
                                        <p class="text-gray-500 text-xs">Send a request right here; your partner accepts + picks their offer.</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-amber-500/20 text-amber-300 rounded-lg hover:bg-amber-500/30"
                                        @click="showCrossPromoBuilder = !showCrossPromoBuilder"
                                    >
                                        {{ showCrossPromoBuilder ? 'Hide' : 'Create Request' }}
                                    </button>
                                </div>

                                <div v-if="crossPromoBuilderResult" class="mt-3 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                                    <p class="text-emerald-300 text-sm">{{ crossPromoBuilderResult }}</p>
                                </div>
                                <div v-if="crossPromoBuilderError" class="mt-3 p-3 rounded-xl bg-red-500/10 border border-red-500/30">
                                    <p class="text-red-300 text-sm">{{ crossPromoBuilderError }}</p>
                                </div>

                                <div v-if="showCrossPromoBuilder" class="mt-4 p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Partner</label>
                                        <div v-if="partners && partners.length > 0">
                                            <select v-model="crossPromoBuilder.partner_business_id" class="input-glass">
                                                <option value="" class="bg-gray-800 text-white">Select a partner...</option>
                                                <option
                                                    v-for="partner in partners"
                                                    :key="partner.id"
                                                    :value="partner.id"
                                                    class="bg-gray-800 text-white"
                                                >
                                                    {{ partner.name }} ({{ partner.category }})
                                                </option>
                                            </select>
                                        </div>
                                        <div v-else class="text-center">
                                            <p class="text-gray-400 mb-3">You don't have any accepted partners yet.</p>
                                            <Link href="/business/partnerships" class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                                                🤝 Find Partners
                                            </Link>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Your Offer</label>
                                        <select v-model="crossPromoBuilder.my_promotion_id" class="input-glass">
                                            <option value="" class="bg-gray-800 text-white">Select your promotion...</option>
                                            <option
                                                v-for="promo in promotions"
                                                :key="promo.id"
                                                :value="promo.id"
                                                class="bg-gray-800 text-white"
                                            >
                                                {{ promo.name }} ({{ promo.discount_type }})
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Name (optional)</label>
                                        <input
                                            v-model="crossPromoBuilder.name"
                                            type="text"
                                            class="input-glass"
                                            placeholder="e.g., Coffee x Donuts"
                                        />
                                    </div>

                                    <button
                                        type="button"
                                        @click="sendCrossPromoRequest"
                                        :disabled="crossPromoBuilderSending || !(crossPromoBuilder.partner_business_id && crossPromoBuilder.my_promotion_id)"
                                        class="w-full py-3 rounded-xl font-semibold bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span v-if="crossPromoBuilderSending">Sending...</span>
                                        <span v-else>Send Request</span>
                                    </button>
                                </div>
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
                                <label class="block text-sm font-medium text-gray-300 mb-2">Description (Optional)</label>
                                <input
                                    v-model="form.placement_description"
                                    type="text"
                                    class="input-glass"
                                    placeholder="e.g., Near register"
                                />
                            </div>
                        </div>

                        <!-- QR Code Design Tips -->
                        <div class="mt-6 p-4 rounded-xl bg-primary-500/10 border border-primary-500/20">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="text-base font-semibold text-white">Design Tips</h3>
                            </div>
                            
                            <div class="space-y-3">
                                <!-- Contrast Tip -->
                                <div class="flex gap-2">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <div class="w-5 h-5 rounded-full bg-primary-500/20 flex items-center justify-center">
                                            <span class="text-primary-400 text-xs font-medium">1</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-white mb-0.5">High Contrast Colors</h4>
                                        <p class="text-xs text-gray-400 leading-relaxed">
                                            Use contrasting colors between background and QR code modules for maximum scannability.
                                        </p>
                                    </div>
                                </div>

                                <!-- Size & Margin Tip -->
                                <div class="flex gap-2">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <div class="w-5 h-5 rounded-full bg-primary-500/20 flex items-center justify-center">
                                            <span class="text-primary-400 text-xs font-medium">2</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-white mb-0.5">Size & Quiet Zone</h4>
                                        <p class="text-xs text-gray-400 leading-relaxed">
                                            Minimum 1" x 1" for prints. Keep margins clear—no text or graphics in the white border.
                                        </p>
                                    </div>
                                </div>

                                <!-- Merch Tip -->
                                <div class="flex gap-2">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <div class="w-5 h-5 rounded-full bg-primary-500/20 flex items-center justify-center">
                                            <span class="text-primary-400 text-xs font-medium">3</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-white mb-0.5">For Merch &amp; Print</h4>
                                        <p class="text-xs text-gray-400 leading-relaxed">
                                            <strong class="text-white">Strongly recommended:</strong> use high-contrast black &amp; white (or very dark on very light) for the most reliable scans.
                                            Use error correction level "M" or "H" for durability. Avoid curved surfaces or creases.
                                        </p>
                                    </div>
                                </div>

                                <!-- Test Scan Tip -->
                                <div class="flex gap-2">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <div class="w-5 h-5 rounded-full bg-accent-500/20 flex items-center justify-center">
                                            <svg class="w-3 h-3 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-white mb-0.5">Always Test Before Printing</h4>
                                        <p class="text-xs text-gray-400 leading-relaxed">
                                            <strong class="text-accent-400">Important:</strong> Test scan with multiple devices before printing on merchandise or materials.
                                        </p>
                                    </div>
                                </div>

                                <!-- Logo Tip -->
                                <div class="flex gap-2">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <div class="w-5 h-5 rounded-full bg-primary-500/20 flex items-center justify-center">
                                            <span class="text-primary-400 text-xs font-medium">4</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-white mb-0.5">Logo Placement</h4>
                                        <p class="text-xs text-gray-400 leading-relaxed">
                                            Keep logos small (max 30% of QR code size) and centered. Don't cover finder patterns.
                                        </p>
                                    </div>
                                </div>
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
                                <div class="flex rounded-lg overflow-hidden border border-white/20">
                                    <button
                                        @click="form.design.background_gradient = null"
                                        class="px-3 py-1.5 text-xs font-medium transition-colors"
                                        :class="!form.design.background_gradient ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                                    >
                                        Solid
                                    </button>
                                    <button
                                        @click="toggleBackgroundGradient"
                                        class="px-3 py-1.5 text-xs font-medium transition-colors"
                                        :class="form.design.background_gradient ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                                    >
                                        🌈 Gradient
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Solid Color -->
                            <div v-if="!form.design.background_gradient" class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="form.design.background_color"
                                    class="w-12 h-12 rounded-lg border border-white/20 cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="form.design.background_color"
                                    class="input-glass flex-1"
                                    placeholder="#FFFFFF"
                                />
                            </div>
                            
                            <!-- Gradient -->
                            <div v-else class="space-y-4">
                                <!-- Gradient Type Toggle -->
                                <div class="flex items-center gap-4">
                                    <div class="flex rounded-lg overflow-hidden border border-white/20">
                                        <button
                                            @click="form.design.background_gradient.type = 'linear'"
                                            class="px-3 py-1.5 text-xs font-medium transition-colors"
                                            :class="form.design.background_gradient.type === 'linear' ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                                        >
                                            ↗ Linear
                                        </button>
                                        <button
                                            @click="form.design.background_gradient.type = 'radial'"
                                            class="px-3 py-1.5 text-xs font-medium transition-colors"
                                            :class="form.design.background_gradient.type === 'radial' ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                                        >
                                            ◉ Radial
                                        </button>
                                    </div>
                                    <div v-if="form.design.background_gradient.type === 'linear'" class="flex items-center gap-2">
                                        <span class="text-gray-400 text-sm">Angle:</span>
                                        <input type="number" v-model="form.design.background_gradient.angle" min="0" max="360" class="input-glass w-20 text-sm" />
                                        <span class="text-gray-400 text-sm">°</span>
                                    </div>
                                </div>
                                <!-- Gradient Preview -->
                                <div 
                                    class="h-8 rounded-lg border border-white/20"
                                    :style="{
                                        background: form.design.background_gradient.type === 'radial'
                                            ? `radial-gradient(circle, ${form.design.background_gradient.colors.map(c => c.color + ' ' + c.position + '%').join(', ')})`
                                            : `linear-gradient(${form.design.background_gradient.angle}deg, ${form.design.background_gradient.colors.map(c => c.color + ' ' + c.position + '%').join(', ')})`
                                    }"
                                ></div>
                                <!-- Color Stops -->
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
                                <div class="flex rounded-lg overflow-hidden border border-white/20">
                                    <button
                                        @click="form.design.module_gradient = null"
                                        class="px-3 py-1.5 text-xs font-medium transition-colors"
                                        :class="!form.design.module_gradient ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                                    >
                                        Solid
                                    </button>
                                    <button
                                        @click="toggleModuleGradient"
                                        class="px-3 py-1.5 text-xs font-medium transition-colors"
                                        :class="form.design.module_gradient ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                                    >
                                        🌈 Gradient
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Solid Color -->
                            <div v-if="!form.design.module_gradient" class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    v-model="form.design.module_color"
                                    class="w-12 h-12 rounded-lg border border-white/20 cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="form.design.module_color"
                                    class="input-glass flex-1"
                                    placeholder="#000000"
                                />
                            </div>
                            
                            <!-- Gradient -->
                            <div v-else class="space-y-4">
                                <!-- Gradient Type Toggle -->
                                <div class="flex items-center gap-4">
                                    <div class="flex rounded-lg overflow-hidden border border-white/20">
                                        <button
                                            @click="form.design.module_gradient.type = 'linear'"
                                            class="px-3 py-1.5 text-xs font-medium transition-colors"
                                            :class="form.design.module_gradient.type === 'linear' ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                                        >
                                            ↗ Linear
                                        </button>
                                        <button
                                            @click="form.design.module_gradient.type = 'radial'"
                                            class="px-3 py-1.5 text-xs font-medium transition-colors"
                                            :class="form.design.module_gradient.type === 'radial' ? 'bg-primary-500 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                                        >
                                            ◉ Radial
                                        </button>
                                    </div>
                                    <div v-if="form.design.module_gradient.type === 'linear'" class="flex items-center gap-2">
                                        <span class="text-gray-400 text-sm">Angle:</span>
                                        <input type="number" v-model="form.design.module_gradient.angle" min="0" max="360" class="input-glass w-20 text-sm" />
                                        <span class="text-gray-400 text-sm">°</span>
                                    </div>
                                </div>
                                <!-- Gradient Preview -->
                                <div 
                                    class="h-8 rounded-lg border border-white/20"
                                    :style="{
                                        background: form.design.module_gradient.type === 'radial'
                                            ? `radial-gradient(circle, ${form.design.module_gradient.colors.map(c => c.color + ' ' + c.position + '%').join(', ')})`
                                            : `linear-gradient(${form.design.module_gradient.angle}deg, ${form.design.module_gradient.colors.map(c => c.color + ' ' + c.position + '%').join(', ')})`
                                    }"
                                ></div>
                                <!-- Color Stops -->
                                <div v-for="(stop, index) in form.design.module_gradient.colors" :key="index" class="flex items-center gap-3">
                                    <input type="color" v-model="stop.color" class="w-10 h-10 rounded-lg border border-white/20 cursor-pointer" />
                                    <input type="range" v-model="stop.position" min="0" max="100" class="flex-1" />
                                    <span class="text-gray-400 text-sm w-12">{{ stop.position }}%</span>
                                    <button v-if="form.design.module_gradient.colors.length > 2" @click="removeGradientColor(form.design.module_gradient, index)" class="text-red-400 hover:text-red-300">✕</button>
                                </div>
                                <button @click="addGradientColor(form.design.module_gradient)" class="text-primary-400 text-sm hover:underline">+ Add Color Stop</button>
                            </div>
                        </div>

                        <!-- Finder Color (Corners) -->
                        <div class="p-4 rounded-xl bg-white/5">
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                Finder Color (Corner Squares)
                                <span class="text-gray-500 text-xs ml-2">Leave empty to match module color</span>
                            </label>
                            <div class="flex items-center space-x-3">
                                <input
                                    type="color"
                                    :value="form.design.finder_color || form.design.module_color"
                                    @input="form.design.finder_color = $event.target.value"
                                    class="w-12 h-12 rounded-lg border border-white/20 cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="form.design.finder_color"
                                    class="input-glass flex-1"
                                    placeholder="Same as module"
                                />
                                <button
                                    v-if="form.design.finder_color"
                                    @click="form.design.finder_color = null"
                                    type="button"
                                    class="p-2 text-gray-400 hover:text-white"
                                    title="Reset to match module color"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Shape Tab -->
                    <div v-show="activeTab === 'shape'" class="space-y-6">
                        <!-- Module Shape -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-3">Module Shape (QR Pattern)</label>
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

                        <!-- Finder Shape (Corners) - SEPARATE from module -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-3">Finder Shape (Corners)</label>
                            <p class="text-gray-500 text-xs mb-3">The three large squares in the corners - independent from module shape</p>
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

                        <!-- Size & Margin -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Size (px)</label>
                                <input
                                    type="range"
                                    v-model.number="form.design.size"
                                    min="200"
                                    max="1000"
                                    step="50"
                                    class="w-full"
                                />
                                <div class="text-center text-gray-400 text-sm mt-1">{{ form.design.size }}px</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Margin</label>
                                <input
                                    type="range"
                                    v-model.number="form.design.margin"
                                    min="0"
                                    max="10"
                                    class="w-full"
                                />
                                <div class="text-center text-gray-400 text-sm mt-1">{{ form.design.margin }}</div>
                            </div>
                        </div>

                        <!-- Error Correction -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Error Correction Level</label>
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
                            <p class="text-gray-500 text-xs mt-2">Higher = more error tolerance, but larger QR. Use H if adding a logo.</p>
                        </div>
                    </div>

                    <!-- Logo Tab -->
                    <div v-show="activeTab === 'logo'" class="space-y-6">
                        <!-- Upload Area -->
                        <div 
                            class="border-2 border-dashed rounded-xl p-8 text-center transition-colors"
                            :class="logoPreview ? 'border-primary-500/50 bg-primary-500/10' : 'border-white/20 hover:border-white/40'"
                        >
                            <input 
                                ref="logoInput"
                                type="file" 
                                accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" 
                                class="hidden" 
                                id="logo-upload"
                                @change="handleLogoUpload"
                            />
                            
                            <div v-if="logoPreview" class="space-y-4">
                                <div class="w-24 h-24 mx-auto rounded-xl overflow-hidden bg-white">
                                    <img :src="logoPreview" class="w-full h-full object-contain" alt="Logo preview" />
                                </div>
                                <div class="flex justify-center gap-3">
                                    <label for="logo-upload" class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 cursor-pointer">
                                        Change Logo
                                    </label>
                                    <button @click="removeLogo" class="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30">
                                        Remove
                                    </button>
                                </div>
                            </div>
                            
                            <label v-else for="logo-upload" class="cursor-pointer block">
                                <div class="w-16 h-16 rounded-full bg-white/10 mx-auto flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p class="text-white font-medium">Upload Logo</p>
                                <p class="text-gray-400 text-sm mt-1">PNG, JPG, SVG, or WebP up to 2MB</p>
                            </label>
                        </div>

                        <!-- Logo Settings (only show when logo uploaded) -->
                        <div v-if="logoPreview" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Logo Size</label>
                                <input
                                    type="range"
                                    v-model.number="form.design.logo_size"
                                    min="0.1"
                                    max="0.4"
                                    step="0.05"
                                    class="w-full"
                                />
                                <div class="text-center text-gray-400 text-sm mt-1">{{ Math.round(form.design.logo_size * 100) }}% of QR</div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Logo Corner Radius</label>
                                <input
                                    type="range"
                                    v-model.number="form.design.logo_border_radius"
                                    min="0"
                                    max="50"
                                    step="5"
                                    class="w-full"
                                />
                                <div class="text-center text-gray-400 text-sm mt-1">{{ form.design.logo_border_radius }}%</div>
                            </div>

                            <label class="flex items-center p-4 rounded-xl bg-white/5 cursor-pointer">
                                <input type="checkbox" v-model="form.design.logo_background" class="rounded border-white/20 bg-white/10 text-primary-500" />
                                <span class="ml-3 text-gray-300">Add white background behind logo</span>
                            </label>

                            <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30">
                                <p class="text-amber-400 text-sm">💡 Tip: Error correction automatically set to H (highest) for better logo scanning.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Text Tab -->
                    <div v-show="activeTab === 'text'" class="space-y-6">
                        <!-- Top Text -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <h4 class="text-white font-medium">Text Above QR Code</h4>
                            <input
                                type="text"
                                v-model="form.design.text_top"
                                class="input-glass"
                                placeholder="e.g., SCAN ME"
                            />
                            
                            <div v-if="form.design.text_top" class="space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Font</label>
                                        <select v-model="form.design.text_top_font" class="input-glass text-sm">
                                            <option v-for="(label, font) in fonts" :key="font" :value="font" class="bg-gray-800 text-white">
                                                {{ label }}
                                            </option>
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
                                
                                <!-- Text Outline -->
                                <div class="flex items-center justify-between p-3 rounded-lg bg-white/5">
                                    <span class="text-gray-300 text-sm">Text Outline</span>
                                    <button
                                        @click="form.design.text_top_outline = !form.design.text_top_outline"
                                        :class="[
                                            'w-10 h-5 rounded-full transition-colors relative',
                                            form.design.text_top_outline ? 'bg-primary-500' : 'bg-gray-600'
                                        ]"
                                    >
                                        <div :class="['w-4 h-4 rounded-full bg-white absolute top-0.5 transition-transform', form.design.text_top_outline ? 'translate-x-5' : 'translate-x-0.5']"></div>
                                    </button>
                                </div>
                                <div v-if="form.design.text_top_outline" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Outline Color</label>
                                        <input type="color" v-model="form.design.text_top_outline_color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Outline Width</label>
                                        <input type="number" v-model="form.design.text_top_outline_width" min="1" max="10" class="input-glass text-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Text -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <h4 class="text-white font-medium">Text Below QR Code</h4>
                            <input
                                type="text"
                                v-model="form.design.text_bottom"
                                class="input-glass"
                                placeholder="e.g., Get 20% Off!"
                            />
                            
                            <div v-if="form.design.text_bottom" class="space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Font</label>
                                        <select v-model="form.design.text_bottom_font" class="input-glass text-sm">
                                            <option v-for="(label, font) in fonts" :key="font" :value="font" class="bg-gray-800 text-white">
                                                {{ label }}
                                            </option>
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
                                
                                <!-- Text Outline -->
                                <div class="flex items-center justify-between p-3 rounded-lg bg-white/5">
                                    <span class="text-gray-300 text-sm">Text Outline</span>
                                    <button
                                        @click="form.design.text_bottom_outline = !form.design.text_bottom_outline"
                                        :class="[
                                            'w-10 h-5 rounded-full transition-colors relative',
                                            form.design.text_bottom_outline ? 'bg-primary-500' : 'bg-gray-600'
                                        ]"
                                    >
                                        <div :class="['w-4 h-4 rounded-full bg-white absolute top-0.5 transition-transform', form.design.text_bottom_outline ? 'translate-x-5' : 'translate-x-0.5']"></div>
                                    </button>
                                </div>
                                <div v-if="form.design.text_bottom_outline" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Outline Color</label>
                                        <input type="color" v-model="form.design.text_bottom_outline_color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Outline Width</label>
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
                                    <p class="text-gray-400 text-sm">Add a border around the QR code</p>
                                </div>
                                <button
                                    @click="form.design.border = form.design.border ? null : { width: 2, color: '#000000', radius: 0 }"
                                    :class="[
                                        'w-12 h-6 rounded-full transition-colors relative',
                                        form.design.border ? 'bg-primary-500' : 'bg-gray-600'
                                    ]"
                                >
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

                        <!-- Glow Effect -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-white font-medium">Glow Effect</span>
                                    <p class="text-gray-400 text-sm">Add a colorful glow around the QR code</p>
                                </div>
                                <button
                                    @click="toggleGlow"
                                    :class="[
                                        'w-12 h-6 rounded-full transition-colors relative',
                                        form.design.glow ? 'bg-primary-500' : 'bg-gray-600'
                                    ]"
                                >
                                    <div :class="['w-5 h-5 rounded-full bg-white absolute top-0.5 transition-transform', form.design.glow ? 'translate-x-6' : 'translate-x-0.5']"></div>
                                </button>
                            </div>
                            <div v-if="form.design.glow" class="space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Glow Color</label>
                                        <input type="color" v-model="form.design.glow.color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Intensity</label>
                                        <input type="range" v-model.number="form.design.glow.intensity" min="5" max="50" class="w-full mt-3" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Spread</label>
                                        <input type="range" v-model.number="form.design.glow.spread" min="0" max="20" class="w-full mt-3" />
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

                        <!-- Shadow Effect -->
                        <div class="p-4 rounded-xl bg-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-white font-medium">Drop Shadow</span>
                                    <p class="text-gray-400 text-sm">Add depth with a customizable shadow</p>
                                </div>
                                <button
                                    @click="toggleShadow"
                                    :class="[
                                        'w-12 h-6 rounded-full transition-colors relative',
                                        form.design.shadow ? 'bg-primary-500' : 'bg-gray-600'
                                    ]"
                                >
                                    <div :class="['w-5 h-5 rounded-full bg-white absolute top-0.5 transition-transform', form.design.shadow ? 'translate-x-6' : 'translate-x-0.5']"></div>
                                </button>
                            </div>
                            <div v-if="form.design.shadow" class="space-y-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Shadow Color</label>
                                        <input type="color" v-model="form.design.shadow.color" class="w-full h-10 rounded-lg border border-white/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Opacity ({{ Math.round(form.design.shadow.opacity * 100) }}%)</label>
                                        <input type="range" v-model.number="form.design.shadow.opacity" min="0.1" max="1" step="0.1" class="w-full mt-3" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Blur</label>
                                        <input type="number" v-model.number="form.design.shadow.blur" min="0" max="50" class="input-glass text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Offset X</label>
                                        <input type="number" v-model.number="form.design.shadow.offsetX" min="-30" max="30" class="input-glass text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Offset Y</label>
                                        <input type="number" v-model.number="form.design.shadow.offsetY" min="-30" max="30" class="input-glass text-sm" />
                                    </div>
                                </div>
                                <div 
                                    class="text-center p-4 rounded-lg bg-white/10"
                                    :style="{ boxShadow: `${form.design.shadow.offsetX}px ${form.design.shadow.offsetY}px ${form.design.shadow.blur}px ${shadowRgba}` }"
                                >
                                    <span class="text-gray-400 text-sm">Shadow Preview</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Panel (1 column) -->
            <div class="space-y-6">
                <!-- Live Preview -->
                <div class="glass-card p-6 sticky top-24">
                    <h3 class="text-lg font-semibold text-white mb-4">Live Preview</h3>
                    
                    <div 
                        class="rounded-xl flex items-center justify-center transition-shadow duration-300 relative overflow-hidden"
                        :class="hasText ? 'min-h-[200px]' : 'aspect-square'"
                        :style="{
                            backgroundColor: '#1a1a2e',
                            borderRadius: previewBorderRadius
                        }"
                    >
                        <img v-if="previewImage" :src="previewImage" class="max-w-full max-h-full qr-preview-img" :class="{ 'opacity-50': previewLoading }" alt="QR Preview" />
                        <div v-else-if="!previewLoading" class="text-gray-400 text-center p-8">
                            <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            <p>Enter a URL to preview</p>
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
                    
                    <!-- Effects indicator -->
                    <div v-if="form.design.glow || form.design.shadow" class="mt-3 text-xs text-gray-500 text-center">
                        ✨ Effects shown in preview • Full render on save
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-4 space-y-3">
                        <button @click="generatePreview" class="w-full py-2 rounded-lg bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                            🔄 Refresh Preview
                        </button>
                        
                        <!-- Create Button -->
                        <button
                            @click="submit"
                            :disabled="form.processing || !form.name"
                            class="w-full btn-accent disabled:opacity-50 disabled:cursor-not-allowed mt-3"
                        >
                            <span v-if="form.processing">Creating...</span>
                            <span v-else>✨ Create QR Code</span>
                        </button>
                        <p class="text-center text-gray-500 text-sm mt-2">
                            You can edit this QR code anytime
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Modal -->
        <div 
            v-if="showSuccessModal" 
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
            @click.self="closeSuccessModal"
        >
            <div class="glass-card p-8 max-w-md w-full mx-4 border-2 border-green-500/50 bg-green-500/10">
                <div class="text-center">
                    <div class="w-20 h-20 rounded-full bg-green-500/20 mx-auto mb-4 flex items-center justify-center">
                        <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-2">QR Code Created!</h2>
                    <p class="text-gray-300" :class="page.props.flash?.merch_tag_url ? 'mb-4' : 'mb-6'">Your QR code has been successfully created and is ready to use.</p>
                    <div v-if="page.props.flash?.merch_tags?.length" class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-left">
                        <p class="text-amber-400 font-medium text-sm mb-2">Merch tags created (testing)</p>
                        <p class="text-gray-400 text-xs mb-3">Each tag is a unique merch QR. Scan any URL to test claim:</p>
                        <div class="space-y-3 max-h-56 overflow-y-auto pr-1">
                            <div v-for="tag in page.props.flash.merch_tags" :key="tag.code" class="flex items-center gap-3">
                                <img
                                    v-if="tag.image_url"
                                    :src="tag.image_url"
                                    alt="Merch tag QR"
                                    class="w-14 h-14 rounded-lg bg-white p-1"
                                />
                                <div class="min-w-0">
                                    <a
                                        :href="tag.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="block text-primary-400 hover:underline text-xs break-all"
                                    >
                                        {{ tag.url }}
                                    </a>
                                    <p class="text-gray-500 text-xs mt-1">Code: {{ tag.code }}</p>
                                    <a
                                        v-if="tag.image_url"
                                        :href="tag.image_url"
                                        download
                                        class="inline-flex items-center mt-2 px-2.5 py-1 rounded-lg bg-white/10 text-white text-xs hover:bg-white/20 transition-all"
                                    >
                                        Download QR
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="page.props.flash?.merch_tag_url" class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-left">
                        <p class="text-amber-400 font-medium text-sm mb-2">Merch tag created (testing)</p>
                        <p class="text-gray-400 text-xs mb-2">Use this to test the claim flow without ordering merch:</p>
                        <a :href="page.props.flash.merch_tag_url" target="_blank" rel="noopener" class="block text-primary-400 hover:underline text-sm break-all">
                            {{ page.props.flash.merch_tag_url }}
                        </a>
                        <p v-if="page.props.flash.merch_tag_code" class="text-gray-500 text-xs mt-1">Code: {{ page.props.flash.merch_tag_code }}</p>
                    </div>
                    <div class="flex gap-3">
                        <button
                            @click="closeSuccessModal"
                            class="flex-1 py-3 rounded-xl bg-gradient-to-r from-primary-500 to-accent-500 text-white font-semibold hover:opacity-90 transition-opacity"
                        >
                            View QR Codes
                        </button>
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
