<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import GameEngine from '@/Games/GameEngine.vue';
import MemoryMatch from '@/Games/games/MemoryMatch.vue';
import TapCounter from '@/Games/games/TapCounter.vue';
import Snake from '@/Games/games/Snake.vue';
import WordSearch from '@/Games/games/WordSearch.vue';
import BrickBreaker from '@/Games/games/BrickBreaker.vue';
import QRDash from '@/Games/games/QRDash.vue';
import CupcakeCatcher from '@/Games/games/CupcakeCatcher.vue';
import SliceSaver from '@/Games/games/SliceSaver.vue';
import VapeCloudPop from '@/Games/games/VapeCloudPop.vue';
import CoffeeRush from '@/Games/games/CoffeeRush.vue';
import { collectAndSendScanGeo } from '@/utils/scanGeo';

const page = usePage();

const props = defineProps({
    qrCode: Object,
    business: Object,
    game: Object,
    locationRequirements: Object,
    dailyPuzzleStatus: Object, // For daily puzzles like Word Search
    qrCodeGame: Object,
    promotionMap: Object,
});

const gameEngine = ref(null);
const sessionToken = ref(null);
const funOnly = ref(false);
const funOnlyReason = ref(null);
const locationVerified = ref(false);
const locationError = ref(null);
const isVerifying = ref(false);
const playId = ref(null);
const isPractice = ref(false);

const user = computed(() => page.props.auth?.user);
const showResults = ref(false);
const gameResult = ref(null);
const isSubmittingScore = ref(false);
const submittingResult = ref(false); // show "saving" state between game end and server response

// Determine if this game has a challenge/leaderboard win mode (show practice button)
const hasChallengeMode = computed(() => {
    const qrGame = props.qrCodeGame;
    if (!qrGame || !qrGame.win_mode) return false;
    const challengeModes = ['score', 'time', 'leaderboard', 'tiered', 'skill'];
    return challengeModes.includes(qrGame.win_mode);
});

const portalCtaHref = computed(() => {
    return '/portal/scans';
});

const portalCtaLabel = computed(() => {
    if (gameResult.value?.punch_card) return 'View My Punch Card';
    return 'View in My Scans';
});

const getPromotionName = (id) => {
    if (!id) return null;
    return props.promotionMap?.[id]?.name || null;
};

// Tier display helpers for tiered (Gold/Silver/Bronze) wins
const tierConfig = {
    gold: { label: 'GOLD', emoji: '🥇', color: 'text-yellow-400', bg: 'from-yellow-500/20 to-amber-500/15', border: 'border-yellow-500/40' },
    silver: { label: 'SILVER', emoji: '🥈', color: 'text-gray-300', bg: 'from-gray-400/20 to-gray-500/15', border: 'border-gray-400/40' },
    bronze: { label: 'BRONZE', emoji: '🥉', color: 'text-orange-400', bg: 'from-orange-500/20 to-amber-600/15', border: 'border-orange-500/40' },
};

const wonTierDisplay = computed(() => {
    const tier = gameResult.value?.tier;
    if (!tier || !gameResult.value?.won) return null;
    const config = tierConfig[tier];
    if (!config) return null;

    // Get the promotion name: prefer server-returned name, fall back to local lookup
    let promoName = gameResult.value?.won_promotion_name || null;
    if (!promoName) {
        const promoId = props.qrCodeGame?.tier_rewards?.[tier];
        promoName = getPromotionName(promoId);
    }

    return { ...config, tier, promoName };
});

const prizeRules = computed(() => {
    const qrGame = props.qrCodeGame;
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
});

const gameComponent = computed(() => {
    const components = {
        memory_match: MemoryMatch,
        tap_counter: TapCounter,
        snake: Snake,
        word_search: WordSearch,
        brick_breaker: BrickBreaker,
        qr_dash: QRDash,
        cupcake_catcher: CupcakeCatcher,
        slice_saver: SliceSaver,
        vape_cloud_pop: VapeCloudPop,
        coffee_rush: CoffeeRush,
    };
    return components[props.game.type] || null;
});

