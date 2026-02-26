<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted, computed } from 'vue';
import { collectAndSendScanGeo } from '@/utils/scanGeo';

const page = usePage();
const isLoggedIn = computed(() => !!page.props.auth?.user);

const props = defineProps({
    qrCode: Object,
    business: Object,
    games: Array,
    locationRequirements: Object,
    leaderboards: Array,
    qrCodeGames: Array,
    promotionMap: Object,
    gameSelectUrl: String,
    showPlatformBranding: {
        type: Boolean,
        default: true,
    },
});

// Game icons mapping
const gameIcons = {
    'memory_match': '🧠',
    'tap_counter': '👆',
    'snake': '🐍',
    'word_search': '🔤',
    'spin_wheel': '🎡',
    'scratch_card': '🎫',
    'trivia': '❓',
    'slot_machine': '🎰',
};

const getGameIcon = (type) => gameIcons[type] || '🎮';

const gameSelectUrl = computed(() => props.gameSelectUrl || `/play/${props.qrCode.code}`);

const qrCodeGameByGameId = computed(() => {
    const map = new Map();
    for (const item of (props.qrCodeGames || [])) {
        map.set(item.game_id, item);
    }
    return map;
});

const getPromotionName = (id) => {
    if (!id) return null;
    return props.promotionMap?.[id]?.name || null;
};

const hasChallengeMode = (game) => {
    const qrGame = qrCodeGameByGameId.value.get(game.id);
    if (!qrGame || !qrGame.win_mode) return false;
    const challengeModes = ['score', 'time', 'leaderboard', 'tiered', 'skill'];
    return challengeModes.includes(qrGame.win_mode);
};

const getPrizeLines = (game) => {
    const qrGame = qrCodeGameByGameId.value.get(game.id);
    if (!qrGame || !qrGame.win_mode) return [];

    const config = qrGame.prize_config || {};
    const promoName = qrGame.promotion?.name || null;
    const hasReward =
        !!qrGame.promotion?.id ||
        (qrGame.tier_rewards && Object.values(qrGame.tier_rewards).some((id) => !!id));

    if (qrGame.win_mode === 'tiered') {
        const tiers = ['gold', 'silver', 'bronze'];
        const lines = [];
        for (const tier of tiers) {
            const score = qrGame.score_tiers?.[tier];
            const rewardPromo = getPromotionName(qrGame.tier_rewards?.[tier]);
            if (score !== null && score !== undefined) {
                const label = tier.charAt(0).toUpperCase() + tier.slice(1);
                lines.push(`${label} ≥ ${score}${rewardPromo ? ` — ${rewardPromo}` : ''}`);
            }
        }
        if (!lines.length) {
            lines.push('Tiered rewards configured');
        }
        if (!hasReward) {
            lines.push('No prize configured');
        }
        return lines;
    }

    if (qrGame.win_mode === 'score') {
        const minScore = config.min_score ?? 0;
        const detail = promoName ? ` — ${promoName}` : '';
        const lines = [`Win at score ≥ ${minScore}${detail}`];
        if (!hasReward) lines.push('No prize configured');
        return lines;
    }

    if (qrGame.win_mode === 'time') {
        const maxTime = config.max_time ?? null;
        const detail = promoName ? ` — ${promoName}` : '';
        const lines = [maxTime ? `Win under ${maxTime}s${detail}` : `Speed run${detail}`];
        if (!hasReward) lines.push('No prize configured');
        return lines;
    }

    if (qrGame.win_mode === 'random') {
        const probability = config.win_probability ?? 100;
        const detail = promoName ? ` — ${promoName}` : '';
        const lines = [`${probability}% chance to win${detail}`];
        if (!hasReward) lines.push('No prize configured');
        return lines;
    }

    if (qrGame.win_mode === 'leaderboard') {
        const threshold = config.leaderboard_position ?? 3;
        return [`Leaderboard: Top ${threshold} win (prizes at period end)`];
    }

    if (!promoName) {
        return ['No prize configured'];
    }
    return [`Prize: ${promoName}`];
};

onMounted(() => {
    collectAndSendScanGeo(props.qrCode?.code);
});
</script>

