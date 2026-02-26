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
const PLATE_WIDTH = 70;
const PLATE_HEIGHT = 20;
const ITEM_SIZE = 35;

const canvas = ref(null);
const ctx = ref(null);
const animationFrame = ref(null);

const plate = ref({ x: CANVAS_WIDTH / 2 - PLATE_WIDTH / 2 });
const items = ref([]);
const pizzasCaught = ref(0);
const bombsHit = ref(0);
const MAX_BOMBS = 3;

const initGame = () => {
    plate.value = { x: CANVAS_WIDTH / 2 - PLATE_WIDTH / 2 };
    items.value = [];
    pizzasCaught.value = 0;
    bombsHit.value = 0;
};

const spawnItem = () => {
    const isBomb = Math.random() < 0.25;
    items.value.push({
        x: Math.random() * (CANVAS_WIDTH - ITEM_SIZE),
        y: -ITEM_SIZE,
        speed: 3 + Math.random() * 2,
        rotation: Math.random() * Math.PI * 2,
        rotationSpeed: (Math.random() - 0.5) * 0.2,
        isPizza: !isBomb,
        emoji: isBomb ? '💣' : ['🍕', '🍕', '🍕', '🌭', '🍔'][Math.floor(Math.random() * 5)],
        points: isBomb ? -100 : 20,
    });
};

const draw = () => {
    if (!ctx.value) return;
    
    const c = ctx.value;
    
    // Background
    c.fillStyle = '#1a1a2e';
    c.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
    
    // Pizza shop pattern
    c.strokeStyle = '#2a2a4e';
    c.lineWidth = 1;
    for (let i = 0; i < CANVAS_WIDTH; i += 40) {
        c.beginPath();
        c.moveTo(i, 0);
        c.lineTo(i, CANVAS_HEIGHT);
        c.stroke();
    }
    for (let i = 0; i < CANVAS_HEIGHT; i += 40) {
        c.beginPath();
        c.moveTo(0, i);
        c.lineTo(CANVAS_WIDTH, i);
        c.stroke();
    }
    
    // Draw items
    items.value.forEach(item => {
        c.save();
        c.translate(item.x + ITEM_SIZE / 2, item.y + ITEM_SIZE / 2);
        c.rotate(item.rotation);
        c.font = `${ITEM_SIZE}px serif`;
        c.textAlign = 'center';
        c.textBaseline = 'middle';
        c.fillText(item.emoji, 0, 0);
        c.restore();
    });
    
    // Draw plate
    c.fillStyle = '#e5e7eb';
    c.beginPath();
    c.ellipse(plate.value.x + PLATE_WIDTH / 2, CANVAS_HEIGHT - 30, PLATE_WIDTH / 2, PLATE_HEIGHT / 2, 0, 0, Math.PI * 2);
    c.fill();
    c.strokeStyle = '#9ca3af';
    c.lineWidth = 2;
    c.stroke();
    
    // Inner ring
    c.strokeStyle = '#d1d5db';
    c.beginPath();
    c.ellipse(plate.value.x + PLATE_WIDTH / 2, CANVAS_HEIGHT - 30, PLATE_WIDTH / 3, PLATE_HEIGHT / 3, 0, 0, Math.PI * 2);
    c.stroke();
    
    // Lives/bombs indicator
    for (let i = 0; i < MAX_BOMBS; i++) {
        c.font = '20px serif';
        c.fillText(i < bombsHit.value ? '💥' : '❤️', 20 + i * 30, 30);
    }
};

const update = () => {
    if (props.gameState !== 'playing') return;
    
    // Spawn new items
    if (Math.random() < 0.04) {
        spawnItem();
    }
    
    // Update items
    items.value.forEach(item => {
        item.y += item.speed;
        item.rotation += item.rotationSpeed;
        
        // Check if caught
        if (item.y + ITEM_SIZE >= CANVAS_HEIGHT - 40 &&
            item.y <= CANVAS_HEIGHT - 20 &&
            item.x + ITEM_SIZE >= plate.value.x &&
            item.x <= plate.value.x + PLATE_WIDTH) {
            item.caught = true;
            if (item.isPizza) {
                pizzasCaught.value++;
                props.addScore(item.points);
            } else {
                bombsHit.value++;
                props.addScore(item.points);
            }
        }
        
        // Check if missed (only pizzas count as miss)
        if (item.y > CANVAS_HEIGHT && !item.caught) {
            item.missed = true;
        }
    });
    
    // Remove processed items
    items.value = items.value.filter(item => !item.caught && !item.missed);
    
    // Check game over
    if (bombsHit.value >= MAX_BOMBS) {
        props.endGame({ pizzasCaught: pizzasCaught.value, bombsHit: bombsHit.value });
        return;
    }
    
    draw();
    animationFrame.value = requestAnimationFrame(update);
};

