<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
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

const props = defineProps({
    allGames: Array,
    businessGames: Object,
    gameTiers: Object,
    subscriptionTier: String,
});

const selectedTier = ref('all');
const showSettings = ref(null);
const demoGame = ref(null);
const gameEngineRef = ref(null);
const demoRunKey = ref(0);
const showDemoResults = ref(false);
const demoResult = ref(null);

const gameComponents = {
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

const getGameComponent = (type) => {
    return gameComponents[type] || null;
};

const openDemo = (game) => {
    demoGame.value = game;
    demoRunKey.value++;
    showDemoResults.value = false;
    demoResult.value = null;
};

const closeDemo = () => {
    demoGame.value = null;
    showDemoResults.value = false;
    demoResult.value = null;
};

const handleDemoComplete = (result) => {
    // In demo mode, just show the score - no server tracking
    console.log('Demo completed with score:', result.score);
    demoResult.value = result;
    showDemoResults.value = true;
};

const restartDemo = () => {
    demoRunKey.value++;
    showDemoResults.value = false;
    demoResult.value = null;
};

// Get game-specific tip
const getGameTip = (gameType) => {
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
    return tips[gameType] || '💡 Tip: Practice makes perfect - keep playing to improve your score!';
};

const filteredGames = computed(() => {
    if (selectedTier.value === 'all') return props.allGames;
    if (selectedTier.value === 'basic') {
        return props.allGames.filter(g => g.tier === 'basic');
    }
    if (selectedTier.value === 'pro') {
        // Show basic + pro games (cumulative)
        return props.allGames.filter(g => g.tier === 'basic' || g.tier === 'pro');
    }
    if (selectedTier.value === 'premium') {
        // Show basic + pro + premium games (cumulative)
        return props.allGames.filter(g => g.tier === 'basic' || g.tier === 'pro' || g.tier === 'premium');
    }
    return props.allGames;
});

const isGameEnabled = (gameId) => {
    return props.businessGames[gameId]?.is_enabled || false;
};

const getGameSettings = (gameId) => {
    return props.businessGames[gameId] || {};
};

const toggleGame = (gameSlug) => {
    const form = useForm({});
    // IMPORTANT: Game uses slug for route model binding (see Game::getRouteKeyName()).
    form.post(`/business/qrcade/games/${gameSlug}/toggle`, {
        preserveScroll: true,
        onSuccess: () => {
            // Refresh the page data to update the enabled status
            router.reload({ only: ['businessGames'] });
        },
    });
};

const settingsForm = useForm({
    max_plays_per_day: null,
    max_plays_per_week: null,
    cooldown_minutes: null,
    leaderboard_enabled: true,
    staff_can_play: false,
});

const openSettings = (game) => {
    const settings = getGameSettings(game.id);
    settingsForm.max_plays_per_day = settings.max_plays_per_day || null;
    settingsForm.max_plays_per_week = settings.max_plays_per_week || null;
    settingsForm.cooldown_minutes = settings.cooldown_minutes || null;
    settingsForm.leaderboard_enabled = settings.leaderboard_enabled ?? true;
    settingsForm.staff_can_play = settings.staff_can_play ?? false;
    showSettings.value = game;
};

const saveSettings = () => {
    // IMPORTANT: Game uses slug for route model binding (see Game::getRouteKeyName()).
    settingsForm.put(`/business/qrcade/games/${showSettings.value.slug}`, {
        preserveScroll: true,
        onSuccess: () => showSettings.value = null,
    });
};

const getTierColor = (tier) => {
    const colors = {
        basic: 'text-gray-400 bg-gray-500/20',
        pro: 'text-blue-400 bg-blue-500/20',
        premium: 'text-purple-400 bg-purple-500/20',
    };
    return colors[tier] || colors.basic;
};

const getGameIcon = (type) => {
    const icons = {
        memory_match: '🧠',
        word_search: '🔤',
        snake: '🐍',
        tap_counter: '👆',
        brick_breaker: '🧱',
        qr_dash: '⬛',
        cupcake_catcher: '🧁',
        slice_saver: '🍕',
        vape_cloud_pop: '☁️',
        coffee_rush: '☕',
    };
    return icons[type] || '🎮';
};

const getRequiredTier = (gameTier) => {
    const tierMap = {
        'basic': 'Starter',
        'pro': 'Growth',
        'premium': 'Pro',
        'seasonal': 'Pro',
    };
    return tierMap[gameTier] || 'Pro';
};
</script>

<template>
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <Link href="/business/qrcade" class="text-gray-400 hover:text-white text-sm mb-2 inline-block">
                        ← Back to QRcade
                    </Link>
                    <h1 class="text-3xl font-bold text-white">Manage Games</h1>
                    <p class="text-gray-400 mt-1">Enable games and configure their settings</p>
                </div>
            </div>

            <!-- Info Box -->
            <div class="glass-card p-4 mb-6 bg-blue-500/10 border border-blue-500/30">
                <div class="flex items-start gap-3">
                    <div class="text-blue-400 text-xl">ℹ️</div>
                    <div class="flex-1">
                        <p class="text-white font-medium mb-1">How Game Toggles Work</p>
                        <p class="text-gray-400 text-sm">
                            When you toggle a game ON, it becomes available to select when creating QR codes. 
                            Only enabled games will appear in the game selection dropdown on the QR code creation page. 
                            Toggle games OFF to hide them from new QR codes (existing QR codes with that game will still work).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tier Filter -->
            <div class="flex gap-2 mb-6">
                <button @click="selectedTier = 'all'"
                    :class="['px-4 py-2 rounded-lg font-medium transition-colors',
                        selectedTier === 'all' ? 'bg-primary-500 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20']">
                    All Games
                </button>
                <button v-for="(label, tier) in gameTiers" :key="tier"
                    @click="selectedTier = tier"
                    :class="['px-4 py-2 rounded-lg font-medium transition-colors',
                        selectedTier === tier ? 'bg-primary-500 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20']">
                    {{ label }}
                </button>
            </div>

            <!-- Games Grid -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="game in filteredGames" :key="game.id"
                    :class="['glass-card p-6 relative overflow-hidden',
                        !game.is_accessible ? 'opacity-60' : '']">
                    <!-- Tier Badge -->
                    <div class="absolute top-3 right-3 flex gap-2">
                        <span :class="['px-2 py-1 rounded text-xs font-medium', getTierColor(game.tier)]">
                            {{ getRequiredTier(game.tier) }}
                        </span>
                        <span v-if="!game.is_accessible" 
                            class="px-2 py-1 rounded text-xs font-medium bg-gray-600 text-gray-300">
                            🔒 Locked
                        </span>
                    </div>

                    <!-- Game Info -->
                    <div class="flex items-start gap-4 mb-4">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-2xl">
                            {{ getGameIcon(game.type) }}
                        </div>
                        <div class="flex-1">
                            <h3 class="text-white font-semibold">{{ game.name }}</h3>
                            <p class="text-gray-400 text-sm mt-1 line-clamp-2">{{ game.description }}</p>
                        </div>
                    </div>

                    <!-- Game Details -->
                    <div class="flex items-center gap-4 text-sm text-gray-400 mb-4">
                        <span v-if="game.time_limit">⏱️ {{ game.time_limit }}s</span>
                        <span v-if="game.max_score">🎯 Max {{ game.max_score }}</span>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-4 border-t border-white/10">
                        <button @click="toggleGame(game.slug)"
                            :disabled="!game.is_accessible"
                            :class="['relative w-12 h-6 rounded-full transition-colors',
                                isGameEnabled(game.id) ? 'bg-emerald-500' : 'bg-gray-600',
                                !game.is_accessible ? 'opacity-50 cursor-not-allowed' : '']">
                            <span :class="['absolute top-1 w-4 h-4 rounded-full bg-white transition-transform',
                                isGameEnabled(game.id) ? 'left-7' : 'left-1']"></span>
                        </button>
                        <div class="flex items-center gap-2">
                            <button @click="openDemo(game)"
                                class="px-3 py-1.5 bg-gradient-to-r from-purple-500 to-pink-500 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                                ▶️ Play Demo
                            </button>
                            <button v-if="game.is_accessible" @click="openSettings(game)"
                                class="text-gray-400 hover:text-white transition-colors">
                                ⚙️
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Modal -->
            <div v-if="showSettings" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="glass-card p-6 w-full max-w-md">
                    <h2 class="text-xl font-semibold text-white mb-4">
                        {{ showSettings.name }} Settings
                    </h2>
                    
                    <form @submit.prevent="saveSettings" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Max Plays Per Day</label>
                            <input type="number" v-model="settingsForm.max_plays_per_day"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="Unlimited">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Max Plays Per Week</label>
                            <input type="number" v-model="settingsForm.max_plays_per_week"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="Unlimited">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Cooldown (minutes)</label>
                            <input type="number" v-model="settingsForm.cooldown_minutes"
                                class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white"
                                placeholder="No cooldown">
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <span class="text-gray-300">Enable Leaderboard</span>
                                <p class="text-xs text-gray-500 mt-0.5">Allow scores from this game to be recorded in location leaderboards</p>
                            </div>
                            <button type="button" @click="settingsForm.leaderboard_enabled = !settingsForm.leaderboard_enabled"
                                :class="['relative w-12 h-6 rounded-full transition-colors flex-shrink-0',
                                    settingsForm.leaderboard_enabled ? 'bg-emerald-500' : 'bg-gray-600']">
                                <span :class="['absolute top-1 w-4 h-4 rounded-full bg-white transition-transform',
                                    settingsForm.leaderboard_enabled ? 'left-7' : 'left-1']"></span>
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-gray-300">Staff Can Play</span>
                            <button type="button" @click="settingsForm.staff_can_play = !settingsForm.staff_can_play"
                                :class="['relative w-12 h-6 rounded-full transition-colors',
                                    settingsForm.staff_can_play ? 'bg-emerald-500' : 'bg-gray-600']">
                                <span :class="['absolute top-1 w-4 h-4 rounded-full bg-white transition-transform',
                                    settingsForm.staff_can_play ? 'left-7' : 'left-1']"></span>
                            </button>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showSettings = null"
                                class="flex-1 px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20">
                                Cancel
                            </button>
                            <button type="submit" :disabled="settingsForm.processing"
                                class="flex-1 px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Demo Modal -->
            <div v-if="demoGame" class="fixed inset-0 bg-black/80 z-50 flex flex-col">
                <!-- Demo Header -->
                <div class="bg-black/50 backdrop-blur-xl border-b border-white/10 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-xl">
                            {{ getGameIcon(demoGame.type) }}
                        </div>
                        <div>
                            <div class="text-white font-semibold">{{ demoGame.name }}</div>
                            <div class="text-amber-400 text-xs font-medium">🎮 Demo Mode</div>
                        </div>
                    </div>
                    <button @click="closeDemo"
                        class="p-2 text-gray-400 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Game Container -->
                <div class="flex-1 overflow-auto">
                    <GameEngine 
                        ref="gameEngineRef"
                        :key="demoRunKey"
                        :game="demoGame"
                        :business="{ name: 'Demo Business', logo: null }"
                        :sessionToken="`demo-${demoRunKey}`"
                        @complete="handleDemoComplete">
                        <template #default="{ gameState, score, timeRemaining, isPaused, addScore, endGame }">
                            <component 
                                :is="getGameComponent(demoGame.type)"
                                :gameState="gameState"
                                :score="score"
                                :timeRemaining="timeRemaining"
                                :isPaused="isPaused"
                                :addScore="addScore"
                                :endGame="endGame"
                                :config="demoGame.config" />
                        </template>
                    </GameEngine>

                    <!-- Demo Results Overlay -->
                    <div v-if="showDemoResults"
                        class="fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
                        <div class="glass-card p-6 w-full max-w-md text-center">
                            <div class="text-5xl mb-3">🏁</div>
                            <h3 class="text-xl font-semibold text-white">Demo Complete</h3>
                            <p class="text-gray-300 mt-1">Score: <span class="text-purple-400 font-bold">{{ demoResult?.score ?? 0 }}</span></p>
                            <p class="text-gray-500 text-xs mt-3">
                                {{ getGameTip(demoGame?.type) }}
                            </p>

                            <div class="mt-6 flex gap-3">
                                <button @click="restartDemo"
                                    class="flex-1 px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600">
                                    Play Again
                                </button>
                                <button @click="closeDemo"
                                    class="flex-1 px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Demo Footer Note -->
                <div class="bg-amber-500/10 border-t border-amber-500/30 px-4 py-2 text-center">
                    <p class="text-amber-400 text-sm">
                        👆 This is a demo preview — scores won't be recorded
                    </p>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.glass-card {
    @apply bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl;
}
</style>

