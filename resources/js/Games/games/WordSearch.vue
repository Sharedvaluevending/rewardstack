<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    config: Object,
    dailySeed: String, // Date string for daily seeded puzzle (e.g., "2025-12-09")
});

const GRID_SIZE = 10;
const ALL_WORDS = ['DISCOUNT', 'REWARD', 'PLAY', 'WIN', 'GAME', 'PRIZE', 'BONUS', 'SAVE', 'DEAL', 'FREE', 'CASH', 'EARN', 'LUCKY', 'SCORE', 'POINTS', 'OFFER', 'VALUE', 'PROMO', 'TOKEN', 'JACKPOT'];

const grid = ref([]);
const wordsToFind = ref([]);
const foundWords = ref([]);
const selectedCells = ref([]);
const isDragging = ref(false);
const debugTouchCount = ref(0);
const debugQueue = ref([]);
const debugSent = ref(0);

const debugLog = (payload) => {
    const entry = { sessionId: 'debug-session', runId: 'wordsearch-touch-offset', ...payload, timestamp: Date.now() };
    debugQueue.value.push(entry);
    if (debugQueue.value.length > 25) debugQueue.value.shift();

    // Send to app endpoint (works from mobile)
    if (debugSent.value < 25) {
        const batch = debugQueue.value.splice(0, debugQueue.value.length);
        fetch('/api/debug/wordsearch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ entries: batch }),
        }).then(() => {
            debugSent.value += batch.length;
        }).catch(() => {
            // If it fails, re-queue once (bounded)
            debugQueue.value.unshift(...batch.slice(-10));
        });
    }

    // (local debug ingest removed)
};

// Seeded random number generator for consistent daily puzzles
class SeededRandom {
    constructor(seed) {
        this.seed = this.hashCode(seed);
    }
    
    hashCode(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash);
    }
    
    next() {
        this.seed = (this.seed * 1103515245 + 12345) & 0x7fffffff;
        return this.seed / 0x7fffffff;
    }
    
    nextInt(min, max) {
        return Math.floor(this.next() * (max - min + 1)) + min;
    }
    
    shuffle(array) {
        const result = [...array];
        for (let i = result.length - 1; i > 0; i--) {
            const j = Math.floor(this.next() * (i + 1));
            [result[i], result[j]] = [result[j], result[i]];
        }
        return result;
    }
}

// Get today's date seed or use provided daily seed
const getDailySeed = () => {
    if (props.dailySeed) {
        return props.dailySeed;
    }
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
};

let rng = null;

const initGame = () => {
    // Use seeded random for daily consistency - everyone gets the same puzzle today
    const seed = getDailySeed();
    rng = new SeededRandom(seed);
    
    // Select 5 words using seeded random - same words for everyone today
    wordsToFind.value = rng.shuffle(ALL_WORDS).slice(0, 5);
    
    foundWords.value = [];
    selectedCells.value = [];
    
    // Create empty grid
    grid.value = Array(GRID_SIZE).fill(null).map(() => 
        Array(GRID_SIZE).fill(null).map(() => ({
            letter: '',
            isPartOfWord: false,
            isFound: false,
            wordIndex: null,
        }))
    );
    
    // Place words using seeded random
    placeWords();
    
    // Fill remaining cells with seeded random letters
    fillRandomLetters();
};

const placeWords = () => {
    const directions = [
        { dx: 1, dy: 0 },  // horizontal
        { dx: 0, dy: 1 },  // vertical
        { dx: 1, dy: 1 },  // diagonal
    ];

    wordsToFind.value.forEach((word, wordIndex) => {
        let placed = false;
        let attempts = 0;

        while (!placed && attempts < 100) {
            const dir = directions[rng.nextInt(0, directions.length - 1)];
            const startX = rng.nextInt(0, GRID_SIZE - 1);
            const startY = rng.nextInt(0, GRID_SIZE - 1);

            if (canPlaceWord(word, startX, startY, dir)) {
                for (let i = 0; i < word.length; i++) {
                    const x = startX + i * dir.dx;
                    const y = startY + i * dir.dy;
                    grid.value[y][x].letter = word[i];
                    grid.value[y][x].isPartOfWord = true;
                    grid.value[y][x].wordIndex = wordIndex;
                }
                placed = true;
            }
            attempts++;
        }
    });
};

