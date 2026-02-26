<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    timeRemaining: Number,
    config: Object,
});

const cups = ref([]);
const caught = ref(0);
const dropped = ref(0);
const combo = ref(0);
const catcherPosition = ref(50);
const gameLoop = ref(null);
const spawnInterval = ref(null);
const touchStartX = ref(null);
const sliderBar = ref(null);
const maxDropped = 5;

const cupTypes = [
    { emoji: '☕', points: 10, speed: 2, name: 'Coffee' },
    { emoji: '🧋', points: 15, speed: 2.5, name: 'Boba' },
    { emoji: '🥤', points: 20, speed: 3, name: 'Iced Coffee' },
    { emoji: '🍵', points: 25, speed: 2, name: 'Matcha' },
    { emoji: '⭐', points: 50, speed: 4, name: 'Golden Cup' }, // Rare
];

const spawnCup = () => {
    if (props.gameState !== 'playing') return;
    
    const typeIndex = Math.random() < 0.05 ? 4 : Math.floor(Math.random() * 4);
    const cupType = cupTypes[typeIndex];
    
    cups.value.push({
        id: Date.now() + Math.random(),
        x: Math.random() * 80 + 10,
        y: 0,
        ...cupType,
        caught: false,
    });
};

const updateGame = () => {
    if (props.gameState !== 'playing') return;
    
    cups.value = cups.value.filter(cup => {
        if (cup.caught) return false;
        
        cup.y += cup.speed * 0.5;
        
        // Check if caught
        const catcherLeft = catcherPosition.value - 12;
        const catcherRight = catcherPosition.value + 12;
        
        if (cup.y >= 85 && cup.y <= 95) {
            if (cup.x >= catcherLeft && cup.x <= catcherRight) {
                // Caught!
                cup.caught = true;
                caught.value++;
                combo.value++;
                
                const comboBonus = Math.min(combo.value, 10) * 5;
                props.addScore(cup.points + comboBonus);
                
                return false;
            }
        }
        
        // Dropped
        if (cup.y > 100) {
            dropped.value++;
            combo.value = 0;
            
            if (dropped.value >= maxDropped) {
                // Game over - too many dropped
                if (gameLoop.value) cancelAnimationFrame(gameLoop.value);
                if (spawnInterval.value) clearInterval(spawnInterval.value);
                
                props.endGame({
                    cupsCaught: caught.value,
                    cupsDropped: dropped.value,
                    gameOverReason: 'Too many cups dropped!',
                });
            }
            
            return false;
        }
        
        return true;
    });
    
    gameLoop.value = requestAnimationFrame(updateGame);
};

const moveCatcher = (direction) => {
    if (props.gameState !== 'playing') return;
    
    const moveAmount = 8;
    if (direction === 'left') {
        catcherPosition.value = Math.max(10, catcherPosition.value - moveAmount);
    } else {
        catcherPosition.value = Math.min(90, catcherPosition.value + moveAmount);
    }
};

const isDraggingSlider = ref(false);

const updateCatcherFromSlider = (clientX) => {
    if (!sliderBar.value || props.gameState !== 'playing') return;
    const rect = sliderBar.value.getBoundingClientRect();
    const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
    catcherPosition.value = Math.max(10, Math.min(90, ratio * 100));
};

const handleSliderTouchStart = (e) => {
    e.preventDefault();
    isDraggingSlider.value = true;
    updateCatcherFromSlider(e.touches[0].clientX);
};

const handleSliderTouchMove = (e) => {
    e.preventDefault();
    if (!isDraggingSlider.value) return;
    updateCatcherFromSlider(e.touches[0].clientX);
};

const handleSliderTouchEnd = () => {
    isDraggingSlider.value = false;
};

const handleSliderMouseDown = (e) => {
    isDraggingSlider.value = true;
    updateCatcherFromSlider(e.clientX);
    document.addEventListener('mousemove', handleDocMouseMove);
    document.addEventListener('mouseup', handleDocMouseUp);
};

const handleDocMouseMove = (e) => {
    if (!isDraggingSlider.value) return;
    updateCatcherFromSlider(e.clientX);
};

const handleDocMouseUp = () => {
    isDraggingSlider.value = false;
    document.removeEventListener('mousemove', handleDocMouseMove);
    document.removeEventListener('mouseup', handleDocMouseUp);
};

const catcherPercent = computed(() => {
    return ((catcherPosition.value - 10) / 80) * 100;
});

const handleKeydown = (e) => {
    if (e.key === 'ArrowLeft' || e.key === 'a') {
        moveCatcher('left');
    } else if (e.key === 'ArrowRight' || e.key === 'd') {
        moveCatcher('right');
    }
};

const handleTouchStart = (e) => {
    touchStartX.value = e.touches[0].clientX;
};

const handleTouchMove = (e) => {
    if (!touchStartX.value || props.gameState !== 'playing') return;
    
    const touch = e.touches[0];
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((touch.clientX - rect.left) / rect.width) * 100;
    catcherPosition.value = Math.max(10, Math.min(90, x));
};

const handleMouseMove = (e) => {
    if (props.gameState !== 'playing') return;
    
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    catcherPosition.value = Math.max(10, Math.min(90, x));
};

