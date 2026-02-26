<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';
import axios from 'axios';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    promotions: {
        type: Object,
        default: () => ({ data: [] }),
    },
});

// Handle both paginated (object with data array) and non-paginated (direct array) responses
const promotionsList = computed(() => {
    if (Array.isArray(props.promotions)) {
        return props.promotions;
    }
    return props.promotions?.data || [];
});

const hasPromotions = computed(() => {
    return promotionsList.value.length > 0;
});

const formatDiscount = (promo) => {
    if (promo.discount_type === 'percentage') {
        return `${promo.discount_value || 0}% off`;
    } else if (promo.discount_type === 'fixed_amount' || promo.discount_type === 'fixed') {
        return `$${promo.discount_value} off`;
    } else if (promo.discount_type === 'bogo') {
        return 'Buy One Get One';
    } else if (promo.discount_type === 'buy_x_get_y') {
        return `Buy ${promo.buy_quantity} Get ${promo.get_quantity}`;
    } else if (promo.discount_type === 'punch_card') {
        return `Punch Card (${promo.punches_required} punches)`;
    } else if (promo.discount_type === 'happy_hour') {
        return `Happy Hour ${promo.discount_value || 0}% off`;
    } else if (promo.discount_type === 'first_time') {
        return `First Visit ${promo.discount_value || 0}% off`;
    } else if (promo.discount_type === 'tiered') {
        return 'Tiered Discount';
    } else if (promo.discount_type === 'free_item') {
        return 'Free Item';
    }
    return promo.discount_type?.replace(/_/g, ' ') || 'Special Offer';
};

const getDiscountTypeIcon = (type) => {
    const icons = {
        percentage: '🏷️',
        fixed_amount: '💵',
        fixed: '💵',
        bogo: '🎁',
        buy_x_get_y: '🛍️',
        punch_card: '⭐',
        happy_hour: '🕐',
        first_time: '👋',
        tiered: '📈',
        free_item: '🎉',
    };
    return icons[type] || '🏷️';
};

const deleteModal = ref({ show: false, promo: null, warnings: [], loading: false });

const deletePromotion = async (promo) => {
    deleteModal.value = { show: false, promo, warnings: [], loading: true };
    try {
        const { data } = await axios.get(route('business.promotions.check-delete', promo.id));
        if (data.warnings && data.warnings.length > 0) {
            deleteModal.value = { show: true, promo, warnings: data.warnings, loading: false };
            return;
        }
    } catch (e) {
        // If check fails, fall through to simple confirm
    }
    deleteModal.value.loading = false;
    if (confirm(`Are you sure you want to delete "${promo.name}"?\n\nThis action cannot be undone.`)) {
        router.delete(route('business.promotions.destroy', promo.id));
    }
};

const confirmDeletePromo = () => {
    const p = deleteModal.value.promo;
    deleteModal.value = { show: false, promo: null, warnings: [], loading: false };
    if (p) {
        router.delete(route('business.promotions.destroy', p.id));
    }
};

const cancelDeletePromo = () => {
    deleteModal.value = { show: false, promo: null, warnings: [], loading: false };
};
</script>