// Check if this is a daily puzzle that's already been completed
const isDailyPuzzleCompleted = computed(() => {
    return props.dailyPuzzleStatus?.is_daily_puzzle && props.dailyPuzzleStatus?.completed_today;
});

const dailySeed = computed(() => {
    return props.dailyPuzzleStatus?.daily_seed || null;
});

onMounted(() => {
    collectAndSendScanGeo(props.qrCode?.code);
});

// Get game-specific tip
const getGameTip = () => {
    const tips = {
        memory_match: '💡 Tip: Try to remember pairs by their position patterns - corners and edges are easier to recall!',
        tap_counter: '💡 Tip: Build combos by tapping consistently - each combo multiplies your score!',
        snake: '💡 Tip: Plan your path ahead - avoid trapping yourself in corners!',
        word_search: '💡 Tip: Daily puzzles are the same for everyone - compete with friends to see who finishes fastest!',
        brick_breaker: '💡 Tip: Aim for the corners to break bricks more efficiently and keep the ball moving!',
        qr_dash: '💡 Tip: Use your double jump wisely - save one jump for tricky obstacles!',
        cupcake_catcher: '💡 Tip: Cupcake Catcher ends after 5 missed desserts or when the timer runs out.',
        slice_saver: '💡 Tip: Watch for patterns in falling slices - they often repeat!',
        vape_cloud_pop: '💡 Tip: Pop clouds quickly to build momentum - speed is key!',
        coffee_rush: '💡 Tip: Keep your combo going - dropped cups reset your multiplier!',
    };
    return tips[props.game?.type] || '💡 Tip: Practice makes perfect - keep playing to improve your score!';
};

// Calculate time until next puzzle
const timeUntilNextPuzzle = computed(() => {
    if (!props.dailyPuzzleStatus?.next_puzzle_at) return null;
    const next = new Date(props.dailyPuzzleStatus.next_puzzle_at);
    const now = new Date();
    const diff = next - now;
    if (diff <= 0) return 'Available now!';
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    return `${hours}h ${minutes}m`;
});

const verifyLocation = async () => {
    if (props.locationRequirements.type === 'none') {
        locationVerified.value = true;
        return startSession();
    }

    isVerifying.value = true;
    locationError.value = null;

    try {
        if (props.locationRequirements.gps_required) {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                });
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            console.log('Verifying location with CSRF token:', csrfToken.substring(0, 20) + '...');

            const response = await axios.post('/api/game/verify-location', {
                qr_code_id: props.qrCode.id,
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
            }, {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });

            const data = response.data;
            
            if (data.verified) {
                locationVerified.value = true;
                await startSession({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                });
            } else {
                locationError.value = 'You must be at the business location to play!';
            }
        }
    } catch (error) {
        locationError.value = 'Could not verify your location. Please enable GPS.';
    } finally {
        isVerifying.value = false;
    }
};

const startSession = async (locationData = {}) => {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        console.log('Starting session with CSRF token:', csrfToken ? 'Present' : 'Missing');

        const resolvedGameSlug = (() => {
            const raw = props.game?.slug;
            if (typeof raw === 'string') {
                // If somehow we got a JSON string, extract the real slug
                if (raw.trim().startsWith('{')) {
                    try {
                        const parsed = JSON.parse(raw);
                        if (parsed?.slug) return parsed.slug;
                        if (parsed?.id) return String(parsed.id);
                    } catch (e) {
                        // ignore
                    }
                }
                return raw;
            }
            // Fallbacks
            if (raw && typeof raw === 'object' && raw.slug) return raw.slug;
            if (props.game?.id != null) return String(props.game.id);
            return '';
        })();

        const apiUrl = `/api/play/${props.qrCode.code}/game/${encodeURIComponent(resolvedGameSlug)}/start`;
        console.log('Making API request to:', apiUrl);

        const response = await axios.post(apiUrl, { ...locationData, is_practice: isPractice.value }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            timeout: 10000, // 10 second timeout
        });

        const data = response.data;

        if (data.success) {
            sessionToken.value = data.session.token;
            console.log('Session token set:', sessionToken.value.substring(0, 10) + '...');
            playId.value = null; // reset for new session
            funOnly.value = !!data.session.fun_only;
            funOnlyReason.value = data.session.fun_only_reason || null;
        } else {
            locationError.value = data.error || 'Could not start game session';
        }
    } catch (error) {
        console.error('Start session error:', error);
        locationError.value = 'Network error. Please try again.';
    }
};

