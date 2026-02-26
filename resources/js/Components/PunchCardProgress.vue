<script setup>
import { computed } from 'vue';

const props = defineProps({
    totalRequired: {
        type: Number,
        required: true,
    },
    currentPunches: {
        type: Number,
        default: 0,
    },
    completedCards: {
        type: Number,
        default: 0,
    },
    icon: {
        type: String,
        default: '⭐',
    },
    primaryColor: {
        type: String,
        default: '#8B5CF6', // purple
    },
    size: {
        type: String,
        default: 'md', // sm, md, lg
    },
    showLabel: {
        type: Boolean,
        default: true,
    },
});

// Generate array of punch slots
const punchSlots = computed(() => {
    const slots = [];
    for (let i = 0; i < props.totalRequired; i++) {
        slots.push({
            index: i,
            isPunched: i < props.currentPunches,
        });
    }
    return slots;
});

// Calculate progress percentage
const progressPercent = computed(() => {
    if (props.totalRequired === 0) return 0;
    return Math.round((props.currentPunches / props.totalRequired) * 100);
});

// Size classes for punch boxes
const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return {
                box: 'w-8 h-8 text-sm',
                icon: 'text-base',
                grid: 'gap-1.5',
            };
        case 'lg':
            return {
                box: 'w-14 h-14 text-xl',
                icon: 'text-2xl',
                grid: 'gap-3',
            };
        default: // md
            return {
                box: 'w-10 h-10 text-base',
                icon: 'text-xl',
                grid: 'gap-2',
            };
    }
});

// Dynamic grid columns based on total punches
const gridCols = computed(() => {
    if (props.totalRequired <= 5) return 'grid-cols-5';
    if (props.totalRequired <= 8) return 'grid-cols-4';
    if (props.totalRequired <= 10) return 'grid-cols-5';
    if (props.totalRequired <= 12) return 'grid-cols-6';
    return 'grid-cols-5';
});
</script>

<template>
    <div class="punch-card-progress">
        <!-- Header with progress -->
        <div v-if="showLabel" class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-300">
                {{ currentPunches }} / {{ totalRequired }} punches
            </span>
            <span v-if="completedCards > 0" class="text-xs text-emerald-400 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ completedCards }} completed
            </span>
        </div>

        <!-- Punch Grid -->
        <div :class="['grid', gridCols, sizeClasses.grid, 'justify-center']">
            <div
                v-for="slot in punchSlots"
                :key="slot.index"
                :class="[
                    sizeClasses.box,
                    'rounded-xl flex items-center justify-center transition-all duration-300 relative',
                    slot.isPunched
                        ? 'border-2 shadow-lg'
                        : 'bg-white/5 border-2 border-white/20 border-dashed'
                ]"
                :style="slot.isPunched ? {
                    background: `linear-gradient(to bottom right, ${primaryColor}4D, ${primaryColor}33)`,
                    borderColor: `${primaryColor}80`,
                    boxShadow: `0 10px 15px -3px ${primaryColor}33`
                } : {}"
            >
                <!-- Punched state - show icon with checkmark -->
                <template v-if="slot.isPunched">
                    <span :class="[sizeClasses.icon, 'relative']">
                        {{ icon }}
                        <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full flex items-center justify-center" :style="{ backgroundColor: primaryColor }">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                    </span>
                </template>
                
                <!-- Empty state - show faded icon -->
                <template v-else>
                    <span :class="[sizeClasses.icon, 'opacity-20 grayscale']">{{ icon }}</span>
                </template>
            </div>
            
            <!-- Reward slot (the +1 free item) -->
            <div
                :class="[
                    sizeClasses.box,
                    'rounded-xl flex items-center justify-center transition-all relative',
                    currentPunches >= totalRequired
                        ? 'bg-gradient-to-br from-yellow-500/30 to-orange-500/30 border-2 border-yellow-500/50 shadow-lg shadow-yellow-500/20 animate-pulse'
                        : 'bg-gradient-to-br from-purple-500/20 to-pink-500/20 border-2 border-purple-500/30'
                ]"
            >
                <span class="text-2xl">🎁</span>
                <span v-if="currentPunches >= totalRequired" class="absolute -top-1 -right-1 text-xs">🎉</span>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="mt-4">
            <div class="h-2 bg-white/10 rounded-full overflow-hidden">
                <div 
                    class="h-full rounded-full transition-all duration-500"
                    :style="{ width: progressPercent + '%', background: `linear-gradient(to right, ${primaryColor}, ${primaryColor}CC)` }"
                ></div>
            </div>
            <p class="text-center text-xs text-gray-500 mt-2">
                <template v-if="currentPunches >= totalRequired">
                    🎉 Ready to claim your reward!
                </template>
                <template v-else-if="currentPunches === 0">
                    Start collecting punches!
                </template>
                <template v-else>
                    {{ totalRequired - currentPunches }} more to go!
                </template>
            </p>
        </div>
    </div>
</template>

<style scoped>
.punch-card-progress {
    @apply p-4 rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10;
}

@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 0 5px rgba(234, 179, 8, 0.3);
    }
    50% {
        box-shadow: 0 0 20px rgba(234, 179, 8, 0.6);
    }
}
</style>
