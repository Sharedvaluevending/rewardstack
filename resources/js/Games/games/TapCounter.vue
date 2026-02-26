<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    timeRemaining: Number,
    config: Object,
});

const taps = ref(0);
const lastTapTime = ref(0);
const comboMultiplier = ref(1);
const showCombo = ref(false);
const tapEffects = ref([]);

const handleTap = (event) => {
    if (props.gameState !== 'playing') return;

    const now = Date.now();
    const timeSinceLastTap = now - lastTapTime.value;

    // Build combo for fast taps
    if (timeSinceLastTap < 300) {
        comboMultiplier.value = Math.min(comboMultiplier.value + 0.1, 3);
        showCombo.value = true;
    } else {
        comboMultiplier.value = 1;
        showCombo.value = false;
    }

    lastTapTime.value = now;
    taps.value++;

    // Calculate points
    const points = Math.round(10 * comboMultiplier.value);
    props.addScore(points);

    // Add tap effect
    const rect = event.currentTarget.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    
    tapEffects.value.push({
        id: Date.now(),
        x,
        y,
        points,
    });

    // Remove effect after animation
    setTimeout(() => {
        tapEffects.value = tapEffects.value.filter(e => e.id !== Date.now() - 500);
    }, 500);
};

watch(() => props.timeRemaining, (time) => {
    if (time <= 0 && props.gameState === 'playing') {
        props.endGame({
            totalTaps: taps.value,
            maxCombo: comboMultiplier.value,
        });
    }
});

watch(() => props.gameState, (state) => {
    if (state === 'playing') {
        taps.value = 0;
        comboMultiplier.value = 1;
        tapEffects.value = [];
    }
});
</script>

<template>
    <div class="flex flex-col items-center justify-center min-h-[70vh] p-4">
        <!-- Combo Display -->
        <div v-if="showCombo" class="mb-4 animate-bounce">
            <span class="px-4 py-2 bg-gradient-to-r from-yellow-500 to-orange-500 text-white font-bold rounded-full text-lg">
                x{{ comboMultiplier.toFixed(1) }} COMBO!
            </span>
        </div>

        <!-- Tap Counter Display -->
        <div class="text-center mb-8">
            <div class="text-4xl sm:text-6xl font-bold text-white mb-2">{{ taps }}</div>
            <div class="text-gray-400 text-sm sm:text-base">TAPS</div>
        </div>

        <!-- Tap Area -->
        <div class="relative">
            <button @click="handleTap"
                    @touchstart="handleTap"
                :disabled="gameState !== 'playing'"
                :class="[
                    'w-48 h-48 sm:w-64 sm:h-64 rounded-full transition-all duration-100',
                    'bg-gradient-to-br from-purple-500 to-pink-500',
                    'shadow-lg shadow-purple-500/50',
                    'flex items-center justify-center text-white text-2xl sm:text-4xl font-bold',
                    'active:scale-95',
                    gameState === 'playing'
                        ? 'hover:scale-105 cursor-pointer'
                        : 'opacity-50 cursor-not-allowed'
                ]">
                <span v-if="gameState === 'playing'" class="select-none">TAP!</span>
                <span v-else-if="gameState === 'ready'" class="text-xl sm:text-2xl select-none">READY?</span>
                <span v-else class="text-xl sm:text-2xl select-none">DONE!</span>
            </button>

            <!-- Tap Effects -->
            <div v-for="effect in tapEffects" :key="effect.id"
                class="absolute pointer-events-none animate-ping"
                :style="{ left: effect.x + 'px', top: effect.y + 'px' }">
                <span class="text-yellow-400 font-bold text-lg">+{{ effect.points }}</span>
            </div>
        </div>

        <!-- Instructions -->
        <div v-if="gameState === 'ready'" class="mt-8 text-center text-gray-400 text-sm">
            Tap as fast as you can! Build combos for bonus points!
        </div>
    </div>
</template>

<style scoped>
@keyframes float-up {
    0% { opacity: 1; transform: translateY(0); }
    100% { opacity: 0; transform: translateY(-50px); }
}
</style>

