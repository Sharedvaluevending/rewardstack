<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    scans: Object,
    savedPromotions: Object, // paginator
    claimedRewards: Array,
    punchCards: Array,
    customerCode: String,
    stats: Object,
    businesses: Array,
    discountTypes: Object,
    filters: Object,
    savedFilters: Object,
});

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        timeZone: 'America/Toronto',
    });
};

// Filter state
const activePromotionType = ref(props.filters?.promotion_type || null);
const activeBusinessId = ref(props.filters?.business_id || null);
const activeStatus = ref(props.filters?.status || 'all');
const crossPromoOnly = ref(props.filters?.cross_promo_only || false);

const togglePartnerDealsOnly = () => {
    // Partner Deals are cross-promos (qr_code.type === 'cross_promo'), not a promotion discount type.
    // Keep UX predictable: when switching to Partner Deals only, clear promo/status filters.
    crossPromoOnly.value = !crossPromoOnly.value;
    if (crossPromoOnly.value) {
        activePromotionType.value = null;
        activeStatus.value = 'all';
    }
    applyFilters();
};

// Save/Unsave functionality
const saving = ref({});
const toggleSave = async (qrCodeId, isSaved) => {
    saving.value[qrCodeId] = true;
    try {
        if (isSaved) {
            await router.delete(`/portal/qr-codes/${qrCodeId}/unsave`, {
                preserveState: false, // Don't preserve state to ensure fresh data
                preserveScroll: true,
                onSuccess: () => {
                    // Force a full page reload to ensure the unsaved item is removed
                    router.reload({ only: ['savedPromotions', 'stats'] });
                },
                onError: () => {
                    // Even on error, reload to ensure consistency
                    router.reload({ only: ['savedPromotions', 'stats'] });
                }
            });
        } else {
            await router.post(`/portal/qr-codes/${qrCodeId}/save`, {}, {
                preserveState: false, // Don't preserve state to ensure fresh data
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['savedPromotions', 'stats'] });
                },
                onError: () => {
                    // Even on error, reload to ensure consistency
                    router.reload({ only: ['savedPromotions', 'stats'] });
                }
            });
        }
    } catch (error) {
        console.error('Error saving/unsaving QR code:', error);
    } finally {
        saving.value[qrCodeId] = false;
    }
};

// Saved carousel controls (horizontal scroll)
const savedCarouselEl = ref(null);
const scrollSaved = (direction) => {
    const el = savedCarouselEl.value;
    if (!el) return;
    const amount = Math.max(260, Math.floor(el.clientWidth * 0.85));
    el.scrollBy({ left: direction * amount, behavior: 'smooth' });
};

// Punch card carousel controls
const punchCardCarouselEl = ref(null);
const punchCardScrollLeft = ref(0);
const punchCardCurrentIndex = ref(0);
const punchCardMaxScroll = ref(0);

const scrollPunchCard = (direction) => {
    const el = punchCardCarouselEl.value;
    if (!el) return;
    const cardWidth = el.querySelector('.snap-start')?.offsetWidth || 0;
    const gap = 16; // gap-4 = 16px
    const scrollAmount = cardWidth + gap;
    el.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
};

// Update scroll position and current index
const updatePunchCardScroll = () => {
    const el = punchCardCarouselEl.value;
    if (!el) return;
    punchCardScrollLeft.value = el.scrollLeft;
    punchCardMaxScroll.value = el.scrollWidth - el.clientWidth;
    
    // Calculate current index based on scroll position
    const cardWidth = el.querySelector('.snap-start')?.offsetWidth || 0;
    const gap = 16;
    if (cardWidth > 0) {
        const index = Math.round(el.scrollLeft / (cardWidth + gap));
        punchCardCurrentIndex.value = Math.min(index, filteredPunchCards.value.length - 1);
    }
};

// Watch for carousel mount and scroll events
onMounted(() => {
    const el = punchCardCarouselEl.value;
    if (el) {
        el.addEventListener('scroll', updatePunchCardScroll);
        setTimeout(() => updatePunchCardScroll(), 100);
    }
});

onUnmounted(() => {
    const el = punchCardCarouselEl.value;
    if (el) {
        el.removeEventListener('scroll', updatePunchCardScroll);
    }
});

const savedSearch = ref(props.savedFilters?.q || '');
const applySavedSearch = () => {
    const params = {};
    if (activePromotionType.value) params.promotion_type = activePromotionType.value;
    if (activeBusinessId.value) params.business_id = activeBusinessId.value;
    if (activeStatus.value !== 'all') params.status = activeStatus.value;
    if (crossPromoOnly.value) params.cross_promo_only = true;
    if (savedSearch.value) params.saved_q = savedSearch.value;

    router.get('/portal/scans', params, {
        preserveState: false,
        preserveScroll: true,
    });
};

