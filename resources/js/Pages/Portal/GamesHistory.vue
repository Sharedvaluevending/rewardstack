<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    plays: Object, // paginator
    totals: Object,
});

const formatDateTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
};
</script>

<template>
    <Head title="Game History" />
    <PortalLayout>
        <div class="mb-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-white">📜 Game History</h1>
                    <p class="text-gray-400 text-sm mt-1">
                        {{ totals?.total_plays || 0 }} plays • {{ totals?.total_wins || 0 }} wins • Best {{ totals?.best_score || 0 }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link href="/portal/games" class="px-4 py-2 rounded-full text-sm font-medium bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                        🎮 Overview
                    </Link>
                    <Link href="/portal/games/nearby" class="px-4 py-2 rounded-full text-sm font-medium bg-white/10 text-gray-300 hover:bg-white/20 transition-colors">
                        📍 Nearby
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="plays?.data?.length" class="space-y-3">
            <div v-for="play in plays.data" :key="play.id" class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-xl">
                        🎮
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-white font-medium truncate">{{ play.game?.name || 'Game' }}</div>
                        <div class="text-gray-400 text-sm truncate">{{ play.business?.name || '—' }}</div>
                        <div class="text-gray-500 text-xs mt-1">{{ formatDateTime(play.created_at) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-purple-400 font-bold">{{ play.score }}</div>
                        <div :class="['text-xs font-medium', play.result === 'win' ? 'text-emerald-400' : 'text-gray-400']">
                            {{ play.result === 'win' ? '🎉 WON' : 'DONE' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-12">
            <div class="text-5xl mb-4">🎮</div>
            <h3 class="text-white font-semibold text-lg mb-2">No history yet</h3>
            <p class="text-gray-400 text-sm">Play a game from a QR scan to see it here.</p>
        </div>
    </PortalLayout>
</template>

