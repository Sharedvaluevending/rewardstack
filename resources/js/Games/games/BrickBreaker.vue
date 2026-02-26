<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    config: Object,
});

const CANVAS_WIDTH = 320;
const CANVAS_HEIGHT = 480;
const PADDLE_WIDTH = 80;
const PADDLE_HEIGHT = 12;
const BALL_SIZE = 10;
const BRICK_ROWS = 5;
const BRICK_COLS = 8;
const BRICK_WIDTH = 36;
const BRICK_HEIGHT = 16;
const BRICK_GAP = 4;

const canvas = ref(null);
const ctx = ref(null);
const animationFrame = ref(null);

const paddle = ref({ x: CANVAS_WIDTH / 2 - PADDLE_WIDTH / 2, y: CANVAS_HEIGHT - 40 });
const ball = ref({ x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT - 60, dx: 4, dy: -4 });
const bricks = ref([]);
const lives = ref(3);
const level = ref(1);

const initGame = () => {
    paddle.value = { x: CANVAS_WIDTH / 2 - PADDLE_WIDTH / 2, y: CANVAS_HEIGHT - 40 };
    ball.value = { x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT - 60, dx: 4, dy: -4 };
    lives.value = 3;
    level.value = 1;
    createBricks();
};

const createBricks = () => {
    bricks.value = [];
    const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6'];
    
    for (let row = 0; row < BRICK_ROWS; row++) {
        for (let col = 0; col < BRICK_COLS; col++) {
            bricks.value.push({
                x: col * (BRICK_WIDTH + BRICK_GAP) + 10,
                y: row * (BRICK_HEIGHT + BRICK_GAP) + 40,
                color: colors[row],
                points: (BRICK_ROWS - row) * 10,
                alive: true,
            });
        }
    }
};

const draw = () => {
    if (!ctx.value) return;
    
    const c = ctx.value;
    
    // Clear
    c.fillStyle = '#1e1b4b';
    c.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
    
    // Draw bricks
    bricks.value.forEach(brick => {
        if (brick.alive) {
            c.fillStyle = brick.color;
            c.beginPath();
            c.roundRect(brick.x, brick.y, BRICK_WIDTH, BRICK_HEIGHT, 4);
            c.fill();
        }
    });
    
    // Draw paddle
    const gradient = c.createLinearGradient(paddle.value.x, 0, paddle.value.x + PADDLE_WIDTH, 0);
    gradient.addColorStop(0, '#8b5cf6');
    gradient.addColorStop(1, '#ec4899');
    c.fillStyle = gradient;
    c.beginPath();
    c.roundRect(paddle.value.x, paddle.value.y, PADDLE_WIDTH, PADDLE_HEIGHT, 6);
    c.fill();
    
    // Draw ball
    c.fillStyle = '#ffffff';
    c.beginPath();
    c.arc(ball.value.x, ball.value.y, BALL_SIZE / 2, 0, Math.PI * 2);
    c.fill();
    
    // Draw lives
    for (let i = 0; i < lives.value; i++) {
        c.fillStyle = '#ef4444';
        c.beginPath();
        c.arc(20 + i * 20, 20, 6, 0, Math.PI * 2);
        c.fill();
    }
};

