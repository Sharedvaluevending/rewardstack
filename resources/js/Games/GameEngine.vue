<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    game: Object,
    business: Object,
    sessionToken: String,
    onComplete: Function,
    readyInfoTitle: String,
    readyInfoLines: Array,
    isPractice: {
        type: Boolean,
        default: false,
    },
    hasChallengeMode: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['start', 'complete', 'score', 'switchMode']);

const gameState = ref('ready'); // ready, playing, paused, completed
const score = ref(0);
const timeRemaining = ref(props.game?.time_limit || 60);
const startTime = ref(null);
const timerInterval = ref(null);
const isPaused = ref(false);

const formattedTime = computed(() => {
    const mins = Math.floor(timeRemaining.value / 60);
    const secs = timeRemaining.value % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
});

const startGame = () => {
    gameState.value = 'playing';
    startTime.value = Date.now();
    emit('start');

    // (local debug ingest removed)
    
    if (props.game?.time_limit) {
        timerInterval.value = setInterval(() => {
            if (!isPaused.value) {
                timeRemaining.value--;
                if (timeRemaining.value <= 0) {
                    endGame();
                }
            }
        }, 1000);
    }
};

const pauseGame = () => {
    isPaused.value = true;
    gameState.value = 'paused';
};

const resumeGame = () => {
    isPaused.value = false;
    gameState.value = 'playing';
};

const addScore = (points) => {
    score.value += points;
    emit('score', score.value);
};

const endGame = (gameData = {}) => {
    if (gameState.value === 'completed') {
        return;
    }
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
    }
    
    gameState.value = 'completed';
    
    const duration = startTime.value ? Math.round((Date.now() - startTime.value) / 1000) : 0;
    
    emit('complete', {
        score: score.value,
        duration,
        gameData,
    });
    
    if (props.onComplete) {
        props.onComplete({
            score: score.value,
            duration,
            gameData,
        });
    }
};

onUnmounted(() => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
    }
});

// Expose methods for child games
defineExpose({
    gameState,
    score,
    timeRemaining,
    isPaused,
    startGame,
    pauseGame,
    resumeGame,
    addScore,
    endGame,
});
</script>

<template>
    <div class="game-engine min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
        <!-- Game Header -->
        <div class="sticky top-0 z-40 bg-black/50 backdrop-blur-xl border-b border-white/10 px-3 sm:px-4 py-3">
            <!-- Practice mode banner -->
            <div v-if="isPractice && gameState === 'playing'" class="flex items-center justify-center gap-2 mb-2 -mx-3 sm:-mx-4 -mt-3 px-3 py-1.5 bg-amber-500/20 border-b border-amber-500/30">
                <span class="text-amber-300 text-xs font-semibold uppercase tracking-wide">Practice Mode</span>
                <span class="text-amber-400/60 text-xs">— Score won't be saved</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div v-if="business?.logo" class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-white overflow-hidden">
                        <img :src="business.logo" :alt="business.name" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <div class="text-white font-semibold text-sm sm:text-base">{{ game?.name }}</div>
                        <div class="text-gray-400 text-xs">{{ business?.name }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Score -->
                    <div class="text-center">
                        <div class="text-xl sm:text-2xl font-bold text-purple-400">{{ score }}</div>
                        <div class="text-gray-400 text-xs">SCORE</div>
                    </div>
                    <!-- Timer -->
                    <div v-if="game?.time_limit" class="text-center">
                        <div :class="['text-xl sm:text-2xl font-bold font-mono',
                            timeRemaining <= 10 ? 'text-red-400 animate-pulse' : 'text-white']">
                            {{ formattedTime }}
                        </div>
                        <div class="text-gray-400 text-xs">TIME</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game Content -->
        <div class="relative">
            <!-- Ready State -->
            <div v-if="gameState === 'ready'" 
                class="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm z-30">
                <div class="text-center p-8 max-w-sm mx-auto">
                    <div class="text-6xl mb-4">🎮</div>
                    <h2 class="text-2xl font-bold text-white mb-2">{{ game?.name }}</h2>
                    <p class="text-gray-400 mb-6">{{ game?.description }}</p>
                    <div v-if="readyInfoLines?.length" class="mb-6">
                        <div class="text-xs uppercase tracking-wide text-purple-300/80 mb-2">
                            {{ readyInfoTitle || 'Prize Rules' }}
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl p-3 text-left">
                            <div v-for="(line, idx) in readyInfoLines" :key="idx" class="text-sm text-gray-200">
                                {{ line }}
                            </div>
                        </div>
                    </div>

                    <!-- Practice mode indicator -->
                    <div v-if="isPractice" class="mb-4 bg-amber-500/15 border border-amber-500/40 rounded-xl px-4 py-3">
                        <p class="text-amber-300 font-semibold text-sm">Practice Mode</p>
                        <p class="text-gray-300 text-xs mt-1">This run won't affect your score or ranking</p>
                    </div>

                    <!-- Single button for practice mode or non-challenge games -->
                    <div v-if="isPractice || !hasChallengeMode" class="space-y-3">
                        <button @click="startGame"
                            class="w-full px-8 py-4 bg-gradient-to-r from-purple-500 to-pink-500 text-white text-lg font-bold rounded-xl hover:opacity-90 transition-opacity">
                            {{ isPractice ? 'START PRACTICE' : 'START GAME' }}
                        </button>
                        <button v-if="isPractice"
                            @click="emit('switchMode', false)"
                            class="w-full px-6 py-3 bg-white/10 border border-white/20 text-white font-semibold rounded-xl hover:bg-white/20 transition-all text-sm">
                            Switch to Play for Score
                        </button>
                    </div>

                    <!-- Two buttons for challenge/leaderboard games -->
                    <div v-else class="space-y-3">
                        <button @click="startGame"
                            class="w-full px-8 py-4 bg-gradient-to-r from-purple-500 to-pink-500 text-white text-lg font-bold rounded-xl hover:opacity-90 transition-opacity flex items-center justify-center gap-2">
                            <span>PLAY FOR SCORE</span>
                        </button>
                        <button @click="emit('switchMode', true)"
                            class="w-full px-6 py-3 bg-white/10 border border-white/20 text-white font-semibold rounded-xl hover:bg-white/20 transition-all flex items-center justify-center gap-2 text-sm">
                            <span>PRACTICE RUN</span>
                            <span class="text-gray-400 text-xs">(doesn't affect rank)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paused State -->
            <div v-if="gameState === 'paused'"
                class="absolute inset-0 flex items-center justify-center bg-black/70 backdrop-blur-sm z-30">
                <div class="text-center p-8">
                    <div class="text-4xl mb-4">⏸️</div>
                    <h2 class="text-2xl font-bold text-white mb-4">PAUSED</h2>
                    <button @click="resumeGame"
                        class="px-8 py-3 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-bold rounded-xl">
                        RESUME
                    </button>
                </div>
            </div>

            <!-- Game Slot -->
            <slot 
                :gameState="gameState"
                :score="score"
                :timeRemaining="timeRemaining"
                :isPaused="isPaused"
                :addScore="addScore"
                :endGame="endGame"
                :startGame="startGame">
            </slot>
        </div>
    </div>
</template>

