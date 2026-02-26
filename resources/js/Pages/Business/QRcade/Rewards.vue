<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const props = defineProps({
    promotions: Array,
    qrCodeGames: Array,
    rewardStats: Object,
});

const selectedGame = ref(null);
const selectedQrCodeGame = ref(null);
const showPrizeModal = ref(false);
const useTieredRewards = ref(false);

// Prize configuration form
const prizeForm = useForm({
    qr_code_game_id: null,
    game_id: null,
    win_mode: 'score', // score, time, random, always, leaderboard, tiered
    prize_type: 'promotion', // (future) promotion, points, badge
    promotion_id: null,
    min_score: 0,
    max_time: null,
    win_probability: 100,
    leaderboard_position: null,
    daily_limit: null,
    total_limit: null,
    tier_rewards: {
        gold: null,
        silver: null,
        bronze: null,
    },
    score_tiers: {
        gold: null,
        silver: null,
        bronze: null,
    },
});

const SUGGESTED_SCORES = {
    memory_match:    { score: null, time: 33 },
    word_search:     { score: 650,  time: 150 },
    snake:           { score: 205 },
    tap_counter:     { score: 13633 },
    brick_breaker:   { score: 1110 },
    qr_dash:         { score: 3210 },
    cupcake_catcher: { score: 600 },
    slice_saver:     { score: 580 },
    vape_cloud_pop:  { score: 2990 },
    coffee_rush:     { score: 9477 },
};

const hasSuggestedData = computed(() => {
    const type = selectedGame.value?.type;
    return type && SUGGESTED_SCORES[type];
});

const applySuggestedScores = () => {
    const type = selectedGame.value?.type;
    if (!type || !SUGGESTED_SCORES[type]) return;
    const data = SUGGESTED_SCORES[type];

    if (useTieredRewards.value && data.score) {
        prizeForm.score_tiers.gold = Math.round(data.score * 1.5);
        prizeForm.score_tiers.silver = data.score;
        prizeForm.score_tiers.bronze = Math.round(data.score * 0.5);
    } else if (data.score) {
        prizeForm.win_mode = 'score';
        prizeForm.min_score = data.score;
        if (data.time) {
            prizeForm.max_time = data.time;
        }
    } else if (data.time) {
        prizeForm.win_mode = 'time';
        prizeForm.max_time = data.time;
    }
};

const speedRunSupportedTypes = ['memory_match', 'word_search'];

const winModes = [
    { value: 'score', name: 'Score Threshold', desc: 'Win when reaching a minimum score', icon: '🎯' },
    { value: 'time', name: 'Speed Run', desc: 'Win by completing under a time limit', icon: '⏱️' },
    { value: 'random', name: 'Random Chance', desc: 'Random probability of winning', icon: '🎲' },
    { value: 'always', name: 'Always Win', desc: 'Everyone gets the prize', icon: '🎁' },
    { value: 'leaderboard', name: 'Leaderboard', desc: 'Top X positions win', icon: '🏆' },
];

const isSpeedRunSupported = (gameType) => speedRunSupportedTypes.includes(gameType);

const selectedQrType = computed(() => selectedQrCodeGame.value?.qr_code?.type || null);
const isLeaderboardQr = computed(() => selectedQrType.value === 'qrcade_leaderboard');

const winModeOptions = computed(() => {
    const gameType = selectedGame.value?.type || null;
    const qrType = selectedQrType.value;
    return winModes.filter((mode) => {
        if (mode.value === 'time' && !isSpeedRunSupported(gameType)) return false;
        if (qrType === 'qrcade_leaderboard') return mode.value === 'leaderboard';
        if (qrType === 'qrcade') return mode.value !== 'leaderboard';
        return true;
    });
});

const promotionsById = computed(() => {
    const map = new Map();
    for (const p of (props.promotions || [])) map.set(p.id, p);
    return map;
});

