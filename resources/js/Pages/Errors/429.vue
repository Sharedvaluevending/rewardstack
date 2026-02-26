<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    status: Number,
});
</script>

<template>
    <Head title="Too Many Requests" />

    <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 flex items-center justify-center p-6">
        <div class="w-full max-w-lg">
            <div class="glass-card p-8 text-center">
                <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-white/20">
                    <span class="text-4xl">⏳</span>
                </div>

                <h1 class="text-2xl font-bold text-white mb-2">Too Many Requests</h1>
                <p class="text-gray-400 text-sm leading-relaxed">
                    You’re tapping fast (or your connection retried requests). Please wait a few seconds and try again.
                </p>

                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="w-full py-3 rounded-xl bg-white/10 text-white font-semibold hover:bg-white/15 border border-white/10 transition-colors"
                        @click="window.location.reload()"
                    >
                        Retry
                    </button>

                    <Link
                        href="/portal/scans"
                        class="w-full py-3 rounded-xl bg-primary-500 text-white font-semibold hover:bg-primary-600 transition-colors"
                    >
                        Back to My Scans
                    </Link>
                </div>

                <div class="mt-5 text-xs text-gray-500">
                    Error {{ status || 429 }}
                </div>
            </div>
        </div>
    </div>
</template>