const handleGameStart = async () => {
    // Ensure we only create one GamePlay per session start
    if (!sessionToken.value || playId.value) return;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await axios.post(`/api/play/session/${sessionToken.value}/play`, { is_practice: isPractice.value }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });

        if (response.data?.success) {
            playId.value = response.data.play_id;
            funOnly.value = !!response.data.fun_only || funOnly.value;
            funOnlyReason.value = response.data.fun_only_reason || funOnlyReason.value;
        }
    } catch (error) {
        console.error('Failed to mark game as playing:', error);
        // Non-fatal; score submit will fail without a play, but we'll still show local results.
    }
};

const handleGameComplete = async (result) => {
    if (showResults.value || submittingResult.value) {
        return;
    }
    // Immediately enter results with a saving state to avoid flashing "Play Again"
    submittingResult.value = true;
    gameResult.value = { ...result, pending: true };
    showResults.value = true;

    // Submit score to server
    try {
        if (isSubmittingScore.value) {
            return;
        }
        isSubmittingScore.value = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        console.log('Submitting score with CSRF token:', csrfToken.substring(0, 20) + '...');

        const response = await axios.post(`/api/play/session/${sessionToken.value}/score`, {
            score: result.score,
            game_data: result.gameData,
        }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });

        const data = response.data;

        if (data.success) {
            gameResult.value = { ...result, ...data.result, pending: false };
            if (data.result?.rewards_disabled) {
                funOnly.value = true;
                funOnlyReason.value = data.result.rewards_disabled_reason || funOnlyReason.value;
            }

            // If this was a daily puzzle and they won, mark it so they can't replay
            if (props.dailyPuzzleStatus?.is_daily_puzzle && data.result?.won) {
                justWonDailyPuzzle.value = true;
            }
        } else if (data.already_completed) {
            // They already completed today's puzzle (caught by server validation)
            justWonDailyPuzzle.value = true;
            gameResult.value = {
                ...result,
                pending: false,
                alreadyCompleted: true,
                message: 'You already completed today\'s puzzle!'
            };
        }
    } catch (error) {
        console.error('Failed to submit score:', error);
    } finally {
        submittingResult.value = false;
        isSubmittingScore.value = false;
    }
};

// Track if user just won a daily puzzle (to prevent instant replay)
const justWonDailyPuzzle = ref(false);

const playAgain = () => {
    // If this was a daily puzzle win, don't allow replay
    if (justWonDailyPuzzle.value) {
        return;
    }
    showResults.value = false;
    gameResult.value = null;
    sessionToken.value = null;
    verifyLocation();
};

const handleSwitchMode = (toPractice) => {
    // Just toggle the local practice flag — no session restart needed.
    // The practice flag is sent to the backend when the game actually starts (startPlaying).
    isPractice.value = toPractice;
};

const switchToScoredPlay = () => {
    isPractice.value = false;
    showResults.value = false;
    gameResult.value = null;
    sessionToken.value = null;
    playId.value = null;
    verifyLocation();
};

