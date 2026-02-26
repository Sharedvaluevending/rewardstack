<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    config: Object,
});

const GRID_SIZE = 20;
const CELL_SIZE = 15;
const INITIAL_SPEED = 150;

const snake = ref([{ x: 10, y: 10 }]);
const food = ref({ x: 15, y: 15 });
const direction = ref({ x: 1, y: 0 });
const nextDirection = ref({ x: 1, y: 0 });
const gameLoop = ref(null);
const speed = ref(INITIAL_SPEED);
const foodEaten = ref(0);

const initGame = () => {
    snake.value = [{ x: 10, y: 10 }];
    direction.value = { x: 1, y: 0 };
    nextDirection.value = { x: 1, y: 0 };
    speed.value = INITIAL_SPEED;
    foodEaten.value = 0;
    spawnFood();
};

const spawnFood = () => {
    let newFood;
    do {
        newFood = {
            x: Math.floor(Math.random() * GRID_SIZE),
            y: Math.floor(Math.random() * GRID_SIZE),
        };
    } while (snake.value.some(s => s.x === newFood.x && s.y === newFood.y));
    food.value = newFood;
};

const moveSnake = () => {
    direction.value = { ...nextDirection.value };
    
    const head = {
        x: snake.value[0].x + direction.value.x,
        y: snake.value[0].y + direction.value.y,
    };

    // Wall collision
    if (head.x < 0 || head.x >= GRID_SIZE || head.y < 0 || head.y >= GRID_SIZE) {
        gameOver();
        return;
    }

    // Self collision
    if (snake.value.some(s => s.x === head.x && s.y === head.y)) {
        gameOver();
        return;
    }

    snake.value.unshift(head);

    // Food collision
    if (head.x === food.value.x && head.y === food.value.y) {
        foodEaten.value++;
        props.addScore(10 + foodEaten.value * 2);
        spawnFood();
        
        // Speed up every 5 food items
        if (foodEaten.value % 5 === 0 && speed.value > 50) {
            speed.value -= 10;
            restartLoop();
        }
    } else {
        snake.value.pop();
    }
};

const gameOver = () => {
    if (gameLoop.value) {
        clearInterval(gameLoop.value);
        gameLoop.value = null;
    }
    props.endGame({
        length: snake.value.length,
        foodEaten: foodEaten.value,
    });
};

const startLoop = () => {
    gameLoop.value = setInterval(moveSnake, speed.value);
};

const restartLoop = () => {
    if (gameLoop.value) {
        clearInterval(gameLoop.value);
    }
    startLoop();
};

const handleKeydown = (e) => {
    if (props.gameState !== 'playing') return;

    const key = e.key;
    
    if ((key === 'ArrowUp' || key === 'w') && direction.value.y !== 1) {
        nextDirection.value = { x: 0, y: -1 };
    } else if ((key === 'ArrowDown' || key === 's') && direction.value.y !== -1) {
        nextDirection.value = { x: 0, y: 1 };
    } else if ((key === 'ArrowLeft' || key === 'a') && direction.value.x !== 1) {
        nextDirection.value = { x: -1, y: 0 };
    } else if ((key === 'ArrowRight' || key === 'd') && direction.value.x !== -1) {
        nextDirection.value = { x: 1, y: 0 };
    }
};

// Touch controls
const touchStart = ref({ x: 0, y: 0 });

const handleTouchStart = (e) => {
    touchStart.value = {
        x: e.touches[0].clientX,
        y: e.touches[0].clientY,
    };
};

const handleTouchEnd = (e) => {
    if (props.gameState !== 'playing') return;
    
    const deltaX = e.changedTouches[0].clientX - touchStart.value.x;
    const deltaY = e.changedTouches[0].clientY - touchStart.value.y;
    
    if (Math.abs(deltaX) > Math.abs(deltaY)) {
        // Horizontal swipe
        if (deltaX > 20 && direction.value.x !== -1) {
            nextDirection.value = { x: 1, y: 0 };
        } else if (deltaX < -20 && direction.value.x !== 1) {
            nextDirection.value = { x: -1, y: 0 };
        }
    } else {
        // Vertical swipe
        if (deltaY > 20 && direction.value.y !== -1) {
            nextDirection.value = { x: 0, y: 1 };
        } else if (deltaY < -20 && direction.value.y !== 1) {
            nextDirection.value = { x: 0, y: -1 };
        }
    }
};

watch(() => props.gameState, (state) => {
    if (state === 'playing') {
        initGame();
        startLoop();
    } else if (state !== 'playing' && gameLoop.value) {
        clearInterval(gameLoop.value);
        gameLoop.value = null;
    }
});

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    if (gameLoop.value) {
        clearInterval(gameLoop.value);
    }
});

const gridStyle = computed(() => ({
    width: `${GRID_SIZE * CELL_SIZE}px`,
    height: `${GRID_SIZE * CELL_SIZE}px`,
}));
</script>

<template>
    <div class="flex flex-col items-center justify-center min-h-[60vh] p-4"
        @touchstart="handleTouchStart"
        @touchend="handleTouchEnd">
        
        <!-- Stats -->
        <div class="mb-4 text-center">
            <div class="text-white text-sm">
                Length: <span class="font-bold text-purple-400">{{ snake.length }}</span>
                &nbsp;•&nbsp;
                Food: <span class="font-bold text-amber-400">{{ foodEaten }}</span>
            </div>
        </div>

        <!-- Game Grid -->
        <div class="relative bg-slate-800/50 rounded-lg border border-white/10"
            :style="gridStyle">
            <!-- Snake -->
            <div v-for="(segment, index) in snake" :key="index"
                :class="[
                    'absolute rounded transition-all duration-75',
                    index === 0 ? 'bg-emerald-400' : 'bg-emerald-500'
                ]"
                :style="{
                    left: `${segment.x * CELL_SIZE}px`,
                    top: `${segment.y * CELL_SIZE}px`,
                    width: `${CELL_SIZE - 1}px`,
                    height: `${CELL_SIZE - 1}px`,
                }">
            </div>
            
            <!-- Food -->
            <div class="absolute flex items-center justify-center text-sm"
                :style="{
                    left: `${food.x * CELL_SIZE}px`,
                    top: `${food.y * CELL_SIZE}px`,
                    width: `${CELL_SIZE}px`,
                    height: `${CELL_SIZE}px`,
                }">
                🍎
            </div>
        </div>

        <!-- Mobile Controls -->
        <div class="mt-6 grid grid-cols-3 gap-2 w-36">
            <div></div>
            <button @click="nextDirection = { x: 0, y: -1 }" 
                class="p-3 bg-white/10 rounded-lg text-white hover:bg-white/20"
                :disabled="gameState !== 'playing'">
                ▲
            </button>
            <div></div>
            <button @click="nextDirection = { x: -1, y: 0 }"
                class="p-3 bg-white/10 rounded-lg text-white hover:bg-white/20"
                :disabled="gameState !== 'playing'">
                ◀
            </button>
            <div></div>
            <button @click="nextDirection = { x: 1, y: 0 }"
                class="p-3 bg-white/10 rounded-lg text-white hover:bg-white/20"
                :disabled="gameState !== 'playing'">
                ▶
            </button>
            <div></div>
            <button @click="nextDirection = { x: 0, y: 1 }"
                class="p-3 bg-white/10 rounded-lg text-white hover:bg-white/20"
                :disabled="gameState !== 'playing'">
                ▼
            </button>
            <div></div>
        </div>

        <!-- Instructions -->
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm">
            Swipe or use arrow keys to move. Eat apples to grow!
        </div>
    </div>
</template>

