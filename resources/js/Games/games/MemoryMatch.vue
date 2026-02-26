<script setup>
import { ref, computed, onMounted, watch } from 'vue';

const props = defineProps({
    gameState: String,
    addScore: Function,
    endGame: Function,
    config: Object,
    qrSeed: {
        type: String,
        default: 'rewardstack',
    },
});

const gridSize = props.config?.gridSize || 4;
const totalPairs = (gridSize * gridSize) / 2;

const emojis = ['🍕', '🍔', '🍟', '🌮', '🍦', '🍩', '🎂', '🍪', '☕', '🍺', '🎮', '🎯', '🎪', '🎨', '🎭', '🎸', '⚽', '🏀'];

const cards = ref([]);
const flippedCards = ref([]);
const matchedPairs = ref(0);
const moves = ref(0);
const isProcessing = ref(false);

// --- Card back "QR" (black/white) ---
const fnv1a32 = (str) => {
    let h = 0x811c9dc5;
    for (let i = 0; i < str.length; i++) {
        h ^= str.charCodeAt(i);
        h = Math.imul(h, 0x01000193);
    }
    return h >>> 0;
};

const mulberry32 = (seed) => {
    let t = seed >>> 0;
    return () => {
        t += 0x6D2B79F5;
        let x = Math.imul(t ^ (t >>> 15), 1 | t);
        x ^= x + Math.imul(x ^ (x >>> 7), 61 | x);
        return ((x ^ (x >>> 14)) >>> 0) / 4294967296;
    };
};

const generateQrLikeSvgDataUri = (seedString) => {
    const size = 84; // px
    const modules = 21; // QR v1-like grid
    const m = size / modules;

    const rnd = mulberry32(fnv1a32(seedString || 'rewardstack'));

    // Create matrix
    const mat = Array.from({ length: modules }, () => Array.from({ length: modules }, () => false));

    const drawFinder = (ox, oy) => {
        for (let y = 0; y < 7; y++) {
            for (let x = 0; x < 7; x++) {
                const isBorder = x === 0 || y === 0 || x === 6 || y === 6;
                const isInner = x >= 2 && x <= 4 && y >= 2 && y <= 4;
                mat[oy + y][ox + x] = isBorder || isInner;
            }
        }
        // white ring
        for (let y = 1; y < 6; y++) {
            for (let x = 1; x < 6; x++) {
                if (x >= 2 && x <= 4 && y >= 2 && y <= 4) continue;
                mat[oy + y][ox + x] = false;
            }
        }
    };

    // Finder patterns (top-left, top-right, bottom-left)
    drawFinder(0, 0);
    drawFinder(modules - 7, 0);
    drawFinder(0, modules - 7);

    // Fill remaining modules with deterministic noise (avoid finder zones)
    const isInFinder = (x, y) =>
        (x < 7 && y < 7) ||
        (x >= modules - 7 && y < 7) ||
        (x < 7 && y >= modules - 7);

    for (let y = 0; y < modules; y++) {
        for (let x = 0; x < modules; x++) {
            if (isInFinder(x, y)) continue;
            // keep a quiet zone-ish border
            if (x === 0 || y === 0 || x === modules - 1 || y === modules - 1) continue;
            mat[y][x] = rnd() > 0.62;
        }
    }

    let rects = '';
    for (let y = 0; y < modules; y++) {
        for (let x = 0; x < modules; x++) {
            if (!mat[y][x]) continue;
            rects += `<rect x="${x * m}" y="${y * m}" width="${m}" height="${m}" />`;
        }
    }

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#ffffff"/>` +
        `<g fill="#000000">${rects}</g></svg>`;

    return `data:image/svg+xml,${encodeURIComponent(svg)}`;
};

const cardBackDataUri = computed(() => generateQrLikeSvgDataUri(props.qrSeed));

const initGame = () => {
    const selectedEmojis = emojis.slice(0, totalPairs);
    const cardPairs = [...selectedEmojis, ...selectedEmojis];
    
    // Shuffle cards
    cards.value = cardPairs
        .map((emoji, index) => ({
            id: index,
            emoji,
            isFlipped: false,
            isMatched: false,
        }))
        .sort(() => Math.random() - 0.5);
    
    flippedCards.value = [];
    matchedPairs.value = 0;
    moves.value = 0;
};

const flipCard = (card) => {
    if (isProcessing.value) return;
    if (card.isFlipped || card.isMatched) return;
    if (flippedCards.value.length >= 2) return;

    card.isFlipped = true;
    flippedCards.value.push(card);
    moves.value++;

    if (flippedCards.value.length === 2) {
        checkMatch();
    }
};

const checkMatch = async () => {
    isProcessing.value = true;
    const [card1, card2] = flippedCards.value;

    await new Promise(r => setTimeout(r, 600));

    if (card1.emoji === card2.emoji) {
        // Match!
        card1.isMatched = true;
        card2.isMatched = true;
        matchedPairs.value++;
        props.addScore(100);

        if (matchedPairs.value === totalPairs) {
            // All matched - bonus for fewer moves
            const bonusScore = Math.max(0, (totalPairs * 3 - moves.value) * 10);
            props.addScore(bonusScore);
            
            setTimeout(() => {
                props.endGame({
                    pairs: matchedPairs.value,
                    moves: moves.value,
                    perfect: moves.value === totalPairs * 2,
                });
            }, 500);
        }
    } else {
        // No match - flip back
        card1.isFlipped = false;
        card2.isFlipped = false;
    }

    flippedCards.value = [];
    isProcessing.value = false;
};

watch(() => props.gameState, (state) => {
    if (state === 'playing') {
        initGame();
    }
});
</script>

<template>
    <div class="p-4">
        <!-- Progress -->
        <div class="mb-4 text-center">
            <div class="text-white text-sm">
                Pairs: <span class="font-bold text-purple-400">{{ matchedPairs }}/{{ totalPairs }}</span>
                &nbsp;•&nbsp;
                Moves: <span class="font-bold">{{ moves }}</span>
            </div>
        </div>

        <!-- Card Grid -->
        <div :class="[
            'grid gap-2 max-w-md mx-auto',
            gridSize === 4 ? 'grid-cols-4' : 'grid-cols-6'
        ]">
            <button v-for="card in cards" :key="card.id"
                @click="flipCard(card)"
                @touchstart="flipCard(card)"
                :disabled="card.isMatched || gameState !== 'playing'"
                :class="[
                    'aspect-square rounded-xl transition-all duration-300 transform',
                    card.isFlipped || card.isMatched 
                        ? 'bg-gradient-to-br from-purple-500 to-pink-500 scale-95' 
                        : 'bg-white/10 hover:bg-white/20 hover:scale-105',
                    card.isMatched && 'opacity-50'
                ]">
                <span v-if="card.isFlipped || card.isMatched" 
                    class="text-2xl sm:text-3xl">
                    {{ card.emoji }}
                </span>
                <div v-else class="w-full h-full p-2">
                    <div class="w-full h-full rounded-lg bg-white/90 border border-white/30 overflow-hidden">
                        <div
                            class="w-full h-full opacity-95"
                            :style="{
                                backgroundImage: `url('${cardBackDataUri}')`,
                                backgroundSize: 'cover',
                                backgroundPosition: 'center',
                            }"
                        />
                    </div>
                </div>
            </button>
        </div>

        <!-- Instructions -->
        <div v-if="gameState === 'ready'" class="mt-6 text-center text-gray-400 text-sm">
            Match all pairs to win! Fewer moves = higher score
        </div>
    </div>
</template>