const sliderBar = ref(null);

const handleMove = (x) => {
    if (props.gameState !== 'playing') return;
    plate.value.x = Math.max(0, Math.min(CANVAS_WIDTH - PLATE_WIDTH, x - PLATE_WIDTH / 2));
};

const movePlateFromClient = (clientX, referenceEl) => {
    if (props.gameState !== 'playing' || !referenceEl) return;
    const rect = referenceEl.getBoundingClientRect();
    const ratio = (clientX - rect.left) / rect.width;
    const canvasX = ratio * CANVAS_WIDTH;
    handleMove(canvasX);
};

const handleMouseMove = (e) => {
    const rect = canvas.value.getBoundingClientRect();
    handleMove(e.clientX - rect.left);
};

const handleTouchMove = (e) => {
    e.preventDefault();
    const rect = canvas.value.getBoundingClientRect();
    handleMove(e.touches[0].clientX - rect.left);
};

const isDraggingSlider = ref(false);

const handleSliderTouchStart = (e) => {
    e.preventDefault();
    isDraggingSlider.value = true;
    movePlateFromClient(e.touches[0].clientX, sliderBar.value);
};

const handleSliderTouchMove = (e) => {
    e.preventDefault();
    if (!isDraggingSlider.value) return;
    movePlateFromClient(e.touches[0].clientX, sliderBar.value);
};

const handleSliderTouchEnd = () => {
    isDraggingSlider.value = false;
};

const handleSliderMouseDown = (e) => {
    isDraggingSlider.value = true;
    movePlateFromClient(e.clientX, sliderBar.value);
    document.addEventListener('mousemove', handleDocMouseMove);
    document.addEventListener('mouseup', handleDocMouseUp);
};

const handleDocMouseMove = (e) => {
    if (!isDraggingSlider.value) return;
    movePlateFromClient(e.clientX, sliderBar.value);
};

const handleDocMouseUp = () => {
    isDraggingSlider.value = false;
    document.removeEventListener('mousemove', handleDocMouseMove);
    document.removeEventListener('mouseup', handleDocMouseUp);
};

const platePercent = computed(() => {
    return (plate.value.x / (CANVAS_WIDTH - PLATE_WIDTH)) * 100;
});

watch(() => props.gameState, (state) => {
    if (state === 'playing') {
        initGame();
        ctx.value = canvas.value?.getContext('2d');
        update();
    } else if (animationFrame.value) {
        cancelAnimationFrame(animationFrame.value);
    }
});

watch(() => props.timeRemaining, (time) => {
    if (time <= 0 && props.gameState === 'playing') {
        props.endGame({ pizzasCaught: pizzasCaught.value, bombsHit: bombsHit.value });
    }
});

onUnmounted(() => {
    if (animationFrame.value) {
        cancelAnimationFrame(animationFrame.value);
    }
    document.removeEventListener('mousemove', handleDocMouseMove);
    document.removeEventListener('mouseup', handleDocMouseUp);
});
</script>

<template>
    <div class="flex flex-col items-center justify-center p-4">
        <div class="mb-4 text-center">
            <span class="text-white">🍕 Saved: <span class="font-bold text-amber-400">{{ pizzasCaught }}</span></span>
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
                class="absolute top-1/2 -translate-y-1/2 w-10 h-8 rounded-lg bg-gradient-to-r from-amber-500 to-red-500 shadow-lg transition-none"
                :style="{ left: `calc(${platePercent}% * 0.875 + 4px)` }"
            ></div>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span class="text-white/30 text-xs font-medium">SLIDE TO MOVE</span>
            </div>
        </div>
        
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm">
            Save the falling pizza slices! Avoid the bombs! 💣
        </div>
    </div>
</template>