const canPlaceWord = (word, startX, startY, dir) => {
    for (let i = 0; i < word.length; i++) {
        const x = startX + i * dir.dx;
        const y = startY + i * dir.dy;

        if (x < 0 || x >= GRID_SIZE || y < 0 || y >= GRID_SIZE) {
            return false;
        }

        const cell = grid.value[y][x];
        if (cell.letter && cell.letter !== word[i]) {
            return false;
        }
    }
    return true;
};

const fillRandomLetters = () => {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    for (let y = 0; y < GRID_SIZE; y++) {
        for (let x = 0; x < GRID_SIZE; x++) {
            if (!grid.value[y][x].letter) {
                grid.value[y][x].letter = letters[rng.nextInt(0, letters.length - 1)];
            }
        }
    }
};

// Grid container reference for touch coordinate calculation
const gridContainer = ref(null);

// Get cell coordinates from touch/mouse event
const getCellFromEvent = (event) => {
    if (!gridContainer.value) return null;

    const rect = gridContainer.value.getBoundingClientRect();
    const cellSize = rect.width / GRID_SIZE;

    // Get the touch/mouse position relative to the grid
    const point = (event.touches && event.touches[0]) ? event.touches[0]
        : (event.changedTouches && event.changedTouches[0]) ? event.changedTouches[0]
        : event;
    const clientX = point?.clientX;
    const clientY = point?.clientY;

    const x = Math.floor((clientX - rect.left) / cellSize);
    const y = Math.floor((clientY - rect.top) / cellSize);

    if (debugTouchCount.value < 8) {
        const elAtPoint = (typeof document !== 'undefined' && typeof document.elementFromPoint === 'function' && clientX != null && clientY != null)
            ? document.elementFromPoint(clientX, clientY)
            : null;
        const elRect = elAtPoint?.getBoundingClientRect?.();
        debugLog({
            hypothesisId: 'B',
            location: 'WordSearch.vue:getCellFromEvent',
            message: 'Computed cell from event',
            data: {
                type: event?.type,
                clientX,
                clientY,
                rect: { left: rect.left, top: rect.top, width: rect.width, height: rect.height },
                cellSize,
                computed: { x, y },
                elementFromPoint: elAtPoint ? { tag: elAtPoint.tagName, dataX: elAtPoint.dataset?.x, dataY: elAtPoint.dataset?.y } : null,
                elementFromPointRect: elRect ? { left: elRect.left, top: elRect.top, width: elRect.width, height: elRect.height } : null,
                vv: (window?.visualViewport ? { scale: window.visualViewport.scale, offsetLeft: window.visualViewport.offsetLeft, offsetTop: window.visualViewport.offsetTop, width: window.visualViewport.width, height: window.visualViewport.height } : null),
            },
        });
    }

    // Check bounds
    if (x >= 0 && x < GRID_SIZE && y >= 0 && y < GRID_SIZE) {
        return { x, y };
    }
    return null;
};

const handleCellMouseDown = (x, y) => {
    if (props.gameState !== 'playing') return;
    isDragging.value = true;
    selectedCells.value = [{ x, y }];
};

const handleCellMouseEnter = (x, y) => {
    if (!isDragging.value || props.gameState !== 'playing') return;

    const last = selectedCells.value[selectedCells.value.length - 1];
    if (!last) return;

    // Only allow straight lines
    const dx = x - selectedCells.value[0].x;
    const dy = y - selectedCells.value[0].y;

    if (dx !== 0 && dy !== 0 && Math.abs(dx) !== Math.abs(dy)) return;

    // Rebuild selection from start to current
    selectedCells.value = [];
    const stepX = dx === 0 ? 0 : dx > 0 ? 1 : -1;
    const stepY = dy === 0 ? 0 : dy > 0 ? 1 : -1;
    const steps = Math.max(Math.abs(dx), Math.abs(dy));

    for (let i = 0; i <= steps; i++) {
        selectedCells.value.push({
            x: selectedCells.value.length === 0 ? x - steps * stepX + i * stepX : selectedCells.value[0].x + i * stepX,
            y: selectedCells.value.length === 0 ? y - steps * stepY + i * stepY : selectedCells.value[0].y + i * stepY,
        });
    }

    // Fix: rebuild from first cell
    const firstX = x - steps * stepX;
    const firstY = y - steps * stepY;
    selectedCells.value = [];
    for (let i = 0; i <= steps; i++) {
        selectedCells.value.push({
            x: firstX + i * stepX,
            y: firstY + i * stepY,
        });
    }
};