// Apply filters
const applyFilters = () => {
    const params = {};
    if (activePromotionType.value) params.promotion_type = activePromotionType.value;
    if (activeBusinessId.value) params.business_id = activeBusinessId.value;
    if (activeStatus.value !== 'all') params.status = activeStatus.value;
    if (crossPromoOnly.value) params.cross_promo_only = true;
    if (savedSearch.value) params.saved_q = savedSearch.value;
    
    router.get('/portal/scans', params, {
        preserveState: false,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    activePromotionType.value = null;
    activeBusinessId.value = null;
    activeStatus.value = 'all';
    crossPromoOnly.value = false;
    savedSearch.value = '';
    router.get('/portal/scans', {}, {
        preserveState: false,
        preserveScroll: true,
    });
};

const hasActiveFilters = computed(() => {
    return activePromotionType.value || activeBusinessId.value || activeStatus.value !== 'all' || crossPromoOnly.value;
});

// Get active filter count
const activeFilterCount = computed(() => {
    let count = 0;
    if (activePromotionType.value) count++;
    if (activeBusinessId.value) count++;
    if (activeStatus.value !== 'all') count++;
    if (crossPromoOnly.value) count++;
    return count;
});

// Filter punch cards client-side so user expectations match the filters
const filteredPunchCards = computed(() => {
    let cards = props.punchCards || [];

    if (activePromotionType.value) {
        cards = cards.filter(c => c?.promotion?.discount_type === activePromotionType.value);
    }

    if (activeBusinessId.value) {
        cards = cards.filter(c => String(c?.business?.id) === String(activeBusinessId.value));
    }

    if (activeStatus.value === 'active') {
        cards = cards.filter(c => c?.is_active === true);
    } else if (activeStatus.value === 'expired') {
        cards = cards.filter(c => c?.is_active === false);
    } else if (activeStatus.value === 'redeemed') {
        cards = cards.filter(c => (c?.completed_cards || 0) > 0);
    }

    return cards;
});

// Watch for filtered punch cards changes to update scroll
watch(() => filteredPunchCards.value, () => {
    setTimeout(() => {
        updatePunchCardScroll();
    }, 100);
}, { deep: true });

const scanActionLabel = (scan) => {
    if (scan?.removed_by_business) return null;
    const type = scan?.qr_code?.type;
    if (scan?.is_winner) return 'Winner →';
    if (scan?.is_non_winner) return 'Try Again';
    if (scan?.reward_link || scan?.reward_promo_code) return 'View Reward →';
    if (scan?.play_again) return 'Play Again →';
    if (scan?.leaderboard_link) return 'View Leaderboard →';
    if (type === 'promotion') return 'View →';
    return 'View →';
};

const promoLink = (code) => (code ? `/promo/${code}?source=portal` : '#');

const scanActionHref = (scan) => {
    if (scan?.removed_by_business) return null;
    if (scan?.is_non_winner) return null;
    if (scan?.reward_promo_code) return promoLink(scan.reward_promo_code);
    if (scan?.reward_link) return scan.reward_link;
    if (scan?.play_again && scan?.play_link) return scan.play_link;
    if (scan?.leaderboard_link) return scan.leaderboard_link;
    // For promotions, use customer promo code if available
    if (scan?.qr_code?.type === 'promotion' && scan?.customer_promo?.code) {
        return promoLink(scan.customer_promo.code);
    }
    const code = scan?.qr_code?.code;
    const type = scan?.qr_code?.type;
    if (!code) return '#';
    if (type === 'promotion') return promoLink(code);
    if (type === 'cross_promo') return promoLink(code);
    return scan.play_link || `/play/${code}`;
};

const filteredRecentScans = computed(() => {
    let rows = props.scans?.data || [];

    if (dismissedScanIds.value.length > 0) {
        const set = new Set(dismissedScanIds.value);
        rows = rows.filter(s => !set.has(s.id));
    }

    if (activeBusinessId.value) {
        rows = rows.filter(s => String(s?.business?.id) === String(activeBusinessId.value));
    }

    if (crossPromoOnly.value) {
        return rows.filter(s => s?.qr_code?.type === 'cross_promo');
    }

    if (activePromotionType.value) {
        rows = rows.filter(s => {
            const promoDt = s?.qr_code?.promotion?.discount_type;
            const winDt = s?.reward_promotion_discount_type;
            if (activePromotionType.value === 'fixed_amount') {
                // Legacy alias support
                return promoDt === 'fixed_amount' || promoDt === 'fixed' || winDt === 'fixed_amount' || winDt === 'fixed';
            }
            return promoDt === activePromotionType.value || winDt === activePromotionType.value;
        });
    }

    if (activeStatus.value !== 'all') {
        const now = new Date();
        rows = rows.filter(s => {
            if (activeStatus.value === 'redeemed') {
                return s.is_redeemed === true;
            }
            
            const p = s?.qr_code?.promotion;
            // Cross-promos (Partner Deals) don't have qr_code.promotion; treat as "active" scans.
            if (!p) {
                return activeStatus.value === 'active' && !s.is_redeemed;
            }
            const startsAt = p.starts_at ? new Date(p.starts_at) : null;
            const endsAt = p.ends_at ? new Date(p.ends_at) : null;
            const isTimeActive = (!startsAt || startsAt <= now) && (!endsAt || endsAt >= now);
            const isActive = (p.is_active !== false) && isTimeActive;
            
            if (activeStatus.value === 'active') return isActive && !s.is_redeemed;
            if (activeStatus.value === 'expired') return !isActive; // only truly expired/inactive
            return true;
        });
    }

    return rows;
});

const DISMISS_KEY = 'portalDismissedScans';
const dismissedScanIds = ref([]);

const loadDismissed = () => {
    try {
        const raw = localStorage.getItem(DISMISS_KEY);
        if (raw) {
            const arr = JSON.parse(raw);
            if (Array.isArray(arr)) dismissedScanIds.value = arr;
        }
    } catch (_) {
        // ignore
    }
};

const saveDismissed = () => {
    try {
        localStorage.setItem(DISMISS_KEY, JSON.stringify(dismissedScanIds.value));
    } catch (_) {
        // ignore
    }
};
const dismissScan = async (id) => {
    if (!id) return;
    try {
        await router.delete(`/portal/scans/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['scans', 'stats'] });
            },
        });
    } catch (error) {
        // fall back to client-side hide if delete fails
        dismissedScanIds.value = Array.from(new Set([...dismissedScanIds.value, id]));
        saveDismissed();
    }
};

const combinedPromotions = computed(() => {
    // Deduplicate rewards by promotion_id (safety net — backend already deduplicates,
    // but guard against stale Inertia cache showing duplicates for repeat leaderboard wins).
    const seenPromoIds = new Set();
    const rewards = (props.claimedRewards || [])
        .filter(r => r?.status !== 'available') // don't show unclaimed rewards in My Promotions
        .filter(r => !r?.promotion?.is_punch_card) // punch cards live in the punch-card carousel
        .filter(r => {
            const pid = r?.promotion_id;
            if (pid && seenPromoIds.has(pid)) return false;
            if (pid) seenPromoIds.add(pid);
            return true;
        })
        .map(r => ({
            ...r,
            is_game_reward: true,
            type: 'reward',
            qr_code: { id: `reward_${r.id}`, name: r.description },
            customer_promo: r.customer_promo || { code: r.display_code || null, qr_image_url: r.qr_image_url },
            save_qr_code_id: r.promo_qr_code_id || null,
            is_saved: r.promo_is_saved ?? false,
        }));

    const rewardPromotionIds = new Set(
        rewards.map(r => r.promotion_id).filter(Boolean)
    );
    
    const saved = (props.savedPromotions?.data || [])
        .filter(s => !s?.promotion?.is_punch_card)
        .filter(s => {
            const promoId = s?.promotion?.id || s?.promotion_id || s?.qr_code?.promotion_id;
            return !promoId || !rewardPromotionIds.has(promoId);
        })
        .map(s => ({
            ...s,
            is_game_reward: false,
            type: 'promotion',
            save_qr_code_id: s?.qr_code?.id || null,
        }));

    return [...rewards, ...saved];
});

const getSaveId = (item) => item?.save_qr_code_id || item?.qr_code?.id;
const canSaveItem = (item) => {
    const saveId = getSaveId(item);
    if (!saveId) return false;
    if (item?.is_game_reward) return !!item?.save_qr_code_id;
    return true;
};

const availableRewards = computed(() =>
    (props.claimedRewards || []).filter((reward) => reward.status === 'available')
);

onMounted(() => {
    loadDismissed();
});

// If scans were reset and IDs reused, previously dismissed IDs can hide all rows.
// Auto-clear dismissed IDs when they would hide every scan on the current page.
watch(() => props.scans?.data, (rows) => {
    const data = Array.isArray(rows) ? rows : [];
    if (!data.length || dismissedScanIds.value.length === 0) return;
    const dismissedSet = new Set(dismissedScanIds.value);
    const allHidden = data.every((row) => dismissedSet.has(row.id));
    if (allHidden) {
        dismissedScanIds.value = [];
        saveDismissed();
    }
}, { deep: true });

const copyText = async (text) => {
    const code = text;
    if (!code) return;
    try {
        await navigator.clipboard.writeText(code);
    } catch (e) {
        // fallback: do nothing silently (some browsers block clipboard without gesture/https)
    }
};
</script>

<template>
    <PortalLayout>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">My Scans & Promotions 📱</h1>
            <p class="text-gray-400 text-sm mt-1">View your scanned QR codes and saved promotions</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-blue-400">{{ stats.total_scans }}</div>
                <div class="text-gray-400 text-xs mt-1">Total Scans</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-purple-400">{{ stats.promotions_scanned }}</div>
                <div class="text-gray-400 text-xs mt-1">Promotions</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-green-400">{{ (punchCards?.length || 0) }}</div>
                <div class="text-gray-400 text-xs mt-1">Punch Cards</div>
            </div>
            <div class="bg-white/5 backdrop-blur rounded-xl p-4 text-center border border-white/10">
                <div class="text-2xl font-bold text-emerald-400">{{ stats.total_redeemed || 0 }}</div>
                <div class="text-gray-400 text-xs mt-1">Redeemed</div>
            </div>
        </div>

        <!-- Redeem Rewards -->
        <div v-if="availableRewards.length" class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-white">Redeem Rewards</h2>
                <Link href="/portal/rewards" class="text-primary-400 hover:text-primary-300 text-sm font-medium">See all →</Link>
            </div>
            <div class="glass-card p-4">
                <div class="space-y-3">
                    <Link
                        v-for="reward in availableRewards"
                        :key="reward.id"
                        :href="`/portal/rewards/${reward.id}`"
                        class="block bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10 hover:border-primary-500/40 transition-colors"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500/80 to-orange-500/80 flex items-center justify-center text-2xl">
                                🎁
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-medium truncate">{{ reward.description || 'Reward' }}</div>
                                <div class="text-gray-400 text-sm">{{ reward.business?.name }}</div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs px-2 py-1 rounded bg-purple-500/20 text-purple-300">
                                    Available
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="mb-6 glass-card p-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white">Filters</h2>
                <button 
                    v-if="hasActiveFilters"
                    @click="clearFilters"
                    class="text-xs text-gray-400 hover:text-white transition-colors"
                >
                    Clear Filters ({{ activeFilterCount }})
                </button>
            </div>

            <!-- Promotion Type Filter -->
            <div class="mb-4">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <label class="block text-sm font-medium text-gray-300">Promotion Type</label>
                    <Link
                        href="/portal/partner-deals"
                        class="text-xs text-primary-400 hover:text-primary-300 font-medium whitespace-nowrap"
                    >
                        View Partner Deals →
                    </Link>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="activePromotionType = null; applyFilters()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                            !activePromotionType && !crossPromoOnly
                                ? 'bg-primary-500 text-white' 
                                : 'bg-white/5 text-gray-400 hover:bg-white/10'
                        ]"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        @click="togglePartnerDealsOnly"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-1',
                            crossPromoOnly
                                ? 'bg-blue-500 text-white'
                                : 'bg-white/5 text-gray-400 hover:bg-white/10'
                        ]"
                    >
                        <span>🤝</span>
                        <span>Partner Deals</span>
                    </button>
                    <button
                        v-for="(label, type) in discountTypes"
                        :key="type"
                        @click="crossPromoOnly = false; activePromotionType = type; applyFilters()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                            activePromotionType === type 
                                ? 'bg-primary-500 text-white' 
                                : 'bg-white/5 text-gray-400 hover:bg-white/10'
                        ]"
                    >
                        {{ label }}
                    </button>
                </div>
            </div>

            <!-- Business Filter -->
            <div class="mb-4" v-if="businesses && businesses.length > 0">
                <label class="block text-sm font-medium text-gray-300 mb-2">Business</label>
                <select
                    v-model="activeBusinessId"
                    @change="applyFilters()"
                    class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-primary-500/50"
                >
                    <option :value="null" class="bg-gray-800 text-white">All Businesses</option>
                    <option v-for="business in businesses" :key="business.id" :value="business.id" class="bg-gray-800 text-white">
                        {{ business.name }}
                    </option>
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <div class="flex gap-2">
                    <button
                        @click="activeStatus = 'all'; applyFilters()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                            activeStatus === 'all' 
                                ? 'bg-primary-500 text-white' 
                                : 'bg-white/5 text-gray-400 hover:bg-white/10'
                        ]"
                    >
                        All
                    </button>
                    <button
                        @click="activeStatus = 'active'; applyFilters()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                            activeStatus === 'active' 
                                ? 'bg-green-500 text-white' 
                                : 'bg-white/5 text-gray-400 hover:bg-white/10'
                        ]"
                    >
                        Active
                    </button>
                    <button
                        @click="activeStatus = 'expired'; applyFilters()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                            activeStatus === 'expired' 
                                ? 'bg-red-500 text-white' 
                                : 'bg-white/5 text-gray-400 hover:bg-white/10'
                        ]"
                    >
                        Expired
                    </button>
                    <button
                        @click="activeStatus = 'redeemed'; applyFilters()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                            activeStatus === 'redeemed' 
                                ? 'bg-emerald-500 text-white' 
                                : 'bg-white/5 text-gray-400 hover:bg-white/10'
                        ]"
                    >
                        Redeemed
                    </button>
                </div>
            </div>
        </div>

        <!-- Punch Cards Section (Carousel) -->
        <div v-if="filteredPunchCards.length > 0" class="mb-6">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <h2 class="text-lg font-semibold text-white">🎯 My Punch Cards</h2>
                    <div class="text-xs text-gray-400 mt-1">
                        Show staff your punch card promo QR/code below to add punches.
                    </div>
                </div>
            </div>

            <!-- Carousel Container -->
            <div class="relative">
                <!-- Carousel -->
                <div 
                    ref="punchCardCarouselEl"
                    class="flex gap-4 overflow-x-auto scrollbar-hide snap-x snap-mandatory pb-4"
                    style="scrollbar-width: none; -ms-overflow-style: none;"
                >
                    <div
                        v-for="card in filteredPunchCards"
                        :key="card.id"
                        class="flex-shrink-0 w-full max-w-sm snap-start"
                    >
                        <Link
                            :href="card.customer_promo?.code ? promoLink(card.customer_promo.code) : (card.promotion.qr_code?.code ? promoLink(card.promotion.qr_code.code) : '#')"
                            class="block glass-card p-5 hover:border-primary-500/50 transition-colors"
                        >
                            <div class="flex flex-col gap-4">
                                <!-- Header -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-white font-medium text-lg mb-1">{{ card.promotion.name }}</h3>
                                        <span class="text-xs text-gray-400">{{ card.business?.name }}</span>
                                    </div>
                                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-2xl flex-shrink-0">
                                        {{ card.promotion.punch_icon }}
                                    </div>
                                </div>

                                <!-- Progress -->
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-2">
                                        <span class="text-gray-400">Progress</span>
                                        <span class="text-white font-medium">{{ card.current_punches }} / {{ card.promotion.punches_required }}</span>
                                    </div>
                                    <div class="h-2 bg-gray-700 rounded-full overflow-hidden">
                                        <div 
                                            class="h-full bg-gradient-to-r from-green-500 to-emerald-500 transition-all duration-500"
                                            :style="{ width: card.progress_percent + '%' }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Stats -->
                                <div class="flex items-center gap-4 text-xs text-gray-400">
                                    <span v-if="card.completed_cards > 0">✅ {{ card.completed_cards }} completed</span>
                                    <span v-if="card.last_punch_at">Last: {{ card.last_punch_at }}</span>
                                </div>

                                <!-- Disabled message -->
                                <div
                                    v-if="card.is_active === false"
                                    class="p-3 rounded-xl bg-red-500/10 border border-red-500/30"
                                >
                                    <p class="text-red-300 text-sm font-medium">This promotion is no longer available.</p>
                                    <p class="text-gray-300 text-xs mt-1">Visit business for newer promotions and discounts.</p>
                                </div>

                                <!-- Customer Promo Code & QR -->
                                <div v-if="card.customer_promo" class="mt-2 p-4 rounded-xl bg-white/5 border border-white/10">
                                    <div class="flex flex-col items-center gap-3">
                                        <div v-if="card.customer_promo.qr_image_url" class="w-32 h-32 bg-white p-3 rounded-lg">
                                            <img :src="card.customer_promo.qr_image_url" alt="Customer promo QR" class="w-full h-full object-contain" />
                                        </div>
                                        <div class="w-full text-center">
                                            <div class="text-xs text-gray-400 mb-2">Customer Promo Code</div>
                                            <div class="text-white font-mono text-base tracking-wider mb-3 break-all">{{ card.customer_promo.code }}</div>
                                            <button
                                                type="button"
                                                class="w-full px-4 py-2 rounded-lg bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10 text-sm font-semibold transition-colors"
                                                @click.prevent="copyText(card.customer_promo.code)"
                                            >
                                                Copy Code
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Carousel Navigation -->
                <div v-if="filteredPunchCards.length > 1" class="flex items-center justify-center gap-2 mt-4">
                    <button
                        @click="scrollPunchCard(-1)"
                        class="p-2 rounded-lg bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
                        :disabled="punchCardScrollLeft === 0"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div class="flex gap-1">
                        <div
                            v-for="(card, index) in filteredPunchCards"
                            :key="card.id"
                            class="w-2 h-2 rounded-full transition-all"
                            :class="punchCardCurrentIndex === index ? 'bg-primary-500 w-6' : 'bg-white/20'"
                        ></div>
                    </div>
                    <button
                        @click="scrollPunchCard(1)"
                        class="p-2 rounded-lg bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
                        :disabled="punchCardScrollLeft >= punchCardMaxScroll"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- My Promotions (Saved + Game Rewards) -->
        <div v-if="combinedPromotions.length > 0" class="mb-6">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <h2 class="text-lg font-semibold text-white">My Promotions</h2>
                    <div class="text-xs text-gray-400 mt-1">Saved ({{ savedPromotions.total || savedPromotions.data.length }})</div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="px-3 py-2 rounded-lg bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10"
                        @click="scrollSaved(-1)"
                        aria-label="Scroll left"
                    >
                        ←
                    </button>
                    <button
                        type="button"
                        class="px-3 py-2 rounded-lg bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10"
                        @click="scrollSaved(1)"
                        aria-label="Scroll right"
                    >
                        →
                    </button>
                </div>
            </div>
            <div class="glass-card p-4">
                <div class="flex items-center gap-2 mb-3">
                    <input
                        v-model="savedSearch"
                        type="text"
                        placeholder="Search saved (business, promo, code)..."
                        class="flex-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 text-sm focus:outline-none focus:border-primary-500/50"
                        @keyup.enter="applySavedSearch"
                    />
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg bg-primary-500 text-white text-sm font-semibold hover:bg-primary-600"
                        @click="applySavedSearch"
                    >
                        Search
                    </button>
                </div>

                <div ref="savedCarouselEl" class="flex gap-4 overflow-x-auto pb-2 scroll-smooth">
                    <div
                        v-for="item in combinedPromotions"
                        :key="item.qr_code.id"
                        class="glass-card p-4 hover:border-primary-500/50 transition-colors relative flex-shrink-0 w-[360px]"
                    >
                        <div v-if="(item.stack_count || 1) > 1" class="absolute top-4 left-4">
                            <span class="text-xs font-semibold px-2 py-1 rounded-lg bg-primary-500/20 text-primary-200 border border-primary-500/30">
                                x{{ item.stack_count }}
                            </span>
                        </div>

                        <!-- Save/Unsave Button (Only for regular promos) -->
                        <button
                            v-if="canSaveItem(item)"
                            @click.stop="toggleSave(getSaveId(item), item.is_saved)"
                            :disabled="saving[getSaveId(item)]"
                            class="absolute top-4 right-4 p-2 rounded-lg transition-colors"
                            :class="item.is_saved 
                                ? 'bg-yellow-500/20 text-yellow-400 hover:bg-yellow-500/30' 
                                : 'bg-white/5 text-gray-400 hover:bg-white/10'"
                            :title="item.is_saved ? 'Unsave' : 'Save'"
                        >
                            <svg v-if="item.is_saved" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </button>

                        <div class="block">
                            <div class="flex flex-col gap-4">
                                <!-- QR Code Image and Code -->
                                <div class="flex flex-col items-center gap-3">
                                    <div v-if="item.customer_promo?.qr_image_url" class="w-32 h-32 bg-white p-2 rounded-lg">
                                        <img 
                                            :src="item.customer_promo.qr_image_url" 
                                            :alt="item.qr_code.name" 
                                            class="w-full h-full object-contain"
                                        />
                                    </div>
                                    <div v-else-if="item.qr_code.image_url" class="w-32 h-32 bg-white p-2 rounded-lg">
                                        <img 
                                            :src="item.qr_code.image_url" 
                                            :alt="item.qr_code.name" 
                                            class="w-full h-full object-contain"
                                        />
                                    </div>
                                    <div v-else class="w-32 h-32 bg-white/10 rounded-lg flex items-center justify-center">
                                        <svg class="w-24 h-24 text-gray-600" viewBox="0 0 100 100">
                                            <rect x="10" y="10" width="25" height="25" rx="3" fill="currentColor"/>
                                            <rect x="65" y="10" width="25" height="25" rx="3" fill="currentColor"/>
                                            <rect x="10" y="65" width="25" height="25" rx="3" fill="currentColor"/>
                                            <rect x="40" y="40" width="20" height="20" rx="2" fill="currentColor"/>
                                        </svg>
                                    </div>
                                    <!-- QR Code Code Display -->
                                    <div class="text-center">
                                        <div class="text-xs text-gray-400 mb-1">
                                            {{ item.is_game_reward ? (item.customer_promo?.code ? 'Reward Code' : 'Redeem Reward') : 'Customer Promo Code' }}
                                        </div>
                                        <div class="text-2xl font-bold text-white font-mono tracking-wider bg-primary-500/20 px-4 py-2 rounded-lg border border-primary-500/30">
                                            <span v-if="item.customer_promo?.code">{{ item.customer_promo.code }}</span>
                                            <span v-else-if="item.is_game_reward">Redeem via QR</span>
                                            <span v-else>...</span>
                                        </div>
                                        <button
                                            v-if="item.customer_promo?.code"
                                            type="button"
                                            class="mt-2 px-3 py-1.5 rounded-lg bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10 text-xs font-semibold"
                                            @click.stop.prevent="copyText(item.customer_promo.code)"
                                        >
                                            Copy
                                        </button>
                                        <div class="text-xs text-gray-500 mt-2">
                                            {{ item.is_game_reward && !item.customer_promo?.code ? 'Show staff this QR to redeem.' : 'Show staff this QR/code to redeem.' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Promotion Details -->
                                <div class="flex items-start gap-3">
                                    <div v-if="item.business?.logo_url" class="w-12 h-12 rounded-lg overflow-hidden flex-shrink-0">
                                        <img :src="item.business.logo_url" :alt="item.business.name" class="w-full h-full object-cover" />
                                    </div>
                                    <div v-else class="w-12 h-12 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-xl flex-shrink-0">
                                        {{ item.is_game_reward ? '🎁' : '🏷️' }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <h3 class="text-white font-medium mb-1 truncate">{{ item.promotion?.name || item.description || item.qr_code.name }}</h3>
                                            <Link 
                                                v-if="!item.removed_by_business && !item.customer_promo?.redeemed_at && item.status !== 'redeemed'"
                                                :href="item.is_game_reward
                                                    ? (item.customer_promo?.code && item.customer_promo.code !== item.reward_code
                                                        ? promoLink(item.customer_promo.code)
                                                        : `/portal/rewards/${item.id}`)
                                                    : promoLink(item.customer_promo?.code || item.qr_code.code)"
                                                class="text-xs text-primary-400 hover:text-primary-300 font-medium"
                                            >
                                                View Details →
                                            </Link>
                                        </div>
                                        <p class="text-gray-400 text-xs mb-2">{{ item.business?.name }}</p>
                                        <p v-if="item.promotion || item.display_value" class="text-sm text-purple-400 mb-1">{{ item.promotion?.display_value || item.display_value }}</p>
                                        <div v-if="item.promotion?.is_punch_card" class="flex items-center gap-2 mt-2">
                                            <span class="text-xs text-gray-400">{{ item.promotion.punches_required }} punches required</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-2">
                                            <span 
                                                :class="[
                                                    'text-xs px-2 py-1 rounded',
                                                    item.removed_by_business
                                                        ? 'bg-red-500/20 text-red-400'
                                                        : item.punch_card_state?.completed_and_reset
                                                            ? 'bg-amber-500/20 text-amber-300'
                                                            : (item.customer_promo?.redeemed_at || item.status === 'redeemed')
                                                                ? 'bg-emerald-500/20 text-emerald-400'
                                                                : (item.is_active 
                                                                    ? 'bg-green-500/20 text-green-400' 
                                                                    : 'bg-red-500/20 text-red-400')
                                                ]"
                                            >
                                                {{
                                                    item.removed_by_business
                                                        ? 'Removed by business'
                                                        : item.punch_card_state?.completed_and_reset
                                                            ? 'Completed — scan again for a new card'
                                                            : (item.customer_promo?.redeemed_at || item.status === 'redeemed')
                                                                ? 'Redeemed'
                                                                : (item.is_active ? 'Active' : 'Expired')
                                                }}
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                {{ formatDate(item.scanned_at || item.saved_at) || 'Recently Won' }}
                                            </span>
                                        </div>

                                        <!-- Unsave button for removed promos -->
                                        <button
                                            v-if="item.removed_by_business && item.is_saved && item.qr_code?.id"
                                            @click.stop.prevent="toggleSave(item.qr_code.id, true)"
                                            class="mt-2 text-xs text-red-400 hover:text-red-300 font-medium"
                                        >
                                            Delete
                                        </button>

                                        <!-- Completed punch card hint -->
                                        <div
                                            v-if="item.punch_card_state?.completed_and_reset"
                                            class="mt-3 p-3 rounded-xl bg-amber-500/10 border border-amber-500/30"
                                        >
                                            <p class="text-amber-200 text-xs font-semibold">This punch card is completed.</p>
                                            <p class="text-amber-100 text-[11px] mt-1">Scan again in-store to start a new card.</p>
                                        </div>

                                        <!-- Disabled/Restriction message -->
                                        <div
                                            v-if="item.redeem_message && !item.customer_promo?.redeemed_at && item.status !== 'redeemed'"
                                            class="mt-3 p-3 rounded-xl border"
                                            :class="[
                                                item.is_active === false 
                                                    ? 'bg-red-500/10 border-red-500/30' 
                                                    : 'bg-amber-500/10 border-amber-500/30'
                                            ]"
                                        >
                                            <p :class="item.is_active === false ? 'text-red-300' : 'text-amber-300'" class="text-xs font-medium">
                                                {{ item.redeem_message }}
                                            </p>
                                            <p v-if="item.is_active === false && item.redeem_message.includes('modified')" class="text-gray-300 text-[10px] mt-1">Visit business for newer promotions.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Saved pagination -->
            <div v-if="savedPromotions?.links && savedPromotions.links.length > 3" class="mt-4 flex justify-center gap-2">
                <Link
                    v-for="(link, index) in savedPromotions.links"
                    :key="index"
                    :href="link.url || '#'"
                    :class="[
                        'px-3 py-1 rounded-lg text-sm transition-colors',
                        link.active 
                            ? 'bg-primary-500 text-white' 
                            : 'bg-white/5 text-gray-400 hover:bg-white/10',
                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                    v-html="link.label"
                ></Link>
        </div>
    </div>

    <!-- Recent Activity (Full History) -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">🕒 Recent Activity</h2>
            <p class="text-gray-400 text-xs mb-3">A complete history of your scans and redemptions.</p>
            <div v-if="filteredRecentScans.length > 0" class="space-y-2">
                <div
                    v-for="scan in filteredRecentScans"
                    :key="scan.id"
                    class="glass-card p-3 flex items-center justify-between"
                >
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center text-lg flex-shrink-0">
                            📱
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <div class="text-white font-medium truncate">{{ scan.qr_code?.name || (scan.is_redeemed ? 'Redeemed Offer' : 'Scan') }}</div>
                            <span v-if="scan.is_redeemed" class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    REDEEMED
                                </span>
                            <span v-else-if="scan.removed_by_business" class="px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 text-[10px] font-bold">
                                REMOVED BY BUSINESS
                            </span>
                            <span v-else-if="scan.promo_expired" class="px-2 py-0.5 rounded-full bg-orange-500/20 text-orange-400 text-[10px] font-bold">
                                EXPIRED
                            </span>
                            <span v-else class="px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-400 text-[10px] font-bold">
                                SCANNED
                            </span>
                            </div>
                                <div class="text-gray-400 text-xs">{{ scan.business?.name }}</div>
                            <div v-if="scan.is_redeemed && scan.redemption_discount_amount > 0" class="text-emerald-400 text-[10px] font-medium mt-1">
                                Saved ${{ Number(scan.redemption_discount_amount || 0).toFixed(2) }}
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-400">{{ formatDate(scan.scanned_at || scan.redemption_at) }}</div>
                                <template v-if="!scan.is_redeemed">
                                    <button
                                        v-if="scan.removed_by_business || scan.is_non_winner || scan.promo_expired"
                                        @click="dismissScan(scan.id)"
                                        class="text-xs mt-1 block text-red-400 hover:text-red-300 font-medium"
                                    >
                                        Delete
                                    </button>
                                    <span
                                        v-if="scan.promo_expired && !scan.is_non_winner && !scan.removed_by_business"
                                        class="text-xs mt-1 block text-gray-400 font-medium"
                                    >
                                        Expired
                                    </span>
                                    <span
                                        v-else-if="scan.is_non_winner"
                                        class="text-xs mt-1 block text-gray-400 font-medium"
                                    >
                                        {{ scanActionLabel(scan) }}
                                    </span>
                                    <Link
                                        v-else-if="scan.qr_code?.code && !scan.promo_expired"
                                        :href="scanActionHref(scan)"
                                        class="text-xs mt-1 block text-primary-400 hover:text-primary-300 font-medium"
                                    >
                                        {{ scanActionLabel(scan) }}
                                    </Link>
                                </template>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="filteredRecentScans.length > 0 && scans.links && scans.links.length > 3" class="mt-4 flex justify-center gap-2">
                <Link
                    v-for="link in scans.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :class="[
                        'px-3 py-1 rounded-lg text-sm transition-colors',
                        link.active 
                            ? 'bg-primary-500 text-white' 
                            : 'bg-white/5 text-gray-400 hover:bg-white/10',
                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                    v-html="link.label"
                ></Link>
            </div>
            <div v-if="filteredRecentScans.length === 0" class="glass-card p-6 text-center">
                <div class="text-4xl mb-2">🧾</div>
                <p class="text-gray-300 text-sm">No recent activity yet.</p>
                <p class="text-gray-500 text-xs mt-1">Scan a promo or play a game to see it here.</p>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="(savedPromotions?.data?.length || 0) === 0 && filteredPunchCards.length === 0 && filteredRecentScans.length === 0" class="text-center py-12">
            <div class="text-6xl mb-4">📱</div>
            <h3 class="text-xl font-semibold text-white mb-2">No Scans Yet</h3>
            <p class="text-gray-400 mb-6">Start scanning QR codes to see your promotions and punch cards here!</p>
            <Link href="/portal/games/nearby" class="inline-block px-6 py-3 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors">
                Find QR Codes Near Me
            </Link>
        </div>
    </PortalLayout>
</template>