const startGame = () => {
    cups.value = [];
    caught.value = 0;
    dropped.value = 0;
    combo.value = 0;
    catcherPosition.value = 50;
    
    window.addEventListener('keydown', handleKeydown);
    
    // Spawn cups
    spawnInterval.value = setInterval(() => {
        if (props.gameState === 'playing') {
            spawnCup();
            // Increase spawn rate over time
            if (Math.random() < 0.3) spawnCup();
        }
    }, 1000);
    
    gameLoop.value = requestAnimationFrame(updateGame);
};

watch(() => props.gameState, (state) => {
    if (state === 'playing') {
        startGame();
    }
});

watch(() => props.timeRemaining, (time) => {
    if (time <= 0 && props.gameState === 'playing') {
        if (gameLoop.value) cancelAnimationFrame(gameLoop.value);
        if (spawnInterval.value) clearInterval(spawnInterval.value);
        window.removeEventListener('keydown', handleKeydown);
        
        props.endGame({
            cupsCaught: caught.value,
            cupsDropped: dropped.value,
        });
    }
});

onUnmounted(() => {
    if (gameLoop.value) cancelAnimationFrame(gameLoop.value);
    if (spawnInterval.value) clearInterval(spawnInterval.value);
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('mousemove', handleDocMouseMove);
    document.removeEventListener('mouseup', handleDocMouseUp);
});
</script>

<template>
    <div class="flex flex-col items-center min-h-[60vh] p-4">
        <!-- Stats Bar -->
        <div class="flex gap-4 mb-4">
            <div class="text-center px-4 py-2 bg-white/10 rounded-lg">
                <div class="text-xl font-bold text-green-400">{{ caught }}</div>
                <div class="text-gray-400 text-xs">Caught</div>
            </div>
            <div class="text-center px-4 py-2 bg-white/10 rounded-lg">
                <div class="text-xl font-bold text-red-400">{{ dropped }}/{{ maxDropped }}</div>
                <div class="text-gray-400 text-xs">Lives</div>
            </div>
            <div v-if="combo > 1" class="text-center px-4 py-2 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-lg">
                <div class="text-xl font-bold text-yellow-400">x{{ combo }}</div>
                <div class="text-gray-400 text-xs">Combo!</div>
            </div>
        </div>

        <!-- Game Area -->
        <div 
            class="relative w-full max-w-md h-96 bg-gradient-to-b from-amber-900/30 to-amber-800/30 rounded-2xl overflow-hidden border border-amber-500/20"
            @touchstart="handleTouchStart"
            @touchmove.prevent="handleTouchMove"
            @mousemove="handleMouseMove"
        >
            <!-- Coffee Shop Background -->
            <div class="absolute top-2 left-1/2 -translate-x-1/2 text-2xl">☕ COFFEE RUSH ☕</div>

            <!-- Falling Cups -->
            <div
                v-for="cup in cups"
                :key="cup.id"
                class="absolute text-4xl transition-none select-none"
                :style="{
                    left: cup.x + '%',
                    top: cup.y + '%',
                    transform: 'translateX(-50%)',
                }"
            >
                {{ cup.emoji }}
            </div>

            <!-- Catcher (Tray) -->
            <div 
                class="absolute bottom-4"
                :style="{ left: catcherPosition + '%', transform: 'translateX(-50%)' }"
            >
                <div class="relative">
                    <div class="text-5xl">🍽️</div>
                    <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-16 h-2 bg-amber-600 rounded-full"></div>
                </div>
            </div>

            <!-- Ready State -->
            <div v-if="gameState === 'ready'" class="absolute inset-0 flex flex-col items-center justify-center bg-black/50">
                <div class="text-6xl mb-4">☕</div>
                <div class="text-white text-xl font-bold">Coffee Rush!</div>
                <div class="text-gray-400 text-sm mt-2">Move tray to catch falling cups</div>
                <div class="text-gray-500 text-xs mt-4">
                    Desktop: Move mouse or Arrow keys<br>
                    Mobile: Touch and drag
                </div>
            </div>

            <!-- Game Over State -->
            <div v-if="gameState === 'completed'" class="absolute inset-0 flex flex-col items-center justify-center bg-black/70">
                <div class="text-4xl mb-2">{{ dropped >= maxDropped ? '😢' : '🎉' }}</div>
                <div class="text-white text-xl font-bold">
                    {{ dropped >= maxDropped ? 'Too Many Spills!' : 'Time\'s Up!' }}
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm max-w-xs">
            Catch falling coffee cups! Don't drop more than {{ maxDropped }} or it's game over! Golden cups (⭐) are worth 50 points!
        </div>

        <!-- Finger Slider Bar -->
        <div
            v-if="gameState === 'playing'"
            ref="sliderBar"
            class="mt-3 w-full max-w-md h-12 rounded-xl bg-white/10 backdrop-blur border border-white/10 relative cursor-pointer select-none"
            style="touch-action: none;"
            @touchstart="handleSliderTouchStart"
            @touchmove="handleSliderTouchMove"
            @touchend="handleSliderTouchEnd"
            @mousedown="handleSliderMouseDown"
        >
            <div class="absolute top-1/2 left-2 right-2 h-1 rounded-full bg-white/10 -translate-y-1/2"></div>
            <div
                class="absolute top-1/2 -translate-y-1/2 w-10 h-8 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 shadow-lg transition-none"
                :style="{ left: `calc(${catcherPercent}% * 0.875 + 4px)` }"
            ></div>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span class="text-white/30 text-xs font-medium">SLIDE TO MOVE</span>
            </div>
        </div>
    </div>
</template>