// Touch event handlers for mobile
const handleTouchStart = (event) => {
    event.preventDefault();
    if (props.gameState !== 'playing') return;

    const cell = getCellFromEvent(event);
    if (debugTouchCount.value < 8) {
        debugLog({
            hypothesisId: 'D',
            location: 'WordSearch.vue:handleTouchStart',
            message: 'Container touchstart',
            data: { cell, target: { tag: event?.target?.tagName, dataX: event?.target?.dataset?.x, dataY: event?.target?.dataset?.y }, draggingBefore: isDragging.value },
        });
    }
    if (cell) {
        isDragging.value = true;
        selectedCells.value = [cell];
        debugTouchCount.value++;
    }
};

const handleTouchMove = (event) => {
    event.preventDefault();
    if (!isDragging.value || props.gameState !== 'playing') return;

    const cell = getCellFromEvent(event);
    if (!cell) return;

    const last = selectedCells.value[selectedCells.value.length - 1];
    if (!last || (last.x === cell.x && last.y === cell.y)) return;

    if (debugTouchCount.value < 8) {
        debugLog({
            hypothesisId: 'E',
            location: 'WordSearch.vue:handleTouchMove',
            message: 'Container touchmove',
            data: { cell, dragging: isDragging.value, last },
        });
    }

    // Only allow straight lines
    const dx = cell.x - selectedCells.value[0].x;
    const dy = cell.y - selectedCells.value[0].y;

    if (dx !== 0 && dy !== 0 && Math.abs(dx) !== Math.abs(dy)) return;

    // Rebuild selection from start to current
    selectedCells.value = [];
    const stepX = dx === 0 ? 0 : dx > 0 ? 1 : -1;
    const stepY = dy === 0 ? 0 : dy > 0 ? 1 : -1;
    const steps = Math.max(Math.abs(dx), Math.abs(dy));

    const firstX = cell.x - steps * stepX;
    const firstY = cell.y - steps * stepY;
    selectedCells.value = [];
    for (let i = 0; i <= steps; i++) {
        selectedCells.value.push({
            x: firstX + i * stepX,
            y: firstY + i * stepY,
        });
    }
};

// Also handle direct cell touches for better mobile experience
const handleCellTouchStart = (x, y, event) => {
    event.preventDefault();
    event.stopPropagation?.();
    if (props.gameState !== 'playing') return;
    const computed = getCellFromEvent(event);
    if (debugTouchCount.value < 8) {
        const tRect = event?.target?.getBoundingClientRect?.();
        debugLog({
            hypothesisId: 'A',
            location: 'WordSearch.vue:handleCellTouchStart',
            message: 'Cell touchstart',
            data: {
                passed: { x, y },
                computedFromEvent: computed,
                target: { tag: event?.target?.tagName, dataX: event?.target?.dataset?.x, dataY: event?.target?.dataset?.y, rect: (tRect ? { left: tRect.left, top: tRect.top, width: tRect.width, height: tRect.height } : null) },
            },
        });
    }
    isDragging.value = true;
    selectedCells.value = [{ x, y }];
    debugTouchCount.value++;
};