onMounted(() => {
    verifyLocation();
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
        <!-- Daily Puzzle Already Completed -->
        <div v-if="isDailyPuzzleCompleted" class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-md w-full text-center">
                <div class="text-6xl mb-4">🎯</div>
                <h2 class="text-2xl font-bold text-white mb-2">Puzzle Complete!</h2>
                <p class="text-gray-400 mb-6">
                    You've already conquered today's Word Search puzzle. Come back tomorrow for a fresh challenge!
                </p>
                
                <!-- Countdown to next puzzle -->
                <div class="bg-purple-500/20 rounded-xl p-4 mb-6">
                    <div class="text-sm text-purple-300 mb-1">Next puzzle in</div>
                    <div class="text-3xl font-bold text-purple-400 font-mono">{{ timeUntilNextPuzzle }}</div>
                </div>

                <div class="space-y-3">
                    <a :href="`/play/${qrCode.code}`"
                        class="block w-full py-3 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-bold rounded-xl hover:opacity-90">
                        Play Other Games
                    </a>
                    <Link href="/portal" 
                        class="block w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20">
                        View My Stats
                    </Link>
                    <Link 
                        href="/portal/scans" 
                        class="block w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20"
                    >
                        Back to My Portal
                    </Link>
                </div>

                <!-- Fun fact or tip -->
                <div class="mt-6 pt-6 border-t border-white/10">
                    <p class="text-gray-500 text-sm">
                        {{ getGameTip() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Location Verification -->
        <div v-else-if="!sessionToken" class="flex items-center justify-center min-h-screen p-4">
            <div class="text-center">
                <div v-if="isVerifying" class="mb-4">
                    <div class="w-16 h-16 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-white mt-4">Verifying location...</p>
                </div>
                <div v-else-if="locationError" class="bg-red-500/20 border border-red-500/50 rounded-xl p-6 max-w-sm">
                    <div class="text-4xl mb-4">📍</div>
                    <p class="text-red-400 mb-4">{{ locationError }}</p>
                    <button @click="verifyLocation"
                        class="px-6 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600">
                        Try Again
                    </button>
                </div>
            </div>
        </div>

        <!-- Game -->
        <GameEngine v-else-if="!showResults"
            ref="gameEngine"
            :game="game"
            :business="business"
            :sessionToken="sessionToken"
            :readyInfoTitle="'Prize Rules'"
            :readyInfoLines="prizeRules"
            :isPractice="isPractice"
            :hasChallengeMode="hasChallengeMode"
            @start="handleGameStart"
            @complete="handleGameComplete"
            @switchMode="handleSwitchMode">
            <template #default="{ gameState, score, timeRemaining, isPaused, addScore, endGame }">
                <component :is="gameComponent"
                    :gameState="gameState"
                    :score="score"
                    :timeRemaining="timeRemaining"
                    :isPaused="isPaused"
                    :addScore="addScore"
                    :endGame="endGame"
                    :config="game.config"
                    :dailySeed="dailySeed"
                    v-bind="game.type === 'memory_match' ? { qrSeed: qrCode.code } : {}" />
            </template>
        </GameEngine>

        <!-- Results Screen -->
        <div v-else class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-md w-full text-center">
                <!-- Saving state -->
                <div v-if="submittingResult" class="mb-6">
                    <div class="w-16 h-16 border-4 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                    <h2 class="text-xl font-bold text-white">Saving your score...</h2>
                    <p class="text-gray-400 text-sm mt-2">Please wait a moment.</p>
                </div>
                        <!-- Fun-only banner -->
                        <div v-if="funOnly" class="mb-5 p-4 rounded-xl bg-red-500/15 border border-red-500/40">
                            <p class="text-red-300 font-bold text-sm">Just for Fun Mode</p>
                            <p class="text-gray-300 text-xs mt-1">
                                {{ funOnlyReason || 'Rewards are disabled for this QR code. You can keep playing for fun.' }}
                            </p>
                        </div>

                <!-- Practice mode result header -->
                <template v-if="!submittingResult && isPractice">
                    <div class="mb-4 bg-amber-500/15 border border-amber-500/40 rounded-xl px-4 py-3">
                        <p class="text-amber-300 font-semibold text-sm">Practice Run Complete</p>
                        <p class="text-gray-300 text-xs mt-1">This score was not saved to your record</p>
                    </div>
                    <div class="text-6xl mb-4">🎯</div>
                    <h2 class="text-3xl font-bold text-white mb-2">PRACTICE SCORE</h2>
                    <div class="text-5xl font-bold text-amber-400 mb-6">
                        {{ gameResult?.score }}
                    </div>
                </template>

                <!-- Tiered win result header (Gold/Silver/Bronze) -->
                <template v-else-if="!submittingResult && wonTierDisplay">
                    <div class="text-6xl mb-2">{{ wonTierDisplay.emoji }}</div>
                    <div class="mb-3">
                        <span :class="['inline-block px-4 py-1 rounded-full text-sm font-black tracking-widest', wonTierDisplay.color, 'bg-white/10']">
                            {{ wonTierDisplay.label }} TIER
                        </span>
                    </div>
                    <h2 class="text-3xl font-bold text-white mb-2">YOU WON!</h2>
                    <div class="text-5xl font-bold mb-4" :class="wonTierDisplay.color">
                        {{ gameResult?.score }}
                    </div>
                    <!-- Show what they won -->
                    <div v-if="wonTierDisplay.promoName" :class="['mb-6 rounded-xl p-4 bg-gradient-to-r border', wonTierDisplay.bg, wonTierDisplay.border]">
                        <div class="text-xs uppercase tracking-wide text-white/60 mb-1">Your Prize</div>
                        <div class="text-lg font-bold text-white">{{ wonTierDisplay.promoName }}</div>
                    </div>
                </template>

                <!-- Normal result header -->
                <template v-else>
                    <div v-if="!submittingResult" class="text-6xl mb-4">
                        {{ gameResult?.won ? '🎉' : (gameResult?.leaderboard?.rank ? '🏆' : '👏') }}
                    </div>
                    <h2 v-if="!submittingResult" class="text-3xl font-bold text-white mb-2">
                        {{ gameResult?.won ? 'YOU WON!' : (gameResult?.leaderboard?.rank ? 'LEADERBOARD UPDATED' : 'GAME OVER') }}
                    </h2>
                    <div v-if="!submittingResult" class="text-5xl font-bold text-purple-400 mb-6">
                        {{ gameResult?.score }}
                    </div>
                </template>
                <div v-if="!submittingResult && !isPractice && prizeRules.length && !wonTierDisplay" class="mb-6 bg-white/5 border border-white/10 rounded-xl p-4 text-left">
                    <div class="text-xs uppercase tracking-wide text-purple-300/80 mb-2">Prize Rules</div>
                    <div v-for="(line, idx) in prizeRules" :key="idx" class="text-sm text-gray-200">
                        {{ line }}
                    </div>
                    <div v-if="!gameResult?.won" class="text-xs text-gray-400 mt-2">
                        No prize this time. Try again to hit the win condition.
                    </div>
                </div>

                <!-- Practice mode tip -->
                <div v-if="!submittingResult && isPractice && prizeRules.length" class="mb-6 bg-white/5 border border-white/10 rounded-xl p-4 text-left">
                    <div class="text-xs uppercase tracking-wide text-purple-300/80 mb-2">What You're Playing For</div>
                    <div v-for="(line, idx) in prizeRules" :key="idx" class="text-sm text-gray-200">
                        {{ line }}
                    </div>
                    <div class="text-xs text-amber-300 mt-2">
                        Ready to compete? Hit "Play for Score" below!
                    </div>
                </div>

                <!-- Leaderboard Status (challenge mode) -->
                <div
                    v-if="!isPractice && !gameResult?.won && gameResult?.leaderboard?.rank"
                    class="mb-6 bg-amber-500/10 border border-amber-500/30 rounded-xl p-4"
                >
                    <div class="text-amber-300 font-semibold">
                        Current rank: #{{ gameResult.leaderboard.rank }}
                        <span v-if="gameResult.leaderboard.threshold" class="text-gray-300 font-normal">
                            (Top {{ gameResult.leaderboard.threshold }} win prizes at period end)
                        </span>
                    </div>
                    <div class="text-gray-300 text-xs mt-2">
                        Your prize (if you finish in a winning position) will be awarded when the leaderboard period ends.
                        <span v-if="gameResult.leaderboard.leaderboard_id">
                            View it in your portal.
                        </span>
                    </div>
                </div>

                <!-- Achievements (hidden in practice mode) -->
                <div v-if="!isPractice && gameResult?.is_high_score" class="mb-4">
                    <span class="px-4 py-2 bg-amber-500/20 text-amber-400 rounded-full text-sm font-medium">
                        🏆 NEW HIGH SCORE!
                    </span>
                </div>
                <div v-if="!isPractice && gameResult?.is_personal_best" class="mb-4">
                    <span class="px-4 py-2 bg-purple-500/20 text-purple-400 rounded-full text-sm font-medium">
                        ⭐ PERSONAL BEST!
                    </span>
                </div>

                <!-- Badges Earned (hidden in practice mode) -->
                <div v-if="!isPractice && gameResult?.badges_earned?.length" class="mb-6">
                    <h3 class="text-white font-medium mb-3">Badges Earned</h3>
                    <div class="flex justify-center gap-3">
                        <div v-for="badge in gameResult.badges_earned" :key="badge.id"
                            class="bg-white/10 rounded-xl p-3 text-center">
                            <div class="text-2xl">{{ badge.icon || '🏅' }}</div>
                            <div class="text-xs text-white mt-1">{{ badge.name }}</div>
                        </div>
                    </div>
                </div>

                <!-- Reward Saved (same flow as scanning a promotion QR, hidden in practice mode) -->
                <div v-if="!isPractice && !submittingResult && gameResult?.user_promo_token?.code" class="mb-6 bg-gradient-to-r from-emerald-500/15 to-green-500/10 rounded-xl p-4 border border-emerald-500/30">
                    <div class="text-2xl mb-2">✅</div>
                    <h3 class="text-white font-bold">Saved to My Scans</h3>
                    <p class="text-emerald-300 text-sm mt-1 mb-3">
                        Your reward is ready to redeem — just like a normal promotion scan.
                    </p>
                    <div class="bg-black/30 rounded-lg p-3 text-center border border-white/10">
                        <div class="text-xs text-gray-300 mb-1">Customer Code</div>
                        <div class="text-emerald-300 font-mono text-xl font-bold tracking-wider">
                            {{ gameResult.user_promo_token.code }}
                        </div>
                        <div class="text-xs text-gray-400 mt-2">
                            Show this code (or the QR in My Scans) to staff to redeem.
                        </div>
                    </div>
                </div>
                <div
                    v-else-if="!isPractice && !submittingResult && gameResult?.won && !gameResult?.punch_card"
                    class="mb-6 bg-amber-500/10 border border-amber-500/30 rounded-xl p-4"
                >
                    <div class="text-amber-300 font-semibold text-sm">Win recorded, but no promo issued</div>
                    <div class="text-gray-300 text-xs mt-2">
                        This can happen if rewards are disabled, a limit was reached, or no promotion is attached.
                    </div>
                </div>

                <!-- Punch Card Progress (for punch-card promotions, hidden in practice mode) -->
                <div v-if="!isPractice && !submittingResult && gameResult?.punch_card" class="mb-6 bg-gradient-to-r from-emerald-500/10 to-green-500/10 rounded-xl p-4 border border-emerald-500/20">
                    <div class="text-2xl mb-2">✅</div>
                    <h3 class="text-white font-bold">Punch Added!</h3>
                    <p class="text-emerald-300 text-sm mt-1 mb-3">
                        {{ gameResult.punch_card.current_punches }} / {{ gameResult.punch_card.total_required }} punches
                        <span v-if="gameResult.punch_card.completed_cards > 0"> • {{ gameResult.punch_card.completed_cards }} completed</span>
                    </p>
                    <div v-if="gameResult.punch_card.card_completed" class="bg-emerald-500/20 rounded-lg p-3 text-emerald-300 text-sm font-medium">
                        🎉 Card completed! Tell staff you finished a full punch card.
                    </div>
                </div>

                <!-- (Removed) Promotion QR code display:
                     For wins, we now generate the same short UP-XXXX-XXXX customer code as a normal promo scan. -->

                <!-- XP Earned (hidden in practice mode) -->
                <div v-if="!isPractice && !submittingResult && gameResult?.xp_earned" class="text-gray-400 text-sm mb-6">
                    +{{ gameResult.xp_earned }} XP
                </div>

                <!-- Actions -->
                <div class="space-y-3">
                    <!-- Daily puzzle completed - no replay -->
                    <template v-if="!submittingResult && justWonDailyPuzzle && gameResult?.won">
                        <div class="bg-emerald-500/20 rounded-xl p-4 mb-2">
                            <p class="text-emerald-400 text-sm font-medium">
                                🎯 Daily puzzle complete! Come back tomorrow for a new challenge.
                            </p>
                        </div>
                        <a :href="`/play/${qrCode.code}`"
                            class="block w-full py-3 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-bold rounded-xl hover:opacity-90">
                            Play Other Games
                        </a>
                    </template>
                    <!-- Practice mode result actions -->
                    <template v-else-if="!submittingResult && isPractice">
                        <button @click="switchToScoredPlay"
                            class="w-full py-3 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-bold rounded-xl hover:opacity-90">
                            PLAY FOR SCORE
                        </button>
                        <button @click="playAgain"
                            class="w-full py-3 bg-white/10 border border-white/20 text-white font-medium rounded-xl hover:bg-white/20">
                            PRACTICE AGAIN
                        </button>
                        <a :href="`/play/${qrCode.code}`"
                            class="block w-full py-3 bg-white/5 text-gray-400 font-medium rounded-xl hover:bg-white/10 text-sm">
                            Choose Another Game
                        </a>
                    </template>
                    <!-- Normal game or didn't win - can replay -->
                    <template v-else-if="!submittingResult">
                        <button @click="playAgain"
                            class="w-full py-3 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-bold rounded-xl hover:opacity-90">
                            PLAY AGAIN
                        </button>
                        <a :href="`/play/${qrCode.code}`"
                            class="block w-full py-3 bg-white/10 text-white font-medium rounded-xl hover:bg-white/20">
                            Choose Another Game
                        </a>
                    </template>
                </div>

                <!-- Sign Up CTA (only show if not authenticated) -->
                <div v-if="!user" class="mt-6 pt-6 border-t border-white/10">
                    <div class="p-4 rounded-xl bg-gradient-to-r from-emerald-500/10 to-green-500/10 border border-emerald-500/20">
                        <p class="text-emerald-400 font-medium text-sm">
                            <template v-if="gameResult?.won">🎁 Save your win to redeem later!</template>
                            <template v-else>💰 Save your scores & earn rewards!</template>
                        </p>
                        <p class="text-gray-400 text-xs mb-3">
                            <template v-if="gameResult?.won">Create a free account to save your reward & redeem it anytime</template>
                            <template v-else>Join free to track progress & earn referral income</template>
                        </p>
                        <div class="flex gap-2">
                            <Link href="/portal/join" class="flex-1 py-2 bg-emerald-500 text-white text-sm font-semibold rounded-lg text-center hover:bg-emerald-600 transition-colors">
                                Join Free
                            </Link>
                            <Link href="/login" class="flex-1 py-2 bg-white/10 text-white text-sm font-medium rounded-lg text-center hover:bg-white/20 transition-colors">
                                Sign In
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Already signed in message -->
                <div v-else class="mt-6 pt-6 border-t border-white/10">
                    <div v-if="funOnly" class="p-4 rounded-xl bg-red-500/10 border border-red-500/20">
                        <p class="text-red-300 font-medium text-sm">
                            🎮 Playing just for fun
                        </p>
                        <p class="text-gray-400 text-xs">
                            Rewards and tracking are disabled for this QR code right now.
                        </p>
                        <Link
                            href="/portal/scans"
                            class="mt-3 inline-flex items-center justify-center w-full px-4 py-2 bg-white/10 text-white text-sm font-medium rounded-lg hover:bg-white/20 transition-colors"
                        >
                            Back to My Portal
                        </Link>
                    </div>
                    <div v-else class="p-4 rounded-xl bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-500/20">
                        <p class="text-blue-400 font-medium text-sm">
                            ✅ Your game has been saved to your account!
                        </p>
                        <p class="text-gray-400 text-xs mb-3">
                            View your progress and rewards in your portal.
                        </p>
                        <Link :href="portalCtaHref" class="block w-full py-2 bg-blue-500 text-white text-sm font-semibold rounded-lg text-center hover:bg-blue-600 transition-colors">
                            {{ portalCtaLabel }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

