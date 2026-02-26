<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    partnerships: Array,
    crossPromos: Array,
    pendingIncomingCrossPromos: Array,
    pendingOutgoingCrossPromos: Array,
    pendingRequests: Number,
    displayModes: Object,
    chainModes: Object,
    rulesStatuses: Object,
    allBusinesses: Array,
    myPromotions: Array,
});

const activeTab = ref('partnerships');
const showRequestModal = ref(false);
const showCrossPromoModal = ref(false);
const showAcceptCrossPromoModal = ref(false);
const searchQuery = ref('');
const selectedPartner = ref(null);
const selectedIncomingCrossPromo = ref(null);

// Paginated partner list
const partnersPage = ref({
    data: props.allBusinesses || [],
    pagination: {
        current_page: 1,
        last_page: 1,
        next_page_url: null,
        prev_page_url: null,
    },
});

const fetchBusinesses = async (page = 1) => {
    const q = searchQuery.value.trim();
    try {
        const res = await axios.get('/business/partnerships/search', {
            params: { search: q, page, per_page: 10 },
        });
        partnersPage.value = res.data;
    } catch (e) {
        console.error('Failed to fetch businesses', e);
    }
};

watch(searchQuery, () => fetchBusinesses(1));

// Forms
const requestForm = useForm({
    partner_business_id: null,
    message: '',
});

const crossPromoForm = useForm({
    partner_business_id: null,
    name: '',
    my_promotion_id: null,
    display_mode: 'split',
    chain_mode: 'open', // 'open' or 'sequential'
    primary_promotion_id: null,
    expires_at: null,
    starts_at: null,
    usage_limit: null,
});

const chainOrder = ref('me_first'); // 'me_first' or 'partner_first'

const acceptCrossPromoForm = useForm({
    my_promotion_id: null,
    agree: false,
    open_qr_after_accept: true,
});

// Computed
const pendingIncoming = computed(() => 
    props.partnerships.filter(p => p.status === 'pending' && !p.is_requester)
);

const pendingOutgoing = computed(() => 
    props.partnerships.filter(p => p.status === 'pending' && p.is_requester)
);

const acceptedPartners = computed(() => 
    props.partnerships.filter(p => p.status === 'accepted')
);

// Send request
const sendRequest = (businessId) => {
    if (!businessId) {
        alert('Please select a business first');
        return;
    }
    requestForm.partner_business_id = businessId;
    requestForm.post('/business/partnerships/request', {
        onSuccess: () => {
            showRequestModal.value = false;
            requestForm.reset();
            searchQuery.value = '';
        },
    });
};

// Accept request
const acceptRequest = (partnershipId) => {
    router.post(`/business/partnerships/${partnershipId}/accept`);
};

// Decline request
const declineRequest = (partnershipId) => {
    if (confirm('Are you sure you want to decline this partnership request?')) {
        router.post(`/business/partnerships/${partnershipId}/decline`);
    }
};

// Cancel request
const cancelRequest = (partnershipId) => {
    if (confirm('Are you sure you want to cancel this partnership request?')) {
        router.post(`/business/partnerships/${partnershipId}/cancel`);
    }
};

// Create cross-promo
const createCrossPromo = () => {
    if (selectedPartner.value?.partner?.id) {
        crossPromoForm.partner_business_id = selectedPartner.value.partner.id;
    }

    // Set primary promotion based on order selection
    if (crossPromoForm.chain_mode === 'sequential') {
        if (chainOrder.value === 'me_first') {
            crossPromoForm.primary_promotion_id = crossPromoForm.my_promotion_id;
        } else {
            crossPromoForm.primary_promotion_id = null; // Partner goes first
        }
    } else {
        crossPromoForm.primary_promotion_id = null;
    }

    crossPromoForm.post('/business/partnerships/cross-promo', {
        onSuccess: () => {
            showCrossPromoModal.value = false;
            crossPromoForm.reset();
        },
    });
};

const openAcceptCrossPromo = (promo) => {
    selectedIncomingCrossPromo.value = promo;
    acceptCrossPromoForm.reset();
    showAcceptCrossPromoModal.value = true;
};

