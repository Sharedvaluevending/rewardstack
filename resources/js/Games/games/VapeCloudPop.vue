<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    timeRemaining: Number,
    config: Object,
});

const clouds = ref([]);
const popped = ref(0);
const missed = ref(0);
const combo = ref(0);
const spawnInterval = ref(null);
const gameLoop = ref(null);

const cloudTypes = [
    { emoji: '💨', points: 10, speed: 1, size: 'text-4xl' },
    { emoji: '☁️', points: 20, speed: 1.5, size: 'text-5xl' },
    { emoji: '🌫️', points: 30, speed: 2, size: 'text-3xl' },
    { emoji: '💭', points: 50, speed: 2.5, size: 'text-6xl' }, // Rare big cloud
];

const spawnCloud = () => {
    if (props.gameState !== 'playing') return;
    
    const typeIndex = Math.random() < 0.1 ? 3 : Math.floor(Math.random() * 3);
    const cloudType = cloudTypes[typeIndex];
    
    const cloud = {
        id: Date.now() + Math.random(),
        x: Math.random() * 80 + 10, // 10-90%
        y: 100, // Start at bottom
        ...cloudType,
        opacity: 1,
        popping: false,
    };
    
    clouds.value.push(cloud);
};

const updateClouds = () => {
    if (props.gameState !== 'playing') return;
    
    clouds.value = clouds.value.filter(cloud => {
        if (cloud.popping) return true; // Keep popping clouds briefly
        
        cloud.y -= cloud.speed * 0.5;
        cloud.opacity = Math.max(0, cloud.opacity - 0.002);
        
        // Cloud escaped or faded
        if (cloud.y < -10 || cloud.opacity <= 0) {
            missed.value++;
            combo.value = 0;
            return false;
        }
        
        return true;
    });
    
    gameLoop.value = requestAnimationFrame(updateClouds);
};

const popCloud = (cloud) => {
    if (props.gameState !== 'playing' || cloud.popping) return;
    
    cloud.popping = true;
    popped.value++;
    combo.value++;
    
    const comboBonus = Math.min(combo.value, 10);
    const points = cloud.points + (comboBonus * 5);
    props.addScore(points);
    
    // Remove after pop animation
    setTimeout(() => {
        clouds.value = clouds.value.filter(c => c.id !== cloud.id);
    }, 200);
};

const startGame = () => {
    clouds.value = [];
    popped.value = 0;
    missed.value = 0;
    combo.value = 0;
    
    // Spawn clouds periodically
    spawnInterval.value = setInterval(() => {
        if (props.gameState === 'playing') {
            spawnCloud();
            // Spawn more clouds as time goes on
            if (Math.random() < 0.3) spawnCloud();
        }
    }, 800);
    
    // Start game loop
    gameLoop.value = requestAnimationFrame(updateClouds);
};

watch(() => props.gameState, (state) => {
    if (state === 'playing') {
        startGame();
    }
});

watch(() => props.timeRemaining, (time) => {
    if (time <= 0 && props.gameState === 'playing') {
        if (spawnInterval.value) clearInterval(spawnInterval.value);
        if (gameLoop.value) cancelAnimationFrame(gameLoop.value);
        
        props.endGame({
            cloudsPopped: popped.value,
            cloudsMissed: missed.value,
            accuracy: popped.value > 0 ? Math.round((popped.value / (popped.value + missed.value)) * 100) : 0,
        });
    }
});

onUnmounted(() => {
    if (spawnInterval.value) clearInterval(spawnInterval.value);
    if (gameLoop.value) cancelAnimationFrame(gameLoop.value);
});
</script>

<template>
    <div class="relative flex flex-col items-center min-h-[60vh] p-4 overflow-hidden">
        <!-- Stats Bar -->
        <div class="flex gap-6 mb-4 z-10">
            <div class="text-center px-4 py-2 bg-white/10 rounded-lg">
                <div class="text-xl font-bold text-purple-400">{{ popped }}</div>
                <div class="text-gray-400 text-xs">Popped</div>
            </div>
            <div class="text-center px-4 py-2 bg-white/10 rounded-lg">
                <div class="text-xl font-bold text-red-400">{{ missed }}</div>
                <div class="text-gray-400 text-xs">Escaped</div>
            </div>
            <div v-if="combo > 1" class="text-center px-4 py-2 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-lg animate-pulse">
                <div class="text-xl font-bold text-yellow-400">x{{ combo }}</div>
                <div class="text-gray-400 text-xs">Combo!</div>
            </div>
        </div>

        <!-- Game Area -->
        <div class="relative w-full h-96 bg-gradient-to-b from-purple-900/30 to-indigo-900/30 rounded-2xl overflow-hidden border border-purple-500/20">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-10 left-10 text-6xl">💨</div>
                <div class="absolute top-20 right-20 text-4xl">☁️</div>
                <div class="absolute bottom-20 left-20 text-5xl">🌫️</div>
            </div>

            <!-- Clouds -->
            <div
                v-for="cloud in clouds"
                :key="cloud.id"
                @click="popCloud(cloud)"
                :class="[
                    'absolute cursor-pointer transition-transform duration-100 select-none',
                    cloud.size,
                    cloud.popping ? 'scale-150 opacity-0' : 'hover:scale-110 active:scale-90'
                ]"
                :style="{
                    left: cloud.x + '%',
                    bottom: cloud.y + '%',
                    opacity: cloud.popping ? 0 : cloud.opacity,
                    transform: `translateX(-50%)`,
                    transition: cloud.popping ? 'all 0.2s ease-out' : 'none',
                }"
            >
                {{ cloud.emoji }}
                <span v-if="cloud.popping" class="absolute -top-4 left-1/2 -translate-x-1/2 text-yellow-400 text-sm font-bold">
                    +{{ cloud.points }}
                </span>
            </div>

            <!-- Ready State -->
            <div v-if="gameState === 'ready'" class="absolute inset-0 flex flex-col items-center justify-center">
                <div class="text-6xl mb-4 animate-bounce">💨</div>
                <div class="text-white text-xl font-bold">Pop the Vape Clouds!</div>
                <div class="text-gray-400 text-sm mt-2">Tap clouds before they float away</div>
            </div>

            <!-- Completed State -->
            <div v-if="gameState === 'completed'" class="absolute inset-0 flex flex-col items-center justify-center bg-black/50">
                <div class="text-4xl mb-2">🎉</div>
                <div class="text-white text-xl font-bold">Time's Up!</div>
            </div>
        </div>

        <!-- Instructions -->
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm max-w-xs">
            Tap clouds before they escape! Build combos for bonus points. Watch out - some clouds are faster!
        </div>
    </div>
</template>

<style scoped>
@keyframes float {
    0%, 100% { transform: translateY(0) translateX(-50%); }
    50% { transform: translateY(-10px) translateX(-50%); }
}
</style>