<template>
    <Head :title="`Play Games - ${business.name}`" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-gray-900 to-slate-900">
        <!-- Header with Business Branding -->
        <div class="relative overflow-hidden">
            <!-- Branded Background -->
            <div class="absolute inset-0 opacity-20"
                :style="{ background: `linear-gradient(135deg, ${business.primary_color || '#7C3AED'}80, ${business.secondary_color || business.primary_color || '#7C3AED'}40)` }">
            </div>
            <div class="absolute inset-0 opacity-10">
                <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            </div>
            
            <div class="relative px-4 py-8 text-center">
                <!-- Business Logo -->
                <div class="mb-4">
                    <div v-if="business.logo_url" 
                        class="w-24 h-24 rounded-2xl bg-white mx-auto p-2 shadow-2xl"
                        :style="{ boxShadow: `0 25px 50px -12px ${business.primary_color || '#7C3AED'}33` }">
                        <img :src="business.logo_url" :alt="business.name" class="w-full h-full object-contain" />
                    </div>
                    <div v-else 
                        class="w-24 h-24 rounded-2xl mx-auto flex items-center justify-center shadow-2xl"
                        :style="{ background: `linear-gradient(to bottom right, ${business.primary_color || '#7C3AED'}, ${business.secondary_color || '#EC4899'})`, boxShadow: `0 25px 50px -12px ${business.primary_color || '#7C3AED'}33` }">
                        <span class="text-4xl font-bold text-white">{{ business.name.charAt(0) }}</span>
                    </div>
                </div>

                <!-- Business Name -->
                <h1 class="text-2xl font-bold text-white mb-1">{{ business.name }}</h1>
                <p class="text-gray-300 text-sm">
                    <span v-if="qrCode?.type === 'qrcade_leaderboard'">🏆 QRcade Leaderboard</span>
                    <span v-else>🎮 QRcade</span>
                </p>
            </div>
        </div>

        <!-- Games Grid -->
        <div class="px-4 pb-8">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <span>{{ games.length === 1 ? 'Play Game' : 'Choose a Game' }}</span>
                <span v-if="games.length > 1" class="text-xs px-2 py-1 rounded-full"
                    :style="{ backgroundColor: `${business.primary_color || '#8B5CF6'}4D`, color: `${business.primary_color || '#8B5CF6'}` }">{{ games.length }} available</span>
            </h2>

            <div class="grid grid-cols-2 gap-4">
                <Link v-for="game in games" :key="game.id"
                    :href="`/play/${qrCode.code}/game/${game.slug}`"
                    class="group relative bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-4 hover:bg-white/10 transition-all duration-300"
                    :style="{ '--hover-border': `${business.primary_color || '#8B5CF6'}80` }"
                    @mouseenter="$event.currentTarget.style.borderColor = `${business.primary_color || '#8B5CF6'}80`"
                    @mouseleave="$event.currentTarget.style.borderColor = ''">
                    
                    <!-- Game Icon -->
                    <div class="text-4xl mb-3 transform group-hover:scale-110 transition-transform">
                        {{ getGameIcon(game.type) }}
                    </div>
                    
                    <!-- Game Name -->
                    <h3 class="text-white font-semibold text-sm mb-1">{{ game.name }}</h3>
                    
                    <!-- Game Info -->
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                        <span v-if="game.time_limit" class="flex items-center gap-1">
                            ⏱️ {{ game.time_limit }}s
                        </span>
                        <span v-if="game.plays_today !== undefined" class="flex items-center gap-1">
                            🎯 {{ game.plays_today || 0 }} plays
                        </span>
                    </div>

                    <!-- Prize Rules -->
                    <div v-if="getPrizeLines(game).length" class="mt-3 text-xs text-purple-200/90">
                        <div class="text-[11px] uppercase tracking-wide text-purple-300/80 mb-1">Prize Rules</div>
                        <div v-for="(line, idx) in getPrizeLines(game)" :key="idx" class="text-gray-300">
                            {{ line }}
                        </div>
                    </div>

                    <!-- Practice available indicator -->
                    <div v-if="hasChallengeMode(game)" class="mt-2">
                        <span class="text-[10px] uppercase tracking-wide text-amber-400/80 bg-amber-500/10 px-1.5 py-0.5 rounded">Practice available</span>
                    </div>

                    <!-- Difficulty Badge -->
                    <div v-if="game.difficulty" class="absolute top-2 right-2">
                        <span :class="[
                            'text-xs px-2 py-0.5 rounded-full',
                            game.difficulty === 'easy' ? 'bg-green-500/20 text-green-400' :
                            game.difficulty === 'medium' ? 'bg-yellow-500/20 text-yellow-400' :
                            'bg-red-500/20 text-red-400'
                        ]">
                            {{ game.difficulty }}
                        </span>
                    </div>

                    <!-- Play Arrow -->
                    <div class="absolute bottom-3 right-3 w-8 h-8 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                        :style="{ backgroundColor: `${business.primary_color || '#8B5CF6'}33` }">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" :style="{ color: business.primary_color || '#8B5CF6' }">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </Link>
            </div>

            <!-- No Games -->
            <div v-if="games.length === 0" class="text-center py-12">
                <div class="text-6xl mb-4">🎮</div>
                <h3 class="text-white font-semibold mb-2">No Games Available</h3>
                <p class="text-gray-400 text-sm">Check back soon for new games!</p>
            </div>
        </div>

        <!-- Leaderboards Preview -->
        <div v-if="qrCode?.type === 'qrcade_leaderboard' && leaderboards && leaderboards.length > 0" class="px-4 pb-8">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <span>🏆 Leaderboards</span>
                <span class="text-xs bg-amber-500/30 text-amber-300 px-2 py-1 rounded-full">Compete for top scores!</span>
            </h2>
            <div class="space-y-3">
                <Link v-for="lb in leaderboards" :key="lb.id"
                    :href="`/portal/leaderboards/${lb.id}?back=${encodeURIComponent(gameSelectUrl)}`"
                    class="block bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-4 hover:bg-white/10 hover:border-amber-500/50 transition-all">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="text-white font-medium mb-1">{{ lb.name }}</div>
                            <div class="text-gray-400 text-xs mb-2">
                                {{ lb.game ? lb.game.name : 'All Games' }}
                            </div>
                            <div v-if="lb.top_scores && lb.top_scores.length > 0" class="flex items-center gap-3 text-xs">
                                <span class="text-gray-500">Top Scores:</span>
                                <span v-for="(entry, idx) in lb.top_scores" :key="idx" class="text-gray-300">
                                    {{ entry.rank === 1 ? '🥇' : entry.rank === 2 ? '🥈' : entry.rank === 3 ? '🥉' : '#' + entry.rank }}
                                    {{ entry.score.toLocaleString() }}
                                </span>
                            </div>
                        </div>
                        <div class="text-amber-400 text-xl font-bold ml-4">→</div>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Location Notice -->
        <div v-if="locationRequirements?.type !== 'none'" class="px-4 pb-8">
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">📍</span>
                    <div>
                        <h4 class="text-amber-400 font-medium text-sm">Location Required</h4>
                        <p class="text-gray-400 text-xs mt-1">
                            You'll need to be at {{ business.name }} to play these games
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sign Up CTA - Only show if not logged in -->
        <div v-if="!isLoggedIn" class="px-4 pb-4">
            <div class="p-4 rounded-xl bg-gradient-to-r from-purple-500/10 to-pink-500/10 border border-purple-500/20">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-purple-400 font-medium text-sm">🏆 Track your scores & earn rewards!</p>
                        <p class="text-gray-400 text-xs">Join free to save progress & compete on leaderboards</p>
                    </div>
                    <Link :href="`/portal/join?redirect_to=${encodeURIComponent(gameSelectUrl)}`" class="px-4 py-2 bg-purple-500 text-white text-sm font-semibold rounded-lg hover:bg-purple-600 transition-colors whitespace-nowrap">
                        Join Free
                    </Link>
                </div>
            </div>
            <div class="mt-2 text-center">
                <Link :href="`/login?redirect_to=${encodeURIComponent(gameSelectUrl)}`" class="text-gray-500 text-xs hover:text-gray-300 transition-colors">
                    Already have an account? Sign in
                </Link>
            </div>
        </div>

        <!-- Footer -->
        <div v-if="showPlatformBranding" class="px-4 pb-6 text-center">
            <p class="text-gray-500 text-xs">
                Powered by <span class="text-purple-400">Revenue QR</span>
            </p>
        </div>
    </div>
</template>
