<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            enabled: false,
            type: 'linear',
            colors: ['#000000', '#333333'],
            angle: 45
        })
    },
    label: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue']);

const gradientTypes = [
    { value: 'linear', label: 'Linear' },
    { value: 'radial', label: 'Radial' },
];

const update = (key, value) => {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value
    });
};

const updateColor = (index, color) => {
    const newColors = [...props.modelValue.colors];
    newColors[index] = color;
    update('colors', newColors);
};

const addColor = () => {
    if (props.modelValue.colors.length < 5) {
        update('colors', [...props.modelValue.colors, '#666666']);
    }
};

const removeColor = (index) => {
    if (props.modelValue.colors.length > 2) {
        const newColors = props.modelValue.colors.filter((_, i) => i !== index);
        update('colors', newColors);
    }
};

const previewStyle = computed(() => {
    if (!props.modelValue.enabled) return { backgroundColor: props.modelValue.colors[0] };
    
    const colors = props.modelValue.colors.join(', ');
    
    if (props.modelValue.type === 'linear') {
        return { background: `linear-gradient(${props.modelValue.angle}deg, ${colors})` };
    } else {
        return { background: `radial-gradient(circle, ${colors})` };
    }
});
</script>

<template>
    <div class="gradient-picker">
        <label v-if="label" class="block text-sm font-medium text-gray-300 mb-2">{{ label }}</label>
        
        <!-- Enable Toggle -->
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm text-gray-400">Enable Gradient</span>
            <button 
                type="button"
                @click="update('enabled', !modelValue.enabled)"
                :class="['relative w-12 h-6 rounded-full transition-colors',
                    modelValue.enabled ? 'bg-primary-500' : 'bg-gray-600']">
                <span :class="['absolute top-1 w-4 h-4 rounded-full bg-white transition-transform',
                    modelValue.enabled ? 'left-7' : 'left-1']"></span>
            </button>
        </div>

        <div v-if="modelValue.enabled" class="space-y-4">
            <!-- Gradient Type -->
            <div>
                <label class="block text-xs text-gray-400 mb-1">Type</label>
                <div class="flex gap-2">
                    <button 
                        v-for="type in gradientTypes" 
                        :key="type.value"
                        @click="update('type', type.value)"
                        :class="['px-3 py-1 rounded text-sm transition-colors',
                            modelValue.type === type.value 
                                ? 'bg-primary-500 text-white' 
                                : 'bg-white/10 text-gray-300 hover:bg-white/20']">
                        {{ type.label }}
                    </button>
                </div>
            </div>

            <!-- Angle (for linear) -->
            <div v-if="modelValue.type === 'linear'">
                <label class="block text-xs text-gray-400 mb-1">Angle: {{ modelValue.angle }}°</label>
                <input 
                    type="range" 
                    :value="modelValue.angle" 
                    @input="update('angle', parseInt($event.target.value))"
                    min="0" 
                    max="360" 
                    class="w-full"
                />
            </div>

            <!-- Colors -->
            <div>
                <label class="block text-xs text-gray-400 mb-1">Colors</label>
                <div class="space-y-2">
                    <div v-for="(color, index) in modelValue.colors" :key="index" class="flex items-center gap-2">
                        <input 
                            type="color" 
                            :value="color" 
                            @input="updateColor(index, $event.target.value)"
                            class="w-8 h-8 rounded cursor-pointer border-0"
                        />
                        <input 
                            type="text" 
                            :value="color" 
                            @input="updateColor(index, $event.target.value)"
                            class="flex-1 px-2 py-1 bg-white/10 border border-white/20 rounded text-white text-sm"
                        />
                        <button 
                            v-if="modelValue.colors.length > 2"
                            @click="removeColor(index)"
                            class="p-1 text-red-400 hover:text-red-300">
                            ✕
                        </button>
                    </div>
                </div>
                <button 
                    v-if="modelValue.colors.length < 5"
                    @click="addColor"
                    class="mt-2 text-sm text-primary-400 hover:text-primary-300">
                    + Add Color
                </button>
            </div>

            <!-- Preview -->
            <div>
                <label class="block text-xs text-gray-400 mb-1">Preview</label>
                <div 
                    class="h-12 rounded-lg"
                    :style="previewStyle"
                ></div>
            </div>
        </div>
    </div>
</template>