const update = () => {
    if (props.gameState !== 'playing') return;
    
    // Move ball
    ball.value.x += ball.value.dx;
    ball.value.y += ball.value.dy;
    
    // Wall collision
    if (ball.value.x <= BALL_SIZE / 2 || ball.value.x >= CANVAS_WIDTH - BALL_SIZE / 2) {
        ball.value.dx *= -1;
    }
    if (ball.value.y <= BALL_SIZE / 2) {
        ball.value.dy *= -1;
    }
    
    // Paddle collision
    if (ball.value.y >= paddle.value.y - BALL_SIZE / 2 &&
        ball.value.y <= paddle.value.y + PADDLE_HEIGHT &&
        ball.value.x >= paddle.value.x &&
        ball.value.x <= paddle.value.x + PADDLE_WIDTH) {
        ball.value.dy = -Math.abs(ball.value.dy);
        
        // Adjust angle based on where ball hits paddle
        const hitPos = (ball.value.x - paddle.value.x) / PADDLE_WIDTH;
        ball.value.dx = (hitPos - 0.5) * 8;
    }
    
    // Brick collision
    bricks.value.forEach(brick => {
        if (brick.alive &&
            ball.value.x >= brick.x &&
            ball.value.x <= brick.x + BRICK_WIDTH &&
            ball.value.y >= brick.y &&
            ball.value.y <= brick.y + BRICK_HEIGHT) {
            brick.alive = false;
            ball.value.dy *= -1;
            props.addScore(brick.points);
        }
    });
    
    // Check if all bricks destroyed
    if (bricks.value.every(b => !b.alive)) {
        level.value++;
        props.addScore(500); // Level bonus
        createBricks();
        ball.value = { x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT - 60, dx: 4 + level.value, dy: -(4 + level.value) };
    }
    
    // Ball falls
    if (ball.value.y >= CANVAS_HEIGHT) {
        lives.value--;
        if (lives.value <= 0) {
            props.endGame({ level: level.value, bricksDestroyed: bricks.value.filter(b => !b.alive).length });
        } else {
            ball.value = { x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT - 60, dx: 4, dy: -4 };
        }
    }
    
    draw();
    animationFrame.value = requestAnimationFrame(update);
};

const sliderBar = ref(null);

const movePaddleFromX = (clientX, referenceEl) => {
    if (props.gameState !== 'playing' || !referenceEl) return;
    const rect = referenceEl.getBoundingClientRect();
    const ratio = (clientX - rect.left) / rect.width;
    const canvasX = ratio * CANVAS_WIDTH;
    paddle.value.x = Math.max(0, Math.min(CANVAS_WIDTH - PADDLE_WIDTH, canvasX - PADDLE_WIDTH / 2));
};

const handleMouseMove = (e) => {
    movePaddleFromX(e.clientX, canvas.value);
};

const handleTouchMove = (e) => {
    e.preventDefault();
    movePaddleFromX(e.touches[0].clientX, canvas.value);
};

const isDraggingSlider = ref(false);

const handleSliderTouchStart = (e) => {
    e.preventDefault();
    isDraggingSlider.value = true;
    movePaddleFromX(e.touches[0].clientX, sliderBar.value);
};

const handleSliderTouchMove = (e) => {
    e.preventDefault();
    if (!isDraggingSlider.value) return;
    movePaddleFromX(e.touches[0].clientX, sliderBar.value);
};

const handleSliderTouchEnd = () => {
    isDraggingSlider.value = false;
};

const handleSliderMouseDown = (e) => {
    isDraggingSlider.value = true;
    movePaddleFromX(e.clientX, sliderBar.value);
    document.addEventListener('mousemove', handleDocMouseMove);
    document.addEventListener('mouseup', handleDocMouseUp);
};

const handleDocMouseMove = (e) => {
    if (!isDraggingSlider.value) return;
    movePaddleFromX(e.clientX, sliderBar.value);
};

const handleDocMouseUp = () => {
    isDraggingSlider.value = false;
    document.removeEventListener('mousemove', handleDocMouseMove);
    document.removeEventListener('mouseup', handleDocMouseUp);
};

const paddlePercent = computed(() => {
    return (paddle.value.x / (CANVAS_WIDTH - PADDLE_WIDTH)) * 100;
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
            <span class="text-white text-sm">Level {{ level }}</span>
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
                :style="{ left: `calc(${paddlePercent}% * 0.875 + 4px)` }"
            ></div>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span class="text-white/30 text-xs font-medium">SLIDE TO MOVE</span>
            </div>
        </div>
        
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm">
            Move paddle to bounce the ball and break all bricks!
        </div>
    </div>
</template>

