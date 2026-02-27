<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    /** CSS selector for scroll container; if not set, uses window */
    targetSelector: {
        type: String,
        default: null,
    },
    /** Scroll threshold (px) after which the indicator hides */
    threshold: {
        type: Number,
        default: 80,
    },
});

const show = ref(true);
let scrollEl = null;

const handleScroll = () => {
    if (!scrollEl) return;
    const scrollTop = scrollEl === window ? window.scrollY : scrollEl.scrollTop;
    if (scrollTop > props.threshold) {
        show.value = false;
    }
};

onMounted(() => {
    scrollEl = props.targetSelector ? document.querySelector(props.targetSelector) : window;
    if (scrollEl) {
        scrollEl.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll();
    }
});

onBeforeUnmount(() => {
    if (scrollEl) {
        scrollEl.removeEventListener('scroll', handleScroll);
    }
});
</script>

<template>
    <Transition name="fade">
        <div
            v-show="show"
            class="scroll-down-indicator flex justify-center py-2"
            aria-hidden="true"
        >
            <svg
                class="w-6 h-6 text-white/80"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 14l-7 7m0 0l-7-7m7 7V3"
                />
            </svg>
        </div>
    </Transition>
</template>

<style scoped>
.scroll-down-indicator svg {
    animation: glowPulse 2s ease-in-out infinite;
}

@keyframes glowPulse {
    0%,
    100% {
        opacity: 1;
        filter: drop-shadow(0 0 6px rgba(168, 85, 247, 0.6));
    }
    50% {
        opacity: 0.7;
        filter: drop-shadow(0 0 12px rgba(168, 85, 247, 0.9));
    }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