const handleCellTouchMove = (x, y, event) => {
    event.preventDefault();
    event.stopPropagation?.();
    if (!isDragging.value || props.gameState !== 'playing') return;

    const last = selectedCells.value[selectedCells.value.length - 1];
    if (!last) return;

    if (debugTouchCount.value < 8) {
        const computed = getCellFromEvent(event);
        debugLog({
            hypothesisId: 'F',
            location: 'WordSearch.vue:handleCellTouchMove',
            message: 'Cell touchmove',
            data: { passed: { x, y }, computedFromEvent: computed, dragging: isDragging.value, last },
        });
    }

    // Only allow straight lines
    const dx = x - selectedCells.value[0].x;
    const dy = y - selectedCells.value[0].y;

    if (dx !== 0 && dy !== 0 && Math.abs(dx) !== Math.abs(dy)) return;

    // Rebuild selection from start to current
    selectedCells.value = [];
    const stepX = dx === 0 ? 0 : dx > 0 ? 1 : -1;
    const stepY = dy === 0 ? 0 : dy > 0 ? 1 : -1;
    const steps = Math.max(Math.abs(dx), Math.abs(dy));

    for (let i = 0; i <= steps; i++) {
        selectedCells.value.push({
            x: selectedCells.value.length === 0 ? x - steps * stepX + i * stepX : selectedCells.value[0].x + i * stepX,
            y: selectedCells.value.length === 0 ? y - steps * stepY + i * stepY : selectedCells.value[0].y + i * stepY,
        });
    }

    // Fix: rebuild from first cell
    const firstX = x - steps * stepX;
    const firstY = y - steps * stepY;
    selectedCells.value = [];
    for (let i = 0; i <= steps; i++) {
        selectedCells.value.push({
            x: firstX + i * stepX,
            y: firstY + i * stepY,
        });
    }
};

const handleMouseUp = () => {
    if (!isDragging.value) return;
    isDragging.value = false;
    
    // Check if selection matches a word
    const selectedWord = selectedCells.value
        .map(({ x, y }) => grid.value[y][x].letter)
        .join('');
    
    const wordIndex = wordsToFind.value.indexOf(selectedWord);
    
    if (wordIndex !== -1 && !foundWords.value.includes(selectedWord)) {
        foundWords.value.push(selectedWord);
        props.addScore(100 + selectedWord.length * 10);
        
        // Mark cells as found
        selectedCells.value.forEach(({ x, y }) => {
            grid.value[y][x].isFound = true;
        });
        
        // Check if all words found
        if (foundWords.value.length === wordsToFind.value.length) {
            setTimeout(() => {
                props.endGame({
                    wordsFound: foundWords.value.length,
                    perfect: true,
                    dailySeed: getDailySeed(),
                    wordsCompleted: [...foundWords.value],
                    expectedWords: [...wordsToFind.value],
                });
            }, 500);
        }
    }
    
    selectedCells.value = [];
};

const isCellSelected = (x, y) => {
    return selectedCells.value.some(cell => cell.x === x && cell.y === y);
};

watch(() => props.gameState, (state) => {
    if (state === 'playing') {
        initGame();
    }
});
</script>

<template>
    <div class="p-4" @mouseup="handleMouseUp" @touchend="handleMouseUp">
        <!-- Word List -->
        <div class="flex flex-wrap justify-center gap-2 mb-4">
            <span v-for="word in wordsToFind" :key="word"
                :class="[
                    'px-3 py-1 rounded-full text-sm font-medium transition-all',
                    foundWords.includes(word)
                        ? 'bg-emerald-500/20 text-emerald-400 line-through'
                        : 'bg-white/10 text-white'
                ]">
                {{ word }}
            </span>
        </div>

        <!-- Progress -->
        <div class="text-center mb-4 text-sm text-gray-400">
            Found: {{ foundWords.length }} / {{ wordsToFind.length }}
        </div>

        <!-- Grid -->
        <div class="flex justify-center">
            <div ref="gridContainer"
                class="inline-grid select-none relative touch-none"
                :style="{ gridTemplateColumns: `repeat(${GRID_SIZE}, 1fr)` }"
                @touchstart="handleTouchStart"
                @touchmove="handleTouchMove">
                <template v-for="(row, y) in grid" :key="`row-${y}`">
                    <div v-for="(cell, x) in row" :key="`${x}-${y}`"
                        :data-x="x"
                        :data-y="y"
                        @mousedown="handleCellMouseDown(x, y)"
                        @mouseenter="handleCellMouseEnter(x, y)"
                        :class="[
                            'w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center font-bold text-sm transition-all cursor-pointer border border-white/20',
                            cell.isFound
                                ? 'bg-emerald-500 text-white'
                                : isCellSelected(x, y)
                                    ? 'bg-purple-500 text-white scale-110'
                                    : 'bg-white/10 text-white hover:bg-white/20'
                        ]">
                        {{ cell.letter }}
                    </div>
                </template>
            </div>
        </div>

        <!-- Instructions -->
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm">
            Find all the hidden words! Drag to select.
        </div>
    </div>
</template>