const acceptCrossPromo = () => {
    if (!selectedIncomingCrossPromo.value?.id) return;
    acceptCrossPromoForm.post(`/business/partnerships/cross-promo/${selectedIncomingCrossPromo.value.id}/accept`, {
        onSuccess: () => {
            const crossPromoId = selectedIncomingCrossPromo.value?.id;
            const shouldOpenQr = !!acceptCrossPromoForm.open_qr_after_accept;
            showAcceptCrossPromoModal.value = false;
            selectedIncomingCrossPromo.value = null;
            acceptCrossPromoForm.reset();
            if (shouldOpenQr && crossPromoId) {
                router.get(`/business/qr-codes/create?cross_promo=${crossPromoId}`);
            }
        },
    });
};

const declineCrossPromo = (promoId) => {
    if (!promoId) return;
    if (confirm('Decline this cross-promo request?')) {
        router.post(`/business/partnerships/cross-promo/${promoId}/decline`);
    }
};

const removePartner = (partnershipId) => {
    if (confirm('Are you sure you want to end this partnership? All active cross-promotions with this partner will be deactivated.')) {
        router.delete(`/business/partnerships/${partnershipId}`);
    }
};

const getStatusColor = (status) => {
    return {
        pending: 'bg-yellow-500/20 text-yellow-400',
        accepted: 'bg-emerald-500/20 text-emerald-400',
        declined: 'bg-red-500/20 text-red-400',
        cancelled: 'bg-gray-500/20 text-gray-400',
    }[status] || 'bg-gray-500/20 text-gray-400';
};
</script>

