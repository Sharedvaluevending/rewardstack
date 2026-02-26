<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: String,
        default: 'https://example.com'
    },
    size: {
        type: Number,
        default: 200
    },
    colors: {
        type: Object,
        default: () => ({
            background: '#ffffff',
            foreground: '#000000',
            finderBackground: '#000000',
            finderForeground: '#ffffff',
        })
    },
    shape: {
        type: String,
        default: 'square' // square, rounded, dots, diamond
    },
    finderShape: {
        type: String,
        default: 'square'
    },
    logo: {
        type: String,
        default: null
    },
    text: {
        type: Object,
        default: () => ({
            top: '',
            bottom: '',
            font: 'sans-serif',
            size: 14,
            color: '#000000'
        })
    },
    border: {
        type: Object,
        default: () => ({
            show: false,
            width: 4,
            color: '#000000',
            radius: 0
        })
    },
    gradient: {
        type: Object,
        default: () => ({
            enabled: false,
            type: 'linear',
            colors: ['#000000', '#333333'],
            angle: 45
        })
    }
});

const containerStyle = computed(() => ({
    width: props.size + 'px',
    padding: props.border.show ? props.border.width + 'px' : '0',
    backgroundColor: props.border.show ? props.border.color : 'transparent',
    borderRadius: props.border.radius + 'px',
}));

const qrStyle = computed(() => ({
    width: '100%',
    aspectRatio: '1',
    backgroundColor: props.colors.background,
    borderRadius: props.border.radius > 0 ? (props.border.radius - props.border.width) + 'px' : '0',
}));

// Simple QR-like pattern for preview
const cells = computed(() => {
    const pattern = [];
    for (let i = 0; i < 21; i++) {
        const row = [];
        for (let j = 0; j < 21; j++) {
            // Create finder patterns
            const isTopLeftFinder = (i < 7 && j < 7);
            const isTopRightFinder = (i < 7 && j > 13);
            const isBottomLeftFinder = (i > 13 && j < 7);
            
            // Create simple data pattern
            const isData = Math.random() > 0.5;
            
            row.push({
                filled: isTopLeftFinder || isTopRightFinder || isBottomLeftFinder || isData,
                isFinder: isTopLeftFinder || isTopRightFinder || isBottomLeftFinder,
            });
        }
        pattern.push(row);
    }
    return pattern;
});

const getCellStyle = (cell) => {
    const baseStyle = {
        backgroundColor: cell.filled 
            ? (cell.isFinder ? props.colors.finderBackground : props.colors.foreground)
            : 'transparent',
    };

    if (props.shape === 'rounded') {
        baseStyle.borderRadius = '20%';
    } else if (props.shape === 'dots') {
        baseStyle.borderRadius = '50%';
    } else if (props.shape === 'diamond') {
        baseStyle.transform = 'rotate(45deg) scale(0.7)';
    }

    return baseStyle;
};
</script>

<template>
    <div class="qr-preview-container" :style="containerStyle">
        <!-- Top Text -->
        <p v-if="text.top" class="text-center mb-2 font-medium" 
            :style="{ fontFamily: text.font, fontSize: text.size + 'px', color: text.color }">
            {{ text.top }}
        </p>

        <!-- QR Code -->
        <div class="qr-code relative" :style="qrStyle">
            <div class="grid grid-cols-[repeat(21,1fr)] gap-0 w-full h-full p-2">
                <template v-for="(row, i) in cells" :key="i">
                    <div 
                        v-for="(cell, j) in row" 
                        :key="`${i}-${j}`"
                        class="aspect-square"
                        :style="getCellStyle(cell)"
                    ></div>
                </template>
            </div>

            <!-- Logo Overlay -->
            <div v-if="logo" class="absolute inset-0 flex items-center justify-center">
                <div class="w-1/4 h-1/4 bg-white rounded flex items-center justify-center p-1">
                    <img :src="logo" alt="Logo" class="max-w-full max-h-full object-contain" />
                </div>
            </div>
        </div>

        <!-- Bottom Text -->
        <p v-if="text.bottom" class="text-center mt-2 font-medium"
            :style="{ fontFamily: text.font, fontSize: text.size + 'px', color: text.color }">
            {{ text.bottom }}
        </p>
    </div>
</template>

<style scoped>
.qr-preview-container {
    display: inline-block;
}

.qr-code {
    overflow: hidden;
}
</style>