const getLeaderboardPrize = (qrCodeGame) => qrCodeGame?.leaderboard_prize?.promotion || null;
const getLeaderboardPrizeConfig = (qrCodeGame) => qrCodeGame?.leaderboard_prize?.prize_config || {};

const tieredValidation = computed(() => {
    if (!useTieredRewards.value) return { ok: true, message: null };

    const g = Number(prizeForm.score_tiers.gold ?? NaN);
    const s = Number(prizeForm.score_tiers.silver ?? NaN);
    const b = Number(prizeForm.score_tiers.bronze ?? NaN);

    if (![g, s, b].every((n) => Number.isFinite(n) && n >= 0)) {
        return { ok: false, message: 'Set Gold, Silver, and Bronze score thresholds (0 or higher).' };
    }
    if (g < s || s < b) {
        return { ok: false, message: 'Tier thresholds must be Gold ≥ Silver ≥ Bronze.' };
    }
    if (!prizeForm.tier_rewards.gold || !prizeForm.tier_rewards.silver || !prizeForm.tier_rewards.bronze) {
        return { ok: false, message: 'Select a promotion for Gold, Silver, and Bronze.' };
    }
    return { ok: true, message: null };
});

const selectGame = (game) => {
    selectedGame.value = game;
    prizeForm.game_id = game.id;
};

const openPrizeModal = (qrCodeGame = null) => {
    if (qrCodeGame) {
        selectedQrCodeGame.value = qrCodeGame;
        selectedGame.value = qrCodeGame.game || null;
        prizeForm.qr_code_game_id = qrCodeGame.id;
        prizeForm.game_id = qrCodeGame.game_id;
        prizeForm.win_mode = qrCodeGame.win_mode || 'score';
        prizeForm.promotion_id = qrCodeGame.promotion_id;
        prizeForm.min_score = qrCodeGame.prize_config?.min_score || 0;
        prizeForm.win_probability = qrCodeGame.prize_config?.win_probability || 100;
        prizeForm.leaderboard_position = qrCodeGame.prize_config?.leaderboard_position || null;
        prizeForm.daily_limit = qrCodeGame.prize_config?.daily_limit ?? null;
        prizeForm.total_limit = qrCodeGame.prize_config?.total_limit ?? null;

        // Tiered config
        if (qrCodeGame.win_mode === 'tiered') {
            useTieredRewards.value = true;
            prizeForm.tier_rewards = {
                gold: qrCodeGame.tier_rewards?.gold ?? null,
                silver: qrCodeGame.tier_rewards?.silver ?? null,
                bronze: qrCodeGame.tier_rewards?.bronze ?? null,
            };
            prizeForm.score_tiers = {
                gold: qrCodeGame.score_tiers?.gold ?? null,
                silver: qrCodeGame.score_tiers?.silver ?? null,
                bronze: qrCodeGame.score_tiers?.bronze ?? null,
            };
        } else {
            useTieredRewards.value = false;
            prizeForm.tier_rewards = { gold: null, silver: null, bronze: null };
            prizeForm.score_tiers = { gold: null, silver: null, bronze: null };
        }

        if (qrCodeGame.qr_code?.type === 'qrcade_leaderboard') {
            prizeForm.win_mode = 'leaderboard';
            useTieredRewards.value = false;
            const leaderboardPrize = getLeaderboardPrize(qrCodeGame);
            const leaderboardConfig = getLeaderboardPrizeConfig(qrCodeGame);
            if (!prizeForm.promotion_id && leaderboardPrize?.id) {
                prizeForm.promotion_id = leaderboardPrize.id;
            }
            if (prizeForm.leaderboard_position === null && leaderboardConfig?.leaderboard_position != null) {
                prizeForm.leaderboard_position = leaderboardConfig.leaderboard_position;
            }
        }
    }
    showPrizeModal.value = true;
};