<template>
    <Head title="Partnerships & Partner Deal Chains" />

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Partnerships</h1>
                <p class="text-gray-400 mt-1">Partner with other businesses for Partner Deal Chains</p>
            </div>
            <button 
                @click="showRequestModal = true"
                class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-semibold rounded-xl hover:opacity-90 transition-opacity"
            >
                🤝 Find Partners
            </button>
        </div>

        <!-- Pending Requests Banner -->
        <div v-if="pendingIncoming.length" class="mb-6 p-4 rounded-xl bg-amber-500/20 border border-amber-500/30">
            <div class="flex items-center">
                <span class="text-2xl mr-3">📬</span>
                <div>
                    <h3 class="text-amber-400 font-semibold">{{ pendingIncoming.length }} Partnership Request{{ pendingIncoming.length > 1 ? 's' : '' }}</h3>
                    <p class="text-gray-400 text-sm">Other businesses want to partner with you!</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex space-x-1 mb-6 bg-white/5 p-1 rounded-xl">
            <button 
                @click="activeTab = 'partnerships'"
                :class="[
                    'flex-1 px-4 py-2 rounded-lg font-medium transition-all',
                    activeTab === 'partnerships' 
                        ? 'bg-white/10 text-white' 
                        : 'text-gray-400 hover:text-white'
                ]"
            >
                Partners
                <span v-if="acceptedPartners.length" class="ml-2 px-2 py-0.5 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">
                    {{ acceptedPartners.length }}
                </span>
            </button>
            <button 
                @click="activeTab = 'requests'"
                :class="[
                    'flex-1 px-4 py-2 rounded-lg font-medium transition-all',
                    activeTab === 'requests' 
                        ? 'bg-white/10 text-white' 
                        : 'text-gray-400 hover:text-white'
                ]"
            >
                Requests
                <span v-if="pendingIncoming.length" class="ml-2 px-2 py-0.5 bg-amber-500/20 text-amber-400 text-xs rounded-full">
                    {{ pendingIncoming.length }}
                </span>
            </button>
            <button 
                @click="activeTab = 'cross-promos'"
                :class="[
                    'flex-1 px-4 py-2 rounded-lg font-medium transition-all',
                    activeTab === 'cross-promos' 
                        ? 'bg-white/10 text-white' 
                        : 'text-gray-400 hover:text-white'
                ]"
            >
                Partner Deal Chains
                <span v-if="pendingIncomingCrossPromos?.length" class="ml-2 px-2 py-0.5 bg-amber-500/20 text-amber-400 text-xs rounded-full">
                    {{ pendingIncomingCrossPromos.length }} pending
                </span>
                <span v-else-if="crossPromos.length" class="ml-2 px-2 py-0.5 bg-purple-500/20 text-purple-400 text-xs rounded-full">
                    {{ crossPromos.length }}
                </span>
            </button>
        </div>

        <!-- Partners Tab -->
        <div v-show="activeTab === 'partnerships'" class="space-y-4">
            <div v-if="acceptedPartners.length === 0" class="text-center py-12">
                <div class="w-20 h-20 rounded-full bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <span class="text-4xl">🤝</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">No Partners Yet</h3>
                <p class="text-gray-400 mb-6">Find other businesses to partner with for Partner Deal Chains!</p>
                <button @click="showRequestModal = true" class="px-6 py-3 bg-primary-500 text-white rounded-xl hover:bg-primary-600">
                    Find Partners
                </button>
            </div>

            <div v-else class="grid gap-4">
                <div 
                    v-for="partner in acceptedPartners" 
                    :key="partner.id"
                    class="glass-card p-4 flex items-center justify-between"
                >
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-xl bg-white/10 flex items-center justify-center overflow-hidden">
                            <img v-if="partner.partner.logo" :src="partner.partner.logo" class="w-full h-full object-cover" />
                            <span v-else class="text-2xl">🏢</span>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold">{{ partner.partner.name }}</h3>
                            <p class="text-gray-400 text-sm">{{ partner.partner.category }} • {{ partner.partner.city }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span :class="['px-3 py-1 rounded-full text-xs font-medium', getStatusColor(partner.status)]">
                            {{ partner.status }}
                        </span>
                        <button
                            v-if="partner.is_requester"
                            @click="selectedPartner = partner; showCrossPromoModal = true"
                            class="px-4 py-2 bg-purple-500/20 text-purple-400 rounded-lg hover:bg-purple-500/30 transition-colors"
                        >
                            Create Partner Deal
                        </button>
                        <div v-else class="px-3 py-2 rounded-lg bg-white/5 text-gray-400 text-xs">
                            Waiting on requester to create a deal
                        </div>
                        <button 
                            @click="removePartner(partner.id)"
                            class="p-2 bg-red-500/10 text-red-400 rounded-lg hover:bg-red-500/20 transition-colors ml-2"
                            title="End Partnership"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requests Tab -->
        <div v-show="activeTab === 'requests'" class="space-y-6">
            <!-- Incoming Requests -->
            <div>
                <h3 class="text-lg font-semibold text-white mb-4">📥 Incoming Requests</h3>
                <div v-if="pendingIncoming.length === 0" class="text-center py-8 bg-white/5 rounded-xl">
                    <p class="text-gray-400">No pending requests from other businesses</p>
                </div>
                <div v-else class="space-y-3">
                    <div 
                        v-for="request in pendingIncoming" 
                        :key="request.id"
                        class="glass-card p-4"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center">
                                    <img v-if="request.partner.logo" :src="request.partner.logo" class="w-full h-full object-cover rounded-xl" />
                                    <span v-else class="text-xl">🏢</span>
                                </div>
                                <div>
                                    <h4 class="text-white font-medium">{{ request.partner.name }}</h4>
                                    <p class="text-gray-400 text-sm">{{ request.partner.category }} • {{ request.partner.city }}</p>
                                    <p v-if="request.message" class="text-gray-300 text-sm mt-2 italic">"{{ request.message }}"</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button 
                                    @click="acceptRequest(request.id)"
                                    class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600"
                                >
                                    Accept
                                </button>
                                <button 
                                    @click="declineRequest(request.id)"
                                    class="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30"
                                >
                                    Decline
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outgoing Requests -->
            <div>
                <h3 class="text-lg font-semibold text-white mb-4">📤 Sent Requests</h3>
                <div v-if="pendingOutgoing.length === 0" class="text-center py-8 bg-white/5 rounded-xl">
                    <p class="text-gray-400">No pending outgoing requests</p>
                </div>
                <div v-else class="space-y-3">
                    <div 
                        v-for="request in pendingOutgoing" 
                        :key="request.id"
                        class="glass-card p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center">
                                    <span class="text-xl">🏢</span>
                                </div>
                                <div>
                                    <h4 class="text-white font-medium">{{ request.partner.name }}</h4>
                                    <p class="text-gray-400 text-sm">Waiting for response...</p>
                                </div>
                            </div>
                            <button 
                                @click="cancelRequest(request.id)"
                                class="px-4 py-2 bg-white/10 text-gray-300 rounded-lg hover:bg-white/20"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Partner Chain Deals Tab -->
        <div v-show="activeTab === 'cross-promos'" class="space-y-4">
            <div class="glass-card p-5 border border-white/10">
                <h3 class="text-lg font-semibold text-white mb-2">How Partner Deal Chains work</h3>
                <div class="text-sm text-gray-400 space-y-2">
                    <p><span class="text-white">1)</span> The business that requested the partnership creates the deal and sets the rules.</p>
                    <p><span class="text-white">2)</span> The partner adds their promotion and confirms (this activates the deal).</p>
                    <p><span class="text-white">3)</span> Both businesses can then create a Partner Deal QR code for printing or sharing.</p>
                </div>
                <div class="mt-3 text-xs text-gray-500">
                    Tip: the second business should usually offer the stronger deal to encourage customers to complete the chain.
                </div>
            </div>
            <!-- Pending Partner Deal Requests -->
            <div v-if="(pendingIncomingCrossPromos?.length || 0) > 0" class="space-y-3">
                <h3 class="text-lg font-semibold text-white">Pending Requests</h3>
                <div
                    v-for="promo in pendingIncomingCrossPromos"
                    :key="promo.id"
                    class="glass-card p-5 border border-amber-500/20"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm text-amber-400 font-medium mb-1">Incoming partner deal request</div>
                            <h4 class="text-white font-semibold">{{ promo.name }}</h4>
                            <p class="text-gray-400 text-sm">
                                From {{ promo.requester?.name }}
                                <span v-if="promo.their_promotion?.name"> • Their offer: {{ promo.their_promotion.name }}</span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="openAcceptCrossPromo(promo)"
                                class="px-4 py-2 bg-emerald-500/20 text-emerald-300 rounded-lg hover:bg-emerald-500/30 transition-colors"
                            >
                                Accept
                            </button>
                            <button
                                @click="declineCrossPromo(promo.id)"
                                class="px-4 py-2 bg-red-500/20 text-red-300 rounded-lg hover:bg-red-500/30 transition-colors"
                            >
                                Decline
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="(pendingOutgoingCrossPromos?.length || 0) > 0" class="space-y-3">
                <h3 class="text-lg font-semibold text-white">Waiting on Partner</h3>
                <div
                    v-for="promo in pendingOutgoingCrossPromos"
                    :key="promo.id"
                    class="glass-card p-5 border border-white/10"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm text-gray-400 font-medium mb-1">Pending</div>
                            <h4 class="text-white font-semibold">{{ promo.name }}</h4>
                            <p class="text-gray-400 text-sm">
                                Partner: {{ promo.partner?.name }}
                                <span v-if="promo.my_promotion?.name"> • Your offer: {{ promo.my_promotion.name }}</span>
                            </p>
                        </div>
                        <div class="text-sm text-gray-500">
                            Waiting for partner to choose their offer
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="crossPromos.length === 0" class="text-center py-12">
                <div class="w-20 h-20 rounded-full bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <span class="text-4xl">🎯</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">No Partner Deals Yet</h3>
                <p class="text-gray-400 mb-6">Partner with another business and create your first Partner Deal Chain!</p>
            </div>

            <div v-else class="grid gap-4">
                <div 
                    v-for="promo in crossPromos" 
                    :key="promo.id"
                    class="glass-card p-6"
                >
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ promo.name }}</h3>
                            <p class="text-gray-400 text-sm">Code: {{ promo.code }}</p>
                        </div>
                        <span class="px-3 py-1 bg-purple-500/20 text-purple-400 rounded-full text-sm">
                            {{ displayModes[promo.display_mode] }}
                        </span>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Your Promotion -->
                        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                            <div class="text-emerald-400 text-sm font-medium mb-2">Your Promotion</div>
                            <div class="text-white font-semibold">{{ promo.my_promotion?.name || 'Not set' }}</div>
                            <div class="text-gray-400 text-sm">{{ promo.my_promotion?.discount_type }}</div>
                        </div>

                        <!-- Partner Promotion -->
                        <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/30">
                            <div class="text-blue-400 text-sm font-medium mb-2">Partner: {{ promo.partner.name }}</div>
                            <div class="text-white font-semibold">{{ promo.partner_promotion?.name || 'Not set' }}</div>
                            <div class="text-gray-400 text-sm">{{ promo.partner_promotion?.discount_type }}</div>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="text-gray-400">Revenue Share: {{ promo.revenue_share_percent }}% each</span>
                            <span class="text-gray-400">Chain: {{ chainModes[promo.chain_mode] }}</span>
                            <span v-if="promo.expires_at" class="text-gray-500 text-xs">
                                Expires: {{ new Date(promo.expires_at).toLocaleDateString() }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="`/business/partnerships/cross-promo/${promo.id}/analytics`" 
                                class="px-3 py-1.5 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 text-sm">
                                Analytics
                            </Link>
                            <Link :href="`/business/partnerships/cross-promo/${promo.id}/edit`" 
                                class="px-3 py-1.5 bg-white/10 text-gray-300 rounded-lg hover:bg-white/20 text-sm">
                                Edit
                            </Link>
                            <Link :href="`/business/qr-codes/create?cross_promo=${promo.id}`" 
                                class="px-3 py-1.5 bg-primary-500/20 text-primary-400 rounded-lg hover:bg-primary-500/30 text-sm">
                                Create QR
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Find Partners Modal -->
        <div v-if="showRequestModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
            <div class="bg-gray-900 rounded-2xl p-6 w-full max-w-lg border border-white/10 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">Find Partners</h2>
                    <button @click="showRequestModal = false; searchQuery = ''; requestForm.partner_business_id = null" class="text-gray-400 hover:text-white">✕</button>
                </div>

                <!-- Dropdown Select -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Select Business</label>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <input 
                                v-model="searchQuery"
                                type="text"
                                class="input-glass w-full"
                                placeholder="🔍 Filter by name, city, or category..."
                            />
                            <button type="button" @click="fetchBusinesses(1)" class="px-3 py-2 bg-white/10 text-gray-200 rounded-lg hover:bg-white/20">
                                Search
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto space-y-2 min-h-[200px] max-h-[360px]">
                            <div v-if="!(partnersPage.data || []).length" class="text-center py-6 text-gray-400">
                                No businesses found
                            </div>
                            
                            <div 
                                v-for="business in (partnersPage.data || [])" 
                                :key="business.id"
                                class="p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors cursor-pointer"
                                @click="requestForm.partner_business_id = business.id"
                                :class="{ 'ring-2 ring-primary-500': requestForm.partner_business_id === business.id }"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center overflow-hidden">
                                            <img v-if="business.logo" :src="business.logo" class="w-full h-full object-cover" />
                                            <span v-else>🏢</span>
                                        </div>
                                        <div>
                                            <div class="text-white font-medium">{{ business.name }}</div>
                                            <div class="text-gray-400 text-xs">
                                                {{ business.type || 'Business' }}
                                                <span v-if="business.city"> • {{ business.city }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <span v-if="business.partnership_status" :class="['px-3 py-1 rounded-full text-xs', getStatusColor(business.partnership_status)]">
                                        {{ business.partnership_status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <button
                                type="button"
                                class="px-3 py-1 rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-40"
                                :disabled="!partnersPage.pagination?.prev_page_url"
                                @click="fetchBusinesses(Math.max(1, (partnersPage.pagination?.current_page || 2) - 1))"
                            >
                                ← Prev
                            </button>
                            <span>
                                Page {{ partnersPage.pagination?.current_page || 1 }} / {{ partnersPage.pagination?.last_page || 1 }}
                            </span>
                            <button
                                type="button"
                                class="px-3 py-1 rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-40"
                                :disabled="!partnersPage.pagination?.next_page_url"
                                @click="fetchBusinesses((partnersPage.pagination?.current_page || 1) + 1)"
                            >
                                Next →
                            </button>
                        </div>
                    </div>
                    <p v-if="requestForm.errors.partner_business_id" class="mt-2 text-sm text-red-400">
                        {{ requestForm.errors.partner_business_id }}
                    </p>
                </div>

                <!-- Optional Message -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Message (Optional)</label>
                    <textarea 
                        v-model="requestForm.message"
                        class="input-glass w-full"
                        rows="3"
                        placeholder="Add a personal message to your partnership request..."
                    ></textarea>
                </div>

                <!-- Submit Button -->
                <div class="flex gap-3 pt-4">
                    <button 
                        type="button"
                        @click="showRequestModal = false; searchQuery = ''; requestForm.reset()" 
                        class="flex-1 py-3 bg-white/10 text-white rounded-xl hover:bg-white/20"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button"
                        @click="sendRequest(requestForm.partner_business_id)"
                        :disabled="!requestForm.partner_business_id || requestForm.processing"
                        class="flex-1 py-3 bg-primary-500 text-white rounded-xl hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="requestForm.processing">Sending...</span>
                        <span v-else>Send Request</span>
                    </button>
                </div>

                <!-- legacy browse block removed; paginated list above handles scrolling -->
            </div>
        </div>

        <!-- Create Partner Deal Modal -->
        <div v-if="showCrossPromoModal && selectedPartner" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
                <div class="bg-gray-900 rounded-2xl p-6 w-full max-w-lg border border-white/10 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white">Create Partner Deal Chain</h2>
                    <button @click="showCrossPromoModal = false" class="text-gray-400 hover:text-white">✕</button>
                </div>

                <form @submit.prevent="createCrossPromo" class="space-y-4 overflow-y-auto pr-1" style="max-height: 70vh;">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Partner</label>
                        <div class="p-3 rounded-lg bg-white/5 text-white">
                            {{ selectedPartner.partner.name }}
                        </div>
                        <input type="hidden" v-model="crossPromoForm.partner_business_id" :value="selectedPartner.partner.id" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Deal Name</label>
                        <input v-model="crossPromoForm.name" type="text" class="input-glass w-full" placeholder="e.g., Coffee & Donut Deal" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Your Promotion</label>
                        <select v-model="crossPromoForm.my_promotion_id" class="input-glass w-full" required>
                            <option value="" class="bg-gray-800 text-white">Select your promotion...</option>
                            <option
                                v-for="p in (myPromotions || [])"
                                :key="p.id"
                                :value="p.id"
                                class="bg-gray-800 text-white"
                            >
                                {{ p.name }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 mt-2">
                            Your partner will choose their own offer when they accept.
                        </p>
                    </div>

                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                        <div class="text-emerald-300 text-sm font-medium mb-1">Rules are locked to your promo</div>
                        <p class="text-xs text-gray-300">
                            Your rules (expiry, redemption limits, valid hours/days) apply to both offers. Your partner will see this when they accept.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Display Mode</label>
                        <select v-model="crossPromoForm.display_mode" class="input-glass w-full">
                            <option v-for="(label, value) in displayModes" :key="value" :value="value" class="bg-gray-800 text-white">
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <!-- Chain Mode -->
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Deal Logic</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="crossPromoForm.chain_mode" value="open" class="text-primary-500 focus:ring-primary-500" />
                                    <span class="text-white">Open (Any Order)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="crossPromoForm.chain_mode" value="sequential" class="text-primary-500 focus:ring-primary-500" />
                                    <span class="text-white">Sequential (Unlock)</span>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 mt-2" v-if="crossPromoForm.chain_mode === 'open'">
                                Users can redeem either offer at any time.
                            </p>
                            <p class="text-xs text-gray-500 mt-2" v-if="crossPromoForm.chain_mode === 'sequential'">
                                Users must redeem the first offer to unlock the second offer.
                            </p>
                        </div>

                        <div v-if="crossPromoForm.chain_mode === 'sequential'">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Who goes first?</label>
                            <select v-model="chainOrder" class="input-glass w-full">
                                <option value="me_first" class="bg-gray-800 text-white">My Promotion First (Unlocks Partner)</option>
                                <option value="partner_first" class="bg-gray-800 text-white">Partner's Promotion First (Unlocks Me)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Expiration & Limits -->
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                        <h4 class="text-sm font-medium text-gray-300">Expiration & Limits (Optional)</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Start Date</label>
                                <input type="date" 
                                    v-model="crossPromoForm.starts_at"
                                    class="input-glass w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Expires At</label>
                                <input type="date" 
                                    v-model="crossPromoForm.expires_at"
                                    class="input-glass w-full" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Total Usage Limit (Optional)</label>
                            <input type="number" 
                                v-model.number="crossPromoForm.usage_limit"
                                min="1"
                                placeholder="Unlimited total claims"
                                class="input-glass w-full" />
                            <p class="text-xs text-gray-500 mt-1">Maximum total claims allowed for this cross-promo</p>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="button" @click="showCrossPromoModal = false" class="flex-1 py-3 bg-white/10 text-white rounded-xl hover:bg-white/20">
                            Cancel
                        </button>
                        <button type="submit" :disabled="crossPromoForm.processing" class="flex-1 py-3 bg-primary-500 text-white rounded-xl hover:bg-primary-600 disabled:opacity-50">
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Accept Partner Deal Modal (incoming request) -->
        <div v-if="showAcceptCrossPromoModal && selectedIncomingCrossPromo" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
            <div class="bg-gray-900 rounded-2xl p-6 w-full max-w-lg border border-white/10">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white">Accept Partner Deal</h2>
                    <button @click="showAcceptCrossPromoModal = false" class="text-gray-400 hover:text-white">✕</button>
                </div>

                <div class="mb-4 p-4 rounded-xl bg-white/5 border border-white/10">
                    <p class="text-white font-semibold">{{ selectedIncomingCrossPromo.name }}</p>
                    <p class="text-gray-400 text-sm">
                        From {{ selectedIncomingCrossPromo.requester?.name }}
                        <span v-if="selectedIncomingCrossPromo.their_promotion?.name"> • Their offer: {{ selectedIncomingCrossPromo.their_promotion.name }}</span>
                        <span
                            v-if="selectedIncomingCrossPromo.their_promotion && selectedIncomingCrossPromo.their_promotion.is_active === false"
                            class="ml-2 px-2 py-0.5 text-xs rounded-full bg-red-500/15 border border-red-500/30 text-red-300"
                        >
                            Inactive
                        </span>
                        <span
                            v-else-if="selectedIncomingCrossPromo.their_promotion?.ends_at"
                            class="ml-2 px-2 py-0.5 text-xs rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-200"
                        >
                            Ends: {{ new Date(selectedIncomingCrossPromo.their_promotion.ends_at).toLocaleDateString() }}
                        </span>
                    </p>
                    <div class="text-xs text-gray-400 mt-2 space-y-1">
                        <div><span class="text-gray-500">Display:</span> {{ displayModes[selectedIncomingCrossPromo.display_mode] }}</div>
                        <div><span class="text-gray-500">Chain:</span> {{ chainModes[selectedIncomingCrossPromo.chain_mode] }}</div>
                    </div>
                </div>

                <div class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                    <h4 class="text-emerald-300 font-medium mb-2">Rules are locked to the requester</h4>
                    <p class="text-sm text-gray-300">
                        All expiry, redemption limits, and schedule rules are set by the requester’s promotion and apply to both offers.
                    </p>
                </div>

                <form @submit.prevent="acceptCrossPromo" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Your Promotion</label>
                        <select v-model="acceptCrossPromoForm.my_promotion_id" class="input-glass w-full" required>
                            <option value="" class="bg-gray-800 text-white">Select your promotion...</option>
                            <option
                                v-for="p in (myPromotions || [])"
                                :key="p.id"
                                :value="p.id"
                                class="bg-gray-800 text-white"
                            >
                                {{ p.name }}
                            </option>
                        </select>
                        <p v-if="acceptCrossPromoForm.errors.my_promotion_id" class="mt-2 text-sm text-red-400">
                            {{ acceptCrossPromoForm.errors.my_promotion_id }}
                        </p>
                        <p class="text-xs text-gray-500 mt-2">
                            Tip: your offer is the “second step” for most customers, so a stronger deal usually performs best.
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-300">
                        <input type="checkbox" v-model="acceptCrossPromoForm.agree" class="text-primary-500 focus:ring-primary-500" />
                        <span>I agree to the requester’s terms, display/chain mode, and promo rules.</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300">
                        <input type="checkbox" v-model="acceptCrossPromoForm.open_qr_after_accept" class="text-primary-500 focus:ring-primary-500" />
                        <span>After I accept, take me to create the Partner Deal QR.</span>
                    </label>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="showAcceptCrossPromoModal = false" class="flex-1 py-3 bg-white/10 text-white rounded-xl hover:bg-white/20">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="
                                acceptCrossPromoForm.processing
                                || !acceptCrossPromoForm.agree
                                || (selectedIncomingCrossPromo.their_promotion && selectedIncomingCrossPromo.their_promotion.is_active === false)
                            "
                            class="flex-1 py-3 bg-emerald-500 text-white rounded-xl hover:bg-emerald-600 disabled:opacity-50"
                        >
                            <span v-if="acceptCrossPromoForm.processing">Accepting...</span>
                            <span v-else-if="selectedIncomingCrossPromo.their_promotion && selectedIncomingCrossPromo.their_promotion.is_active === false">Partner promo inactive</span>
                            <span v-else>Accept</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