<template>
    <Head title="Promotions" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Promotions</h1>
                <p class="text-gray-400 mt-1">Create and manage your discounts and deals</p>
            </div>
            <div class="flex gap-3">
                <Link href="/business/promotion-ideas" class="btn-glass">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    💡 Ideas
                </Link>
                <Link href="/business/promotion-templates" class="btn-glass">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                    </svg>
                    Templates
                </Link>
                <Link href="/business/promotions/create" class="btn-primary">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Promotion
                </Link>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!hasPromotions" class="glass-card p-12 text-center">
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-accent-500/20 to-primary-500/20 flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">No promotions yet</h3>
            <p class="text-gray-400 mb-6 max-w-md mx-auto">
                Create your first promotion to start attracting customers. Choose from templates or build your own custom deal.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <Link href="/business/promotion-templates" class="btn-glass">
                    Browse Templates
                </Link>
                <Link href="/business/promotions/create" class="btn-accent">
                    Create Your First Promotion
                </Link>
            </div>
            
            <!-- Quick Ideas -->
            <div class="mt-10 pt-8 border-t border-white/10">
                <h4 class="text-sm font-medium text-gray-400 mb-4">Popular Promotion Types</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-2xl mx-auto">
                    <div class="glass-card p-4 text-center hover:bg-white/10 transition-colors cursor-pointer" @click="$inertia.get('/business/promotions/create?type=percentage')">
                        <div class="text-2xl mb-2">🏷️</div>
                        <span class="text-sm text-gray-300">Percentage Off</span>
                    </div>
                    <div class="glass-card p-4 text-center hover:bg-white/10 transition-colors cursor-pointer" @click="$inertia.get('/business/promotions/create?type=bogo')">
                        <div class="text-2xl mb-2">🎁</div>
                        <span class="text-sm text-gray-300">BOGO</span>
                    </div>
                    <div class="glass-card p-4 text-center hover:bg-white/10 transition-colors cursor-pointer" @click="$inertia.get('/business/promotions/create?type=punch_card')">
                        <div class="text-2xl mb-2">⭐</div>
                        <span class="text-sm text-gray-300">Punch Card</span>
                    </div>
                    <div class="glass-card p-4 text-center hover:bg-white/10 transition-colors cursor-pointer" @click="$inertia.get('/business/promotions/create?type=happy_hour')">
                        <div class="text-2xl mb-2">🕐</div>
                        <span class="text-sm text-gray-300">Happy Hour</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promotions Grid -->
        <div v-else>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div 
                    v-for="promo in promotionsList" 
                    :key="promo.id" 
                    class="glass-card p-6 hover:bg-white/5 transition-all group"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ getDiscountTypeIcon(promo.discount_type) }}</span>
                            <h3 class="text-lg font-semibold text-white group-hover:text-primary-400 transition-colors">
                                {{ promo.name }}
                            </h3>
                        </div>
                        <span :class="[
                            'px-2 py-1 rounded text-xs font-medium',
                            promo.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'
                        ]">
                            {{ promo.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    
                    <p class="text-gray-400 text-sm mb-4 line-clamp-2 min-h-[40px]">
                        {{ promo.description || 'No description provided' }}
                    </p>
                    
                    <div class="bg-gradient-to-r from-primary-500/20 to-accent-500/20 rounded-lg p-3 mb-4">
                        <span class="text-lg font-bold gradient-text">{{ formatDiscount(promo) }}</span>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-2 mb-4 text-sm">
                        <div class="bg-white/5 rounded-lg p-2 text-center">
                            <div class="text-white font-semibold">{{ promo.redemptions_count || 0 }}</div>
                            <div class="text-gray-500 text-xs">Redemptions</div>
                        </div>
                        <div class="bg-white/5 rounded-lg p-2 text-center">
                            <div class="text-white font-semibold">{{ promo.qr_codes_count || 0 }}</div>
                            <div class="text-gray-500 text-xs">QR Codes</div>
                        </div>
                    </div>

                    <div class="flex justify-between items-center pt-4 border-t border-white/10">
                        <div class="flex gap-3">
                            <Link :href="`/business/promotions/${promo.id}`" class="text-gray-400 hover:text-white text-sm transition-colors">
                                View →
                            </Link>
                            <Link :href="`/business/promotions/${promo.id}/edit`" class="text-primary-400 hover:text-primary-300 text-sm transition-colors">
                                Edit
                            </Link>
                        </div>
                        <button
                            @click="deletePromotion(promo)"
                            class="text-red-400 hover:text-red-300 text-sm transition-colors"
                            title="Delete promotion"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="promotions.last_page > 1" class="mt-8 flex justify-center">
                <nav class="flex items-center gap-2">
                    <Link 
                        v-if="promotions.current_page > 1"
                        :href="promotions.prev_page_url"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                    >
                        Previous
                    </Link>
                    <span class="px-4 py-2 text-gray-400">
                        Page {{ promotions.current_page }} of {{ promotions.last_page }}
                    </span>
                    <Link 
                        v-if="promotions.current_page < promotions.last_page"
                        :href="promotions.next_page_url"
                        class="px-4 py-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                    >
                        Next
                    </Link>
                </nav>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
        <div v-if="deleteModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="cancelDeletePromo"></div>
            <div class="relative w-full max-w-md bg-slate-900 border border-white/20 rounded-2xl shadow-2xl p-6">
                <h3 class="text-lg font-bold text-white mb-2">Delete "{{ deleteModal.promo?.name }}"?</h3>
                <p class="text-gray-400 text-sm mb-4">This promotion has linked data that will be affected:</p>
                <ul class="space-y-2 mb-6">
                    <li v-for="(w, i) in deleteModal.warnings" :key="i" class="flex gap-2 text-sm">
                        <span class="text-amber-400 flex-shrink-0">&#9888;</span>
                        <span class="text-gray-300">{{ w }}</span>
                    </li>
                </ul>
                <div class="flex gap-3 justify-end">
                    <button @click="cancelDeletePromo" class="px-4 py-2 rounded-lg bg-white/10 text-gray-200 hover:bg-white/20 border border-white/10 text-sm">
                        Cancel
                    </button>
                    <button @click="confirmDeletePromo" class="px-4 py-2 rounded-lg bg-red-500/80 text-white hover:bg-red-500 text-sm font-semibold">
                        Delete Anyway
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