const savePrizeConfig = () => {
    if (isLeaderboardQr.value) {
        prizeForm.win_mode = 'leaderboard';
        useTieredRewards.value = false;
    }
    if (useTieredRewards.value) {
        prizeForm.win_mode = 'tiered';
        prizeForm.promotion_id = null;
    }
    prizeForm.put(`/business/qrcade/rewards/${prizeForm.qr_code_game_id}`, {
        onSuccess: () => {
            showPrizeModal.value = false;
            prizeForm.reset();
            useTieredRewards.value = false;
        },
    });
};

const getPrizeDescription = (qrCodeGame) => {
    const leaderboardPrize = getLeaderboardPrize(qrCodeGame);
    const config = qrCodeGame.prize_config || getLeaderboardPrizeConfig(qrCodeGame) || {};
    
    if (qrCodeGame.win_mode === 'tiered') {
        const tr = qrCodeGame.tier_rewards || {};
        const st = qrCodeGame.score_tiers || {};
        const goldName = promotionsById.value.get(tr.gold)?.name || 'Gold promo';
        const silverName = promotionsById.value.get(tr.silver)?.name || 'Silver promo';
        const bronzeName = promotionsById.value.get(tr.bronze)?.name || 'Bronze promo';
        return `Tiered: Gold ≥ ${st.gold ?? '?'} → ${goldName}, Silver ≥ ${st.silver ?? '?'} → ${silverName}, Bronze ≥ ${st.bronze ?? '?'} → ${bronzeName}`;
    }

    const promotion = qrCodeGame.promotion || leaderboardPrize;
    if (!promotion) return 'No prize configured';
    let desc = promotion.name;
    
    if (qrCodeGame.win_mode === 'score') {
        desc += ` (Score ≥ ${config.min_score || 0})`;
    } else if (qrCodeGame.win_mode === 'always') {
        desc += ' (Everyone wins)';
    } else if (qrCodeGame.win_mode === 'leaderboard') {
        desc += ` (Top ${config.leaderboard_position || 3})`;
    } else if (qrCodeGame.win_mode === 'random') {
        desc += ` (${config.win_probability || 100}% chance)`;
    }
    
    return desc;
};
</script>

