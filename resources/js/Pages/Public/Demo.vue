<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';

defineOptions({
    layout: MainLayout,
});

const videoEl = ref(null);
const isMuted = ref(true);
const PLAYBACK_RATE = 0.9;

const unmute = async () => {
    const el = videoEl.value;
    if (!el) return;
    el.playbackRate = PLAYBACK_RATE;
    el.muted = false;
    el.volume = 1;
    isMuted.value = false;
    try {
        await el.play();
    } catch {
        // Some browsers still block play until gesture; controls remain available.
    }
};

onMounted(() => {
    // Keep overlay state in sync if user unmutes via native controls.
    const el = videoEl.value;
    if (!el) return;

    // Default speed (0.90x).
    el.playbackRate = PLAYBACK_RATE;

    // Try autoplay with sound first. If blocked, fall back to muted autoplay.
    // (Most browsers require a user gesture for autoplay-with-audio.)
    (async () => {
        try {
            el.muted = false;
            el.volume = 1;
            await el.play();
        } catch {
            el.muted = true;
            try {
                await el.play();
            } catch {
                // If autoplay is fully blocked, user can press play.
            }
        }
    })();

    // Auto-unmute on first user interaction anywhere (so they don't need to click the button).
    const tryUnmuteOnGesture = async () => {
        await unmute();
    };
    window.addEventListener('pointerdown', tryUnmuteOnGesture, { capture: true, once: true });
    window.addEventListener('keydown', tryUnmuteOnGesture, { capture: true, once: true });
    window.addEventListener('touchstart', tryUnmuteOnGesture, { capture: true, once: true });

    isMuted.value = !!el.muted;
    el.addEventListener('volumechange', () => {
        isMuted.value = !!el.muted;
    });
});
</script>

<template>
    <Head title="Demo" />

    <section class="py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-5xl font-bold mb-6">
                <span class="gradient-text">See Revenue QR in Action</span>
            </h1>
            <p class="text-xl text-gray-400 mb-12">
                Watch how easy it is to create QR code promotions that drive real results.
            </p>

            <div class="mb-10">
                <p class="text-lg sm:text-xl font-semibold text-white">
                    For about <span class="gradient-text">66¢ a day</span>, turn one-time sales into repeat customers.
                </p>
                <p class="text-sm sm:text-base text-gray-400 mt-1">
                    <span class="text-primary-400">No POS required</span> • Set up in minutes • Built for small businesses
                </p>
            </div>

            <!-- Demo Video -->
            <div class="glass-card p-4 sm:p-6 md:p-8 mb-12">
                <div class="relative">
                    <video
                        ref="videoEl"
                        class="w-full aspect-video rounded-2xl bg-black/40 border border-white/10"
                        autoplay
                        controls
                        muted
                        playsinline
                        preload="metadata"
                    >
                        <source src="/videos/Mike_s%20Video%20-%20Jan%209,%202026.mp4" type="video/mp4" />
                        Your browser does not support the video tag.
                    </video>

                    <!-- Autoplay with sound is blocked by most browsers; require a user gesture to unmute. -->
                    <div
                        v-if="isMuted"
                        class="absolute inset-0 flex items-center justify-center"
                    >
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-black/60 border border-white/20 text-white text-sm font-semibold hover:bg-black/70 transition-colors"
                            @click="unmute"
                        >
                            Tap to unmute
                        </button>
                    </div>
                </div>
            </div>

            <!-- CTA -->
            <div class="space-y-4">
                <p class="text-gray-400">Ready to try it yourself?</p>
                <Link href="/register" class="btn-primary text-lg px-10 py-4 inline-block">
                    Start Your Free Trial
                </Link>
            </div>
        </div>
    </section>
</template>

