<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    timeRemaining: Number,
    config: Object,
});

const CANVAS_WIDTH = 320;
const CANVAS_HEIGHT = 480;
const BASKET_WIDTH = 60;
const BASKET_HEIGHT = 40;
const ITEM_SIZE = 30;

const canvas = ref(null);
const ctx = ref(null);
const animationFrame = ref(null);

const basket = ref({ x: CANVAS_WIDTH / 2 - BASKET_WIDTH / 2 });
const items = ref([]);
const caught = ref(0);
const missed = ref(0);
const MAX_MISSED = 5;

const itemTypes = [
    { emoji: '🧁', points: 10, good: true },
    { emoji: '🍩', points: 15, good: true },
    { emoji: '🍰', points: 20, good: true },
    { emoji: '🎂', points: 25, good: true },
    { emoji: '💣', points: -50, good: false },
];

const initGame = () => {
    basket.value = { x: CANVAS_WIDTH / 2 - BASKET_WIDTH / 2 };
    items.value = [];
    caught.value = 0;
    missed.value = 0;
};

const spawnItem = () => {
    const type = itemTypes[Math.floor(Math.random() * itemTypes.length)];
    items.value.push({
        x: Math.random() * (CANVAS_WIDTH - ITEM_SIZE),
        y: -ITEM_SIZE,
        speed: 2 + Math.random() * 3,
        ...type,
    });
};

const draw = () => {
    if (!ctx.value) return;
    
    const c = ctx.value;
    
    // Background gradient
    const gradient = c.createLinearGradient(0, 0, 0, CANVAS_HEIGHT);
    gradient.addColorStop(0, '#fdf2f8');
    gradient.addColorStop(1, '#fce7f3');
    c.fillStyle = gradient;
    c.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
    
    // Draw items
    items.value.forEach(item => {
        c.font = `${ITEM_SIZE}px serif`;
        c.textAlign = 'center';
        c.fillText(item.emoji, item.x + ITEM_SIZE / 2, item.y + ITEM_SIZE);
    });
    
    // Draw basket
    c.fillStyle = '#92400e';
    c.beginPath();
    c.moveTo(basket.value.x, CANVAS_HEIGHT - BASKET_HEIGHT);
    c.lineTo(basket.value.x + BASKET_WIDTH, CANVAS_HEIGHT - BASKET_HEIGHT);
    c.lineTo(basket.value.x + BASKET_WIDTH - 10, CANVAS_HEIGHT);
    c.lineTo(basket.value.x + 10, CANVAS_HEIGHT);
    c.closePath();
    c.fill();
    
    // Basket rim
    c.fillStyle = '#78350f';
    c.fillRect(basket.value.x - 5, CANVAS_HEIGHT - BASKET_HEIGHT - 5, BASKET_WIDTH + 10, 8);
    
    // Draw hearts (lives)
    const livesLeft = MAX_MISSED - missed.value;
    for (let i = 0; i < MAX_MISSED; i++) {
        c.font = '20px serif';
        c.fillText(i < livesLeft ? '❤️' : '🖤', 20 + i * 25, 30);
    }
};

const update = () => {
    if (props.gameState !== 'playing') {
        if (animationFrame.value) {
            cancelAnimationFrame(animationFrame.value);
            animationFrame.value = null;
        }
        return;
    }
    
    // Spawn new items
    if (Math.random() < 0.03) {
        spawnItem();
    }
    
    // Update items
    items.value.forEach(item => {
        item.y += item.speed;
        
        // Check if caught
        if (item.y + ITEM_SIZE >= CANVAS_HEIGHT - BASKET_HEIGHT &&
            item.y <= CANVAS_HEIGHT &&
            item.x + ITEM_SIZE >= basket.value.x &&
            item.x <= basket.value.x + BASKET_WIDTH) {
            item.caught = true;
            if (item.good) {
                caught.value++;
                props.addScore(item.points);
            } else {
                props.addScore(item.points);
                missed.value++;
            }
        }
        
        // Check if missed
        if (item.y > CANVAS_HEIGHT && !item.caught && item.good) {
            item.missed = true;
            missed.value++;
        }
    });
    
    // Remove caught/missed items
    items.value = items.value.filter(item => !item.caught && !item.missed && item.y <= CANVAS_HEIGHT + ITEM_SIZE);
    
    // Check game over
    if (missed.value >= MAX_MISSED) {
        if (animationFrame.value) {
            cancelAnimationFrame(animationFrame.value);
            animationFrame.value = null;
        }
        props.endGame({ caught: caught.value, missed: missed.value });
        return;
    }
    
    draw();
    animationFrame.value = requestAnimationFrame(update);
};

const sliderBar = ref(null);