<template>
    <Head title="QRcade Rewards" />

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Game Rewards Configuration</h1>
            <p class="text-gray-400 mt-1">Configure what prizes customers can win from games</p>
        </div>

        <!-- Info Card -->
        <div class="glass-card p-6 mb-8 border-l-4 border-primary-500">
            <h3 class="text-white font-medium mb-2">How Game Prizes Work</h3>
            <p class="text-gray-400 text-sm">
                When you attach games to a QR code, you can configure what promotion/prize the customer wins.
                Set win conditions like minimum score, leaderboard position, time limits, or random chance.
            </p>
        </div>

        <!-- QR Codes with Games -->
        <div class="space-y-6">
            <h2 class="text-xl font-semibold text-white">QR Codes with Games</h2>
            
            <div v-if="qrCodeGames && qrCodeGames.length" class="space-y-4">
                <div v-for="qrGame in qrCodeGames" :key="qrGame.id" class="glass-card p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-2xl">
                                🎮
                            </div>
                            <div>
                                <h3 class="text-white font-medium">{{ qrGame.game?.name }}</h3>
                                <p class="text-gray-400 text-sm">QR Code: {{ qrGame.qr_code?.name }}</p>
                                <p class="text-gray-500 text-xs mt-1">
                                    {{ qrGame.qr_code?.type === 'qrcade_leaderboard' ? 'Leaderboard QR' : 'Play-to-Win QR' }}
                                </p>
                                <p class="text-gray-500 text-xs mt-1">{{ getPrizeDescription(qrGame) }}</p>
                            </div>
                        </div>
                        <button @click="openPrizeModal(qrGame)" class="btn-primary text-sm">
                            Configure Prize
                        </button>
                    </div>

                    <!-- Current Configuration -->
                        <div class="mt-4 grid grid-cols-4 gap-4 pt-4 border-t border-white/10">
                        <div class="text-center">
                            <p class="text-gray-500 text-xs">Win Mode</p>
                            <p class="text-white font-medium capitalize">{{ qrGame.win_mode || 'Score' }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-xs">Prize</p>
                                <p class="text-primary-400 font-medium">
                                    {{ qrGame.win_mode === 'tiered' ? 'Tiered (Gold/Silver/Bronze)' : (qrGame.promotion?.name || qrGame.leaderboard_prize?.promotion?.name || 'None') }}
                                </p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-xs">Total Wins</p>
                            <p class="text-white font-medium">{{ qrGame.total_wins || 0 }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-xs">Status</p>
                            <p :class="qrGame.is_active ? 'text-green-400' : 'text-gray-400'" class="font-medium">
                                {{ qrGame.is_active ? 'Active' : 'Inactive' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="glass-card p-12 text-center">
                <div class="text-6xl mb-4">🎮</div>
                <h3 class="text-xl font-semibold text-white mb-2">No Games Attached Yet</h3>
                <p class="text-gray-400 mb-6">Attach games to your QR codes to configure prizes</p>
                <a href="/business/qr-codes/create" class="btn-primary">Create QR Code with Games</a>
            </div>
        </div>

        <!-- Prize Configuration Modal -->
        <div v-if="showPrizeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="glass-card p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Configure Prize</h2>
                        <button
                            v-if="hasSuggestedData"
                            type="button"
                            @click="applySuggestedScores"
                            class="mt-2 px-3 py-1.5 text-xs font-medium rounded-lg border border-primary-500/40 bg-primary-500/10 text-primary-300 hover:bg-primary-500/20 transition-all"
                        >
                            Use Suggested Scores
                        </button>
                        <p v-if="hasSuggestedData" class="text-gray-500 text-xs mt-1">
                            Averages tested by the silent partner (a 10 year old).
                        </p>
                    </div>
                    <button @click="showPrizeModal = false" class="text-gray-400 hover:text-white text-2xl">&times;</button>
                </div>

                <form @submit.prevent="savePrizeConfig" class="space-y-6">
                    <!-- Reward Style -->
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-white font-medium">Reward Style</div>
                                <p class="text-gray-400 text-sm mt-1">
                                    Default is a single reward. Enable tiered rewards only if you want different prizes for Gold/Silver/Bronze scores.
                                </p>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-300 select-none">
                                <input
                                    type="checkbox"
                                    v-model="useTieredRewards"
                                    class="accent-primary-500"
                                    :disabled="isLeaderboardQr"
                                />
                                Advanced: Tiered
                            </label>
                        </div>
                        <p v-if="useTieredRewards" class="mt-3 text-xs text-amber-300">
                            If using tiered rewards: ensure Gold, Silver, and Bronze promotions all use the same rules (max per user, stackable, etc.).
                        </p>
                        <p v-if="isLeaderboardQr" class="mt-3 text-xs text-amber-300">
                            Leaderboard QR codes only support Leaderboard mode. Configure prizes in QRcade → Leaderboards.
                        </p>
                    </div>

                    <!-- Win Mode Selection (Single reward) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-3">Win Condition</label>
                        <div v-if="!useTieredRewards" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <button
                                v-for="mode in winModeOptions"
                                :key="mode.value"
                                type="button"
                                @click="prizeForm.win_mode = mode.value"
                                :class="[
                                    'p-4 rounded-xl border text-left transition-all',
                                    prizeForm.win_mode === mode.value
                                        ? 'border-primary-500 bg-primary-500/20'
                                        : 'border-white/20 hover:border-white/40'
                                ]"
                            >
                                <div class="text-2xl mb-2">{{ mode.icon }}</div>
                                <p class="text-white font-medium text-sm">{{ mode.name }}</p>
                                <p class="text-gray-500 text-xs">{{ mode.desc }}</p>
                            </button>
                        </div>
                        <div v-if="prizeForm.win_mode === 'always' && !useTieredRewards" class="mt-3 text-xs text-amber-300">
                            Current config is set to Always Win. Select another win condition to change it.
                        </div>
                        <div v-if="isLeaderboardQr && !useTieredRewards" class="mt-3 text-xs text-gray-400">
                            Leaderboard prizes are awarded when the leaderboard period ends.
                        </div>
                        <div v-if="prizeForm.win_mode === 'time' && !useTieredRewards && !isSpeedRunSupported(selectedGame?.type)" class="mt-3 text-xs text-amber-300">
                            Speed Run is not available for this game. Choose another win condition.
                        </div>
                        <div v-else class="p-4 rounded-xl bg-white/5 border border-white/10">
                            <div class="text-white font-medium">Tiered Score Thresholds</div>
                            <p class="text-gray-500 text-sm mt-1">
                                Set Gold ≥ Silver ≥ Bronze. Each tier can award a different promotion.
                            </p>
                        </div>
                    </div>

                    <!-- Mode-specific Settings -->
                    <div v-if="useTieredRewards" class="p-4 rounded-xl bg-white/5">
                        <p class="text-xs text-purple-400 mb-4 flex items-start gap-1">
                            <span>💡</span>
                            <span>
                                <strong>Best practice:</strong> Create separate promotions for each tier. Avoid reusing promotions that are already assigned as leaderboard prizes or other game rewards — shared redemption limits may cause unexpected results.
                                <br /><br />
                                <strong class="text-amber-300">Important:</strong> All three promotions (Gold, Silver, Bronze) should use the same rules — same max per user, same stackable setting, etc. Mismatched rules can cause confusing behavior.
                            </span>
                        </p>
                        <div class="grid md:grid-cols-3 gap-4">
                            <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                                <div class="text-yellow-400 font-semibold mb-2">Gold</div>
                                <label class="block text-xs text-gray-400 mb-1">Min score</label>
                                <input v-model="prizeForm.score_tiers.gold" type="number" min="0" class="input-glass" placeholder="300" />
                                <label class="block text-xs text-gray-400 mt-3 mb-1">Promotion</label>
                                <select v-model="prizeForm.tier_rewards.gold" class="input-glass">
                                    <option value="" class="bg-gray-800 text-white">Select...</option>
                                    <option v-for="promo in promotions" :key="promo.id" :value="promo.id" class="bg-gray-800 text-white">
                                        {{ promo.name }} ({{ promo.discount_type }})
                                    </option>
                                </select>
                            </div>

                            <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                                <div class="text-gray-300 font-semibold mb-2">Silver</div>
                                <label class="block text-xs text-gray-400 mb-1">Min score</label>
                                <input v-model="prizeForm.score_tiers.silver" type="number" min="0" class="input-glass" placeholder="200" />
                                <label class="block text-xs text-gray-400 mt-3 mb-1">Promotion</label>
                                <select v-model="prizeForm.tier_rewards.silver" class="input-glass">
                                    <option value="" class="bg-gray-800 text-white">Select...</option>
                                    <option v-for="promo in promotions" :key="promo.id" :value="promo.id" class="bg-gray-800 text-white">
                                        {{ promo.name }} ({{ promo.discount_type }})
                                    </option>
                                </select>
                            </div>

                            <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                                <div class="text-amber-600 font-semibold mb-2">Bronze</div>
                                <label class="block text-xs text-gray-400 mb-1">Min score</label>
                                <input v-model="prizeForm.score_tiers.bronze" type="number" min="0" class="input-glass" placeholder="100" />
                                <label class="block text-xs text-gray-400 mt-3 mb-1">Promotion</label>
                                <select v-model="prizeForm.tier_rewards.bronze" class="input-glass">
                                    <option value="" class="bg-gray-800 text-white">Select...</option>
                                    <option v-for="promo in promotions" :key="promo.id" :value="promo.id" class="bg-gray-800 text-white">
                                        {{ promo.name }} ({{ promo.discount_type }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div v-if="!tieredValidation.ok" class="mt-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
                            {{ tieredValidation.message }}
                        </div>
                    </div>

                    <div v-if="prizeForm.win_mode === 'score'" class="p-4 rounded-xl bg-white/5">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Minimum Score to Win</label>
                        <input v-model="prizeForm.min_score" type="number" min="0" class="input-glass" placeholder="100" />
                        <p class="text-gray-500 text-sm mt-2">Players must score at least this many points to win the prize</p>
                    </div>

                    <div v-if="prizeForm.win_mode === 'time'" class="p-4 rounded-xl bg-white/5">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Maximum Time (seconds)</label>
                        <input v-model="prizeForm.max_time" type="number" min="1" class="input-glass" placeholder="60" />
                        <p class="text-gray-500 text-sm mt-2">Players must complete the game within this time to win</p>
                    </div>

                    <div v-if="prizeForm.win_mode === 'random'" class="p-4 rounded-xl bg-white/5">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Win Probability (%)</label>
                        <input v-model="prizeForm.win_probability" type="number" min="1" max="100" class="input-glass" placeholder="25" />
                        <p class="text-gray-500 text-sm mt-2">Percentage chance of winning after completing the game</p>
                    </div>

                    <div v-if="prizeForm.win_mode === 'leaderboard'" class="p-4 rounded-xl bg-white/5">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Leaderboard Position</label>
                        <select v-model="prizeForm.leaderboard_position" class="input-glass">
                            <option :value="1" class="bg-gray-800 text-white">Top 1 (Winner only)</option>
                            <option :value="3" class="bg-gray-800 text-white">Top 3</option>
                            <option :value="5" class="bg-gray-800 text-white">Top 5</option>
                            <option :value="10" class="bg-gray-800 text-white">Top 10</option>
                        </select>
                        <p class="text-gray-500 text-sm mt-2">Players in these positions win at the end of each period</p>
                    </div>

                    <!-- Prize Selection -->
                    <div v-if="!useTieredRewards">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Prize (Promotion) <span class="text-gray-500 font-normal">(Optional)</span></label>
                        <div v-if="prizeForm.promotion_id" class="mb-2 text-xs text-gray-400">
                            Selected promo: <span class="text-gray-200 font-medium">{{ promotionsById.get(prizeForm.promotion_id)?.name }}</span>
                        </div>
                        <select v-model="prizeForm.promotion_id" class="input-glass">
                            <option value="" class="bg-gray-800 text-white">No prize - leave empty</option>
                            <option v-for="promo in promotions" :key="promo.id" :value="promo.id" class="bg-gray-800 text-white">
                                {{ promo.name }} ({{ promo.discount_type }})
                            </option>
                        </select>
                        <p class="text-gray-500 text-sm mt-2">
                            Winners receive this prize after meeting the selected win condition.
                        </p>
                        <p v-if="!prizeForm.promotion_id" class="text-xs text-purple-400 mt-2 flex items-start gap-1">
                            <span>💡</span>
                            <span>
                                <strong>Tip:</strong> Leave empty if you want this game to be <strong>play-only</strong> (no instant prize).
                                For weekly/monthly prizes, set a <strong>Reward Prize</strong> in <strong>QRcade → Leaderboards</strong> using a dedicated Promotion QR code.
                            </span>
                        </p>
                    </div>

                    <!-- Limits -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Daily Prize Limit</label>
                            <input v-model="prizeForm.daily_limit" type="number" min="0" class="input-glass" placeholder="Unlimited" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Total Prize Limit</label>
                            <input v-model="prizeForm.total_limit" type="number" min="0" class="input-glass" placeholder="Unlimited" />
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-4 pt-4 border-t border-white/10">
                        <button type="button" @click="showPrizeModal = false" class="px-6 py-3 rounded-xl text-gray-400 hover:text-white hover:bg-white/10">
                            Cancel
                        </button>
                        <button type="submit" :disabled="prizeForm.processing || (useTieredRewards && !tieredValidation.ok)" class="btn-primary">
                            {{ prizeForm.processing ? 'Saving...' : 'Save Configuration' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
