<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '#000000'
    },
    label: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue']);

const presetColors = [
    '#000000', '#ffffff', '#ef4444', '#f97316', '#eab308',
    '#22c55e', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899'
];

const updateColor = (color) => {
    emit('update:modelValue', color);
};
</script>

<template>
    <div class="color-picker">
        <label v-if="label" class="block text-sm font-medium text-gray-300 mb-2">{{ label }}</label>
        <div class="flex items-center gap-2">
            <input 
                type="color" 
                :value="modelValue" 
                @input="updateColor($event.target.value)"
                class="w-10 h-10 rounded cursor-pointer border-0"
            />
            <input 
                type="text" 
                :value="modelValue" 
                @input="updateColor($event.target.value)"
                class="flex-1 px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm"
                placeholder="#000000"
            />
        </div>
        <div class="flex gap-1 mt-2">
            <button 
                v-for="color in presetColors" 
                :key="color"
                @click="updateColor(color)"
                class="w-6 h-6 rounded border border-white/20 hover:scale-110 transition-transform"
                :style="{ backgroundColor: color }"
                :class="{ 'ring-2 ring-primary-500': modelValue === color }"
            ></button>
        </div>
    </div>
</template>