const handleMove = (x) => {
    if (props.gameState !== 'playing') return;
    basket.value.x = Math.max(0, Math.min(CANVAS_WIDTH - BASKET_WIDTH, x - BASKET_WIDTH / 2));
};

const moveBasketFromClient = (clientX, referenceEl) => {
    if (props.gameState !== 'playing' || !referenceEl) return;
    const rect = referenceEl.getBoundingClientRect();
    const ratio = (clientX - rect.left) / rect.width;
    const canvasX = ratio * CANVAS_WIDTH;
    handleMove(canvasX);
};

const handleMouseMove = (e) => {
    if (!canvas.value) return;
    const rect = canvas.value.getBoundingClientRect();
    handleMove(e.clientX - rect.left);
};

const handleTouchMove = (e) => {
    if (!canvas.value) return;
    e.preventDefault();
    const rect = canvas.value.getBoundingClientRect();
    handleMove(e.touches[0].clientX - rect.left);
};

const isDraggingSlider = ref(false);

const handleSliderTouchStart = (e) => {
    e.preventDefault();
    isDraggingSlider.value = true;
    moveBasketFromClient(e.touches[0].clientX, sliderBar.value);
};

const handleSliderTouchMove = (e) => {
    e.preventDefault();
    if (!isDraggingSlider.value) return;
    moveBasketFromClient(e.touches[0].clientX, sliderBar.value);
};

const handleSliderTouchEnd = () => {
    isDraggingSlider.value = false;
};

const handleSliderMouseDown = (e) => {
    isDraggingSlider.value = true;
    moveBasketFromClient(e.clientX, sliderBar.value);
    document.addEventListener('mousemove', handleDocMouseMove);
    document.addEventListener('mouseup', handleDocMouseUp);
};

const handleDocMouseMove = (e) => {
    if (!isDraggingSlider.value) return;
    moveBasketFromClient(e.clientX, sliderBar.value);
};

const handleDocMouseUp = () => {
    isDraggingSlider.value = false;
    document.removeEventListener('mousemove', handleDocMouseMove);
    document.removeEventListener('mouseup', handleDocMouseUp);
};

const basketPercent = computed(() => {
    return (basket.value.x / (CANVAS_WIDTH - BASKET_WIDTH)) * 100;
});

watch(() => props.gameState, (state) => {
    // Always cancel any existing animation frame first
    if (animationFrame.value) {
        cancelAnimationFrame(animationFrame.value);
        animationFrame.value = null;
    }
    
    if (state === 'playing') {
        initGame();
        if (canvas.value) {
            ctx.value = canvas.value.getContext('2d');
            if (ctx.value) {
                update();
            }
        }
    }
});

watch(() => props.timeRemaining, (time) => {
    if (time <= 0 && props.gameState === 'playing') {
        props.endGame({ caught: caught.value, missed: missed.value });
    }
});

onMounted(() => {
    if (canvas.value) {
        ctx.value = canvas.value.getContext('2d');
    }
});

onUnmounted(() => {
    if (animationFrame.value) {
        cancelAnimationFrame(animationFrame.value);
        animationFrame.value = null;
    }
    document.removeEventListener('mousemove', handleDocMouseMove);
    document.removeEventListener('mouseup', handleDocMouseUp);
});
</script>

<template>
    <div class="flex flex-col items-center justify-center p-4">
        <div class="mb-4 text-center">
            <span class="text-white">Caught: <span class="font-bold text-purple-400">{{ caught }}</span></span>
        </div>
        
        <canvas
            ref="canvas"
            :width="CANVAS_WIDTH"
            :height="CANVAS_HEIGHT"
            class="rounded-lg border border-white/10"
            @mousemove="handleMouseMove"
            @touchmove="handleTouchMove"
        ></canvas>

        <!-- Finger Slider Bar -->
        <div
            v-if="gameState === 'playing'"
            ref="sliderBar"
            class="mt-3 w-[320px] h-12 rounded-xl bg-white/10 backdrop-blur border border-white/10 relative cursor-pointer select-none"
            style="touch-action: none;"
            @touchstart="handleSliderTouchStart"
            @touchmove="handleSliderTouchMove"
            @touchend="handleSliderTouchEnd"
            @mousedown="handleSliderMouseDown"
        >
            <div class="absolute top-1/2 left-2 right-2 h-1 rounded-full bg-white/10 -translate-y-1/2"></div>
            <div
                class="absolute top-1/2 -translate-y-1/2 w-10 h-8 rounded-lg bg-gradient-to-r from-purple-500 to-pink-500 shadow-lg transition-none"
                :style="{ left: `calc(${basketPercent}% * 0.875 + 4px)` }"
            ></div>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span class="text-white/30 text-xs font-medium">SLIDE TO MOVE</span>
            </div>
        </div>
        
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm">
            Catch the desserts! Avoid the bombs! 💣
        </div>
    </div>
</template>

